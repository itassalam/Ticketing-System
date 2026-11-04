<?php
session_start();
require_once '../config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$is_technician = hasRole(['admin', 'teknisi_atem']);
$back_url = $is_technician ? '/pages/antrian_atem.php' : '/pages/tiket_saya_atem.php';
if (!$id) {
    setFlash('danger', 'ID tiket tidak valid.');
    redirect(APP_URL . $back_url);
}

$query = $pdo->prepare("
    SELECT t.*, k.nama AS kategori_nama, k.sla_jam, k.sla_respon_jam,
           COALESCE(u.nama, t.pemohon_nama, 'Pengunjung') AS pemohon_nama,
           COALESCE(u.divisi, t.pemohon_divisi, '') AS pemohon_divisi,
           tek.nama AS teknisi_nama
    FROM tiket_atem t
    LEFT JOIN kategori_atem k ON k.id=t.kategori_id
    LEFT JOIN users u ON u.id=t.user_id
    LEFT JOIN users tek ON tek.id=t.teknisi_id
    WHERE t.id=?
");
$query->execute([$id]);
$ticket = $query->fetch();
if (!$ticket) {
    $movedTicket = $pdo->prepare('SELECT id FROM tiket_atem WHERE source_ipsrs_id=?');
    $movedTicket->execute([$id]);
    $movedId = $movedTicket->fetchColumn();
    if ($movedId) {
        redirect(APP_URL . '/pages/detail_tiket_atem.php?id=' . (int)$movedId);
    }
    setFlash('danger', 'Tiket ATEM tidak ditemukan.');
    redirect(APP_URL . $back_url);
}
if (!$is_technician && (int)$ticket['user_id'] !== (int)$_SESSION['user_id']) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/pages/tiket_saya_atem.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $is_final = in_array($ticket['status'], ['selesai', 'ditolak', 'tidak_bisa'], true);

    if ($action === 'ambil' && $is_technician && $ticket['status'] === 'menunggu') {
        $update = $pdo->prepare("
            UPDATE tiket_atem
            SET status='diproses', teknisi_id=?, waktu_proses=NOW(),
                durasi_respon_menit=TIMESTAMPDIFF(MINUTE, waktu_submit, NOW())
            WHERE id=? AND status='menunggu'
        ");
        $update->execute([(int)$_SESSION['user_id'], $id]);
        if ($update->rowCount()) {
            $pdo->prepare("INSERT INTO tiket_atem_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,'menunggu','diproses',?)")
                ->execute([$id, (int)$_SESSION['user_id'], 'Tiket mulai diproses oleh ' . ($_SESSION['user_nama'] ?? 'Teknisi')]);
            setFlash('success', 'Tiket berhasil diambil.');
        } else {
            setFlash('warning', 'Tiket sudah diproses oleh petugas lain.');
        }
        redirect(APP_URL . '/pages/detail_tiket_atem.php?id=' . $id);
    }

    if ($action === 'status' && $is_technician && !$is_final) {
        $new_status = $_POST['status_baru'] ?? '';
        $note = trim($_POST['catatan_teknisi'] ?? '');
        if (!in_array($new_status, ['selesai', 'ditolak', 'tidak_bisa'], true)) {
            setFlash('danger', 'Status baru tidak valid.');
        } else {
            $pdo->prepare("
                UPDATE tiket_atem SET status=?, catatan_teknisi=?, waktu_selesai=NOW(),
                    teknisi_id=COALESCE(teknisi_id, ?),
                    waktu_proses=COALESCE(waktu_proses, NOW()),
                    durasi_respon_menit=COALESCE(durasi_respon_menit, TIMESTAMPDIFF(MINUTE, waktu_submit, NOW())),
                    durasi_selesai_menit=TIMESTAMPDIFF(MINUTE, waktu_submit, NOW())
                WHERE id=?
            ")->execute([$new_status, $note ?: null, (int)$_SESSION['user_id'], $id]);
            $pdo->prepare("INSERT INTO tiket_atem_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
                ->execute([$id, (int)$_SESSION['user_id'], $ticket['status'], $new_status, $note ?: 'Status tiket diperbarui.']);
            setFlash('success', 'Status tiket berhasil diperbarui.');
        }
        redirect(APP_URL . '/pages/detail_tiket_atem.php?id=' . $id);
    }

    if ($action === 'assign' && $is_technician) {
        $technician_id = (int)($_POST['teknisi_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM users WHERE id=? AND status='aktif' AND role IN ('admin','teknisi_atem')");
        $check->execute([$technician_id]);
        if (!$technician_id || !$check->fetchColumn()) {
            setFlash('danger', 'Teknisi ATEM yang dipilih tidak valid.');
        } else {
            $pdo->prepare('UPDATE tiket_atem SET teknisi_id=? WHERE id=?')->execute([$technician_id, $id]);
            $pdo->prepare("INSERT INTO tiket_atem_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
                ->execute([$id, (int)$_SESSION['user_id'], $ticket['status'], $ticket['status'], 'Tiket ditugaskan kepada teknisi #' . $technician_id]);
            setFlash('success', 'Teknisi berhasil ditugaskan.');
        }
        redirect(APP_URL . '/pages/detail_tiket_atem.php?id=' . $id);
    }

    if ($action === 'komentar') {
        $comment = trim($_POST['isi'] ?? '');
        if ($comment === '') {
            setFlash('danger', 'Komentar tidak boleh kosong.');
        } else {
            $pdo->prepare('INSERT INTO komentar_atem (tiket_id,user_id,isi) VALUES (?,?,?)')
                ->execute([$id, (int)$_SESSION['user_id'], $comment]);
            $pdo->prepare("INSERT INTO tiket_atem_log (tiket_id,user_id,status_dari,status_ke,keterangan) VALUES (?,?,?,?,?)")
                ->execute([$id, (int)$_SESSION['user_id'], $ticket['status'], $ticket['status'], 'Komentar: ' . substr($comment, 0, 80)]);
            setFlash('success', 'Komentar berhasil dikirim.');
        }
        redirect(APP_URL . '/pages/detail_tiket_atem.php?id=' . $id . '#diskusi');
    }
}

$query->execute([$id]);
$ticket = $query->fetch();
$comments_query = $pdo->prepare("SELECT c.*, u.nama, u.role FROM komentar_atem c LEFT JOIN users u ON u.id=c.user_id WHERE c.tiket_id=? ORDER BY c.created_at ASC");
$comments_query->execute([$id]);
$comments = $comments_query->fetchAll();
$logs_query = $pdo->prepare("SELECT l.*, u.nama FROM tiket_atem_log l LEFT JOIN users u ON u.id=l.user_id WHERE l.tiket_id=? ORDER BY l.created_at ASC");
$logs_query->execute([$id]);
$logs = $logs_query->fetchAll();
$photos_query = $pdo->prepare("
    SELECT f.*, u.nama AS uploader
    FROM tiket_atem_foto f
    LEFT JOIN users u ON u.id=f.user_id
    WHERE f.tiket_id=?
    ORDER BY f.created_at
");
$photos_query->execute([$id]);
$photos = $photos_query->fetchAll();
$technicians = $is_technician ? $pdo->query("SELECT id,nama FROM users WHERE status='aktif' AND role IN ('admin','teknisi_atem') ORDER BY nama")->fetchAll() : [];
$final = in_array($ticket['status'], ['selesai', 'ditolak', 'tidak_bisa'], true);
$duration = $final ? $ticket['durasi_selesai_menit'] : durasiSekarang($ticket['waktu_submit']);
$page_title = 'Detail Tiket ATEM';
$active_menu = $is_technician ? 'antrian_atem' : 'tiket_saya_atem';
include '../includes/header.php';
?>
<div class="page-header">
  <h4><i class="fa fa-heart-pulse text-primary"></i> &nbsp;Detail Tiket ATEM</h4>
  <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><a href="<?= APP_URL . $back_url ?>">Tiket ATEM</a><span class="sep">/</span><span class="cur"><?= clean($ticket['nomor']) ?></span></div>
</div>
<div class="content">
  <?= showFlash() ?>
  <div class="panel">
    <div class="panel-hd"><h5><?= clean($ticket['nomor']) ?> — <?= clean($ticket['judul']) ?></h5><?= badgeStatus($ticket['status']) ?></div>
    <div class="panel-bd">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:18px;">
        <div><small class="text-muted">Kategori</small><div><?= clean($ticket['kategori_nama'] ?? '-') ?></div></div>
        <div><small class="text-muted">Prioritas</small><div><?= badgePrioritas($ticket['prioritas']) ?></div></div>
        <div><small class="text-muted">Pemohon</small><div><?= clean($ticket['pemohon_nama'] ?? ($ticket['legacy_user_id'] ? 'Akun IPSRS #' . (int)$ticket['legacy_user_id'] . ' (tidak tersedia)' : 'Pengguna')) ?> (<?= clean($ticket['pemohon_divisi'] ?: '-') ?>)<br><?= clean($ticket['pemohon_telepon'] ?: '-') ?></div></div>
        <div><small class="text-muted">Teknisi</small><div><?= clean($ticket['teknisi_nama'] ?? ($ticket['legacy_teknisi_id'] ? 'Akun IPSRS #' . (int)$ticket['legacy_teknisi_id'] . ' (tidak tersedia)' : 'Belum ditugaskan')) ?></div></div>
        <div><small class="text-muted">Lokasi / Aset</small><div><?= clean($ticket['lokasi'] ?: '-') ?> / <?= clean($ticket['aset_info'] ?: '-') ?></div></div>
        <div><small class="text-muted">Dibuat</small><div><?= formatTanggal($ticket['waktu_submit'], true) ?></div></div>
        <div><small class="text-muted">Durasi</small><div><?= formatDurasi($duration) ?></div></div>
      </div>
      <h5>Deskripsi</h5><div style="white-space:pre-wrap;margin-bottom:18px;"><?= nl2br(clean($ticket['deskripsi'])) ?></div>
      <?php if ($ticket['catatan_teknisi']): ?><h5>Catatan Penanganan</h5><div style="white-space:pre-wrap;"><?= nl2br(clean($ticket['catatan_teknisi'])) ?></div><?php endif; ?>
      <?php if ($photos): ?>
      <h5 style="margin-top:18px;">Foto Bukti</h5>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
        <?php foreach ($photos as $photo): ?>
        <a href="<?= APP_URL . '/' . clean($photo['path']) ?>" target="_blank" rel="noopener" style="display:block;text-align:center;">
          <img src="<?= APP_URL . '/' . clean($photo['path']) ?>" alt="<?= clean($photo['nama_file']) ?>" loading="lazy" style="width:120px;height:100px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;">
          <small class="text-muted" style="display:block;margin-top:4px;"><?= clean($photo['uploader'] ?? ($photo['legacy_user_id'] ? 'Akun IPSRS #' . (int)$photo['legacy_user_id'] : 'Pengguna')) ?> · <?= formatTanggal($photo['created_at'], true) ?></small>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($is_technician): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:18px;">
        <?php if ($ticket['status'] === 'menunggu'): ?><form method="POST"><input type="hidden" name="action" value="ambil"><button class="btn btn-primary"><i class="fa fa-play"></i> Ambil &amp; Proses</button></form><?php endif; ?>
        <?php if (!$final): ?>
        <form method="POST" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
          <input type="hidden" name="action" value="assign"><select name="teknisi_id" class="sel-filter" required><option value="">Pilih teknisi ATEM</option><?php foreach ($technicians as $technician): ?><option value="<?= (int)$technician['id'] ?>" <?= (int)$ticket['teknisi_id'] === (int)$technician['id'] ? 'selected' : '' ?>><?= clean($technician['nama']) ?></option><?php endforeach; ?></select><button class="btn btn-default"><i class="fa fa-user-check"></i> Tugaskan</button>
        </form>
        <?php endif; ?>
      </div>
      <?php if (!$final): ?>
      <form method="POST" style="margin-top:18px;max-width:700px;">
        <input type="hidden" name="action" value="status">
        <div class="form-group"><label for="status_baru">Selesaikan tiket</label><select id="status_baru" name="status_baru" class="form-control" required><option value="selesai">Selesai</option><option value="ditolak">Ditolak</option><option value="tidak_bisa">Tidak bisa ditangani</option></select></div>
        <div class="form-group"><label for="catatan_teknisi">Catatan penanganan</label><textarea id="catatan_teknisi" name="catatan_teknisi" class="form-control" rows="3"></textarea></div>
        <button class="btn btn-primary"><i class="fa fa-check"></i> Simpan Status</button>
      </form>
      <?php endif; endif; ?>
    </div>
  </div>
  <div class="panel" id="diskusi" style="margin-top:16px;">
    <div class="panel-hd"><h5>Diskusi</h5></div><div class="panel-bd">
      <?php if (!$comments): ?><p class="text-muted">Belum ada komentar.</p><?php endif; ?>
      <?php foreach ($comments as $comment): ?><div style="padding:10px 0;border-bottom:1px solid #eee;"><strong><?= clean($comment['nama'] ?? ($comment['legacy_user_id'] ? 'Akun IPSRS #' . (int)$comment['legacy_user_id'] : 'Pengguna')) ?></strong> <small class="text-muted"><?= formatTanggal($comment['created_at'], true) ?></small><div style="white-space:pre-wrap;"><?= nl2br(clean($comment['isi'])) ?></div></div><?php endforeach; ?>
      <form method="POST" style="margin-top:14px;"><input type="hidden" name="action" value="komentar"><div class="form-group"><label for="isi">Tambah komentar</label><textarea id="isi" name="isi" class="form-control" rows="3" required></textarea></div><button class="btn btn-primary"><i class="fa fa-comment"></i> Kirim</button></form>
    </div>
  </div>
  <div class="panel" style="margin-top:16px;"><div class="panel-hd"><h5>Riwayat</h5></div><div class="panel-bd">
    <?php foreach ($logs as $log): ?><div style="padding:7px 0;border-bottom:1px solid #eee;"><strong><?= clean($log['status_ke']) ?></strong> · <?= clean($log['nama'] ?? ($log['legacy_user_id'] ? 'Akun IPSRS #' . (int)$log['legacy_user_id'] : 'Sistem')) ?> · <span class="text-muted"><?= formatTanggal($log['created_at'], true) ?></span><div><?= nl2br(clean($log['keterangan'] ?? '')) ?></div></div><?php endforeach; ?>
  </div></div>
</div>
<?php include '../includes/footer.php'; ?>
