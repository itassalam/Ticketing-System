<?php
session_start();
require_once 'config.php';
requireLogin();
$page_title  = 'Dashboard';
$active_menu = 'dashboard';

$_cur_role = $_SESSION['user_role'] ?? 'user';
$uid       = (int)($_SESSION['user_id'] ?? 0);
$is_admin  = hasRole('admin');

// ══════════════════════════════════════════════════════════════════════════════
// 1. KONFIGURASI MODUL TIKET
//    Tambah modul baru cukup dengan menambah satu entri di sini.
// ══════════════════════════════════════════════════════════════════════════════
$MODULES = [
  'it' => [
    'label' => 'Tiket IT', 'short' => 'IT', 'icon' => 'fa-desktop',
    'color' => '#2563eb', 'soft' => '#eff6ff', 'av' => 'av-blue',
    'tbl' => 'tiket', 'kat' => 'kategori', 'mnt' => 'maintenance_it', 'aset' => 'aset_it',
    'p_detail' => 'detail_tiket.php', 'p_antrian' => 'antrian.php', 'p_all' => 'semua_tiket.php',
    'p_sla' => 'sla.php', 'p_mine' => 'tiket_saya.php', 'p_create' => 'buat_tiket.php',
    'p_mnt' => 'maintenance_it.php',
    'managers' => ['admin', 'teknisi'], 'tech_roles' => "'teknisi','admin'",
  ],
  'ipsrs' => [
    'label' => 'Tiket IPSRS', 'short' => 'IPSRS', 'icon' => 'fa-toolbox',
    'color' => '#16a34a', 'soft' => '#f0fdf4', 'av' => 'av-green',
    'tbl' => 'tiket_ipsrs', 'kat' => 'kategori_ipsrs', 'mnt' => 'maintenance_ipsrs', 'aset' => 'aset_ipsrs',
    'p_detail' => 'detail_tiket_ipsrs.php', 'p_antrian' => 'antrian_ipsrs.php', 'p_all' => 'semua_tiket_ipsrs.php',
    'p_sla' => 'sla_ipsrs.php', 'p_mine' => 'tiket_saya_ipsrs.php', 'p_create' => 'buat_tiket_sarpras.php',
    'p_mnt' => 'maintenance_ipsrs.php',
    'managers' => ['admin', 'teknisi_ipsrs'], 'tech_roles' => "'teknisi_ipsrs'",
  ],
  'atem' => [
    'label' => 'Tiket ATEM', 'short' => 'ATEM', 'icon' => 'fa-heart-pulse',
    'color' => '#0284c7', 'soft' => '#f0f9ff', 'av' => 'av-blue',
    'tbl' => 'tiket_atem', 'kat' => 'kategori_atem', 'mnt' => 'maintenance_atem', 'aset' => 'aset_atem',
    'p_detail' => 'detail_tiket_atem.php', 'p_antrian' => 'antrian_atem.php', 'p_all' => 'semua_tiket_atem.php',
    'p_sla' => 'sla_atem.php', 'p_mine' => 'tiket_saya_atem.php', 'p_create' => 'buat_tiket_atem.php',
    'p_mnt' => 'maintenance_atem.php',
    'managers' => ['admin', 'teknisi_atem'], 'tech_roles' => "'teknisi_atem'",
  ],
];

// ══════════════════════════════════════════════════════════════════════════════
// 2. FUNGSI DATA  (semua nama tabel berasal dari $MODULES di atas, bukan input user)
// ══════════════════════════════════════════════════════════════════════════════
function dash_stats(PDO $pdo, array $m, ?int $uid = null): array {
    $out = ['total' => 0, 'menunggu' => 0, 'diproses' => 0, 'selesai' => 0, 'ditolak' => 0];
    try {
        $st = $pdo->prepare("SELECT status, COUNT(*) AS n FROM {$m['tbl']}" . ($uid ? " WHERE user_id=?" : "") . " GROUP BY status");
        $st->execute($uid ? [$uid] : []);
        foreach ($st->fetchAll() as $r) {
            $n   = (int)$r['n'];
            $key = $r['status'] === 'tidak_bisa' ? 'ditolak' : $r['status'];
            $out['total'] += $n;
            if (isset($out[$key])) $out[$key] += $n;
        }
    } catch (Exception $e) {}
    return $out;
}

function dash_waiting(PDO $pdo, array $m): int {
    try { return (int)$pdo->query("SELECT COUNT(*) FROM {$m['tbl']} WHERE status='menunggu'")->fetchColumn(); }
    catch (Exception $e) { return 0; }
}

function dash_count(PDO $pdo, array $m, string $when): int {
    $w = $when === 'today'
        ? "DATE(created_at)=CURDATE()"
        : "MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())";
    try { return (int)$pdo->query("SELECT COUNT(*) FROM {$m['tbl']} WHERE $w")->fetchColumn(); }
    catch (Exception $e) { return 0; }
}

function dash_antrian(PDO $pdo, array $m, int $limit): array {
    try {
        return $pdo->query("
            SELECT t.*, k.nama AS kat_nama, u.nama AS req_nama, u.divisi
            FROM {$m['tbl']} t
            LEFT JOIN {$m['kat']} k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.user_id
            WHERE t.status = 'menunggu'
            ORDER BY CASE t.prioritas WHEN 'Tinggi' THEN 1 WHEN 'Sedang' THEN 2 ELSE 3 END,
                     t.waktu_submit ASC
            LIMIT " . (int)$limit
        )->fetchAll();
    } catch (Exception $e) { return []; }
}

function dash_diproses(PDO $pdo, array $m): array {
    try {
        return $pdo->query("
            SELECT t.*, k.nama AS kat_nama, k.sla_jam, u.nama AS req_nama, tek.nama AS tek_nama
            FROM {$m['tbl']} t
            LEFT JOIN {$m['kat']} k ON k.id = t.kategori_id
            LEFT JOIN users u ON u.id = t.user_id
            LEFT JOIN users tek ON tek.id = t.teknisi_id
            WHERE t.status = 'diproses'
            ORDER BY t.waktu_submit ASC LIMIT 8
        ")->fetchAll();
    } catch (Exception $e) { return []; }
}

function dash_recent(PDO $pdo, array $m, int $uid, int $limit): array {
    try {
        $st = $pdo->prepare("
            SELECT t.*, k.nama AS kat_nama, tek.nama AS tek_nama
            FROM {$m['tbl']} t
            LEFT JOIN {$m['kat']} k ON k.id = t.kategori_id
            LEFT JOIN users tek ON tek.id = t.teknisi_id
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC LIMIT " . (int)$limit
        );
        $st->execute([$uid]);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

function dash_sla(PDO $pdo, array $m): array {
    $def = ['total' => 0, 'selesai' => 0, 'sla_met' => 0, 'avg_respon' => 0, 'avg_selesai' => 0, 'pct' => 0];
    try {
        $r = $pdo->query("
            SELECT COUNT(*) AS total,
                SUM(t.status IN ('selesai','ditolak','tidak_bisa')) AS selesai,
                SUM(t.status='selesai' AND t.durasi_selesai_menit <= (
                    SELECT k.sla_jam*60 FROM {$m['kat']} k WHERE k.id = t.kategori_id
                )) AS sla_met,
                AVG(t.durasi_respon_menit)  AS avg_respon,
                AVG(t.durasi_selesai_menit) AS avg_selesai
            FROM {$m['tbl']} t
            WHERE MONTH(t.created_at)=MONTH(NOW()) AND YEAR(t.created_at)=YEAR(NOW())
        ")->fetch();
        if ($r) $def = array_merge($def, array_map(fn($v) => $v ?? 0, $r));
    } catch (Exception $e) {}
    $def['pct'] = (int)$def['selesai'] > 0 ? (int)round($def['sla_met'] / $def['selesai'] * 100) : 0;
    return $def;
}

function dash_chart(PDO $pdo, array $m): array {
    $dates = [];
    for ($i = 6; $i >= 0; $i--) $dates[] = date('Y-m-d', strtotime("-$i days"));
    $map = [];
    try {
        $st = $pdo->prepare("SELECT DATE(created_at) AS tgl, COUNT(*) AS n FROM {$m['tbl']} WHERE DATE(created_at) >= ? GROUP BY DATE(created_at)");
        $st->execute([$dates[0]]);
        foreach ($st->fetchAll() as $r) $map[$r['tgl']] = (int)$r['n'];
    } catch (Exception $e) {}
    $rows = [];
    foreach ($dates as $d) $rows[] = ['lbl' => date('d/m', strtotime($d)), 'n' => $map[$d] ?? 0];
    return ['rows' => $rows, 'max' => max(array_column($rows, 'n')) ?: 1];
}

function dash_mnt(PDO $pdo, array $m): array {
    try {
        return $pdo->query("
            SELECT a.nama_aset, a.no_inventaris, mm.tgl_maintenance_berikut,
                   DATEDIFF(mm.tgl_maintenance_berikut, CURDATE()) AS sisa_hari
            FROM {$m['mnt']} mm
            JOIN {$m['aset']} a ON a.id = mm.aset_id
            WHERE mm.id IN (SELECT MAX(id) FROM {$m['mnt']} GROUP BY aset_id)
              AND mm.tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY mm.tgl_maintenance_berikut ASC LIMIT 5
        ")->fetchAll();
    } catch (Exception $e) { return []; }
}

function dash_top(PDO $pdo, array $m): array {
    try {
        return $pdo->query("
            SELECT u.nama,
                COUNT(t.id) AS total,
                SUM(t.status='selesai') AS selesai,
                AVG(t.durasi_selesai_menit) AS avg_selesai,
                SUM(t.status='selesai' AND t.durasi_selesai_menit <= (
                    SELECT k.sla_jam*60 FROM {$m['kat']} k WHERE k.id = t.kategori_id
                )) AS sla_met
            FROM users u
            LEFT JOIN {$m['tbl']} t ON t.teknisi_id = u.id
                AND MONTH(t.created_at)=MONTH(NOW()) AND YEAR(t.created_at)=YEAR(NOW())
            WHERE u.role IN ({$m['tech_roles']}) AND u.status='aktif'
            GROUP BY u.id
            HAVING total > 0
            ORDER BY selesai DESC, avg_selesai ASC
            LIMIT 5
        ")->fetchAll();
    } catch (Exception $e) { return []; }
}

// Infrastruktur: aset IT, monitor koneksi (1 query, bukan 1 per perangkat), server room
function dash_infra(PDO $pdo): array {
    $o = [
        'aset'    => ['total' => 0, 'baik' => 0, 'rusak' => 0, 'perbaikan' => 0],
        'kon'     => ['total' => 0, 'online' => 0, 'offline' => 0],
        'offline' => [],
        'sr'      => null,
        'sr_alert' => false,
    ];
    try {
        foreach ($pdo->query("SELECT kondisi, COUNT(*) n FROM aset_it GROUP BY kondisi")->fetchAll() as $r) {
            $n = (int)$r['n']; $k = strtolower($r['kondisi']);
            $o['aset']['total'] += $n;
            if ($k === 'baik')                     $o['aset']['baik']      += $n;
            elseif ($k === 'rusak')                $o['aset']['rusak']     += $n;
            elseif (str_contains($k, 'perbaikan')) $o['aset']['perbaikan'] += $n;
        }
    } catch (Exception $e) {}
    try {
        $rows = $pdo->query("
            SELECT m.id, m.nama, m.ip_address,
                   (SELECT l.status FROM koneksi_log l WHERE l.monitor_id = m.id ORDER BY l.cek_at DESC LIMIT 1) AS last_status
            FROM koneksi_monitor m WHERE m.aktif = 1
        ")->fetchAll();
        $o['kon']['total'] = count($rows);
        foreach ($rows as $r) {
            if ($r['last_status'] === 'online') $o['kon']['online']++;
            elseif ($r['last_status'] === 'offline') { $o['kon']['offline']++; $o['offline'][] = $r; }
        }
    } catch (Exception $e) {}
    try {
        $sr = $pdo->query("SELECT * FROM server_room_log ORDER BY tanggal DESC, waktu DESC LIMIT 1")->fetch();
        if ($sr) {
            $o['sr'] = $sr;
            $o['sr_alert'] = (bool)($sr['ada_alarm'] || $sr['ada_banjir'] || $sr['ada_asap'] || $sr['status_overall'] === 'Kritis');
        }
    } catch (Exception $e) {}
    return $o;
}

// ══════════════════════════════════════════════════════════════════════════════
// 3. FUNGSI TAMPILAN
// ══════════════════════════════════════════════════════════════════════════════
function dash_sec_head(array $m, string $right = ''): void { ?>
  <div class="dash-sec">
    <div class="dash-sec-t">
      <span class="dot" style="background:<?= $m['soft'] ?>;color:<?= $m['color'] ?>;"><i class="fa <?= $m['icon'] ?>"></i></span>
      <?= $m['label'] ?>
    </div>
    <div class="dash-sec-r"><?= $right ?></div>
  </div>
<?php }

function dash_kpi_row(array $s): void {
    $items = [
        ['Total',              'fa-ticket-alt',   $s['total'],    '#475569', '#f1f5f9'],
        ['Menunggu',           'fa-clock',        $s['menunggu'], '#b45309', '#fef3c7'],
        ['Diproses',           'fa-cogs',         $s['diproses'], '#1d4ed8', '#dbeafe'],
        ['Selesai',            'fa-check-circle', $s['selesai'],  '#047857', '#d1fae5'],
        ['Ditolak / Tdk Bisa', 'fa-ban',          $s['ditolak'],  '#b91c1c', '#fee2e2'],
    ];
    echo '<div class="dash-kpis">';
    foreach ($items as [$l, $ic, $v, $c, $bg]) {
        echo '<div class="dash-kpi"><div class="ic" style="background:' . $bg . ';color:' . $c . ';"><i class="fa ' . $ic . '"></i></div>'
           . '<div><div class="n">' . (int)$v . '</div><div class="l">' . $l . '</div></div></div>';
    }
    echo '</div>';
}

// $items: [label, value, icon, color, bg, href|null, sub]
function dash_info_row(array $items, int $cols = 4): void {
    echo '<div class="dash-kpis dash-info c' . $cols . '">';
    foreach ($items as $it) {
        [$l, $v, $ic, $c, $bg, $href, $sub] = $it + [null, null, null, null, null, null, ''];
        $tag = $href ? 'a' : 'div';
        echo '<' . $tag . ($href ? ' href="' . $href . '"' : '') . ' class="dash-kpi' . ($href ? ' link' : '') . '">'
           . '<div class="ic" style="background:' . $bg . ';color:' . $c . ';"><i class="fa ' . $ic . '"></i></div>'
           . '<div><div class="n">' . $v . '</div><div class="l">' . $l . '</div>'
           . ($sub !== '' ? '<div class="s">' . $sub . '</div>' : '')
           . '</div></' . $tag . '>';
    }
    echo '</div>';
}

function dash_tbl_antrian(array $rows, array $m): void { ?>
  <div class="panel-bd np tbl-wrap">
    <table>
      <thead><tr><th>Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Pemohon</th><th>Masuk</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="td-empty"><i class="fa fa-check-circle" style="color:var(--green);"></i> Tidak ada antrian</td></tr>
        <?php else: foreach ($rows as $t): $dur = durasiSekarang($t['waktu_submit']); ?>
        <tr>
          <td><a href="<?= APP_URL ?>/pages/<?= $m['p_detail'] ?>?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
          <td style="max-width:150px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= clean($t['judul']) ?>"><?= clean($t['judul']) ?></span></td>
          <td style="font-size:11px;"><?= clean($t['kat_nama'] ?? '—') ?></td>
          <td><?= badgePrioritas($t['prioritas']) ?></td>
          <td><div class="d-flex ai-c gap6"><div class="av av-xs <?= $m['av'] ?>"><?= getInitials($t['req_nama']) ?></div><div><?= clean($t['req_nama']) ?><br><span class="text-muted text-sm"><?= clean($t['divisi'] ?? '') ?></span></div></div></td>
          <td style="white-space:nowrap;"><span style="font-size:11px;color:#94a3b8;"><?= formatTanggal($t['waktu_submit'], true) ?></span>
            <?php if ($dur > 60): ?><br><span style="font-size:10px;color:var(--red);font-weight:700;"><i class="fa fa-clock"></i> <?= formatDurasi($dur) ?></span><?php endif; ?>
          </td>
          <td><a href="<?= APP_URL ?>/pages/<?= $m['p_detail'] ?>?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><i class="fa fa-wrench"></i> Proses</a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
<?php }

function dash_tbl_diproses(array $rows, array $m): void { ?>
  <div class="panel-bd np tbl-wrap">
    <table>
      <thead><tr><th>Tiket</th><th>Judul</th><th>Teknisi</th><th>Prioritas</th><th>Durasi</th><th>SLA</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="td-empty"><i class="fa fa-mug-hot"></i> Tidak ada tiket yang sedang diproses</td></tr>
        <?php else: foreach ($rows as $t):
          $dur = durasiSekarang($t['waktu_submit']);
          $sla = !empty($t['sla_jam']) ? slaStatus($dur, $t['sla_jam']) : null; ?>
        <tr>
          <td><a href="<?= APP_URL ?>/pages/<?= $m['p_detail'] ?>?id=<?= $t['id'] ?>" style="color:var(--primary);font-weight:700;"><?= clean($t['nomor']) ?></a></td>
          <td style="max-width:150px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($t['judul']) ?></span><small style="color:#94a3b8;"><?= clean($t['req_nama']) ?></small></td>
          <td><?= $t['tek_nama'] ? '<div class="d-flex ai-c gap6"><div class="av av-xs ' . $m['av'] . '">' . getInitials($t['tek_nama']) . '</div>' . clean($t['tek_nama']) . '</div>' : '<span class="text-muted">—</span>' ?></td>
          <td><?= badgePrioritas($t['prioritas']) ?></td>
          <td style="font-weight:700;color:<?= $sla && $sla['status'] === 'breach' ? 'var(--red)' : '#333' ?>;"><?= formatDurasi($dur) ?></td>
          <td>
            <?php if ($sla): ?>
            <span class="sla-badge" style="background:<?= $sla['status'] === 'aman' ? '#d1fae5' : ($sla['status'] === 'warning' ? '#fef3c7' : '#fee2e2') ?>;color:<?= $sla['status'] === 'aman' ? '#065f46' : ($sla['status'] === 'warning' ? '#92400e' : '#991b1b') ?>;"><?= $sla['persen'] ?>%</span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><a href="<?= APP_URL ?>/pages/<?= $m['p_detail'] ?>?id=<?= $t['id'] ?>" class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
<?php }

function dash_tbl_recent(array $rows, array $m): void { ?>
  <div class="panel-bd np tbl-wrap">
    <table>
      <thead><tr><th>No. Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Teknisi</th><th>Tanggal</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="td-empty"><i class="fa fa-inbox"></i> Belum ada tiket. <a href="<?= APP_URL ?>/pages/<?= $m['p_create'] ?>">Buat sekarang</a></td></tr>
        <?php else: foreach ($rows as $t): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/pages/<?= $m['p_detail'] ?>?id=<?= $t['id'] ?>" style="color:<?= $m['color'] ?>;font-weight:700;"><?= clean($t['nomor']) ?></a></td>
          <td style="max-width:150px;"><span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($t['judul']) ?></span></td>
          <td><?= clean($t['kat_nama'] ?? '—') ?></td>
          <td><?= badgePrioritas($t['prioritas']) ?></td>
          <td><?= badgeStatus($t['status']) ?></td>
          <td><?= $t['tek_nama'] ? '<div class="d-flex ai-c gap6"><div class="av av-xs ' . $m['av'] . '">' . getInitials($t['tek_nama']) . '</div>' . clean($t['tek_nama']) . '</div>' : '<span class="text-muted">—</span>' ?></td>
          <td style="color:#94a3b8;font-size:11px;white-space:nowrap;"><?= formatTanggal($t['waktu_submit']) ?></td>
          <td><a href="<?= APP_URL ?>/pages/<?= $m['p_detail'] ?>?id=<?= $t['id'] ?>" class="btn btn-info btn-sm"><i class="fa fa-eye"></i></a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
<?php }

function dash_panel_sla(array $sla, array $m): void {
    $p   = $sla['pct'];
    $col = $p >= 90 ? 'var(--green)' : ($p >= 70 ? 'var(--orange)' : 'var(--red)');
    $cls = $p >= 90 ? 'pg-green' : ($p >= 70 ? 'pg-orange' : 'pg-red'); ?>
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-chart-line" style="color:<?= $m['color'] ?>;"></i> &nbsp;SLA <?= $m['short'] ?> Bulan Ini</h5>
      <a href="<?= APP_URL ?>/pages/<?= $m['p_sla'] ?>" class="btn btn-default btn-sm">Detail</a>
    </div>
    <div class="panel-bd">
      <div style="text-align:center;margin-bottom:12px;">
        <div style="font-size:32px;font-weight:800;color:<?= $col ?>;"><?= $p ?>%</div>
        <div style="font-size:11px;color:#94a3b8;">Tiket selesai dalam target SLA</div>
      </div>
      <div class="progress" style="height:8px;margin-bottom:14px;"><div class="progress-fill <?= $cls ?>" style="width:<?= $p ?>%"></div></div>
      <?php foreach ([
          ['Total Tiket', $sla['total']], ['Sudah Selesai', $sla['selesai']], ['Dalam Target SLA', $sla['sla_met']],
          ['Avg. Respon', formatDurasi((int)round($sla['avg_respon']))], ['Avg. Penyelesaian', formatDurasi((int)round($sla['avg_selesai']))],
      ] as [$l, $v]): ?>
      <div class="d-flex ai-c" style="justify-content:space-between;margin-bottom:7px;font-size:12px;">
        <span style="color:#888;"><?= $l ?></span><strong style="color:#333;"><?= $v ?></strong>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php }

function dash_panel_chart(array $chart, array $m): void { ?>
  <div class="panel">
    <div class="panel-hd"><h5><i class="fa fa-chart-bar" style="color:<?= $m['color'] ?>;"></i> &nbsp;7 Hari Terakhir</h5></div>
    <div class="panel-bd">
      <div class="chart-wrap" style="height:80px;">
        <?php foreach ($chart['rows'] as $cd): ?>
        <div class="chart-col" title="<?= $cd['lbl'] ?>: <?= $cd['n'] ?> tiket">
          <?php if ($cd['n']): ?><div class="chart-val"><?= $cd['n'] ?></div><?php endif; ?>
          <div class="chart-bar" style="background:<?= $m['color'] ?>;opacity:.7;height:<?= $cd['n'] ? round($cd['n'] / $chart['max'] * 60) + 10 : 4 ?>px;width:100%;"></div>
          <div class="chart-lbl"><?= $cd['lbl'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php }

function dash_panel_mnt(array $rows, array $m): void { ?>
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-screwdriver-wrench" style="color:<?= $m['color'] ?>;"></i> &nbsp;Maintenance <?= $m['short'] ?> Mendekat</h5>
      <a href="<?= APP_URL ?>/pages/<?= $m['p_mnt'] ?>" class="btn btn-default btn-sm">Lihat</a>
    </div>
    <div class="panel-bd">
      <?php if (empty($rows)): ?>
      <div style="text-align:center;color:#94a3b8;font-size:12px;padding:10px 0;">
        <i class="fa fa-circle-check" style="color:#22c55e;font-size:22px;display:block;margin-bottom:6px;"></i>
        Tidak ada jadwal dalam 7 hari ke depan
      </div>
      <?php else: foreach ($rows as $r):
        $sisa = (int)$r['sisa_hari'];
        $c  = $sisa <= 0 ? '#dc2626' : ($sisa <= 2 ? '#d97706' : '#2563eb');
        $bg = $sisa <= 0 ? '#fee2e2' : ($sisa <= 2 ? '#fef9c3' : '#eff6ff'); ?>
      <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #f8fafc;">
        <div style="width:34px;height:34px;background:<?= $bg ?>;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:11px;font-weight:800;color:<?= $c ?>;"><?= $sisa <= 0 ? 'NOW' : $sisa . 'h' ?></div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($r['nama_aset']) ?></div>
          <div style="font-size:10.5px;color:#94a3b8;"><?= htmlspecialchars($r['no_inventaris'] ?? '') ?> &nbsp;&bull;&nbsp; <?= date('d M Y', strtotime($r['tgl_maintenance_berikut'])) ?></div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
<?php }

function dash_panel_top(array $rows, array $m): void {
    if (empty($rows)) return;
    $medals = ['🥇', '🥈', '🥉']; ?>
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-trophy" style="color:#d97706;"></i> &nbsp;Top Teknisi <?= $m['short'] ?> Bulan Ini</h5>
      <a href="<?= APP_URL ?>/pages/<?= $m['p_sla'] ?>" class="btn btn-default btn-sm">Laporan SLA</a>
    </div>
    <div class="panel-bd np tbl-wrap">
      <table>
        <thead><tr><th>#</th><th>Teknisi</th><th>Total</th><th>Selesai</th><th>SLA Met</th><th>Avg. Selesai</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $i => $tek):
            $p = (int)($tek['selesai'] ?? 0) > 0 ? round((int)($tek['sla_met'] ?? 0) / (int)$tek['selesai'] * 100) : 0; ?>
          <tr>
            <td style="font-size:16px;text-align:center;"><?= $medals[$i] ?? ($i + 1) ?></td>
            <td><div class="d-flex ai-c gap6"><div class="av av-xs <?= $m['av'] ?>"><?= getInitials($tek['nama']) ?></div><span style="font-weight:600;"><?= clean($tek['nama']) ?></span></div></td>
            <td style="font-weight:700;"><?= (int)($tek['total'] ?? 0) ?></td>
            <td style="color:var(--green);font-weight:700;"><?= (int)($tek['selesai'] ?? 0) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px;">
                <div class="progress" style="width:50px;"><div class="progress-fill <?= $p >= 90 ? 'pg-green' : ($p >= 70 ? 'pg-orange' : 'pg-red') ?>" style="width:<?= $p ?>%;"></div></div>
                <span style="font-size:11px;font-weight:700;"><?= $p ?>%</span>
              </div>
            </td>
            <td style="font-size:12px;"><?= formatDurasi((int)round($tek['avg_selesai'] ?? 0)) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php }

function dash_panel_server(?array $sr): void { ?>
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-server" style="color:#64748b;"></i> &nbsp;Ruangan Server</h5>
      <a href="<?= APP_URL ?>/pages/server_room.php" class="btn btn-default btn-sm">Detail</a>
    </div>
    <div class="panel-bd">
      <?php if (!$sr): ?>
      <div style="text-align:center;color:#94a3b8;font-size:12px;padding:10px 0;">
        <i class="fa fa-server" style="font-size:22px;display:block;margin-bottom:6px;color:#cbd5e1;"></i>
        Belum ada data pemantauan
      </div>
      <?php else:
        $c  = $sr['status_overall'] === 'Normal' ? '#16a34a' : ($sr['status_overall'] === 'Perhatian' ? '#d97706' : '#dc2626');
        $bg = $sr['status_overall'] === 'Normal' ? '#dcfce7' : ($sr['status_overall'] === 'Perhatian' ? '#fef9c3' : '#fee2e2'); ?>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <span style="font-size:11px;color:#64748b;">Update: <?= date('d M, H:i', strtotime($sr['tanggal'] . ' ' . $sr['waktu'])) ?></span>
        <span style="padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;background:<?= $bg ?>;color:<?= $c ?>;"><?= $sr['status_overall'] ?></span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
        <?php foreach ([
          ['Suhu', $sr['suhu_in'] !== null ? $sr['suhu_in'] . '°C' : '—', (float)($sr['suhu_in'] ?? 0) > 27 ? '#dc2626' : '#16a34a'],
          ['Kelembaban', $sr['kelembaban'] !== null ? $sr['kelembaban'] . '%' : '—', (float)($sr['kelembaban'] ?? 0) > 65 ? '#d97706' : '#16a34a'],
          ['PLN', $sr['tegangan_pln'] !== null ? $sr['tegangan_pln'] . 'V' : '—', '#2563eb'],
          ['Baterai UPS', $sr['baterai_ups'] !== null ? $sr['baterai_ups'] . '%' : '—', (float)($sr['baterai_ups'] ?? 100) < 30 ? '#dc2626' : '#16a34a'],
        ] as [$l, $v, $vc]): ?>
        <div style="background:#f8fafc;border-radius:6px;padding:8px 10px;">
          <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;"><?= $l ?></div>
          <div style="font-size:15px;font-weight:800;color:<?= $vc ?>;"><?= $v ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($sr['ada_alarm'] || $sr['ada_banjir'] || $sr['ada_asap']): ?>
      <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
        <?php
        if ($sr['ada_alarm'])  echo '<span style="background:#fee2e2;color:#b91c1c;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:700;"><i class="fa fa-bell"></i> Alarm</span>';
        if ($sr['ada_banjir']) echo '<span style="background:#fff1f2;color:#e11d48;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:700;"><i class="fa fa-water"></i> Banjir</span>';
        if ($sr['ada_asap'])   echo '<span style="background:#fef9c3;color:#a16207;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:700;"><i class="fa fa-smog"></i> Asap</span>';
        ?>
      </div>
      <?php endif; ?>
      <div style="margin-top:8px;border-top:1px solid #f1f5f9;padding-top:8px;display:flex;gap:5px;flex-wrap:wrap;font-size:11px;">
        <?php foreach ([['AC 1', $sr['kondisi_ac1'] ?? '—'], ['AC 2', $sr['kondisi_ac2'] ?? '—'], ['Pintu', $sr['kondisi_pintu'] ?? '—'], ['CCTV', $sr['kondisi_cctv'] ?? '—']] as [$l, $v]):
          $ok = in_array($v, ['Normal', 'Baik', 'Terkunci', 'Aktif']); ?>
        <span style="background:<?= $ok ? '#f0fdf4' : '#fef2f2' ?>;color:<?= $ok ? '#15803d' : '#b91c1c' ?>;padding:2px 7px;border-radius:4px;font-weight:600;"><?= $l ?>: <?= $v ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
<?php }

function dash_panel_aset(array $a): void { ?>
  <div class="panel">
    <div class="panel-hd">
      <h5><i class="fa fa-laptop" style="color:#10b981;"></i> &nbsp;Kondisi Aset IT</h5>
      <a href="<?= APP_URL ?>/pages/aset_it.php" class="btn btn-default btn-sm">Lihat</a>
    </div>
    <div class="panel-bd">
      <?php foreach ([['Baik', $a['baik'], 'pg-green'], ['Perbaikan', $a['perbaikan'], 'pg-orange'], ['Rusak', $a['rusak'], 'pg-red']] as [$l, $v, $cls]):
        $pc = $a['total'] ? round($v / $a['total'] * 100) : 0; ?>
      <div style="margin-bottom:10px;">
        <div class="d-flex ai-c" style="justify-content:space-between;font-size:12px;margin-bottom:4px;">
          <span style="color:#888;"><?= $l ?></span><strong style="color:#333;"><?= $v ?> <span style="color:#94a3b8;font-weight:400;">(<?= $pc ?>%)</span></strong>
        </div>
        <div class="progress" style="height:6px;"><div class="progress-fill <?= $cls ?>" style="width:<?= $pc ?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php }

function dash_panel_offline(array $rows): void { ?>
  <div class="panel" style="<?= $rows ? 'border-left:3px solid #ef4444;' : '' ?>">
    <div class="panel-hd">
      <h5><i class="fa fa-wifi" style="color:<?= $rows ? '#ef4444' : '#22c55e' ?>;"></i> &nbsp;Perangkat Jaringan<?= $rows ? ' Offline <span style="color:#ef4444;">(' . count($rows) . ')</span>' : '' ?></h5>
      <a href="<?= APP_URL ?>/pages/cek_koneksi.php" class="btn btn-default btn-sm">Monitor</a>
    </div>
    <div class="panel-bd">
      <?php if (empty($rows)): ?>
      <div style="text-align:center;color:#94a3b8;font-size:12px;padding:10px 0;">
        <i class="fa fa-circle-check" style="color:#22c55e;font-size:22px;display:block;margin-bottom:6px;"></i>
        Semua perangkat online
      </div>
      <?php else: foreach ($rows as $ko): ?>
      <div style="display:flex;align-items:center;gap:8px;padding:7px 12px;margin-bottom:6px;background:#fff1f2;border:1px solid #fecaca;border-radius:7px;">
        <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;flex-shrink:0;animation:dashPulse 1.2s infinite;"></span>
        <div>
          <div style="font-size:12px;font-weight:700;color:#1e293b;"><?= htmlspecialchars($ko['nama']) ?></div>
          <div style="font-size:10.5px;color:#94a3b8;"><?= htmlspecialchars($ko['ip_address']) ?></div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
<?php }

// ══════════════════════════════════════════════════════════════════════════════
// 4. LOGIKA HALAMAN: ROLE, TAB FILTER, DATA
// ══════════════════════════════════════════════════════════════════════════════
$my_modules = [];   // modul yang dikelola role ini (admin/teknisi)
foreach ($MODULES as $k => $mod) {
    if (hasRole($mod['managers'])) $my_modules[] = $k;
}
$is_manager = !empty($my_modules);
$can_infra  = hasRole(['admin', 'teknisi']);
$tab_mods   = $is_manager ? $my_modules : array_keys($MODULES);   // user biasa: lihat tiket miliknya di semua modul

$tabs = [];
foreach ($tab_mods as $k) $tabs[$k] = [$MODULES[$k]['label'], $MODULES[$k]['icon']];
if ($can_infra) $tabs['infra'] = ['Infrastruktur', 'fa-server'];
if (count($tabs) > 1) $tabs = ['semua' => ['Semua', 'fa-table-cells-large']] + $tabs;

// Tab dipilih lewat ?kat=..., dan diingat di session supaya tetap sama saat kembali ke dashboard
$kat = $_GET['kat'] ?? ($_SESSION['dash_kat'] ?? '');
if (!is_string($kat) || !isset($tabs[$kat])) $kat = array_key_first($tabs);
$_SESSION['dash_kat'] = $kat;

$full       = ($kat !== 'semua');                                   // true = tampilan detail satu kategori
$load       = ($kat === 'semua') ? $tab_mods : (isset($MODULES[$kat]) ? [$kat] : []);
$show_infra = $can_infra && in_array($kat, ['semua', 'infra'], true);

// Hanya modul yang dipilih yang di-query
$data = [];
foreach ($load as $k) {
    $mod = $MODULES[$k];
    if ($is_manager) {
        $d = [
            'stats'   => dash_stats($pdo, $mod),
            'antrian' => dash_antrian($pdo, $mod, $full ? 8 : 5),
            'sla'     => dash_sla($pdo, $mod),
            'mnt'     => dash_mnt($pdo, $mod),
        ];
        if ($full) {
            $d['today']    = dash_count($pdo, $mod, 'today');
            $d['month']    = dash_count($pdo, $mod, 'month');
            $d['diproses'] = dash_diproses($pdo, $mod);
            $d['chart']    = dash_chart($pdo, $mod);
            $d['top']      = $is_admin ? dash_top($pdo, $mod) : [];
        }
    } else {
        $d = [
            'stats'  => dash_stats($pdo, $mod, $uid),
            'recent' => dash_recent($pdo, $mod, $uid, $full ? 8 : 5),
        ];
    }
    $data[$k] = $d;
}
$infra = $show_infra ? dash_infra($pdo) : null;

// Badge antrian di tiap tab (hanya untuk teknisi/admin)
$badge = [];
if ($is_manager) foreach ($my_modules as $k) $badge[$k] = dash_waiting($pdo, $MODULES[$k]);

// Alert kritis (hanya untuk bagian yang sedang tampil)
$alerts = [];
if ($is_manager) {
    foreach ($data as $k => $d) {
        $mod = $MODULES[$k];
        if ($d['stats']['menunggu'] > 5)
            $alerts[] = [APP_URL . '/pages/' . $mod['p_antrian'], 'fa-triangle-exclamation', $d['stats']['menunggu'] . ' tiket ' . $mod['short'] . ' menunggu penanganan', '#fef3c7', '#fde68a', '#92400e'];
        if (!empty($d['mnt']))
            $alerts[] = [APP_URL . '/pages/' . $mod['p_mnt'], 'fa-screwdriver-wrench', count($d['mnt']) . ' aset ' . $mod['short'] . ' perlu maintenance dalam 7 hari', '#fef9c3', '#fde047', '#713f12'];
    }
}
if ($infra) {
    if ($infra['kon']['offline'] > 0)
        $alerts[] = [APP_URL . '/pages/cek_koneksi.php', 'fa-wifi', $infra['kon']['offline'] . ' perangkat jaringan offline', '#fee2e2', '#fecaca', '#991b1b'];
    if ($infra['sr_alert'] && $infra['sr'])
        $alerts[] = [APP_URL . '/pages/server_room.php', 'fa-server', 'Ruangan server: status ' . $infra['sr']['status_overall'], '#fee2e2', '#fecaca', '#991b1b'];
}

include 'includes/header.php';
?>

<style>
/* ─── Dashboard ─── */
.dash-tabs { display:flex; gap:4px; flex-wrap:nowrap; overflow-x:auto; margin-bottom:16px; padding:5px; background:#fff; border:1px solid #e8ecf0; border-radius:12px; width:fit-content; max-width:100%; }
.dash-tab { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:8px; color:#64748b; font-size:12.5px; font-weight:600; text-decoration:none; white-space:nowrap; transition:background .18s, color .18s; }
.dash-tab:hover { background:rgba(0,229,176,.08); color:#006b52; }
.dash-tab.active { background:var(--primary); color:#0a0f14; box-shadow:0 2px 10px var(--primary-glow); }
.dash-tab .cnt { background:var(--danger); color:#fff; font-size:10px; font-weight:700; padding:1px 6px; border-radius:20px; line-height:1.5; }

.dash-alerts { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
.dash-alert { display:flex; align-items:center; gap:8px; padding:8px 14px; border-radius:8px; border:1px solid; font-size:12px; font-weight:600; text-decoration:none; }

.dash-block { margin-bottom:28px; }
.dash-block:last-child { margin-bottom:0; }
.dash-sec { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; }
.dash-sec-t { display:flex; align-items:center; gap:10px; font-size:14px; font-weight:700; color:#1e293b; }
.dash-sec-t .dot { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:13px; }
.dash-sec-r { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.dash-chip { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }

.dash-kpis { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
.dash-kpis.dash-info.c4 { grid-template-columns:repeat(4,minmax(0,1fr)); }
.dash-kpis.dash-info.c3 { grid-template-columns:repeat(3,minmax(0,1fr)); }
.dash-kpi { background:#fff; border:1px solid #e8ecf0; border-radius:10px; padding:12px 14px; display:flex; align-items:center; gap:12px; text-decoration:none; min-width:0; }
.dash-kpi.link { transition:box-shadow .18s; }
.dash-kpi.link:hover { box-shadow:0 4px 14px rgba(0,0,0,.08); }
.dash-kpi .ic { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
.dash-kpi .n { font-size:20px; font-weight:800; color:#1e293b; line-height:1; }
.dash-kpi .l { font-size:11px; color:#94a3b8; margin-top:3px; }
.dash-kpi .s { font-size:10px; color:#94a3b8; margin-top:2px; }

.dash-grid { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr); gap:14px; margin-bottom:14px; align-items:start; }
.dash-grid .panel, .dash-col .panel, .dash-block > .panel:last-child { margin-bottom:0; }
.dash-col { display:flex; flex-direction:column; gap:14px; }
.dash-block .muted { color:#aaa; font-weight:400; }
.dash-welcome { border-left:4px solid var(--primary); margin-bottom:16px; }

@keyframes dashPulse { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.5;transform:scale(1.3);} }

@media (max-width:1100px) {
  .dash-kpis, .dash-kpis.dash-info.c4 { grid-template-columns:repeat(3,minmax(0,1fr)); }
  .dash-kpis.dash-info.c3 { grid-template-columns:repeat(3,minmax(0,1fr)); }
}
@media (max-width:1000px) { .dash-grid { grid-template-columns:1fr; } }
@media (max-width:640px) {
  .dash-kpis, .dash-kpis.dash-info.c4, .dash-kpis.dash-info.c3 { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .dash-sec { flex-direction:column; align-items:flex-start; }
}
</style>

<div class="page-header">
  <h4><i class="fa fa-home text-primary"></i> &nbsp;Dashboard</h4>
  <div class="breadcrumb"><span class="cur">Beranda</span></div>
</div>

<div class="content">
<?= showFlash() ?>

<?php if (!$is_manager): ?>
<!-- Banner sambutan (user biasa) -->
<div class="panel dash-welcome">
  <div class="panel-bd" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <i class="fa fa-headset" style="font-size:32px;color:var(--primary);opacity:.4;"></i>
    <div style="flex:1;min-width:220px;">
      <strong style="font-size:13px;">Selamat datang, <?= clean($_SESSION['user_nama']) ?>!</strong>
      <p style="color:#888;font-size:12px;margin-top:2px;">Ada masalah dengan perangkat atau fasilitas? Pilih jenis tiketnya dan tim kami akan segera menangani.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php foreach ($MODULES as $mod): ?>
      <a href="<?= APP_URL ?>/pages/<?= $mod['p_create'] ?>" class="btn btn-default" style="white-space:nowrap;border-color:<?= $mod['color'] ?>;color:<?= $mod['color'] ?>;">
        <i class="fa <?= $mod['icon'] ?>"></i> <?= $mod['label'] ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (count($tabs) > 1): ?>
<!-- Filter kategori -->
<nav class="dash-tabs" aria-label="Filter kategori dashboard">
  <?php foreach ($tabs as $k => [$lbl, $ic]): ?>
  <a href="?kat=<?= $k ?>" class="dash-tab <?= $kat === $k ? 'active' : '' ?>">
    <i class="fa <?= $ic ?>"></i><?= $lbl ?>
    <?php if (!empty($badge[$k])): ?><span class="cnt"><?= $badge[$k] ?></span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if (!empty($alerts)): ?>
<div class="dash-alerts">
  <?php foreach ($alerts as [$href, $ic, $txt, $bg, $bd, $cl]): ?>
  <a href="<?= $href ?>" class="dash-alert" style="background:<?= $bg ?>;border-color:<?= $bd ?>;color:<?= $cl ?>;"><i class="fa <?= $ic ?>"></i> <?= $txt ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>


<?php // ══════════════ USER BIASA: tiket milik sendiri ══════════════
if (!$is_manager):
  foreach ($load as $k): $mod = $MODULES[$k]; $d = $data[$k]; ?>
<div class="dash-block">
  <?php dash_sec_head($mod,
      '<a href="' . APP_URL . '/pages/' . $mod['p_create'] . '" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Buat Tiket</a>'
    . '<a href="' . APP_URL . '/pages/' . $mod['p_mine'] . '" class="btn btn-default btn-sm">Lihat Semua</a>'); ?>
  <?php dash_kpi_row($d['stats']); ?>
  <div class="panel">
    <div class="panel-hd"><h5><i class="fa <?= $mod['icon'] ?>" style="color:<?= $mod['color'] ?>;"></i> &nbsp;Tiket <?= $mod['short'] ?> Terbaru</h5></div>
    <?php dash_tbl_recent($d['recent'], $mod); ?>
  </div>
</div>
<?php endforeach; endif; ?>


<?php // ══════════════ TEKNISI / ADMIN: per modul ══════════════
if ($is_manager):
  foreach ($load as $k): $mod = $MODULES[$k]; $d = $data[$k]; $sla = $d['sla'];
    $slaCol = $sla['pct'] >= 90 ? ['#065f46', '#d1fae5'] : ($sla['pct'] >= 70 ? ['#92400e', '#fef3c7'] : ['#991b1b', '#fee2e2']); ?>
<div class="dash-block">

  <?php if (!$full): // ── Ringkas (tab Semua) ── ?>
    <?php dash_sec_head($mod,
        '<span class="dash-chip" style="background:' . $slaCol[1] . ';color:' . $slaCol[0] . ';">SLA ' . $sla['pct'] . '%</span>'
      . '<a href="?kat=' . $k . '" class="btn btn-default btn-sm">Buka detail <i class="fa fa-arrow-right"></i></a>'); ?>
    <?php dash_kpi_row($d['stats']); ?>
    <div class="panel">
      <div class="panel-hd">
        <h5><i class="fa fa-inbox" style="color:<?= $mod['color'] ?>;"></i> &nbsp;Antrian Menunggu <span class="muted">(<?= count($d['antrian']) ?>)</span></h5>
        <a href="<?= APP_URL ?>/pages/<?= $mod['p_antrian'] ?>" class="btn btn-default btn-sm">Lihat Semua</a>
      </div>
      <?php dash_tbl_antrian($d['antrian'], $mod); ?>
    </div>

  <?php else: // ── Detail satu kategori ── ?>
    <?php dash_kpi_row($d['stats']); ?>
    <?php dash_info_row([
        ['Tiket Hari Ini',  $d['today'], 'fa-calendar-day', '#3b82f6', '#eff6ff', null, ''],
        ['Tiket Bulan Ini', $d['month'], 'fa-calendar-alt', '#7c3aed', '#f5f3ff', null, ''],
        ['Jadwal Maintenance', count($d['mnt']), 'fa-screwdriver-wrench', !empty($d['mnt']) ? '#d97706' : '#94a3b8', !empty($d['mnt']) ? '#fffbeb' : '#f8fafc', APP_URL . '/pages/' . $mod['p_mnt'], 'dalam 7 hari ke depan'],
        ['SLA Bulan Ini', $sla['pct'] . '%', 'fa-chart-line', $slaCol[0], $slaCol[1], APP_URL . '/pages/' . $mod['p_sla'], $sla['sla_met'] . ' dari ' . $sla['selesai'] . ' tiket selesai'],
    ], 4); ?>

    <div class="dash-grid">
      <div class="dash-col">
      <div class="panel">
        <div class="panel-hd">
          <h5><i class="fa fa-inbox" style="color:<?= $mod['color'] ?>;"></i> &nbsp;Antrian Menunggu <span class="muted">(<?= count($d['antrian']) ?>)</span></h5>
          <a href="<?= APP_URL ?>/pages/<?= $mod['p_antrian'] ?>" class="btn btn-default btn-sm">Lihat Semua</a>
        </div>
        <?php dash_tbl_antrian($d['antrian'], $mod); ?>
      </div>

      <div class="panel">
        <div class="panel-hd">
          <h5><i class="fa fa-cogs" style="color:<?= $mod['color'] ?>;"></i> &nbsp;Sedang Diproses</h5>
          <a href="<?= APP_URL ?>/pages/<?= $mod['p_all'] ?>?status=diproses" class="btn btn-default btn-sm">Lihat Semua</a>
        </div>
        <?php dash_tbl_diproses($d['diproses'], $mod); ?>
      </div>
      </div><!-- /kolom kiri -->

      <div class="dash-col">
        <?php dash_panel_sla($sla, $mod); ?>
        <?php dash_panel_chart($d['chart'], $mod); ?>
        <?php dash_panel_mnt($d['mnt'], $mod); ?>
      </div><!-- /kolom kanan -->
    </div>

    <?php dash_panel_top($d['top'], $mod); ?>
  <?php endif; ?>

</div>
<?php endforeach; endif; ?>


<?php // ══════════════ INFRASTRUKTUR (admin & teknisi IT) ══════════════
if ($infra):
  $a  = $infra['aset'];  $kn = $infra['kon'];  $sr = $infra['sr'];
  $srCol = !$sr ? '#94a3b8' : ($sr['status_overall'] === 'Normal' ? '#16a34a' : ($sr['status_overall'] === 'Perhatian' ? '#d97706' : '#dc2626')); ?>
<div class="dash-block">
  <?php if (!$full):
    dash_sec_head(['soft' => '#f1f5f9', 'color' => '#475569', 'icon' => 'fa-server', 'label' => 'Infrastruktur'],
        '<a href="?kat=infra" class="btn btn-default btn-sm">Buka detail <i class="fa fa-arrow-right"></i></a>');
  endif; ?>

  <?php dash_info_row([
      ['Total Aset IT', $a['total'], 'fa-laptop', '#10b981', '#ecfdf5', APP_URL . '/pages/aset_it.php',
          $a['baik'] . ' baik' . ($a['rusak'] ? ' · ' . $a['rusak'] . ' rusak' : '')],
      ['Koneksi Online', $kn['online'] . '<span style="font-size:12px;color:#94a3b8;font-weight:400;">/' . $kn['total'] . '</span>', 'fa-wifi',
          $kn['offline'] ? '#f43f5e' : '#22c55e', $kn['offline'] ? '#fff1f2' : '#f0fdf4', APP_URL . '/pages/cek_koneksi.php',
          $kn['offline'] ? $kn['offline'] . ' offline' : 'semua normal'],
      ['Ruangan Server', $sr ? '<span style="font-size:15px;color:' . $srCol . ';">' . $sr['status_overall'] . '</span>' : '<span style="font-size:13px;color:#94a3b8;">Belum ada data</span>',
          'fa-server', $infra['sr_alert'] ? '#f43f5e' : '#64748b', $infra['sr_alert'] ? '#fff1f2' : '#f8fafc', APP_URL . '/pages/server_room.php',
          $sr ? ($sr['suhu_in'] !== null ? $sr['suhu_in'] . '°C' : '') . ($sr['kelembaban'] !== null ? ' · ' . $sr['kelembaban'] . '%RH' : '') : ''],
  ], 3); ?>

  <?php if ($full): ?>
  <div class="dash-grid" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr);">
    <?php dash_panel_server($sr); ?>
    <div class="dash-col">
      <?php dash_panel_aset($a); ?>
      <?php dash_panel_offline($infra['offline']); ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>