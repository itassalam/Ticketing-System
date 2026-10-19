<?php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'teknisi_atem'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title = 'Antrian Tiket ATEM';
$active_menu = 'antrian_atem';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$search = trim($_GET['q'] ?? '');
$priority = $_GET['prioritas'] ?? '';
$category_id = (int)($_GET['kat'] ?? 0);
if (!in_array($priority, ['', 'Tinggi', 'Sedang', 'Rendah'], true)) $priority = '';

$where = ["t.status = 'menunggu'"];
$params = [];
if ($search !== '') {
    $where[] = '(t.nomor LIKE ? OR t.judul LIKE ? OR u.nama LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
if ($priority !== '') {
    $where[] = 't.prioritas = ?';
    $params[] = $priority;
}
if ($category_id) {
    $where[] = 't.kategori_id = ?';
    $params[] = $category_id;
}
$where_sql = implode(' AND ', $where);

$count = $pdo->prepare("SELECT COUNT(*) FROM tiket_atem t LEFT JOIN users u ON u.id=t.user_id WHERE $where_sql");
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $pages);
$offset = ($page - 1) * $per_page;

$tickets_query = $pdo->prepare("
    SELECT t.*, k.nama AS kategori_nama, k.sla_jam, u.nama AS pemohon_nama, u.divisi
    FROM tiket_atem t
    LEFT JOIN kategori_atem k ON k.id = t.kategori_id
    LEFT JOIN users u ON u.id = t.user_id
    WHERE $where_sql
    ORDER BY CASE t.prioritas WHEN 'Tinggi' THEN 1 WHEN 'Sedang' THEN 2 ELSE 3 END,
             t.waktu_submit ASC
    LIMIT $per_page OFFSET $offset
");
$tickets_query->execute($params);
$tickets = $tickets_query->fetchAll();
$categories = $pdo->query("SELECT id, nama FROM kategori_atem WHERE aktif=1 ORDER BY nama")->fetchAll();
$high_priority = (int)$pdo->query("SELECT COUNT(*) FROM tiket_atem WHERE status='menunggu' AND prioritas='Tinggi'")->fetchColumn();

include '../includes/header.php';
?>
<div class="page-header">
  <h4><i class="fa fa-inbox text-primary"></i> &nbsp;Antrian Tiket ATEM</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Antrian ATEM</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Ringkasan antrian -->
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
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:<?= $high_priority ? '#fef2f2' : '#fff' ?>;border:1px solid <?= $high_priority ? '#fca5a5' : '#e2e8f0' ?>;border-radius:8px;flex:1;min-width:140px;cursor:pointer;"
         onclick="document.querySelector('[name=prioritas]').value='Tinggi';document.getElementById('atem-filter').submit()">
      <div style="width:36px;height:36px;border-radius:8px;background:<?= $high_priority ? '#fee2e2' : '#f1f5f9' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa fa-circle-exclamation" style="color:<?= $high_priority ? '#dc2626' : '#94a3b8' ?>;font-size:15px;"></i>
      </div>
      <div>
        <div style="font-size:20px;font-weight:800;color:<?= $high_priority ? '#dc2626' : '#1e293b' ?>;"><?= $high_priority ?></div>
        <div style="font-size:11px;color:<?= $high_priority ? '#dc2626' : '#94a3b8' ?>;">Prioritas Tinggi</div>
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
        <form method="GET" id="atem-filter" style="display:flex;gap:7px;flex-wrap:wrap;">
          <input type="search" name="q" value="<?= clean($search) ?>" class="inp-search"
                 placeholder="Cari no. tiket, judul, pemohon..."
                 onchange="this.form.submit()">
          <select name="prioritas" class="sel-filter" onchange="this.form.submit()">
          <option value="">Semua Prioritas</option>
          <?php foreach (['Tinggi', 'Sedang', 'Rendah'] as $value): ?><option value="<?= $value ?>" <?= $priority === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?>
          </select>
          <select name="kat" class="sel-filter" onchange="this.form.submit()">
          <option value="">Semua Kategori</option>
          <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= $category_id === (int)$category['id'] ? 'selected' : '' ?>><?= clean($category['nama']) ?></option><?php endforeach; ?>
          </select>
          <?php if ($search || $priority || $category_id): ?>
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
        <?php if (!$tickets): ?>
          <tr>
            <td colspan="11" class="td-empty">
              <i class="fa fa-check-circle" style="color:var(--green);"></i>
              Tidak ada antrian tiket ATEM!
            </td>
          </tr>
      <?php else: foreach ($tickets as $index => $ticket):
          $duration = durasiSekarang($ticket['waktu_submit']);
          $overdue = $ticket['sla_jam'] && $duration > (int)$ticket['sla_jam'] * 60;
      ?>
          <tr style="<?= $overdue ? 'background:#fff8f8;' : '' ?>">
            <td style="color:#bbb;"><?= $offset + $index + 1 ?></td>
            <td>
              <a href="<?= APP_URL ?>/pages/detail_tiket_atem.php?id=<?= (int)$ticket['id'] ?>"
                 style="color:var(--primary);font-weight:700;font-family:monospace;font-size:11.5px;">
                <?= clean($ticket['nomor']) ?>
              </a>
            </td>
            <td style="max-width:170px;">
              <span title="<?= clean($ticket['judul']) ?>" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= clean($ticket['judul']) ?>
              </span>
            </td>
            <td><?= clean($ticket['kategori_nama'] ?? '-') ?></td>
            <td><?= badgePrioritas($ticket['prioritas']) ?></td>
            <td>
              <div class="d-flex ai-c gap6">
                <div class="av av-xs"><?= getInitials($ticket['pemohon_nama']) ?></div>
                <div><?= clean($ticket['pemohon_nama']) ?><br><span class="text-muted text-sm"><?= clean($ticket['divisi'] ?? '') ?></span></div>
              </div>
            </td>
            <td style="font-size:11px;">
              <?= $ticket['aset_info'] ? '<span style="font-weight:600;color:#374151;">'.clean($ticket['aset_info']).'</span>' : '<span style="color:#d1d5db;">—</span>' ?>
            </td>
            <td style="font-size:11px;color:#888;max-width:120px;">
              <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($ticket['lokasi'] ?? '-') ?></span>
            </td>
            <td style="font-size:11px;color:#aaa;white-space:nowrap;"><?= formatTanggal($ticket['waktu_submit'], true) ?></td>
            <td>
              <span style="font-size:12px;font-weight:700;color:<?= $overdue ? 'var(--red)' : ($duration > 60 ? 'var(--orange)' : 'var(--green)') ?>;">
                <i class="fa fa-clock"></i> <?= formatDurasi($duration) ?>
              </span>
              <?php if ($overdue): ?><br><span style="font-size:10px;color:var(--red);">⚠ SLA Terlewat!</span><?php endif; ?>
            </td>
            <td>
              <a href="<?= APP_URL ?>/pages/detail_tiket_atem.php?id=<?= (int)$ticket['id'] ?>" class="btn btn-primary btn-sm">
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
        Menampilkan <?= $total ? $offset + 1 : 0 ?>–<?= min($offset + $per_page, $total) ?> dari <?= $total ?>
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
  </div>
</div>
<?php include '../includes/footer.php'; ?>