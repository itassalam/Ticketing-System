<?php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'teknisi_atem'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title = 'Semua Tiket ATEM';
$active_menu = 'semua_tiket_atem';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$status = $_GET['status'] ?? '';
$priority = $_GET['prioritas'] ?? '';
$search = trim($_GET['q'] ?? '');
if (!in_array($status, ['', 'menunggu', 'diproses', 'selesai', 'ditolak', 'tidak_bisa'], true)) $status = '';
if (!in_array($priority, ['', 'Tinggi', 'Sedang', 'Rendah'], true)) $priority = '';

$where = ['1=1'];
$params = [];
if ($status) { $where[] = 't.status=?'; $params[] = $status; }
if ($priority) { $where[] = 't.prioritas=?'; $params[] = $priority; }
if ($search) {
    $where[] = '(t.nomor LIKE ? OR t.judul LIKE ? OR u.nama LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
$where_sql = implode(' AND ', $where);
$count = $pdo->prepare("SELECT COUNT(*) FROM tiket_atem t LEFT JOIN users u ON u.id=t.user_id WHERE $where_sql");
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $pages);
$offset = ($page - 1) * $per_page;

$query = $pdo->prepare("
    SELECT t.*, k.nama AS kategori_nama, u.nama AS pemohon, tek.nama AS teknisi
    FROM tiket_atem t
    LEFT JOIN kategori_atem k ON k.id=t.kategori_id
    LEFT JOIN users u ON u.id=t.user_id
    LEFT JOIN users tek ON tek.id=t.teknisi_id
    WHERE $where_sql
    ORDER BY t.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$query->execute($params);
$tickets = $query->fetchAll();
$stats = $pdo->query('SELECT status, COUNT(*) AS total FROM tiket_atem GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
include '../includes/header.php';
?>
<div class="page-header"><h4><i class="fa fa-ticket-alt text-primary"></i> &nbsp;Semua Tiket ATEM</h4><div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">Semua Tiket ATEM</span></div></div>
<div class="content">
  <?= showFlash() ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
    <?php foreach (['menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai','ditolak'=>'Ditolak','tidak_bisa'=>'Tidak bisa'] as $key=>$label): ?>
    <a class="btn btn-default btn-sm" href="?<?= http_build_query(array_merge($_GET, ['status'=>$key])) ?>"><?= $label ?> <strong><?= (int)($stats[$key] ?? 0) ?></strong></a>
    <?php endforeach; ?>
  </div>
  <div class="panel">
    <div class="tbl-tools"><form method="GET" style="display:flex;gap:7px;flex-wrap:wrap;">
      <input type="search" name="q" class="inp-search" value="<?= clean($search) ?>" placeholder="Cari tiket ATEM...">
      <select name="status" class="sel-filter"><option value="">Semua Status</option><?php foreach (['menunggu','diproses','selesai','ditolak','tidak_bisa'] as $value): ?><option value="<?= $value ?>" <?= $status===$value?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$value)) ?></option><?php endforeach; ?></select>
      <select name="prioritas" class="sel-filter"><option value="">Semua Prioritas</option><?php foreach (['Tinggi','Sedang','Rendah'] as $value): ?><option value="<?= $value ?>" <?= $priority===$value?'selected':'' ?>><?= $value ?></option><?php endforeach; ?></select>
      <button class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Cari</button>
      <?php if ($status || $priority || $search): ?><a href="?" class="btn btn-default btn-sm">Reset</a><?php endif; ?>
    </form></div>
    <div class="tbl-wrap"><table>
      <thead><tr><th>No. Tiket</th><th>Judul</th><th>Kategori</th><th>Pemohon</th><th>Teknisi</th><th>Prioritas</th><th>Status</th><th>Masuk</th></tr></thead>
      <tbody>
      <?php if (!$tickets): ?><tr><td colspan="8" class="td-empty">Belum ada tiket ATEM.</td></tr>
      <?php else: foreach ($tickets as $ticket): ?><tr>
        <td><a href="<?= APP_URL ?>/pages/detail_tiket_atem.php?id=<?= (int)$ticket['id'] ?>"><?= clean($ticket['nomor']) ?></a></td>
        <td><?= clean($ticket['judul']) ?></td><td><?= clean($ticket['kategori_nama'] ?? '-') ?></td>
        <td><?= clean($ticket['pemohon'] ?? '-') ?></td><td><?= clean($ticket['teknisi'] ?? '-') ?></td>
        <td><?= badgePrioritas($ticket['prioritas']) ?></td><td><?= badgeStatus($ticket['status']) ?></td>
        <td><?= formatTanggal($ticket['waktu_submit'], true) ?></td>
      </tr><?php endforeach; endif; ?>
      </tbody>
    </table></div>
    <div class="tbl-footer"><span class="tbl-info">Menampilkan <?= $total ? $offset+1 : 0 ?>–<?= min($offset+$per_page,$total) ?> dari <?= $total ?></span>
      <?php if ($pages>1): ?><div class="pagination"><?php for($i=1;$i<=$pages;$i++): ?><a class="pag-btn <?= $i===$page?'active':'' ?>" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
