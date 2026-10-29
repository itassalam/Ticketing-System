<?php
// pages/antrian_ipsrs.php
session_start();
require_once '../config.php';
requireLogin();
if (hasExactRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title  = 'Antrian Tiket IPSRS';
$active_menu = 'antrian_ipsrs';

$page     = max(1, (int)($_GET['page']      ?? 1));
$per_page = 15;
$search   = $_GET['q']        ?? '';
$fp       = $_GET['prioritas'] ?? '';
$fk       = $_GET['kat']       ?? '';

$where  = ["t.status='menunggu'", "t.jenis_tiket='Non-Medis'"]; $params = [];
if ($search) {
    $where[]  = '(t.nomor LIKE ? OR t.judul LIKE ? OR u.nama LIKE ?)';
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($fp) { $where[] = 't.prioritas=?';    $params[] = $fp; }
if ($fk) { $where[] = 't.kategori_id=?'; $params[] = $fk; }
$wsql = implode(' AND ', $where);

// Total
$st = $pdo->prepare("SELECT COUNT(*) FROM tiket_ipsrs t LEFT JOIN users u ON u.id=t.user_id WHERE $wsql");
$st->execute($params);
$total  = (int)$st->fetchColumn();
$pages  = max(1, ceil($total / $per_page));
$page   = min($page, $pages);
$offset = ($page - 1) * $per_page;

// Data
$st = $pdo->prepare("
    SELECT t.*,
           k.nama  AS kat_nama, k.sla_jam,
           u.nama  AS req_nama, u.divisi,
           a.nama_aset, a.no_inventaris AS aset_inv
    FROM tiket_ipsrs t
    LEFT JOIN kategori_ipsrs k ON k.id = t.kategori_id
    LEFT JOIN users           u ON u.id = t.user_id
    LEFT JOIN aset_ipsrs      a ON a.id = t.aset_id
    WHERE $wsql
    ORDER BY
        CASE t.prioritas WHEN 'Tinggi' THEN 1 WHEN 'Sedang' THEN 2 ELSE 3 END,
        t.waktu_submit ASC
    LIMIT $per_page OFFSET $offset
");
$st->execute($params);
$tikets = $st->fetchAll();

// Kategori IPSRS untuk filter
try {
    $kat_list = $pdo->query("SELECT id, nama FROM kategori_ipsrs WHERE jenis='Non-Medis' ORDER BY nama")->fetchAll();
} catch (Exception $e) { $kat_list = []; }

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-inbox" style="color:var(--primary);"></i> &nbsp;Antrian Tiket IPSRS</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Antrian IPSRS</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- ── Stats bar ── -->
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;flex:1;min-width:140px;">
      <div style="width:36px;height:36px;border-radius:8px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-inbox" style="color:#d97706;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $total ?></div>
        <div style="font-size:11px;color:#94a3b8;">Total Antrian</div>
      </div>
    </div>
    <?php
    // Prioritas Tinggi menunggu
    try {
        $cnt_tinggi = (int)$pdo->query("SELECT COUNT(*) FROM tiket_ipsrs WHERE status='menunggu' AND prioritas='Tinggi'")->fetchColumn();
    } catch (Exception $e) { $cnt_tinggi = 0; }
    ?>
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:<?= $cnt_tinggi ? '#fef2f2' : '#fff' ?>;border:1px solid <?= $cnt_tinggi ? '#fca5a5' : '#e2e8f0' ?>;border-radius:8px;flex:1;min-width:140px;cursor:pointer;"
         onclick="document.querySelector('[name=prioritas]').value='Tinggi';document.getElementById('sf').submit()">
      <div style="width:36px;height:36px;border-radius:8px;background:<?= $cnt_tinggi ? '#fee2e2' : '#f1f5f9' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-circle-exclamation" style="color:<?= $cnt_tinggi ? '#dc2626' : '#94a3b8' ?>;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-size:20px;font-weight:800;color:<?= $cnt_tinggi ? '#dc2626' : '#1e293b' ?>;"><?= $cnt_tinggi ?></div>
        <div style="font-size:11px;color:<?= $cnt_tinggi ? '#dc2626' : '#94a3b8' ?>;">Prioritas Tinggi</div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-hd">
      <h5>Menunggu Penanganan <span style="color:#aaa;font-weight:400;">(<?= $total ?>)</span></h5>
    </div>

    <!-- Filter -->
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf" style="display:flex;gap:7px;flex-wrap:wrap;">
          <input type="text" name="q" value="<?= clean($search) ?>" class="inp-search"
                 placeholder="Cari no. tiket, judul, pemohon…"
                 onchange="document.getElementById('sf').submit()">

          <!-- Filter Prioritas -->
          <select name="prioritas" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Semua Prioritas</option>
            <option value="Tinggi" <?= $fp==='Tinggi'?'selected':'' ?>>🔴 Tinggi</option>
            <option value="Sedang" <?= $fp==='Sedang'?'selected':'' ?>>🟡 Sedang</option>
            <option value="Rendah" <?= $fp==='Rendah'?'selected':'' ?>>🟢 Rendah</option>
          </select>

          <!-- Filter Kategori -->
          <select name="kat" class="sel-filter" onchange="document.getElementById('sf').submit()">
            <option value="">Semua Kategori</option>
            <?php foreach ($kat_list as $k): ?>
            <option value="<?= $k['id'] ?>" <?= $fk == $k['id'] ? 'selected' : '' ?>><?= clean($k['nama']) ?></option>
            <?php endforeach; ?>
          </select>

          <?php if ($search || $fp || $fk): ?>
          <a href="?" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Reset</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- Tabel -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>No. Tiket</th>
            <th>Judul</th>
            <th>Kategori</th>
            <th>Prioritas</th>
            <th>Pemohon</th>
            <th>Aset Terkait</th>
            <th>Lokasi</th>
            <th>Masuk</th>
            <th>Menunggu</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tikets)): ?>
          <tr>
            <td colspan="11" class="td-empty">
              <i class="fa fa-check-circle" style="color:var(--green);"></i>
              Tidak ada antrian tiket IPSRS!
            </td>
          </tr>
          <?php else:
            $no = $offset + 1;
            foreach ($tikets as $t):
              $durasi = durasiSekarang($t['waktu_submit']);
              $over   = $t['sla_jam'] && $durasi > $t['sla_jam'] * 60;
          ?>
          <tr style="<?= $over ? 'background:#fff8f8;' : '' ?>">

            <td style="color:#bbb;"><?= $no++ ?></td>

            <!-- No. Tiket -->
            <td>
              <a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>"
                 style="color:var(--primary);font-weight:700;font-family:monospace;font-size:11.5px;">
                <?= clean($t['nomor']) ?>
              </a>
            </td>

            <!-- Judul -->
            <td style="max-width:170px;">
              <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= clean($t['judul']) ?>">
                <?= clean($t['judul']) ?>
              </span>
            </td>

            <!-- Kategori -->
            <td>
              <span style="font-size:11px;color:#64748b;"><?= clean($t['kat_nama'] ?? '-') ?></span>
            </td>

            <!-- Prioritas -->
            <td><?= badgePrioritas($t['prioritas']) ?></td>

            <!-- Pemohon -->
            <td>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs"><?= getInitials($t['req_nama']) ?></div>
                <div>
                  <?= clean($t['req_nama']) ?>
                  <br><span class="text-muted text-sm"><?= clean($t['divisi'] ?? '') ?></span>
                </div>
              </div>
            </td>

            <!-- Aset Terkait -->
            <td style="font-size:11px;">
              <?php if ($t['nama_aset']): ?>
              <span style="font-weight:600;color:#374151;"><?= clean($t['nama_aset']) ?></span>
              <br><span style="font-family:monospace;font-size:10px;color:#6d28d9;background:#f5f3ff;padding:1px 5px;border-radius:3px;"><?= clean($t['aset_inv']) ?></span>
              <?php else: ?>
              <span style="color:#d1d5db;">—</span>
              <?php endif; ?>
            </td>

            <!-- Lokasi -->
            <td style="font-size:11px;color:#888;max-width:120px;">
              <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= clean($t['lokasi'] ?? '-') ?>
              </span>
            </td>

            <!-- Waktu masuk -->
            <td style="font-size:11px;color:#aaa;white-space:nowrap;">
              <?= formatTanggal($t['waktu_submit'], true) ?>
            </td>

            <!-- Durasi menunggu -->
            <td>
              <span style="font-size:12px;font-weight:700;color:<?= $over ? 'var(--red)' : ($durasi > 60 ? 'var(--orange)' : 'var(--green)') ?>;">
                <i class="fa fa-clock"></i> <?= formatDurasi($durasi) ?>
              </span>
              <?php if ($over): ?>
              <br><span style="font-size:10px;color:var(--red);">⚠ SLA Terlewat!</span>
              <?php endif; ?>
            </td>

            <!-- Aksi -->
            <td>
              <a href="<?= APP_URL ?>/pages/detail_tiket_ipsrs.php?id=<?= $t['id'] ?>"
                 class="btn btn-primary btn-sm">
                <i class="fa fa-wrench"></i> Tangani
              </a>
            </td>

          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="tbl-footer">
      <span class="tbl-info">
        Menampilkan <?= min($offset + 1, $total) ?>–<?= min($offset + $per_page, $total) ?> dari <?= $total ?>
      </span>
      <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pag-btn">
          <i class="fa fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
           class="pag-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pag-btn">
          <i class="fa fa-chevron-right"></i>
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /panel -->
</div><!-- /content -->

<?php include '../includes/footer.php'; ?>