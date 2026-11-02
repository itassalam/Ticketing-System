<?php
// pages/buat_tiket_atem.php
session_start();
require_once '../config.php';
requireLogin();
$page_title  = 'Buat Tiket ATEM';
$active_menu = 'buat_tiket_atem';

/* ── KONFIGURASI: sesuaikan dengan nama tabel aset ATEM Anda ───────────────
   Diasumsikan strukturnya sama dengan aset_ipsrs:
   id, no_inventaris, nama_aset, kategori, merek, model_aset, serial_number,
   kondisi, bagian_id, pj_user_id
   ------------------------------------------------------------------------ */
const TABEL_ASET_ATEM = 'aset_atem';

$categories = $pdo->query("SELECT id, nama, icon, sla_jam FROM kategori_atem WHERE aktif=1 ORDER BY nama")->fetchAll();

$lokasi_list = $pdo->query("
    SELECT nama, kode, lokasi FROM bagian
    WHERE status='aktif' AND lokasi IS NOT NULL AND lokasi != ''
    ORDER BY urutan ASC, nama ASC
")->fetchAll();

$user_lokasi_default = '';
if (!empty($_SESSION['user_divisi'])) {
    $stLok = $pdo->prepare("SELECT lokasi FROM bagian WHERE nama=? AND lokasi IS NOT NULL LIMIT 1");
    $stLok->execute([$_SESSION['user_divisi']]);
    $user_lokasi_default = $stLok->fetchColumn() ?: '';
}

// Cek apakah kolom aset_id sudah ada di tiket_atem (opsional)
$has_aset_id = false;
try {
    $has_aset_id = (bool)$pdo->query("SHOW COLUMNS FROM tiket_atem LIKE 'aset_id'")->fetch();
} catch (Throwable $e) {}

// ── AJAX: Cari aset ATEM untuk picker ─────────────────────────────────────────
if (isset($_GET['aset_picker_search'])) {
    $q   = trim($_GET['q']   ?? '');
    $kat = trim($_GET['kat'] ?? '');

    $where  = ["a.kondisi NOT IN ('Rusak','Tidak Aktif')"];
    $params = [];

    if ($q !== '') {
        $lq      = "%$q%";
        $where[] = "(a.no_inventaris LIKE ? OR a.nama_aset LIKE ? OR a.merek LIKE ? OR a.model_aset LIKE ? OR a.serial_number LIKE ?)";
        $params  = array_merge($params, [$lq, $lq, $lq, $lq, $lq]);
    }
    if ($kat !== '') {
        $where[]  = "a.kategori = ?";
        $params[] = $kat;
    }

    $wsql = implode(' AND ', $where);
    $tbl  = TABEL_ASET_ATEM;
    try {
        $st = $pdo->prepare("
            SELECT a.id, a.no_inventaris, a.nama_aset, a.kategori,
                   a.merek, a.model_aset, a.serial_number, a.kondisi,
                   b.nama AS bagian_nama, b.kode AS bagian_kode, b.lokasi AS bagian_lokasi,
                   u.nama AS pj_nama
            FROM {$tbl} a
            LEFT JOIN bagian b ON b.id = a.bagian_id
            LEFT JOIN users  u ON u.id = a.pj_user_id
            WHERE $wsql
            ORDER BY a.nama_aset ASC
            LIMIT 60
        ");
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $rows = [];
    }
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title           = trim($_POST['judul'] ?? '');
    $description     = trim($_POST['deskripsi'] ?? '');
    $category_id     = (int)($_POST['kategori_id'] ?? 0);
    $priority        = $_POST['prioritas'] ?? 'Sedang';
    $location_choice = trim($_POST['lokasi_pilih'] ?? '');
    $location_manual = trim($_POST['lokasi_manual'] ?? '');
    $location        = $location_choice === '__manual__'
        ? $location_manual
        : ($location_choice !== '' ? $location_choice : trim($_POST['lokasi'] ?? ''));

    $aset_id    = (int)($_POST['aset_id'] ?? 0) ?: null;
    $asset_info = trim($_POST['aset_info'] ?? '');

    $valid_priority = ['Rendah', 'Sedang', 'Tinggi'];
    $category_check = $pdo->prepare('SELECT id FROM kategori_atem WHERE id=? AND aktif=1');
    $category_check->execute([$category_id]);

    if ($title === '' || $description === '' || !$category_check->fetchColumn() || !in_array($priority, $valid_priority, true)) {
        setFlash('danger', 'Lengkapi judul, deskripsi, kategori, dan prioritas yang valid.');
    } else {
        try {
            $pdo->beginTransaction();

            if ($has_aset_id) {
                $insert = $pdo->prepare("
                    INSERT INTO tiket_atem (judul, deskripsi, kategori_id, prioritas, user_id, lokasi, aset_info, aset_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([$title, $description, $category_id, $priority, (int)$_SESSION['user_id'], $location ?: null, $asset_info ?: null, $aset_id]);
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO tiket_atem (judul, deskripsi, kategori_id, prioritas, user_id, lokasi, aset_info)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([$title, $description, $category_id, $priority, (int)$_SESSION['user_id'], $location ?: null, $asset_info ?: null]);
            }

            $ticket_id = (int)$pdo->lastInsertId();
            $number = 'TKT-ATEM-' . date('Y') . '-' . str_pad((string)$ticket_id, 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE tiket_atem SET nomor=? WHERE id=?')->execute([$number, $ticket_id]);
            $pdo->prepare("
                INSERT INTO tiket_atem_log (tiket_id, user_id, status_dari, status_ke, keterangan)
                VALUES (?, ?, NULL, 'menunggu', ?)
            ")->execute([$ticket_id, (int)$_SESSION['user_id'], 'Tiket dibuat oleh ' . ($_SESSION['user_nama'] ?? 'Pengguna')]);
            $pdo->commit();
            setFlash('success', "Tiket ATEM <strong>$number</strong> berhasil dibuat. Tim ATEM akan segera menghubungi Anda.");
            redirect(APP_URL . '/pages/detail_tiket_atem.php?id=' . $ticket_id);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

$cur_pilih  = $_POST['lokasi_pilih']  ?? $user_lokasi_default;
$cur_manual = $_POST['lokasi_manual'] ?? '';

// Daftar kategori aset ATEM untuk chip filter di modal
try {
    $kat_aset_opts = $pdo->query("
        SELECT DISTINCT kategori FROM " . TABEL_ASET_ATEM . "
        WHERE kategori IS NOT NULL AND kategori != ''
          AND kondisi NOT IN ('Rusak','Tidak Aktif')
        ORDER BY kategori
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $kat_aset_opts = [];
}

include '../includes/header.php';
?>

<style>
/* ═══════════ ASET PICKER ═══════════ */
.ap-trigger {
    display: flex; align-items: center; gap: 10px;
    width: 100%; padding: 10px 14px; text-align: left;
    border: 2px dashed #cbd5e1; border-radius: 8px;
    background: #f8fafc; cursor: pointer; box-sizing: border-box;
    font-family: inherit; font-size: 13px; font-weight: 600; color: #64748b;
    transition: all .18s;
}
.ap-trigger:hover    { border-color: #26B99A; background: #f0fdf9; color: #0f766e; }
.ap-trigger.has-aset { border-style: solid; border-color: #86efac; background: linear-gradient(135deg,#f0fdf9,#dcfce7); color: #166534; }
.ap-trig-icon { width: 34px; height: 34px; border-radius: 7px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 15px; transition: all .18s; }
.ap-trigger.has-aset .ap-trig-icon { background: #15803d; }
.ap-trigger.has-aset .ap-trig-icon i { color: #fff !important; }
.ap-trig-txt   { flex: 1; min-width: 0; }
.ap-trig-name  { font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ap-trig-sub   { font-size: 10.5px; color: #94a3b8; margin-top: 1px; }
.ap-trigger.has-aset .ap-trig-sub { color: #4ade80; }

.ap-selected-card {
    display: none; margin-top: 9px; padding: 11px 13px;
    background: linear-gradient(135deg,#f0fdf9,#ecfdf5);
    border: 1.5px solid #86efac; border-radius: 8px; position: relative;
}
.ap-selected-card.show { display: block; }
.asc-row    { display: flex; align-items: flex-start; gap: 11px; }
.asc-icon   { width: 38px; height: 38px; border-radius: 8px; background: #15803d; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.asc-body   { flex: 1; min-width: 0; }
.asc-nama   { font-size: 13px; font-weight: 700; color: #1e293b; }
.asc-inv    { display: inline-block; font-family: 'Courier New', monospace; font-size: 10.5px; font-weight: 700; background: #dcfce7; color: #15803d; border: 1px solid #86efac; padding: 1px 7px; border-radius: 4px; margin-top: 2px; }
.asc-meta   { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
.asc-meta-i { display: flex; align-items: center; gap: 4px; font-size: 11px; color: #64748b; }
.asc-meta-i i { color: #26B99A; font-size: 10px; }
.asc-kond   { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 10px; font-size: 10.5px; font-weight: 700; }
.asc-k-baik { background: #dcfce7; color: #166534; }
.asc-k-serv { background: #fef9c3; color: #854d0e; }
.asc-remove { position: absolute; top: 9px; right: 10px; width: 22px; height: 22px; border-radius: 50%; background: #fee2e2; border: none; cursor: pointer; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 10px; transition: background .14s; }
.asc-remove:hover { background: #fca5a5; }

/* ═══════════ MODAL ASET PICKER ═══════════ */
.apm-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,.52); z-index: 9200;
    align-items: center; justify-content: center; padding: 16px;
}
.apm-overlay.open { display: flex; }
.apm-box {
    background: #fff; width: 100%; max-width: 700px;
    border-radius: 12px; box-shadow: 0 28px 70px rgba(0,0,0,.25);
    overflow: hidden; display: flex; flex-direction: column; max-height: 88vh;
}
.apm-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; background: linear-gradient(135deg,#1a2e3f,#1b5c4a); flex-shrink: 0; }
.apm-head-l { display: flex; align-items: center; gap: 10px; }
.apm-head-ic { width: 30px; height: 30px; background: rgba(38,185,154,.25); border: 1px solid rgba(38,185,154,.5); border-radius: 6px; display: flex; align-items: center; justify-content: center; }
.apm-head-title { color: #fff; font-size: 13.5px; font-weight: 700; }
.apm-head-sub   { color: rgba(255,255,255,.4); font-size: 10px; margin-top: 1px; }
.apm-close { width: 26px; height: 26px; border-radius: 50%; background: rgba(255,255,255,.1); border: none; cursor: pointer; color: #ccc; font-size: 12px; display: flex; align-items: center; justify-content: center; transition: background .15s; }
.apm-close:hover { background: #ef4444; color: #fff; }
.apm-search-area { padding: 12px 16px 10px; border-bottom: 1px solid #f1f5f9; background: #fafafa; flex-shrink: 0; }
.apm-jenis-tab  { display: inline-block; padding: 4px 13px; border-radius: 14px; font-size: 11px; font-weight: 700; background: #ec4899; color: #fff; }
.apm-search-wrap { display: flex; border: 1.5px solid #d1d5db; border-radius: 8px; overflow: hidden; background: #fff; transition: border .18s, box-shadow .18s; }
.apm-search-wrap:focus-within { border-color: #26B99A; box-shadow: 0 0 0 3px rgba(38,185,154,.1); }
.apm-search-inp { flex: 1; padding: 9px 12px; border: none; outline: none; font-size: 13px; font-family: inherit; }
.apm-search-btn { padding: 9px 15px; background: #26B99A; border: none; color: #fff; cursor: pointer; font-size: 13px; transition: background .15s; }
.apm-search-btn:hover { background: #1a7a5e; }
.apm-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 9px; }
.apm-chip  { padding: 3px 11px; border-radius: 14px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; cursor: pointer; white-space: nowrap; transition: all .14s; user-select: none; }
.apm-chip:hover  { border-color: #26B99A; color: #0f766e; background: #f0fdf9; }
.apm-chip.active { background: #26B99A; color: #fff; border-color: #26B99A; }
.apm-list { flex: 1; overflow-y: auto; min-height: 220px; }
.apm-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid #f3f4f6; cursor: pointer; transition: background .13s; }
.apm-item:hover { background: #f0fdf9; }
.apm-item:last-child { border-bottom: none; }
.apm-item.selected { background: #dcfce7; }
.apm-item-ic   { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.apm-item-body { flex: 1; min-width: 0; }
.apm-item-nama { font-size: 12.5px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.apm-item-inv  { display: inline-block; font-family: 'Courier New', monospace; font-size: 10px; background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; padding: 1px 6px; border-radius: 3px; margin-top: 2px; }
.apm-item-meta { font-size: 10.5px; color: #94a3b8; margin-top: 2px; }
.apm-item-lok  { font-size: 10px; color: #b0bec5; margin-top: 2px; }
.apm-item-right { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.apm-kond      { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 10px; font-size: 10.5px; font-weight: 700; white-space: nowrap; }
.apm-k-baik    { background: #dcfce7; color: #166534; }
.apm-k-serv    { background: #fef9c3; color: #854d0e; }
.apm-state { padding: 40px 20px; text-align: center; color: #94a3b8; }
.apm-state i { font-size: 34px; display: block; margin-bottom: 10px; }
.apm-state-title { font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 4px; }
.apm-state-sub   { font-size: 11px; }
.apm-foot { padding: 10px 16px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; flex-shrink: 0; }
.apm-count { font-size: 11px; color: #94a3b8; }
.apm-hint  { font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 5px; }
.apm-hint i { color: #26B99A; }
mark.apm-hl { background: #fef9c3; color: #854d0e; border-radius: 2px; padding: 0 1px; font-weight: 700; font-style: normal; }
</style>

<div class="page-header">
  <h4><i class="fa fa-heart-pulse" style="color:var(--primary);"></i> &nbsp;Buat Tiket ATEM</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Beranda</a><span class="sep">/</span>
    <span class="cur">Buat Tiket ATEM</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <div class="g3">
    <!-- ════ FORM PANEL ════ -->
    <div class="panel">
      <div class="panel-hd">
        <h5><i class="fa fa-edit" style="color:var(--primary);"></i> &nbsp;Form Pengajuan Tiket ATEM</h5>
      </div>
      <div class="panel-bd">
        <form method="POST">

          <div class="form-group">
            <label for="judul">Judul Keluhan / Permintaan <span class="req">*</span></label>
            <input type="text" class="form-control" id="judul" name="judul" maxlength="255" required
                   placeholder="Contoh: Anjungan tidak dapat mencetak nomor antrean..."
                   value="<?= clean($_POST['judul'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label for="sel-lokasi">Lokasi / Ruangan <span class="req">*</span></label>
            <select name="lokasi_pilih" id="sel-lokasi" class="form-control"
                    onchange="toggleManual(this.value)" required>
              <option value="">-- Pilih Ruangan / Bagian --</option>
              <?php foreach ($lokasi_list as $l): ?>
              <option value="<?= clean($l['lokasi']) ?>"
                      <?= $cur_pilih === $l['lokasi'] ? 'selected' : '' ?>>
                <?= clean($l['nama']) ?><?= $l['kode'] ? ' ('.clean($l['kode']).')' : '' ?>
                &nbsp;—&nbsp;<?= clean($l['lokasi']) ?>
              </option>
              <?php endforeach; ?>
              <option value="__manual__" <?= $cur_pilih === '__manual__' ? 'selected' : '' ?>>
                ✏️ Lainnya (ketik manual)
              </option>
            </select>

            <div id="box-manual" style="margin-top:8px;<?= $cur_pilih === '__manual__' ? '' : 'display:none' ?>">
              <input type="text" name="lokasi_manual" id="inp-manual" class="form-control"
                     maxlength="255" placeholder="Contoh: Ruang pelayanan, lantai 2..."
                     value="<?= clean($cur_manual) ?>" <?= $cur_pilih === '__manual__' ? 'required' : '' ?>>
              <div class="form-hint"><i class="fa fa-info-circle"></i> Ketik lokasi spesifik jika tidak ada di daftar.</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="kategori_id">Kategori <span class="req">*</span></label>
              <select class="form-control" id="kategori_id" name="kategori_id" required>
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>"
                        <?= (int)($_POST['kategori_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>>
                  <?= clean($category['nama']) ?> — Target <?= (int)$category['sla_jam'] ?> jam
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="prioritas">Tingkat Prioritas</label>
              <select class="form-control" id="prioritas" name="prioritas">
                <option value="Rendah" <?= ($_POST['prioritas'] ?? '') === 'Rendah' ? 'selected' : '' ?>>🟢 Rendah — Tidak mendesak</option>
                <option value="Sedang" <?= ($_POST['prioritas'] ?? 'Sedang') === 'Sedang' ? 'selected' : '' ?>>🟡 Sedang — Perlu segera</option>
                <option value="Tinggi" <?= ($_POST['prioritas'] ?? '') === 'Tinggi' ? 'selected' : '' ?>>🔴 Tinggi — Sangat mendesak</option>
              </select>
            </div>
          </div>

          <!-- ASET ATEM PICKER -->
          <div class="form-group">
            <label>
              <i class="fa fa-heart-pulse" style="color:var(--primary);margin-right:3px;"></i>
              Peralatan / Aset ATEM yang Bermasalah
              <span style="font-size:10.5px;font-weight:400;color:#94a3b8;margin-left:4px;">(opsional)</span>
            </label>

            <input type="hidden" name="aset_id"   id="inp-aset-id"   value="<?= (int)($_POST['aset_id'] ?? 0) ?: '' ?>">
            <input type="hidden" name="aset_info" id="inp-aset-info" value="<?= clean($_POST['aset_info'] ?? '') ?>">

            <button type="button" id="ap-trig-btn" class="ap-trigger" onclick="apmBuka()">
              <div class="ap-trig-icon">
                <i class="fa fa-magnifying-glass" style="color:#64748b;" id="ap-trig-ico"></i>
              </div>
              <div class="ap-trig-txt">
                <div class="ap-trig-name" id="ap-trig-name">Cari dan pilih dari daftar aset ATEM…</div>
                <div class="ap-trig-sub"  id="ap-trig-sub">Klik untuk membuka daftar peralatan</div>
              </div>
              <i class="fa fa-chevron-right" style="font-size:10px;color:#cbd5e1;margin-left:auto;flex-shrink:0;"></i>
            </button>

            <div class="ap-selected-card" id="ap-selected-card">
              <button type="button" class="asc-remove" onclick="apmReset()" title="Hapus pilihan aset">
                <i class="fa fa-times"></i>
              </button>
              <div class="asc-row">
                <div class="asc-icon">
                  <i class="fa fa-heart-pulse" style="color:#fff;font-size:15px;" id="asc-ico"></i>
                </div>
                <div class="asc-body">
                  <div class="asc-nama" id="asc-nama">—</div>
                  <div style="margin-top:3px;"><div class="asc-inv" id="asc-inv">—</div></div>
                  <div class="asc-meta">
                    <div class="asc-meta-i" id="asc-row-merek"><i class="fa fa-tag"></i><span id="asc-merek">—</span></div>
                    <div class="asc-meta-i" id="asc-row-serial"><i class="fa fa-barcode"></i><span id="asc-serial" style="font-family:monospace;font-size:10.5px;">—</span></div>
                    <div class="asc-meta-i" id="asc-row-lokasi"><i class="fa fa-location-dot"></i><span id="asc-lokasi">—</span></div>
                    <div class="asc-meta-i" id="asc-row-pj"><i class="fa fa-user"></i><span id="asc-pj">—</span></div>
                    <div class="asc-meta-i">
                      <i class="fa fa-circle-half-stroke"></i>
                      <span class="asc-kond asc-k-baik" id="asc-kond">—</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="form-hint">
              <i class="fa fa-lightbulb" style="color:#f59e0b;"></i>
              Pilih peralatan yang bermasalah agar teknisi ATEM langsung tahu aset mana yang harus ditangani.
            </div>
          </div>

          <div class="form-group">
            <label for="deskripsi">Deskripsi Masalah / Permintaan <span class="req">*</span></label>
            <textarea class="form-control" id="deskripsi" name="deskripsi" style="min-height:110px;"
                      placeholder="Jelaskan masalah secara detail:&#10;- Sejak kapan terjadi?&#10;- Apa yang sudah dicoba?&#10;- Kondisi peralatan saat ini (jika ada)"
                      required><?= clean($_POST['deskripsi'] ?? '') ?></textarea>
          </div>

          <div style="display:flex;gap:8px;margin-top:4px;">
            <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Kirim Tiket ATEM</button>
            <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-default">Batal</a>
          </div>
        </form>
      </div>
    </div>

    <!-- ════ INFO PANEL KANAN ════ -->
    <div>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-info-circle" style="color:var(--primary);"></i> &nbsp;Alur Penanganan ATEM</h5></div>
        <div class="panel-bd">
          <?php
          $steps = [
            ['fa-paper-plane','var(--primary)','Anda Kirim Tiket','Tiket masuk ke antrian ATEM'],
            ['fa-clock','var(--yellow)','Menunggu','Tim ATEM akan segera memproses'],
            ['fa-cogs','var(--blue)','Diproses','Teknisi ATEM sedang menangani'],
            ['fa-check-circle','var(--green)','Selesai','Masalah berhasil diselesaikan'],
          ];
          foreach ($steps as $i => [$ic,$cl,$t,$d]):
          ?>
          <div style="display:flex;gap:10px;margin-bottom:<?= $i<3?'14px':'0' ?>;<?= $i<3?'padding-bottom:14px;border-bottom:1px solid #f5f5f5;':'' ?>">
            <div style="width:30px;height:30px;border-radius:50%;background:<?= $cl ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa <?= $ic ?>" style="color:#fff;font-size:12px;"></i>
            </div>
            <div>
              <div style="font-size:12px;font-weight:700;color:#333;"><?= $t ?></div>
              <div style="font-size:11px;color:#aaa;margin-top:1px;"><?= $d ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <hr class="divider">
          <p style="font-size:11px;color:#aaa;line-height:1.7;">
            <i class="fa fa-ban" style="color:var(--red);"></i> <strong>Ditolak:</strong> Permintaan tidak sesuai kebijakan.<br>
            <i class="fa fa-ban" style="color:var(--purple);"></i> <strong>Tidak Bisa:</strong> Di luar kemampuan teknisi ATEM.
          </p>
        </div>
      </div>

      <?php if (!empty($categories)): ?>
      <div class="panel">
        <div class="panel-hd"><h5><i class="fa fa-tags" style="color:var(--primary);"></i> &nbsp;Target SLA per Kategori</h5></div>
        <div class="panel-bd" style="padding:10px 14px;">
          <?php foreach ($categories as $category): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:12px;">
            <span><i class="fa <?= clean($category['icon'] ?? 'fa-tag') ?>" style="color:var(--primary);width:16px;"></i> <?= clean($category['nama']) ?></span>
            <span style="color:#aaa;"><?= (int)$category['sla_jam'] ?> jam</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /g3 -->
</div><!-- /content -->


<!-- ════ MODAL ASET PICKER ATEM ════ -->
<div class="apm-overlay" id="apm-overlay" onclick="if(event.target===this)apmTutup()">
  <div class="apm-box">

    <div class="apm-head">
      <div class="apm-head-l">
        <div class="apm-head-ic"><i class="fa fa-heart-pulse" style="color:#26B99A;font-size:13px;"></i></div>
        <div>
          <div class="apm-head-title">Pilih Peralatan / Aset ATEM</div>
          <div class="apm-head-sub">Cari berdasarkan nama, no. inventaris, merek, atau serial</div>
        </div>
      </div>
      <button type="button" class="apm-close" onclick="apmTutup()"><i class="fa fa-times"></i></button>
    </div>

    <div class="apm-search-area">
      <div style="margin-bottom:9px;">
        <span class="apm-jenis-tab">💗 Aset ATEM</span>
      </div>
      <div class="apm-search-wrap">
        <input type="text" id="apm-inp" class="apm-search-inp"
               placeholder="Ketik nama aset, no. inventaris, merek, serial…"
               oninput="apmDebounce(this.value)" autocomplete="off">
        <button type="button" class="apm-search-btn" onclick="apmLoad(document.getElementById('apm-inp').value)">
          <i class="fa fa-search"></i>
        </button>
      </div>
      <div class="apm-chips" id="apm-chips">
        <span class="apm-chip active" data-kat="">
          <i class="fa fa-layer-group" style="font-size:10px;margin-right:2px;"></i> Semua Kategori
        </span>
        <?php foreach ($kat_aset_opts as $ka): ?>
        <span class="apm-chip" data-kat="<?= clean($ka) ?>"><?= clean($ka) ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="apm-list" id="apm-list">
      <div class="apm-state">
        <i class="fa fa-magnifying-glass" style="color:#e2e8f0;"></i>
        <div class="apm-state-title">Ketik untuk mencari aset ATEM</div>
        <div class="apm-state-sub">Atau klik salah satu filter di atas untuk melihat semua</div>
      </div>
    </div>

    <div class="apm-foot">
      <div class="apm-count" id="apm-count">—</div>
      <div class="apm-hint"><i class="fa fa-circle-info"></i> Hanya menampilkan aset Baik &amp; Dalam Perbaikan</div>
    </div>

  </div>
</div>
<!-- ════ END MODAL ASET PICKER ════ -->


<script>
const APP_URL = '<?= APP_URL ?>';

/* ══ LOKASI TOGGLE ══ */
function toggleManual(val) {
    const box = document.getElementById('box-manual');
    const inp = document.getElementById('inp-manual');
    const manual = val === '__manual__';
    box.style.display = manual ? 'block' : 'none';
    inp.required = manual;
    if (manual) inp.focus(); else inp.value = '';
}
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('sel-lokasi');
    if (sel && sel.value) toggleManual(sel.value);

    // chip filter
    document.querySelectorAll('#apm-chips .apm-chip').forEach(chip => {
        chip.addEventListener('click', () => apmSetKat(chip.dataset.kat || '', chip));
    });
});

/* ══ ASET PICKER ══ */
let _apmKat = '', _apmTimer = null, _apmSelId = null;
const DEFAULT_ICON = ['fa-heart-pulse', '#db2777', '#fdf2f8'];

function apmBuka()  { document.getElementById('apm-overlay').classList.add('open'); apmLoad(''); setTimeout(() => document.getElementById('apm-inp').focus(), 160); }
function apmTutup() { document.getElementById('apm-overlay').classList.remove('open'); }

function apmSetKat(kat, el) {
    _apmKat = kat;
    document.querySelectorAll('.apm-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    apmLoad(document.getElementById('apm-inp').value);
}
function apmDebounce(q) { clearTimeout(_apmTimer); _apmShowLoading(); _apmTimer = setTimeout(() => apmLoad(q), 300); }
function apmLoad(q) {
    let url = `${APP_URL}/pages/buat_tiket_atem.php?aset_picker_search=1&q=${encodeURIComponent(q||'')}`;
    if (_apmKat) url += `&kat=${encodeURIComponent(_apmKat)}`;
    fetch(url).then(r => { if (!r.ok) throw new Error(); return r.json(); }).then(d => _apmRender(d, q)).catch(() => _apmShowError());
}

let _apmData = {};
function _apmRender(data, q) {
    const list = document.getElementById('apm-list');
    const cnt  = document.getElementById('apm-count');
    if (!data || !data.length) {
        cnt.textContent = '0 aset';
        list.innerHTML = `<div class="apm-state"><i class="fa fa-box-open" style="color:#e2e8f0;"></i><div class="apm-state-title">Tidak ada aset ditemukan</div><div class="apm-state-sub">Coba kata kunci lain atau ubah filter</div></div>`;
        return;
    }
    cnt.textContent = data.length + (data.length >= 60 ? '+' : '') + ' aset';
    _apmData = {};
    list.innerHTML = data.map(a => {
        _apmData[a.id] = a;
        const [ico, col, bg] = DEFAULT_ICON;
        const kCls = a.kondisi === 'Baik' ? 'apm-k-baik' : 'apm-k-serv';
        const kIco = a.kondisi === 'Baik' ? 'fa-circle-check' : 'fa-wrench';
        const merekModel = [a.merek, a.model_aset].filter(Boolean).map(_esc).join(' · ');
        const lokasiStr  = _lokasiStr(a);
        return `<div class="apm-item${_apmSelId==a.id?' selected':''}" data-id="${a.id}">
          <div class="apm-item-ic" style="background:${bg};"><i class="fa ${ico}" style="color:${col};"></i></div>
          <div class="apm-item-body">
            <div class="apm-item-nama">${_hl(_esc(a.nama_aset), q)}</div>
            <div class="apm-item-inv">${_hl(_esc(a.no_inventaris), q)}</div>
            <div class="apm-item-meta">${merekModel || _esc(a.kategori || '—')}</div>
            ${lokasiStr ? `<div class="apm-item-lok"><i class="fa fa-location-dot" style="color:#26B99A;font-size:9px;"></i> ${_esc(lokasiStr)}</div>` : ''}
          </div>
          <div class="apm-item-right">
            <div class="apm-kond ${kCls}"><i class="fa ${kIco}" style="font-size:9px;"></i> ${_esc(a.kondisi)}</div>
          </div>
        </div>`;
    }).join('');
    list.querySelectorAll('.apm-item').forEach(el => el.addEventListener('click', () => apmPilih(_apmData[el.dataset.id])));
}
function _lokasiStr(a) {
    return [a.bagian_kode ? '['+a.bagian_kode+']' : '', a.bagian_nama, a.bagian_lokasi].filter(Boolean).join(' ');
}
function _apmShowLoading() {
    document.getElementById('apm-count').textContent = '…';
    document.getElementById('apm-list').innerHTML = `<div class="apm-state"><i class="fa fa-spinner fa-spin" style="color:#26B99A;"></i><div class="apm-state-title">Mencari aset…</div></div>`;
}
function _apmShowError() {
    document.getElementById('apm-count').textContent = '—';
    document.getElementById('apm-list').innerHTML = `<div class="apm-state"><i class="fa fa-triangle-exclamation" style="color:#f59e0b;"></i><div class="apm-state-title">Gagal memuat data</div><div class="apm-state-sub">Periksa koneksi jaringan Anda</div></div>`;
}

function apmPilih(a) {
    if (!a) return;
    const inv = a.no_inventaris || '', nama = a.nama_aset || '', merek = a.merek || '', model = a.model_aset || '';
    const kond = a.kondisi || '', serial = a.serial_number || '', pj = a.pj_nama || '';
    const lokasiStr = _lokasiStr(a), bagianLokasi = a.bagian_lokasi || '';
    const [ico] = DEFAULT_ICON;

    _apmSelId = a.id;
    document.getElementById('inp-aset-id').value   = a.id;
    document.getElementById('inp-aset-info').value = [inv, nama, merek, model].filter(Boolean).join(' | ');

    document.getElementById('ap-trig-btn').classList.add('has-aset');
    document.getElementById('ap-trig-ico').className    = 'fa ' + ico;
    document.getElementById('ap-trig-name').textContent = nama + '  (' + inv + ')';
    document.getElementById('ap-trig-sub').textContent  = [merek, model].filter(Boolean).join(' · ') || 'Klik untuk ganti pilihan';

    document.getElementById('asc-nama').textContent = nama;
    document.getElementById('asc-inv').textContent  = inv;
    const kondEl = document.getElementById('asc-kond');
    kondEl.textContent = kond;
    kondEl.className   = 'asc-kond ' + (kond === 'Baik' ? 'asc-k-baik' : 'asc-k-serv');

    _setRow('asc-row-merek',  'asc-merek',  [merek, model].filter(Boolean).join(' · '));
    _setRow('asc-row-serial', 'asc-serial', serial);
    _setRow('asc-row-pj',     'asc-pj',     pj);
    _setRow('asc-row-lokasi', 'asc-lokasi', lokasiStr);

    // Auto-isi lokasi bila belum dipilih
    if (bagianLokasi) {
        const selLok = document.getElementById('sel-lokasi');
        if (!selLok.value) {
            for (const opt of selLok.options) {
                if (opt.value && opt.value.trim() === bagianLokasi.trim()) { selLok.value = opt.value; toggleManual(opt.value); break; }
            }
        }
    }

    document.getElementById('ap-selected-card').classList.add('show');
    apmTutup();
}
function _setRow(rowId, spanId, val) {
    const row = document.getElementById(rowId);
    if (val) { document.getElementById(spanId).textContent = val; row.style.display = ''; }
    else row.style.display = 'none';
}
function apmReset() {
    _apmSelId = null;
    document.getElementById('inp-aset-id').value   = '';
    document.getElementById('inp-aset-info').value = '';
    document.getElementById('ap-trig-btn').classList.remove('has-aset');
    document.getElementById('ap-trig-ico').className    = 'fa fa-magnifying-glass';
    document.getElementById('ap-trig-name').textContent = 'Cari dan pilih dari daftar aset ATEM…';
    document.getElementById('ap-trig-sub').textContent  = 'Klik untuk membuka daftar peralatan';
    document.getElementById('ap-selected-card').classList.remove('show');
}
function _esc(s) { if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function _hl(html, q) { if (!q || !q.trim()) return html; const safe = q.trim().replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); return html.replace(new RegExp(`(${safe})`,'gi'),'<mark class="apm-hl">$1</mark>'); }
</script>

<?php include '../includes/footer.php'; ?>