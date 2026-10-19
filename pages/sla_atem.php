<?php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin', 'teknisi_atem'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title = 'Laporan SLA ATEM';
$active_menu = 'sla_atem';
$month = max(1, min(12, (int)($_GET['bulan'] ?? date('m'))));
$year = max(2020, min((int)date('Y') + 1, (int)($_GET['tahun'] ?? date('Y'))));
$query = $pdo->prepare("
    SELECT k.id, k.nama, k.sla_jam, COUNT(t.id) AS total,
           SUM(t.status IN ('menunggu','diproses')) AS aktif,
           SUM(t.status='selesai') AS selesai,
           SUM(t.status='selesai' AND t.durasi_selesai_menit <= k.sla_jam*60) AS sesuai_sla,
           AVG(t.durasi_respon_menit) AS rata_respon,
           AVG(t.durasi_selesai_menit) AS rata_selesai
    FROM kategori_atem k
    LEFT JOIN tiket_atem t ON t.kategori_id=k.id AND MONTH(t.created_at)=? AND YEAR(t.created_at)=?
    WHERE k.aktif=1
    GROUP BY k.id
    ORDER BY k.nama
");
$query->execute([$month, $year]);
$category_stats = $query->fetchAll();
$overall_query = $pdo->prepare("
    SELECT COUNT(*) AS total, SUM(status='menunggu') AS menunggu,
           SUM(status='diproses') AS diproses, SUM(status='selesai') AS selesai,
           SUM(status='selesai' AND durasi_selesai_menit <= (
               SELECT sla_jam*60 FROM kategori_atem WHERE id=tiket_atem.kategori_id
           )) AS sesuai_sla,
           AVG(durasi_respon_menit) AS rata_respon,
           AVG(durasi_selesai_menit) AS rata_selesai
    FROM tiket_atem WHERE MONTH(created_at)=? AND YEAR(created_at)=?
");
$overall_query->execute([$month, $year]);
$overall = $overall_query->fetch();
$month_names = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
include '../includes/header.php';
?>
<div class="page-header"><h4><i class="fa fa-chart-line text-primary"></i> &nbsp;Laporan SLA ATEM</h4><div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">SLA ATEM</span></div></div>
<div class="content">
  <?= showFlash() ?>
  <div class="panel"><div class="panel-bd"><form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <label>Periode:</label>
    <select name="bulan" class="sel-filter"><?php foreach($month_names as $index=>$name): ?><option value="<?= $index+1 ?>" <?= $month===$index+1?'selected':'' ?>><?= $name ?></option><?php endforeach; ?></select>
    <select name="tahun" class="sel-filter"><?php for($y=(int)date('Y')+1;$y>=2020;$y--): ?><option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select>
    <button class="btn btn-primary btn-sm">Tampilkan</button>
  </form></div></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0;">
    <?php foreach(['total'=>'Total tiket','menunggu'=>'Menunggu','diproses'=>'Diproses','selesai'=>'Selesai'] as $key=>$label): ?><div class="panel" style="padding:12px 16px;min-width:130px;flex:1;"><div style="font-size:22px;font-weight:800;"><?= (int)($overall[$key]??0) ?></div><div class="text-muted"><?= $label ?></div></div><?php endforeach; ?>
  </div>
  <div class="panel"><div class="panel-hd"><h5>SLA per Kategori — <?= $month_names[$month-1] ?> <?= $year ?></h5></div><div class="tbl-wrap"><table>
    <thead><tr><th>Kategori</th><th>Target</th><th>Total</th><th>Aktif</th><th>Selesai</th><th>Sesuai SLA</th><th>Rata-rata Respon</th><th>Rata-rata Selesai</th></tr></thead>
    <tbody><?php if (!$category_stats): ?><tr><td colspan="8" class="td-empty">Belum ada data kategori.</td></tr><?php else: foreach($category_stats as $row): ?><tr>
      <td><?= clean($row['nama']) ?></td><td><?= (int)$row['sla_jam'] ?> jam</td><td><?= (int)$row['total'] ?></td><td><?= (int)$row['aktif'] ?></td><td><?= (int)$row['selesai'] ?></td>
      <td><?= (int)$row['sesuai_sla'] ?> / <?= (int)$row['selesai'] ?></td><td><?= formatDurasi((int)($row['rata_respon']??0)) ?></td><td><?= formatDurasi((int)($row['rata_selesai']??0)) ?></td>
    </tr><?php endforeach; endif; ?></tbody>
  </table></div></div>
</div>
<?php include '../includes/footer.php'; ?>
