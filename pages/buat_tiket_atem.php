<?php
session_start();
require_once '../config.php';
requireLogin();
$page_title = 'Buat Tiket ATEM';
$active_menu = 'buat_tiket_atem';
$categories = $pdo->query("SELECT id, nama FROM kategori_atem WHERE aktif=1 ORDER BY nama")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['judul'] ?? '');
    $description = trim($_POST['deskripsi'] ?? '');
    $category_id = (int)($_POST['kategori_id'] ?? 0);
    $priority = $_POST['prioritas'] ?? 'Sedang';
    $location = trim($_POST['lokasi'] ?? '');
    $asset_info = trim($_POST['aset_info'] ?? '');
    $valid_priority = ['Rendah', 'Sedang', 'Tinggi'];
    $category_check = $pdo->prepare('SELECT id FROM kategori_atem WHERE id=? AND aktif=1');
    $category_check->execute([$category_id]);

    if ($title === '' || $description === '' || !$category_check->fetchColumn() || !in_array($priority, $valid_priority, true)) {
        setFlash('danger', 'Lengkapi judul, deskripsi, kategori, dan prioritas yang valid.');
    } else {
        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare("
                INSERT INTO tiket_atem (judul, deskripsi, kategori_id, prioritas, user_id, lokasi, aset_info)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([$title, $description, $category_id, $priority, (int)$_SESSION['user_id'], $location ?: null, $asset_info ?: null]);
            $ticket_id = (int)$pdo->lastInsertId();
            $number = 'TKT-ATEM-' . date('Y') . '-' . str_pad((string)$ticket_id, 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE tiket_atem SET nomor=? WHERE id=?')->execute([$number, $ticket_id]);
            $pdo->prepare("
                INSERT INTO tiket_atem_log (tiket_id, user_id, status_dari, status_ke, keterangan)
                VALUES (?, ?, NULL, 'menunggu', ?)
            ")->execute([$ticket_id, (int)$_SESSION['user_id'], 'Tiket dibuat oleh ' . ($_SESSION['user_nama'] ?? 'Pengguna')]);
            $pdo->commit();
            setFlash('success', 'Tiket ATEM berhasil dibuat.');
            redirect(APP_URL . '/pages/detail_tiket_atem.php?id=' . $ticket_id);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

include '../includes/header.php';
?>
<div class="page-header">
  <h4><i class="fa fa-heart-pulse text-primary"></i> &nbsp;Buat Tiket ATEM</h4>
  <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">Buat Tiket ATEM</span></div>
</div>
<div class="content">
  <?= showFlash() ?>
  <div class="panel" style="max-width:850px;">
    <div class="panel-hd"><h5>Informasi Gangguan ATEM</h5></div>
    <div class="panel-bd">
      <form method="POST">
        <div class="form-group"><label for="judul">Judul gangguan <span class="text-danger">*</span></label><input class="form-control" id="judul" name="judul" maxlength="255" required value="<?= clean($_POST['judul'] ?? '') ?>"></div>
        <div class="form-group"><label for="kategori_id">Kategori <span class="text-danger">*</span></label><select class="form-control" id="kategori_id" name="kategori_id" required><option value="">Pilih kategori</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)($_POST['kategori_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>><?= clean($category['nama']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="prioritas">Prioritas</label><select class="form-control" id="prioritas" name="prioritas"><?php foreach (['Rendah', 'Sedang', 'Tinggi'] as $value): ?><option value="<?= $value ?>" <?= ($_POST['prioritas'] ?? 'Sedang') === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="lokasi">Lokasi</label><input class="form-control" id="lokasi" name="lokasi" maxlength="255" value="<?= clean($_POST['lokasi'] ?? '') ?>" placeholder="Contoh: Lobby utama"></div>
        <div class="form-group"><label for="aset_info">Nama / nomor aset (opsional)</label><input class="form-control" id="aset_info" name="aset_info" maxlength="255" value="<?= clean($_POST['aset_info'] ?? '') ?>" placeholder="Contoh: ATEM Lobby Utama / ATEM-001"></div>
        <div class="form-group"><label for="deskripsi">Deskripsi gangguan <span class="text-danger">*</span></label><textarea class="form-control" id="deskripsi" name="deskripsi" rows="6" required><?= clean($_POST['deskripsi'] ?? '') ?></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Kirim Tiket</button>
        <a href="<?= APP_URL ?>/pages/tiket_saya_atem.php" class="btn btn-default">Batal</a>
      </form>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
