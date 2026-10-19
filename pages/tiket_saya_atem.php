<?php
session_start();
require_once '../config.php';
requireLogin();

$page_title = 'Tiket ATEM Saya';
$active_menu = 'tiket_saya_atem';
$uid = (int)$_SESSION['user_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
if (!in_array($status, ['', 'menunggu', 'diproses', 'selesai', 'ditolak', 'tidak_bisa'], true)) $status = '';

$where = ['t.user_id=?'];
$params = [$uid];
if ($status) { $where[] = 't.status=?'; $params[] = $status; }
if ($search) {
    $where[] = '(t.nomor LIKE ? OR t.judul LIKE ?)';
    array_push($params, "%$search%", "%$search%");
}
$where_sql = implode(' AND ', $where);
$count = $pdo->prepare("SELECT COUNT(*) FROM tiket_atem t WHERE $where_sql");
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $pages);
$offset = ($page - 1) * $per_page;

$query = $pdo->prepare("
    SELECT t.*, k.nama AS kategori_nama, tek.nama AS teknisi
    FROM tiket_atem t
    LEFT JOIN kategori_atem k ON k.id=t.kategori_id
    LEFT JOIN users tek ON tek.id=t.teknisi_id
    WHERE $where_sql
    ORDER BY t.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$query->execute($params);
$tickets = $query->fetchAll();
$stats_query = $pdo->prepare('SELECT status, COUNT(*) AS total FROM tiket_atem WHERE user_id=? GROUP BY status');
$stats_query->execute([$uid]);
$stats = $stats_query->fetchAll(PDO::FETCH_KEY_PAIR);
include '../includes/header.php';
?>
<div class="page-header"><h4><i class="fa fa-heart-pulse text-primary"></i> &nbsp;Tiket ATEM Saya</h4><div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">Tiket ATEM Saya</span></div></div>
<div class="content">
  <?= showFlash() ?>
  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;">
    <?php foreach ([''=>'Semua','menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai','ditolak'=>'Ditolak','tidak_bisa'=>'Tidak bisa'] as $key=>$label):
      $count_for_status = $key === '' ? array_sum($stats) : (int)($stats[$key] ?? 0);
    ?>
    <a href="?<?= http_build_query(array_filter(['status'=>$key,'q'=>$search])) ?>" class="btn <?= $status===$key?'btn-primary':'btn-default' ?> btn-sm"><?= $label ?> <strong><?= $count_for_status ?></strong></a>
    <?php endforeach; ?>
    <a href="<?= APP_URL ?>/pages/buat_tiket_atem.php" class="btn btn-primary btn-sm" style="margin-left:auto;"><i class="fa fa-plus"></i> Buat Tiket ATEM</a>
  </div>
  <div class="panel">
    <div class="tbl-tools"><form method="GET" style="display:flex;gap:7px;">
      <?php if ($status): ?><input type="hidden" name="status" value="<?= clean($status) ?>"><?php endif; ?>
      <input type="search" name="q" class="inp-search" value="<?= clean($search) ?>" placeholder="Cari tiket ATEM...">
      <button class="btn btn-default btn-sm"><i class="fa fa-search"></i> Cari</button>
    </form><span class="tbl-info"><?= $total ?> tiket ditemukan</span></div>
    <div class="tbl-wrap"><table>
      <thead><tr><th>No. Tiket</th><th>Judul</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Teknisi</th><th>Masuk</th><th>Durasi</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php if (!$tickets): ?><tr><td colspan="9" class="td-empty">Belum ada tiket ATEM.</td></tr>
      <?php else: foreach ($tickets as $ticket):
          $final = in_array($ticket['status'], ['selesai','ditolak','tidak_bisa'], true);
          $duration = $final ? $ticket['durasi_selesai_menit'] : durasiSekarang($ticket['waktu_submit']);
      ?><tr>
        <td><a href="<?= APP_URL ?>/pages/detail_tiket_atem.php?id=<?= (int)$ticket['id'] ?>"><?= clean($ticket['nomor']) ?></a></td>
        <td><?= clean($ticket['judul']) ?></td><td><?= clean($ticket['kategori_nama'] ?? '-') ?></td>
        <td><?= badgePrioritas($ticket['prioritas']) ?></td><td><?= badgeStatus($ticket['status']) ?></td>
        <td><?= clean($ticket['teknisi'] ?? 'Belum ditugaskan') ?></td><td><?= formatTanggal($ticket['waktu_submit'], true) ?></td>
        <td><?= formatDurasi($duration) ?></td><td><a class="btn btn-info btn-sm" href="<?= APP_URL ?>/pages/detail_tiket_atem.php?id=<?= (int)$ticket['id'] ?>"><i class="fa fa-eye"></i></a></td>
      </tr><?php endforeach; endif; ?>
      </tbody>
    </table></div>
    <div class="tbl-footer"><span class="tbl-info">Menampilkan <?= $total?$offset+1:0 ?>–<?= min($offset+$per_page,$total) ?> dari <?= $total ?></span>
      <?php if($pages>1): ?><div class="pagination"><?php for($i=1;$i<=$pages;$i++): ?><a class="pag-btn <?= $i===$page?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
