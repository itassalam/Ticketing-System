<?php
// pages/profil.php
session_start();
require_once '../config.php';
requireLogin();

$page_title  = 'Profil Saya';
$active_menu = 'profil';

$uid = (int)$_SESSION['user_id'];

$st = $pdo->prepare("SELECT * FROM users WHERE id=?");
$st->execute([$uid]);
$user = $st->fetch();

// ── Upload dir berkas ─────────────────────────────────────────────────────────
define('PRF_UPLOAD_DIR', dirname(__DIR__) . '/uploads/berkas_karyawan/');
if (!is_dir(PRF_UPLOAD_DIR)) @mkdir(PRF_UPLOAD_DIR, 0755, true);

$self = APP_URL . '/pages/profil.php';

// ══════════════════════════════════════════════════════════════════════════════
// POST HANDLER
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ── Update nama ───────────────────────────────────────────────────────────
    if ($act === 'profil') {
        $n = trim($_POST['nama'] ?? '');
        if ($n === '') {
            setFlash('danger', 'Nama wajib diisi.');
        } else {
            $pdo->prepare("UPDATE users SET nama=? WHERE id=?")->execute([$n, $uid]);
            $_SESSION['user_nama'] = $n;
            setFlash('success', 'Profil berhasil diperbarui.');
        }
    }

    // ── Ganti password ────────────────────────────────────────────────────────
    elseif ($act === 'password') {
        $old = $_POST['old'] ?? '';
        $new = $_POST['new'] ?? '';
        $cnf = $_POST['cnf'] ?? '';
        if (!password_verify($old, $user['password']))  setFlash('danger', 'Password lama salah.');
        elseif (strlen($new) < 6)                       setFlash('danger', 'Password baru minimal 6 karakter.');
        elseif ($new !== $cnf)                          setFlash('danger', 'Konfirmasi password tidak cocok.');
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
            setFlash('success', 'Password berhasil diubah.');
        }
    }

    // ── Upload berkas ─────────────────────────────────────────────────────────
    elseif ($act === 'upload_berkas_profil') {
        $jenis_id = (int)($_POST['jenis_berkas_id'] ?? 0);
        $ket      = trim($_POST['keterangan']  ?? '');
        $tgl_dok  = trim($_POST['tgl_dokumen'] ?? '') ?: null;
        $tgl_exp  = trim($_POST['tgl_exp']     ?? '') ?: null;

        if (!$jenis_id) { setFlash('danger', 'Pilih jenis berkas.'); redirect($self . '#berkas'); }

        $js = $pdo->prepare("SELECT j.*, k.id AS kid FROM master_jenis_berkas j LEFT JOIN master_kategori_berkas k ON k.id=j.kategori_id WHERE j.id=? AND j.status='aktif'");
        $js->execute([$jenis_id]);
        $jenis = $js->fetch(PDO::FETCH_ASSOC);
        if (!$jenis) { setFlash('danger', 'Jenis berkas tidak valid.'); redirect($self . '#berkas'); }

        if (empty($_FILES['file_berkas']['name'])) { setFlash('danger', 'Pilih file terlebih dahulu.'); redirect($self . '#berkas'); }

        $file    = $_FILES['file_berkas'];
        $allowed = array_map('trim', explode(',', $jenis['format_file'] ?: 'pdf,jpg,jpeg,png'));
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            setFlash('danger', 'Format tidak diizinkan. Gunakan: ' . strtoupper(implode(', ', $allowed)));
            redirect($self . '#berkas');
        }
        if ($file['size'] > 10 * 1024 * 1024) { setFlash('danger', 'File melebihi 10 MB.'); redirect($self . '#berkas'); }

        $up_dir = PRF_UPLOAD_DIR . $uid . '/';
        if (!is_dir($up_dir)) @mkdir($up_dir, 0755, true);
        $nama_file = uniqid('bk_', true) . '.' . $ext;

        if (move_uploaded_file($file['tmp_name'], $up_dir . $nama_file)) {
            try {
                // Ganti berkas lama (satu berkas per jenis)
                $old = $pdo->prepare("SELECT nama_file FROM berkas_karyawan WHERE user_id=? AND jenis_berkas_id=? LIMIT 1");
                $old->execute([$uid, $jenis_id]);
                if ($r = $old->fetch()) {
                    @unlink($up_dir . $r['nama_file']);
                    $pdo->prepare("DELETE FROM berkas_karyawan WHERE user_id=? AND jenis_berkas_id=?")->execute([$uid, $jenis_id]);
                }
                $pdo->prepare("INSERT INTO berkas_karyawan (user_id,jenis_berkas_id,kategori_id,nama_file,nama_asli,ukuran,mime_type,keterangan,tgl_dokumen,tgl_exp,status_verif,uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?,'pending',?)")
                    ->execute([$uid, $jenis_id, $jenis['kid'], $nama_file, $file['name'], $file['size'], $file['type'] ?: null, $ket ?: null, $tgl_dok, $tgl_exp, $uid]);
                setFlash('success', 'Berkas <strong>' . htmlspecialchars($jenis['nama']) . '</strong> berhasil diunggah. Menunggu verifikasi HRD.');
            } catch (Exception $e) {
                @unlink($up_dir . $nama_file);
                setFlash('danger', 'Gagal: ' . htmlspecialchars($e->getMessage()));
            }
        } else {
            setFlash('danger', 'Gagal mengunggah file.');
        }
        redirect($self . '#berkas');
    }

    // ── Hapus berkas milik sendiri ────────────────────────────────────────────
    elseif ($act === 'hapus_berkas_profil') {
        $bid = (int)($_POST['berkas_id'] ?? 0);
        if ($bid) {
            $r = $pdo->prepare("SELECT * FROM berkas_karyawan WHERE id=? AND user_id=?");
            $r->execute([$bid, $uid]);
            if ($b = $r->fetch()) {
                @unlink(PRF_UPLOAD_DIR . $uid . '/' . $b['nama_file']);
                $pdo->prepare("DELETE FROM berkas_karyawan WHERE id=?")->execute([$bid]);
                setFlash('success', 'Berkas dihapus.');
            }
        }
        redirect($self . '#berkas');
    }

    redirect($self);
}

// ── Statistik tiket ───────────────────────────────────────────────────────────
$my = $pdo->prepare("SELECT status, COUNT(*) n FROM tiket WHERE user_id=? GROUP BY status");
$my->execute([$uid]);
$ms = [];
foreach ($my->fetchAll() as $r) $ms[$r['status']] = $r['n'];

// ── Master berkas (dikelompokkan per kategori) ────────────────────────────────
$master_berkas = [];
try {
    $master_berkas = $pdo->query("
        SELECT j.id, j.nama, j.icon, j.keterangan, j.wajib, j.has_exp, j.has_tgl_terbit,
               j.format_file, j.urutan,
               k.id AS kategori_id, k.nama AS nama_kategori,
               k.icon AS kat_icon, k.warna AS kat_warna
        FROM master_jenis_berkas j
        LEFT JOIN master_kategori_berkas k ON k.id=j.kategori_id
        WHERE j.status='aktif' AND (k.status='aktif' OR k.id IS NULL)
        ORDER BY k.urutan, k.nama, j.urutan, j.nama
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$berkas_kat = [];
foreach ($master_berkas as $b) {
    $key = ($b['kategori_id'] ?? 0) . '|' . ($b['nama_kategori'] ?? 'Umum');
    if (!isset($berkas_kat[$key])) {
        $berkas_kat[$key] = [
            'nama'  => $b['nama_kategori'] ?? 'Umum',
            'icon'  => $b['kat_icon']      ?? 'fa-folder',
            'warna' => $b['kat_warna']     ?? '#6366f1',
            'items' => [],
        ];
    }
    $berkas_kat[$key]['items'][] = $b;
}
$total_wajib = array_sum(array_column($master_berkas, 'wajib'));

// ── Berkas yang sudah diupload user ───────────────────────────────────────────
$berkas_uploaded = [];
try {
    $bu = $pdo->prepare("SELECT * FROM berkas_karyawan WHERE user_id=?");
    $bu->execute([$uid]);
    foreach ($bu->fetchAll(PDO::FETCH_ASSOC) as $row) $berkas_uploaded[$row['jenis_berkas_id']] = $row;
} catch (Exception $e) {}

$wajib_done = count(array_filter($master_berkas, fn($j) => $j['wajib'] && isset($berkas_uploaded[$j['id']])));
$pct_berkas = $total_wajib > 0 ? min(100, (int)round($wajib_done / $total_wajib * 100)) : 0;

$berkas_exp_cnt = count(array_filter($berkas_uploaded, function ($b) {
    return $b['tgl_exp'] && strtotime($b['tgl_exp']) < strtotime('+30 days') && strtotime($b['tgl_exp']) >= time();
}));

// ── Label role ────────────────────────────────────────────────────────────────
$rs_map = [
    'admin'         => ['Admin',          'fa-shield-alt',         '#5b21b6'],
    'teknisi'       => ['Teknisi IT',     'fa-wrench',             '#1d4ed8'],
    'teknisi_ipsrs' => ['Teknisi IPSRS',  'fa-screwdriver-wrench', '#c2410c'],
    'management_umum' => ['Management Umum','fa-people-group',     '#9d174d'],
    'wadir_umum'      => ['Wadir Umum',    'fa-user-tie',           '#0369a1'],
    'hrd'             => ['Management Umum','fa-people-group',     '#9d174d'],
    'keuangan'      => ['Keuangan',       'fa-coins',              '#0e7490'],
    'akreditasi'    => ['Tim Akreditasi', 'fa-medal',              '#854d0e'],
    'user'          => ['User',           'fa-user',               '#374151'],
];
[$rs_label, $rs_icon, $rs_color] = $rs_map[$user['role']] ?? $rs_map['user'];

include '../includes/header.php';
?>

<style>
.prf-panel, .prf-panel *, .prf-up-ov, .prf-up-ov * { box-sizing: border-box; }

/* ── Panel & header ── */
.prf-panel { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; }
.prf-top { display:flex; align-items:center; gap:16px; padding:20px 24px; flex-wrap:wrap; }
.prf-av { width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,#00e5b0,#00c896); color:#0a0f14; font-size:19px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.prf-id { flex:1; min-width:200px; }
.prf-nama { font-size:16px; font-weight:800; color:#0f172a; line-height:1.3; }
.prf-meta { margin-top:5px; display:flex; align-items:center; gap:8px 14px; flex-wrap:wrap; font-size:12px; color:#6b7280; }
.prf-meta > span { display:inline-flex; align-items:center; gap:5px; }
.prf-badge { font-weight:700; font-size:11px; padding:3px 10px; border-radius:20px; }
.prf-prog { display:flex; align-items:center; gap:10px; flex-shrink:0; font-size:11px; color:#6b7280; }
.prf-prog-bar { width:110px; height:6px; border-radius:99px; background:#e5e7eb; overflow:hidden; }
.prf-prog-bar > div { height:100%; background:linear-gradient(90deg,#6366f1,#4f46e5); }
.prf-prog b { color:#6366f1; font-size:12px; }

.prf-stats { display:grid; grid-template-columns:repeat(4,1fr); border-top:1px solid #f0f2f7; }
.prf-stat { padding:11px 10px; text-align:center; border-right:1px solid #f0f2f7; }
.prf-stat:last-child { border-right:none; }
.prf-stat-num { font-size:19px; font-weight:800; line-height:1; }
.prf-stat-lbl { font-size:10px; color:#9ca3af; font-weight:500; margin-top:3px; }

/* ── Tabs ── */
.prf-tabs { display:flex; border-top:1px solid #e5e7eb; border-bottom:2px solid #e5e7eb; background:#fafafa; overflow-x:auto; }
.prf-tab { padding:12px 18px; font-size:12.5px; font-weight:600; color:#6b7280; border:none; background:none; cursor:pointer; white-space:nowrap; border-bottom:2px solid transparent; margin-bottom:-2px; transition:color .15s,background .15s; display:flex; align-items:center; gap:7px; font-family:inherit; }
.prf-tab.active { color:#00c896; border-bottom-color:#00c896; }
.prf-tab:hover:not(.active) { color:#374151; background:#f3f4f6; }
.prf-tab .cnt { font-size:10px; font-weight:700; padding:1px 7px; border-radius:10px; background:#e0e7ff; color:#4f46e5; }
.prf-pane { display:none; padding:24px; }
.prf-pane.active { display:block; }

/* ── Form ── */
.prf-sec { font-size:10px; font-weight:700; color:#00c896; letter-spacing:1.2px; text-transform:uppercase; margin:0 0 12px; padding-bottom:7px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:6px; }
.prf-g2 { display:grid; grid-template-columns:1fr 1fr; gap:4px 16px; max-width:640px; }
.prf-fg { display:flex; flex-direction:column; gap:5px; margin-bottom:12px; }
.prf-fg label { font-size:11.5px; font-weight:600; color:#374151; }
.prf-fg label .opt { font-size:10px; color:#9ca3af; font-weight:400; }
.prf-fg .req { color:#ef4444; }
.prf-fg input { height:38px; border:1px solid #d1d5db; border-radius:8px; padding:0 12px; font-size:13px; font-family:inherit; background:#f9fafb; color:#111827; width:100%; transition:border-color .15s,background .15s,box-shadow .15s; }
.prf-fg input:focus { outline:none; border-color:#00c896; background:#fff; box-shadow:0 0 0 3px rgba(0,200,150,.12); }
.prf-fg input[readonly] { background:#f3f4f6; color:#9ca3af; cursor:not-allowed; }

/* ── Berkas ── */
.bk-summary { background:linear-gradient(135deg,#f5f3ff,#ede9fe); border:1px solid #c4b5fd; border-radius:12px; padding:16px 20px; margin-bottom:14px; display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.bk-note { background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:11px 14px; margin-bottom:16px; font-size:12px; color:#92400e; display:flex; gap:8px; align-items:flex-start; }
.bk-kat-card { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; margin-bottom:10px; }
.bk-kat-hd { background:#f8fafc; padding:11px 16px; display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none; transition:background .15s; }
.bk-kat-hd:hover { background:#f1f5f9; }
.bk-kat-arrow { font-size:10px; color:#94a3b8; transition:transform .2s; margin-left:4px; }
.bk-kat-body { display:none; }
.bk-kat-card.open .bk-kat-body { display:block; }
.bk-kat-card.open .bk-kat-arrow { transform:rotate(180deg); }
.bk-item-row { display:flex; align-items:center; gap:12px; padding:11px 16px; border-top:1px solid #f1f5f9; transition:background .12s; }
.bk-item-row:hover { background:#fafbff; }
.bk-item-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
.bk-info { flex:1; min-width:0; }
.bk-title { font-size:12.5px; font-weight:600; color:#1e293b; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.bk-sub { font-size:10.5px; color:#9ca3af; margin-top:2px; overflow:hidden; text-overflow:ellipsis; }
.bk-acts { display:flex; align-items:center; gap:6px; flex-shrink:0; }
.bk-chip { font-size:10px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; display:inline-flex; align-items:center; gap:4px; }
.bk-chip-ok { background:#dcfce7; color:#16a34a; }
.bk-chip-pending { background:#fef3c7; color:#d97706; }
.bk-chip-ditolak { background:#fee2e2; color:#dc2626; }
.bk-chip-kosong { background:#f1f5f9; color:#94a3b8; }
.bk-req { font-size:9px; font-weight:700; padding:1px 5px; border-radius:4px; background:#fee2e2; color:#dc2626; }
.bk-exp-soon { font-size:9.5px; font-weight:700; padding:1px 6px; border-radius:4px; background:#fff7ed; color:#ea580c; }
.bk-expired { font-size:9.5px; font-weight:700; padding:1px 6px; border-radius:4px; background:#fee2e2; color:#dc2626; }
.bk-btn { font-size:11px; font-weight:600; padding:6px 11px; border-radius:8px; display:inline-flex; align-items:center; gap:5px; white-space:nowrap; cursor:pointer; font-family:inherit; text-decoration:none; transition:all .15s; }
.bk-btn-view { border:1px solid #e2e8f0; background:#fff; color:#374151; }
.bk-btn-view:hover { background:#f1f5f9; }
.bk-btn-up { border:1.5px dashed #94a3b8; background:#f8fafc; color:#64748b; }
.bk-btn-up:hover { border-color:#6366f1; color:#6366f1; background:#f5f3ff; }
.bk-btn-del { width:30px; height:30px; padding:0; justify-content:center; border:1px solid #fca5a5; background:#fff5f5; color:#dc2626; }
.bk-btn-del:hover { background:#dc2626; color:#fff; }
.bk-empty { text-align:center; padding:40px 20px; color:#9ca3af; }

/* ── Modal upload ── */
.prf-up-ov { display:none; position:fixed; inset:0; background:rgba(15,23,42,.65); z-index:99999; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(4px); }
.prf-up-ov.open { display:flex; }
.prf-up-box { background:#fff; border-radius:14px; box-shadow:0 24px 80px rgba(0,0,0,.3); width:100%; max-width:460px; max-height:92vh; overflow:hidden auto; animation:prfIn .2s ease; }
@keyframes prfIn { from{opacity:0;transform:translateY(14px) scale(.97);} to{opacity:1;transform:none;} }
.prf-up-hd { background:linear-gradient(135deg,#4f46e5,#6366f1); padding:14px 18px; display:flex; align-items:center; gap:10px; color:#fff; }
.prf-up-title { font-size:13px; font-weight:700; flex:1; }
.prf-up-close { width:28px; height:28px; border-radius:7px; border:none; background:rgba(255,255,255,.15); color:#fff; cursor:pointer; font-size:12px; display:flex; align-items:center; justify-content:center; transition:background .15s; }
.prf-up-close:hover { background:#dc2626; }
.prf-up-body { padding:18px; }
.prf-up-drop { border:2px dashed #c7d2fe; border-radius:12px; padding:22px; text-align:center; background:#f5f3ff; cursor:pointer; margin-bottom:14px; transition:all .15s; }
.prf-up-drop:hover, .prf-up-drop.drag { border-color:#6366f1; background:#ede9fe; }
.prf-up-drop i { font-size:24px; color:#6366f1; margin-bottom:8px; display:block; }
.prf-up-drop p { font-size:12.5px; color:#6b7280; margin:0; }
.prf-up-drop small { font-size:10.5px; color:#9ca3af; }
.prf-up-prev { display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:9px 12px; margin-bottom:12px; font-size:12px; align-items:center; gap:8px; }
.prf-up-fg { margin-bottom:10px; }
.prf-up-fg label { font-size:11px; font-weight:600; color:#374151; display:block; margin-bottom:4px; }
.prf-up-fg input, .prf-up-fg textarea { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:7px 11px; font-size:12.5px; font-family:inherit; background:#f9fafb; color:#111827; transition:border-color .15s; }
.prf-up-fg input:focus, .prf-up-fg textarea:focus { outline:none; border-color:#6366f1; background:#fff; box-shadow:0 0 0 3px rgba(99,102,241,.1); }
.prf-up-ft { padding:12px 18px; border-top:1px solid #e5e7eb; display:flex; gap:8px; justify-content:flex-end; background:#f8fafc; }

@media(max-width:700px) {
  .prf-g2 { grid-template-columns:1fr; }
  .prf-pane { padding:16px; }
  .prf-prog { width:100%; }
  .prf-prog-bar { flex:1; }
  .bk-item-row { flex-wrap:wrap; }
  .bk-acts { width:100%; justify-content:flex-end; }
}
</style>

<div class="page-header">
  <h4><i class="fa fa-user-circle text-primary"></i> &nbsp;Profil Saya</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Profil</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <div class="prf-panel">

    <!-- ══ HEADER ══ -->
    <div class="prf-top">
      <div class="prf-av"><?= getInitials($user['nama']) ?></div>
      <div class="prf-id">
        <div class="prf-nama"><?= clean($user['nama']) ?></div>
        <div class="prf-meta">
          <span><i class="fa fa-at" style="font-size:10px;"></i><?= clean($user['username']) ?></span>
          <?php if (!empty($user['divisi'])): ?>
          <span><i class="fa fa-building" style="font-size:10px;"></i><?= clean($user['divisi']) ?></span>
          <?php endif; ?>
          <span class="prf-badge" style="color:<?= $rs_color ?>;background:<?= $rs_color ?>18;">
            <i class="fa <?= $rs_icon ?>" style="font-size:9px;"></i> <?= $rs_label ?>
          </span>
        </div>
      </div>
      <?php if ($total_wajib > 0): ?>
      <div class="prf-prog" title="<?= $wajib_done ?> dari <?= $total_wajib ?> berkas wajib">
        <span>Berkas</span>
        <div class="prf-prog-bar"><div style="width:<?= $pct_berkas ?>%;"></div></div>
        <b><?= $pct_berkas ?>%</b>
      </div>
      <?php endif; ?>
    </div>

    <!-- Statistik tiket -->
    <div class="prf-stats">
      <?php foreach ([['Menunggu',$ms['menunggu']??0,'#f59e0b'],['Diproses',$ms['diproses']??0,'#3b82f6'],['Selesai',$ms['selesai']??0,'#10b981'],['Ditolak',$ms['ditolak']??0,'#ef4444']] as [$lbl,$val,$clr]): ?>
      <div class="prf-stat">
        <div class="prf-stat-num" style="color:<?= $clr ?>;"><?= (int)$val ?></div>
        <div class="prf-stat-lbl">Tiket <?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ══ TABS ══ -->
    <div class="prf-tabs">
      <button type="button" class="prf-tab active" data-pane="tp-profil"><i class="fa fa-user"></i> Edit Profil</button>
      <button type="button" class="prf-tab" data-pane="tp-pw"><i class="fa fa-lock"></i> Ganti Password</button>
    </div>

    <!-- ══ TAB: Edit Profil ══ -->
    <div id="tp-profil" class="prf-pane active">
      <form method="POST">
        <input type="hidden" name="action" value="profil">
        <div class="prf-sec"><i class="fa fa-user"></i> Informasi Akun</div>
        <div class="prf-g2">
          <div class="prf-fg">
            <label>Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" value="<?= clean($user['nama']) ?>" required>
          </div>
          <div class="prf-fg">
            <label>Username <span class="opt">(untuk login, tidak dapat diubah)</span></label>
            <input type="text" value="<?= clean($user['username']) ?>" readonly>
          </div>
          <div class="prf-fg">
            <label>Divisi / Unit</label>
            <input type="text" value="<?= clean($user['divisi'] ?? '') ?: '—' ?>" readonly>
          </div>
          <div class="prf-fg">
            <label>Role <span class="opt">(diatur oleh admin)</span></label>
            <input type="text" value="<?= $rs_label ?>" readonly>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Profil</button>
      </form>
    </div>
   
    <!-- ══ TAB: Ganti Password ══ -->
    <div id="tp-pw" class="prf-pane active">
       <form method="POST">
      <div class="prf-sec"><i class="fa fa-lock"></i> Ubah Password</div>
       <div class="prf-g3">
        <input type="hidden" name="action" value="password">
        <div class="prf-fg"><label>Password Lama <span class="req">*</span></label><input type="password" name="old" required autocomplete="current-password"></div>
        <div class="prf-fg"><label>Password Baru <span class="req">*</span></label><input type="password" name="new" id="pw-new" required placeholder="Min. 6 karakter" autocomplete="new-password"></div>
        <div class="prf-fg"><label>Konfirmasi Password Baru <span class="req">*</span></label><input type="password" name="cnf" id="pw-cnf" required autocomplete="new-password"></div>
        <div id="pw-match-msg" style="font-size:11.5px;margin-bottom:12px;display:none;"></div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa fa-lock"></i> Ganti Password</button>
    </form>
  </div>
  </div><!-- /.prf-panel -->
</div><!-- /.content -->


<!-- ══════════ MODAL UPLOAD BERKAS ══════════ -->
<div id="prf-up-modal" class="prf-up-ov">
  <div class="prf-up-box">
    <div class="prf-up-hd">
      <i class="fa fa-cloud-arrow-up" style="font-size:17px;"></i>
      <span class="prf-up-title" id="prf-up-title">Upload Berkas</span>
      <button type="button" class="prf-up-close" id="prf-up-x"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="prf-up-form">
      <input type="hidden" name="action"          value="upload_berkas_profil">
      <input type="hidden" name="jenis_berkas_id" id="prf-up-jenis-id" value="">
      <div class="prf-up-body">
        <div class="prf-up-drop" id="prf-up-drop">
          <i class="fa fa-file-arrow-up"></i>
          <p>Klik atau seret file ke sini</p>
          <small id="prf-up-hint">Format: PDF, JPG, PNG — Maks. 10 MB</small>
        </div>
        <input type="file" name="file_berkas" id="prf-up-file" style="display:none;">
        <div class="prf-up-prev" id="prf-up-prev">
          <i class="fa fa-file-circle-check" style="color:#16a34a;"></i>
          <span id="prf-up-fname" style="flex:1;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
          <span id="prf-up-fsize" style="color:#9ca3af;flex-shrink:0;"></span>
        </div>
        <div id="prf-up-terbit-wrap" class="prf-up-fg" style="display:none;">
          <label>Tanggal Terbit Dokumen</label>
          <input type="date" name="tgl_dokumen">
        </div>
        <div id="prf-up-exp-wrap" class="prf-up-fg" style="display:none;">
          <label>Tanggal Kadaluarsa</label>
          <input type="date" name="tgl_exp">
        </div>
        <div class="prf-up-fg">
          <label>Keterangan <span style="font-size:10px;color:#9ca3af;">(opsional)</span></label>
          <textarea name="keterangan" rows="2" placeholder="No. dokumen, instansi, catatan…"></textarea>
        </div>
      </div>
      <div class="prf-up-ft">
        <button type="button" class="btn btn-default" id="prf-up-cancel"><i class="fa fa-times"></i> Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-cloud-arrow-up"></i> Upload</button>
      </div>
    </form>
  </div>
</div>


<script>
(function () {
  /* ── Tabs ── */
  var tabs  = document.querySelectorAll('.prf-tab');
  var panes = document.querySelectorAll('.prf-pane');
  function openTab(id) {
    tabs.forEach(function (b)  { b.classList.toggle('active', b.getAttribute('data-pane') === id); });
    panes.forEach(function (p) { p.classList.toggle('active', p.id === id); });
  }
  tabs.forEach(function (b) { b.addEventListener('click', function () { openTab(b.getAttribute('data-pane')); }); });
  if (location.hash === '#berkas') openTab('tp-berkas');

  /* ── Validasi konfirmasi password ── */
  var pwNew = document.getElementById('pw-new'), pwCnf = document.getElementById('pw-cnf'), pwMsg = document.getElementById('pw-match-msg');
  function checkPw() {
    if (!pwCnf.value) { pwMsg.style.display = 'none'; return; }
    pwMsg.style.display = 'block';
    pwMsg.innerHTML = pwNew.value === pwCnf.value
      ? '<span style="color:#10b981;"><i class="fa fa-check"></i> Password cocok</span>'
      : '<span style="color:#ef4444;"><i class="fa fa-times"></i> Password tidak cocok</span>';
  }
  pwNew.addEventListener('input', checkPw);
  pwCnf.addEventListener('input', checkPw);

  /* ── Accordion kategori berkas ── */
  document.querySelectorAll('.bk-kat-hd').forEach(function (hd) {
    hd.addEventListener('click', function () { hd.parentNode.classList.toggle('open'); });
  });

  /* ── Modal upload ── */
  var mod   = document.getElementById('prf-up-modal');
  var form  = document.getElementById('prf-up-form');
  var file  = document.getElementById('prf-up-file');
  var drop  = document.getElementById('prf-up-drop');
  var prev  = document.getElementById('prf-up-prev');

  function openUpload(btn) {
    form.reset();
    prev.style.display = 'none';
    document.getElementById('prf-up-jenis-id').value = btn.dataset.id;
    document.getElementById('prf-up-title').textContent = btn.dataset.nama;
    document.getElementById('prf-up-exp-wrap').style.display    = btn.dataset.exp    === '1' ? 'block' : 'none';
    document.getElementById('prf-up-terbit-wrap').style.display = btn.dataset.terbit === '1' ? 'block' : 'none';
    var fmt = btn.dataset.fmt;
    document.getElementById('prf-up-hint').textContent = 'Format: ' + fmt.toUpperCase() + ' — Maks. 10 MB';
    file.setAttribute('accept', fmt.split(',').map(function (f) { return '.' + f.trim(); }).join(','));
    mod.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeUpload() { mod.classList.remove('open'); document.body.style.overflow = ''; }

  document.querySelectorAll('.bk-btn-up').forEach(function (b) { b.addEventListener('click', function () { openUpload(b); }); });
  document.getElementById('prf-up-x').addEventListener('click', closeUpload);
  document.getElementById('prf-up-cancel').addEventListener('click', closeUpload);
  mod.addEventListener('click', function (e) { if (e.target === mod) closeUpload(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && mod.classList.contains('open')) closeUpload(); });

  function showPrev(f) {
    document.getElementById('prf-up-fname').textContent = f.name;
    document.getElementById('prf-up-fsize').textContent = f.size < 1048576 ? (f.size / 1024).toFixed(1) + ' KB' : (f.size / 1048576).toFixed(1) + ' MB';
    prev.style.display = 'flex';
  }
  drop.addEventListener('click', function () { file.click(); });
  file.addEventListener('change', function () { if (file.files[0]) showPrev(file.files[0]); });
  drop.addEventListener('dragover',  function (e) { e.preventDefault(); drop.classList.add('drag'); });
  drop.addEventListener('dragleave', function () { drop.classList.remove('drag'); });
  drop.addEventListener('drop', function (e) {
    e.preventDefault(); drop.classList.remove('drag');
    if (e.dataTransfer.files[0]) {
      var dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]);
      file.files = dt.files;
      showPrev(e.dataTransfer.files[0]);
    }
  });
  form.addEventListener('submit', function (e) {
    if (!file.files[0]) { e.preventDefault(); alert('Pilih file terlebih dahulu!'); }
  });
})();
</script>

<?php include '../includes/footer.php'; ?>