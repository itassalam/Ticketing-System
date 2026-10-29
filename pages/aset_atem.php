<?php
session_start();
require_once '../config.php';
requireLogin();
if (hasExactRole('user')) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title  = 'Aset ATEM';
$active_menu = 'aset_atem';

/* ════════════════════════════════════════════════════════════════════════════
   MODE VIEW (TANPA DATABASE)
   Data aset disimpan di $_SESSION['atem_demo_aset'] — dipakai bersama dengan
   halaman maintenance_atem.php. Buka  ?reset_demo=1  untuk reset data contoh.
════════════════════════════════════════════════════════════════════════════ */
function atemFind(array $list, $id) {
    foreach ($list as $r) if ((int)$r['id'] === (int)$id) return $r;
    return null;
}

if (!isset($_SESSION['atem_demo_aset']) || isset($_GET['reset_demo'])) {
    $d = fn($n) => date('Y-m-d', strtotime("$n days"));
    $base = [
        [1,'ATEM Lobby Utama', 'Anjungan','Advantech','Baik',           'Lobby Utama',     'Terpakai'],
        [2,'ATEM Poli Anak',   'Anjungan','Advantech','Baik',           'Poliklinik Lt.1', 'Terpakai'],
        [3,'ATEM Farmasi',     'Anjungan','Elo',      'Dalam Perbaikan','Depan Farmasi',   'Terpakai'],
        [4,'ATEM Pendaftaran', 'Anjungan','Elo',      'Baik',           'Pendaftaran',     'Terpakai'],
        [5,'ATEM Rawat Jalan', 'Anjungan','Zebra',    'Baik',           'Rawat Jalan Lt.2','Terpakai'],
        [6,'ATEM Radiologi',   'Anjungan','Zebra',    'Rusak',          'Radiologi',       'Tidak Terpakai'],
    ];
    $_SESSION['atem_demo_aset'] = [];
    foreach ($base as [$id,$nama,$kat,$merek,$kond,$lok,$sp]) {
        $_SESSION['atem_demo_aset'][] = [
            'id'=>$id,'no_inventaris'=>'ATEM-'.str_pad($id,3,'0',STR_PAD_LEFT),'nama_aset'=>$nama,'kategori'=>$kat,
            'merek'=>$merek,'kondisi'=>$kond,'lokasi'=>$lok,'status_pakai'=>$sp,
            'no_aset_rs'=>'RS-ATM-'.str_pad($id,3,'0',STR_PAD_LEFT),'model_aset'=>'','serial_number'=>'',
            'penanggung_jawab'=>'Teknisi IT','tanggal_beli'=>$d(-400),'harga_beli'=>18500000,'sumber_dana'=>'BLUD',
            'no_bast'=>'','garansi_sampai'=>$d(330),'keterangan'=>'',
        ];
    }
    if (isset($_GET['reset_demo'])) { setFlash('success','Data aset contoh dikembalikan ke awal.'); redirect(APP_URL.'/pages/aset_atem.php'); }
}
$aset_store = &$_SESSION['atem_demo_aset'];
$bisa_catat = true;

$kat_saran    = ['Anjungan','Anjungan Pendaftaran','Anjungan Antrian','Anjungan Farmasi','Anjungan Informasi','Printer Tiket','Touchscreen / Monitor','Barcode / QR Scanner','Mini PC / CPU','Jaringan (Switch / AP)','UPS / Kelistrikan','Lainnya'];
$sumber_saran = ['APBN','APBD','BLUD','Hibah','Pembelian Mandiri','Donasi','CSR','Lainnya'];
$pj_saran     = ['Teknisi IT','Admin'];

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    if ($act === 'tambah' || $act === 'edit') {
        $auto_inv = ($_POST['auto_inv'] ?? '0') === '1';
        $row = [
            'nama_aset'        => trim($_POST['nama_aset']        ?? ''),
            'no_aset_rs'       => trim($_POST['no_aset_rs']       ?? ''),
            'kategori'         => trim($_POST['kategori']         ?? ''),
            'kondisi'          => trim($_POST['kondisi']          ?? 'Baik'),
            'status_pakai'     => trim($_POST['status_pakai']     ?? 'Terpakai'),
            'merek'            => trim($_POST['merek']            ?? ''),
            'model_aset'       => trim($_POST['model_aset']       ?? ''),
            'serial_number'    => trim($_POST['serial_number']    ?? ''),
            'lokasi'           => trim($_POST['lokasi']           ?? ''),
            'penanggung_jawab' => trim($_POST['penanggung_jawab'] ?? ''),
            'tanggal_beli'     => $_POST['tanggal_beli']          ?: null,
            'harga_beli'       => strlen($_POST['harga_beli'] ?? '') ? (int)$_POST['harga_beli'] : null,
            'sumber_dana'      => trim($_POST['sumber_dana']      ?? ''),
            'no_bast'          => trim($_POST['no_bast']          ?? ''),
            'garansi_sampai'   => $_POST['garansi_sampai']        ?: null,
            'keterangan'       => trim($_POST['keterangan']       ?? ''),
        ];

        if ($act === 'tambah') {
            $newId = $aset_store ? max(array_column($aset_store,'id')) + 1 : 1;
            $prefix = 'INV-ATEM-'.date('Y').'-'; $seq = 1;
            foreach ($aset_store as $r) if (strpos($r['no_inventaris'],$prefix)===0) $seq = max($seq,(int)substr($r['no_inventaris'],-4)+1);
            $no_inv = $auto_inv ? $prefix.str_pad($seq,4,'0',STR_PAD_LEFT) : trim($_POST['no_inventaris'] ?? '');
            $aset_store[] = array_merge(['id'=>$newId,'no_inventaris'=>$no_inv], $row);
            setFlash('success','Aset <strong>'.htmlspecialchars($row['nama_aset']).'</strong> berhasil ditambahkan dengan nomor <code>'.htmlspecialchars($no_inv).'</code>.');
        } else {
            $id = (int)$_POST['id'];
            foreach ($aset_store as $k => $r) if ((int)$r['id'] === $id)
                $aset_store[$k] = array_merge($r, ['no_inventaris'=>trim($_POST['no_inventaris'] ?? $r['no_inventaris'])], $row);
            setFlash('success','Aset berhasil diperbarui.');
        }
        redirect(APP_URL.'/pages/aset_atem.php');
    }

    if ($act === 'hapus' && hasRole('admin')) {
        $id = (int)$_POST['id'];
        $aset_store = array_values(array_filter($aset_store, fn($r) => (int)$r['id'] !== $id));
        setFlash('success','Aset berhasil dihapus.');
        redirect(APP_URL.'/pages/aset_atem.php');
    }
}

// ── AJAX ─────────────────────────────────────────────────────────────────────
if (isset($_GET['get_aset'])) {
    header('Content-Type: application/json');
    echo json_encode(atemFind($aset_store, (int)$_GET['get_aset']) ?: null);
    exit;
}
if (isset($_GET['preview_no_inv'])) {
    $prefix = 'INV-ATEM-'.date('Y').'-'; $seq = 1;
    foreach ($aset_store as $r) if (strpos($r['no_inventaris'],$prefix)===0) $seq = max($seq,(int)substr($r['no_inventaris'],-4)+1);
    header('Content-Type: application/json');
    echo json_encode(['no' => $prefix.str_pad($seq,4,'0',STR_PAD_LEFT)]);
    exit;
}

// ── Filter & Pagination ──────────────────────────────────────────────────────
$page     = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15;
$fk       = $_GET['kategori']     ?? '';
$fkondisi = $_GET['kondisi']      ?? '';
$fstatus  = $_GET['status_pakai'] ?? '';
$search   = $_GET['q']            ?? '';
$today    = date('Y-m-d');
$date_30  = date('Y-m-d', strtotime('+30 days'));

$filtered = array_values(array_filter($aset_store, function($a) use ($fk,$fkondisi,$fstatus,$search) {
    if ($fk       && ($a['kategori'] ?? '')     !== $fk)       return false;
    if ($fkondisi && ($a['kondisi'] ?? '')      !== $fkondisi) return false;
    if ($fstatus  && ($a['status_pakai'] ?? 'Terpakai') !== $fstatus) return false;
    if ($search) {
        $h = implode(' ', [$a['no_inventaris'] ?? '', $a['no_aset_rs'] ?? '', $a['nama_aset'] ?? '', $a['merek'] ?? '', $a['lokasi'] ?? '', $a['penanggung_jawab'] ?? '']);
        if (stripos($h,$search) === false) return false;
    }
    return true;
}));
usort($filtered, fn($a,$b) => $b['id'] <=> $a['id']);
$total  = count($filtered);
$pages  = max(1,(int)ceil($total/$per_page));
$page   = min($page,$pages);
$offset = ($page-1)*$per_page;
$asets  = array_slice($filtered,$offset,$per_page);

// ── Stats ────────────────────────────────────────────────────────────────────
$stats_kondisi = []; $stats_pakai = [];
foreach ($aset_store as $a) {
    $stats_kondisi[$a['kondisi'] ?? 'Baik'] = ($stats_kondisi[$a['kondisi'] ?? 'Baik'] ?? 0) + 1;
    $sp = $a['status_pakai'] ?? 'Terpakai';
    $stats_pakai[$sp] = ($stats_pakai[$sp] ?? 0) + 1;
}
$total_all = count($aset_store);
$kat_opts  = array_values(array_unique(array_filter(array_column($aset_store,'kategori'))));
sort($kat_opts);

// Jadwal maintenance (dari data halaman Maintenance ATEM)
$latest = [];
foreach (($_SESSION['atem_demo'] ?? []) as $m)
    if (!isset($latest[$m['aset_id']]) || $m['id'] > $latest[$m['aset_id']]['id']) $latest[$m['aset_id']] = $m;
$jml_mnt_late = count(array_filter($latest, fn($m) => $m['tgl_maintenance_berikut'] < $today));
$jml_mnt_due  = count(array_filter($latest, fn($m) => $m['tgl_maintenance_berikut'] >= $today && $m['tgl_maintenance_berikut'] <= $date_30));
$jml_garansi  = count(array_filter($aset_store, fn($a) => !empty($a['garansi_sampai']) && $a['garansi_sampai'] >= $today && $a['garansi_sampai'] <= $date_30));

include '../includes/header.php';
?>

<style>
.kondisi-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
.kb-baik{background:#dcfce7;color:#166534;} .kb-rusak{background:#fee2e2;color:#991b1b;}
.kb-perbaikan{background:#fef9c3;color:#854d0e;} .kb-tidak{background:#f1f5f9;color:#64748b;}
.sp-terpakai{background:#dbeafe;color:#1e40af;} .sp-tidak{background:#d1fae5;color:#065f46;} .sp-dipinjam{background:#fef3c7;color:#92400e;}

tr.row-tidak-terpakai{background:linear-gradient(90deg,#f0fdf4,#f7fffe) !important;}
tr.row-tidak-terpakai td{background:transparent !important;opacity:.82;}
tr.row-tidak-terpakai td:first-child{border-left:4px solid #22c55e;}
tr.row-tidak-terpakai .nama-aset-txt{color:#6b7280 !important;text-decoration:line-through;text-decoration-color:#86efac;}
tr.row-dipinjam{background:linear-gradient(90deg,#fffbeb,#fefce8) !important;}
tr.row-dipinjam td{background:transparent !important;}
tr.row-dipinjam td:first-child{border-left:4px solid #f59e0b;}
tr.row-mnt-late td:first-child{border-left:4px solid #ef4444 !important;}

.inv-badge{font-family:'Courier New',monospace;font-size:11px;font-weight:700;background:linear-gradient(135deg,#fff7ed,#ffedd5);color:#c2410c;border:1px solid #fed7aa;padding:2px 8px;border-radius:5px;white-space:nowrap;}
.stat-card-aset{background:#fff;border-radius:8px;border:1px solid #e8ecf0;padding:14px 16px;display:flex;align-items:center;gap:12px;transition:box-shadow .2s;}
.stat-card-aset:hover{box-shadow:0 4px 12px rgba(0,0,0,.07);}
.stat-card-aset.active-filter{border-color:#f97316;box-shadow:0 0 0 2px rgba(249,115,22,.18);}
.stat-icon-aset{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.kal-exp{color:#ef4444;font-weight:700;} .kal-soon{color:#f59e0b;font-weight:700;} .kal-ok{color:#22c55e;} .kal-none{color:#cbd5e1;}
.alert-banner{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:7px;margin-bottom:14px;flex-wrap:wrap;}
.sp-legend-bar{display:flex;align-items:center;gap:16px;padding:9px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;margin-bottom:14px;flex-wrap:wrap;}
.sp-legend-item{display:flex;align-items:center;gap:6px;font-size:11.5px;color:#374151;}
.sp-legend-dot{width:12px;height:12px;border-radius:3px;flex-shrink:0;}
.inv-toggle-wrap{display:flex;align-items:center;gap:8px;padding:7px 11px;border-radius:6px;background:#f8fafc;border:1px solid #e2e8f0;cursor:pointer;user-select:none;transition:all .18s;flex-shrink:0;}
.inv-toggle-wrap:hover{border-color:#f97316;background:#fff7ed;}
.inv-sw{position:relative;width:34px;height:18px;flex-shrink:0;}
.inv-sw input{opacity:0;width:0;height:0;}
.inv-sl{position:absolute;inset:0;background:#cbd5e1;border-radius:34px;transition:.2s;cursor:pointer;}
.inv-sl:before{content:'';position:absolute;width:12px;height:12px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
.inv-sw input:checked~.inv-sl{background:#f97316;}
.inv-sw input:checked~.inv-sl:before{transform:translateX(16px);}
.f-label{font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:4px;}
.f-inp{width:100%;padding:8px 11px;border:1px solid #d1d5db;border-radius:6px;font-size:12.5px;box-sizing:border-box;font-family:inherit;transition:border .18s;background:#fff;}
.f-inp:focus{outline:none;border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12);}
.f-inp:disabled{background:#f8fafc;color:#94a3b8;cursor:not-allowed;}
.grs-modal{height:1px;background:#f0f0f0;margin:14px 0;}
.modal-sec{font-size:11px;font-weight:800;color:#fff;background:linear-gradient(135deg,#c2410c,#f97316);padding:5px 11px;border-radius:5px;margin:14px 0 10px;display:flex;align-items:center;gap:6px;}
#f-status_pakai.val-tidak{border-color:#22c55e;background:#f0fdf4;color:#065f46;}
#f-status_pakai.val-dipinjam{border-color:#f59e0b;background:#fffbeb;color:#92400e;}
#f-status_pakai.val-terpakai{border-color:#3b82f6;background:#eff6ff;color:#1e40af;}
.cetak-drop-wrap{position:relative;display:inline-block;}
.cetak-drop-menu{display:none;position:absolute;right:0;top:36px;z-index:9999;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 8px 28px rgba(0,0,0,.13);min-width:248px;overflow:hidden;}
.cetak-drop-menu.open{display:block;}
.cdm-head{padding:8px 13px;background:#f8fafc;border-bottom:1px solid #e2e8f0;}
.cdm-head-txt{font-size:11px;font-weight:700;color:#374151;}
.cdm-section{padding:6px 13px 4px;font-size:9.5px;font-weight:700;color:#94a3b8;letter-spacing:1.2px;text-transform:uppercase;border-bottom:1px solid #f1f5f9;}
.cdm-item{display:flex;align-items:center;gap:10px;padding:9px 13px;text-decoration:none;color:#1e293b;border-bottom:1px solid #f1f5f9;transition:background .14s;}
.cdm-item:hover{background:#fff7ed;}
.cdm-icon{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;}
.cdm-item-sm{display:flex;align-items:center;gap:9px;padding:7px 13px;text-decoration:none;color:#1e293b;border-bottom:1px solid #f1f5f9;font-size:12px;transition:background .14s;}
.cdm-item-sm:hover{background:#fff7ed;}
.cdm-foot{padding:6px 13px;background:#f8fafc;border-top:1px solid #e2e8f0;}
.cdm-foot-txt{font-size:10px;color:#94a3b8;}
</style>

<div class="page-header">
  <h4><i class="fa fa-desktop" style="color:#f97316;"></i> &nbsp;Aset ATEM</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span><span class="cur">Aset ATEM</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>
  <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:8px;padding:9px 14px;margin-bottom:14px;font-size:12px;display:flex;align-items:center;justify-content:space-between;gap:10px;">
    <span><i class="fa fa-circle-info"></i> <strong>Mode View</strong> — memakai data contoh (belum terhubung database). Perubahan hanya tersimpan di session.</span>
    <a href="?reset_demo=1" style="color:#1e40af;font-weight:700;text-decoration:underline;white-space:nowrap;">Reset data contoh</a>
  </div>

  <?php if ($jml_mnt_late > 0 || $jml_mnt_due > 0 || $jml_garansi > 0): ?>
  <div class="alert-banner" style="background:#fff7ed;border:1px solid #fed7aa;">
    <i class="fa fa-triangle-exclamation" style="color:#f59e0b;font-size:18px;flex-shrink:0;"></i>
    <div style="flex:1;">
      <div style="font-weight:700;color:#92400e;font-size:13px;">Perhatian — Jadwal Pemeliharaan Aset ATEM</div>
      <div style="font-size:11.5px;color:#78350f;margin-top:3px;display:flex;gap:18px;flex-wrap:wrap;">
        <?php if($jml_mnt_late>0): ?><span><i class="fa fa-circle-xmark" style="color:#ef4444;"></i> <strong><?= $jml_mnt_late ?></strong> aset maintenance <span style="color:#ef4444;font-weight:700;">sudah terlambat</span></span><?php endif; ?>
        <?php if($jml_mnt_due>0): ?><span><i class="fa fa-wrench" style="color:#f59e0b;"></i> <strong><?= $jml_mnt_due ?></strong> aset maintenance jatuh tempo &lt;30 hari</span><?php endif; ?>
        <?php if($jml_garansi>0): ?><span><i class="fa fa-shield-halved" style="color:#f59e0b;"></i> <strong><?= $jml_garansi ?></strong> garansi berakhir &lt;30 hari</span><?php endif; ?>
      </div>
    </div>
    <a href="<?= APP_URL ?>/pages/maintenance_atem.php" style="font-size:11.5px;font-weight:700;color:#c2410c;text-decoration:underline;white-space:nowrap;">Buka Maintenance ATEM</a>
  </div>
  <?php endif; ?>

  <!-- Stats Kondisi -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin-bottom:10px;">
    <div class="stat-card-aset">
      <div class="stat-icon-aset" style="background:#fff7ed;"><i class="fa fa-desktop" style="color:#f97316;"></i></div>
      <div><div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $total_all ?></div><div style="font-size:11px;color:#94a3b8;">Total Aset ATEM</div></div>
    </div>
    <?php foreach([
      ['Baik','#dcfce7','#22c55e','fa-circle-check'],
      ['Rusak','#fee2e2','#ef4444','fa-circle-xmark'],
      ['Dalam Perbaikan','#fef9c3','#f59e0b','fa-wrench'],
      ['Tidak Aktif','#f1f5f9','#94a3b8','fa-ban'],
    ] as [$k,$bg,$ic,$ico]): ?>
    <div class="stat-card-aset">
      <div class="stat-icon-aset" style="background:<?= $bg ?>;"><i class="fa <?= $ico ?>" style="color:<?= $ic ?>;"></i></div>
      <div><div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stats_kondisi[$k]??0 ?></div><div style="font-size:11px;color:#94a3b8;"><?= $k ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Stats Status Pakai -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin-bottom:16px;">
    <?php foreach([
      ['Terpakai','#dbeafe','#1d4ed8','fa-circle-dot','Aktif digunakan'],
      ['Tidak Terpakai','#d1fae5','#065f46','fa-circle','Belum/tidak digunakan'],
      ['Dipinjam','#fef3c7','#92400e','fa-hand-holding','Sedang dipinjam'],
    ] as [$k,$bg,$ic,$ico,$sub]): $isActive = ($fstatus===$k); ?>
    <a href="?status_pakai=<?= urlencode($isActive?'':$k) ?>&kondisi=<?= urlencode($fkondisi) ?>&q=<?= urlencode($search) ?>"
       class="stat-card-aset <?= $isActive?'active-filter':'' ?>" style="text-decoration:none;cursor:pointer;">
      <div class="stat-icon-aset" style="background:<?= $bg ?>;"><i class="fa <?= $ico ?>" style="color:<?= $ic ?>;"></i></div>
      <div>
        <div style="font-size:20px;font-weight:800;color:#1e293b;"><?= $stats_pakai[$k]??0 ?></div>
        <div style="font-size:11px;color:#94a3b8;"><?= $k ?></div>
        <div style="font-size:10px;color:#cbd5e1;"><?= $sub ?></div>
      </div>
      <?php if($isActive): ?><i class="fa fa-check-circle" style="margin-left:auto;color:#f97316;font-size:14px;"></i><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Legend -->
  <div class="sp-legend-bar">
    <span style="font-size:11px;font-weight:700;color:#374151;margin-right:4px;"><i class="fa fa-palette" style="color:#f97316;"></i> Keterangan warna:</span>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#fff;border:1.5px solid #e2e8f0;"></div> Normal = <strong>Terpakai</strong></div>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#d1fae5;border:1.5px solid #22c55e;"></div> Hijau + <s>coret</s> = <strong>Tidak Terpakai</strong></div>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#fef3c7;border:1.5px solid #f59e0b;"></div> Kuning = <strong>Dipinjam</strong></div>
    <div class="sp-legend-item"><div class="sp-legend-dot" style="background:#fee2e2;border:1.5px solid #ef4444;"></div> Merah kiri = <strong>Maintenance Terlambat</strong></div>
  </div>

  <!-- Quick filter kondisi -->
  <div style="display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;">
    <?php foreach([''=>'Semua','Baik'=>'Baik','Rusak'=>'Rusak','Dalam Perbaikan'=>'Dalam Perbaikan','Tidak Aktif'=>'Tidak Aktif'] as $v=>$l):
      $cnt=$v===''?$total_all:($stats_kondisi[$v]??0); ?>
    <a href="?kondisi=<?= urlencode($v) ?>&status_pakai=<?= urlencode($fstatus) ?>"
       class="btn <?= $fkondisi===$v?'btn-primary':'btn-default' ?>" style="font-size:12px;">
      <?= $l ?> <span style="background:<?= $fkondisi===$v?'rgba(255,255,255,.3)':'#ddd' ?>;border-radius:9px;padding:0 6px;font-size:10px;"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="tbl-tools">
      <div class="tbl-tools-l">
        <form method="GET" id="sf-atem" style="display:flex;gap:7px;flex-wrap:wrap;align-items:center;">
          <?php if($fkondisi): ?><input type="hidden" name="kondisi" value="<?= clean($fkondisi) ?>"><?php endif; ?>
          <input type="text" name="q" value="<?= clean($search) ?>" class="inp-search" placeholder="Cari nama, no. inv, lokasi…" onchange="document.getElementById('sf-atem').submit()">
          <select name="kategori" class="sel-filter" onchange="document.getElementById('sf-atem').submit()">
            <option value="">Semua Kategori</option>
            <?php foreach($kat_opts as $k): ?><option value="<?= clean($k) ?>" <?= $fk===$k?'selected':'' ?>><?= clean($k) ?></option><?php endforeach; ?>
          </select>
          <select name="status_pakai" class="sel-filter" onchange="document.getElementById('sf-atem').submit()" style="border-color:<?= $fstatus?'#f97316':'#d1d5db' ?>;">
            <option value="">Semua Status</option>
            <option value="Terpakai"       <?= $fstatus==='Terpakai'?'selected':'' ?>>🔵 Terpakai</option>
            <option value="Tidak Terpakai" <?= $fstatus==='Tidak Terpakai'?'selected':'' ?>>🟢 Tidak Terpakai</option>
            <option value="Dipinjam"       <?= $fstatus==='Dipinjam'?'selected':'' ?>>🟡 Dipinjam</option>
          </select>
          <?php if($search||$fk||$fstatus): ?><a href="?kondisi=<?= urlencode($fkondisi) ?>" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Reset</a><?php endif; ?>
        </form>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="tbl-info"><?= $total ?> aset</span>

        <!-- Dropdown Cetak -->
        <div class="cetak-drop-wrap" id="wrap-cetak">
          <button type="button" onclick="toggleCetakDrop(event)" class="btn btn-default btn-sm" style="border-color:#f97316;color:#c2410c;font-weight:600;">
            <i class="fa fa-print"></i> Cetak <i class="fa fa-chevron-down" style="font-size:9px;margin-left:3px;"></i>
          </button>
          <div class="cetak-drop-menu" id="cetak-drop">
            <div class="cdm-head"><div class="cdm-head-txt"><i class="fa fa-file-pdf" style="color:#ef4444;margin-right:5px;"></i> Laporan Aset ATEM</div></div>
            <a href="<?= APP_URL ?>/pages/cetak_aset_atem.php?mode=semua" target="_blank" class="cdm-item">
              <div class="cdm-icon" style="background:#fff7ed;"><i class="fa fa-desktop" style="color:#f97316;"></i></div>
              <div><div style="font-size:12.5px;font-weight:700;">Semua Aset ATEM</div><div style="font-size:10.5px;color:#94a3b8;">Laporan lengkap</div></div>
            </a>
            <a href="<?= APP_URL ?>/pages/cetak_aset_atem.php?mode=semua&status_pakai=Tidak+Terpakai" target="_blank" class="cdm-item">
              <div class="cdm-icon" style="background:#d1fae5;"><i class="fa fa-circle" style="color:#16a34a;"></i></div>
              <div><div style="font-size:12.5px;font-weight:700;">Tidak Terpakai</div><div style="font-size:10.5px;color:#94a3b8;">Aset yang belum/tidak digunakan</div></div>
            </a>
            <?php if(!empty($kat_opts)): ?>
            <div class="cdm-section">Per Kategori</div>
            <?php foreach($kat_opts as $k): ?>
            <a href="<?= APP_URL ?>/pages/cetak_aset_atem.php?mode=kategori&kategori=<?= urlencode($k) ?>" target="_blank" class="cdm-item-sm">
              <i class="fa fa-tag" style="color:#f97316;font-size:10px;"></i> <span style="font-weight:600;"><?= clean($k) ?></span>
            </a>
            <?php endforeach; endif; ?>
            <div class="cdm-section">Filter Kondisi</div>
            <?php foreach(['Baik'=>['fa-circle-check','#16a34a','#dcfce7'],'Rusak'=>['fa-circle-xmark','#dc2626','#fee2e2'],'Dalam Perbaikan'=>['fa-wrench','#d97706','#fef9c3'],'Tidak Aktif'=>['fa-ban','#64748b','#f1f5f9']] as $kond=>[$kico,$kcol,$kbg]): ?>
            <a href="<?= APP_URL ?>/pages/cetak_aset_atem.php?mode=semua&kondisi=<?= urlencode($kond) ?>" target="_blank" class="cdm-item-sm">
              <div style="width:22px;height:22px;border-radius:50%;background:<?= $kbg ?>;display:flex;align-items:center;justify-content:center;"><i class="fa <?= $kico ?>" style="color:<?= $kcol ?>;font-size:10px;"></i></div>
              <span>Kondisi: <strong><?= $kond ?></strong></span>
              <span style="margin-left:auto;font-size:10.5px;background:<?= $kbg ?>;color:<?= $kcol ?>;padding:1px 7px;border-radius:9px;font-weight:700;"><?= $stats_kondisi[$kond]??0 ?></span>
            </a>
            <?php endforeach; ?>
            <div class="cdm-foot"><div class="cdm-foot-txt"><i class="fa fa-circle-info" style="color:#f97316;"></i> Laporan terbuka di tab baru sebagai PDF</div></div>
          </div>
        </div>

        <?php if($bisa_catat): ?><button onclick="bukaModalTambah()" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Tambah Aset</button><?php endif; ?>
      </div>
    </div>

    <!-- Tabel -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:42px;text-align:center;">
              <input type="checkbox" id="cb-all" title="Pilih semua" onchange="toggleAllCb(this)" style="width:13px;height:13px;cursor:pointer;accent-color:#f97316;">
            </th>
            <th>No. Inventaris</th>
            <th>No. Aset RS</th>
            <th>Nama Aset</th>
            <th>Kategori</th>
            <th>Merek / Model</th>
            <th>Lokasi</th>
            <th>Penanggung Jawab</th>
            <th>Status Pakai</th>
            <th>Kondisi</th>
            <th>Maintenance Berikutnya</th>
            <th style="width:90px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($asets)): ?>
          <tr><td colspan="12" class="td-empty"><i class="fa fa-desktop"></i> Tidak ada data aset ATEM</td></tr>
          <?php else: foreach($asets as $a):
            $sp    = $a['status_pakai'] ?? 'Terpakai';
            $spc   = ['Terpakai'=>'sp-terpakai','Tidak Terpakai'=>'sp-tidak','Dipinjam'=>'sp-dipinjam'][$sp] ?? 'sp-terpakai';
            $spi   = ['Terpakai'=>'fa-circle-dot','Tidak Terpakai'=>'fa-circle','Dipinjam'=>'fa-hand-holding'][$sp] ?? 'fa-circle-dot';
            $kond  = $a['kondisi'] ?? 'Baik';
            $kc    = ['Baik'=>'kb-baik','Rusak'=>'kb-rusak','Dalam Perbaikan'=>'kb-perbaikan','Tidak Aktif'=>'kb-tidak'][$kond] ?? 'kb-tidak';
            $ki    = ['Baik'=>'fa-circle-check','Rusak'=>'fa-circle-xmark','Dalam Perbaikan'=>'fa-wrench','Tidak Aktif'=>'fa-ban'][$kond] ?? 'fa-circle';
            $next  = $latest[$a['id']]['tgl_maintenance_berikut'] ?? null;
            $last  = $latest[$a['id']]['tgl_maintenance'] ?? null;
            $mnt_late = $next && $next < $today;
            $mnt_soon = $next && !$mnt_late && $next <= $date_30;
            $row_class = '';
            if ($sp === 'Tidak Terpakai') $row_class = 'row-tidak-terpakai';
            elseif ($sp === 'Dipinjam')   $row_class = 'row-dipinjam';
            elseif ($mnt_late)            $row_class = 'row-mnt-late';
            $pj = $a['penanggung_jawab'] ?? '';
          ?>
          <tr class="<?= $row_class ?>">
            <td style="text-align:center;vertical-align:middle;padding:4px 3px;">
              <input type="checkbox" class="cb-aset" value="<?= (int)$a['id'] ?>" style="width:13px;height:13px;cursor:pointer;accent-color:#f97316;display:block;margin:0 auto 4px;">
              <a href="<?= APP_URL ?>/pages/cetak_label_aset_atem.php?id=<?= (int)$a['id'] ?>" target="_blank" title="Cetak Label Aset"
                 style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:linear-gradient(135deg,#fff7ed,#ffedd5);border:1px solid #fed7aa;border-radius:4px;color:#c2410c;font-size:10px;text-decoration:none;">
                <i class="fa fa-tag"></i>
              </a>
            </td>
            <td><span class="inv-badge"><?= clean($a['no_inventaris']) ?></span></td>
            <td>
              <?php if(!empty($a['no_aset_rs'])): ?><span style="font-family:monospace;font-size:11px;background:#f1f5f9;color:#475569;padding:2px 6px;border-radius:4px;"><?= clean($a['no_aset_rs']) ?></span>
              <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
            </td>
            <td>
              <div class="nama-aset-txt" style="font-weight:600;color:#1e293b;font-size:13px;"><?= clean($a['nama_aset']) ?></div>
              <?php if(!empty($a['keterangan'])): ?><small style="color:#aaa;font-size:10.5px;"><?= mb_strimwidth(clean($a['keterangan']),0,40,'…') ?></small><?php endif; ?>
              <?php if($sp==='Tidak Terpakai'): ?><div style="margin-top:2px;"><span style="font-size:9.5px;background:#d1fae5;color:#065f46;padding:1px 6px;border-radius:3px;font-weight:700;">TIDAK TERPAKAI</span></div><?php endif; ?>
              <?php if($sp==='Dipinjam'): ?><div style="margin-top:2px;"><span style="font-size:9.5px;background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:3px;font-weight:700;">DIPINJAM</span></div><?php endif; ?>
            </td>
            <td style="font-size:11.5px;color:#475569;"><?= clean($a['kategori'] ?: '—') ?></td>
            <td style="font-size:12px;">
              <span style="font-weight:600;"><?= clean($a['merek'] ?: '—') ?></span>
              <?php if(!empty($a['model_aset'])): ?><br><small style="color:#aaa;"><?= clean($a['model_aset']) ?></small><?php endif; ?>
            </td>
            <td style="font-size:12px;"><i class="fa fa-location-dot" style="color:#94a3b8;font-size:10px;"></i> <?= clean($a['lokasi'] ?: '—') ?></td>
            <td>
              <?php if($pj): ?>
              <div class="d-flex ai-c gap6"><div class="av av-xs"><?= getInitials($pj) ?></div><div style="font-size:12px;"><?= clean($pj) ?></div></div>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td><span class="kondisi-badge <?= $spc ?>"><i class="fa <?= $spi ?>"></i> <?= clean($sp) ?></span></td>
            <td><span class="kondisi-badge <?= $kc ?>"><i class="fa <?= $ki ?>"></i> <?= clean($kond) ?></span></td>
            <td style="font-size:11px;white-space:nowrap;">
              <?php if(!$next): ?><span class="kal-none">Belum ada</span>
              <?php elseif($mnt_late): ?><span class="kal-exp"><i class="fa fa-triangle-exclamation"></i> Terlambat</span><br><small style="color:#f87171;"><?= date('d M Y',strtotime($next)) ?></small>
              <?php elseif($mnt_soon): ?><span class="kal-soon"><i class="fa fa-clock"></i> Segera</span><br><small style="color:#fbbf24;"><?= date('d M Y',strtotime($next)) ?></small>
              <?php else: ?><span class="kal-ok"><?= date('d M Y',strtotime($next)) ?></span><?php endif; ?>
              <?php if($last): ?><div style="font-size:10px;color:#94a3b8;">Terakhir: <?= date('d M Y',strtotime($last)) ?></div><?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:4px;">
                <?php if($bisa_catat): ?><button onclick="editAset(<?= (int)$a['id'] ?>)" class="btn btn-warning btn-sm" title="Edit"><i class="fa fa-pen"></i></button><?php endif; ?>
                <?php if(hasRole('admin')): ?><button onclick="hapusAset(<?= (int)$a['id'] ?>,'<?= addslashes(clean($a['nama_aset'])) ?>')" class="btn btn-danger btn-sm" title="Hapus"><i class="fa fa-trash"></i></button><?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="tbl-footer">
      <span class="tbl-info">Menampilkan <?= $total ? $offset+1 : 0 ?>–<?= min($offset+$per_page,$total) ?> dari <?= $total ?></span>
      <?php if($pages>1): ?>
      <div class="pagination">
        <?php if($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="pag-btn"><i class="fa fa-chevron-left"></i></a><?php endif; ?>
        <?php for($i=1;$i<=$pages;$i++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="pag-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
        <?php if($page<$pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="pag-btn"><i class="fa fa-chevron-right"></i></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div><!-- /content -->


<!-- ═══════════ MODAL TAMBAH / EDIT ═══════════ -->
<div class="modal-ov" id="m-tambah-aset" style="align-items:flex-start;justify-content:center;padding-top:20px;">
  <div style="background:#fff;width:100%;max-width:800px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:15px 20px;background:linear-gradient(135deg,#7c2d12,#ea580c);">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:32px;height:32px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:7px;display:flex;align-items:center;justify-content:center;">
          <i id="modal-icon" class="fa fa-plus" style="color:#fff;font-size:13px;"></i>
        </div>
        <div>
          <div id="modal-title" style="color:#fff;font-size:14px;font-weight:700;">Tambah Aset ATEM Baru</div>
          <div style="color:rgba(255,255,255,.5);font-size:10.5px;">Isi data aset dengan lengkap dan benar</div>
        </div>
      </div>
      <button onclick="tutupModal()" style="width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.15);border:none;cursor:pointer;color:#fff;font-size:13px;display:flex;align-items:center;justify-content:center;"
        onmouseover="this.style.background='#ef4444';" onmouseout="this.style.background='rgba(255,255,255,.15)';"><i class="fa fa-times"></i></button>
    </div>

    <form method="POST" action="<?= APP_URL ?>/pages/aset_atem.php" id="form-aset">
      <input type="hidden" name="_action"  id="f-action"   value="tambah">
      <input type="hidden" name="id"       id="f-id"       value="">
      <input type="hidden" name="auto_inv" id="f-auto-inv" value="1">

      <div style="padding:18px 20px;max-height:74vh;overflow-y:auto;">

        <!-- No. Inventaris -->
        <div style="margin-bottom:14px;">
          <label class="f-label">No. Inventaris <span style="color:#ef4444;">*</span></label>
          <div style="display:flex;align-items:center;gap:9px;">
            <div style="flex:1;position:relative;">
              <input type="text" name="no_inventaris" id="f-no_inventaris" class="f-inp" placeholder="Akan digenerate otomatis…" style="font-family:'Courier New',monospace;padding-right:36px;" disabled>
              <i id="inv-icon" class="fa fa-lock" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;pointer-events:none;"></i>
            </div>
            <label class="inv-toggle-wrap">
              <label class="inv-sw" style="margin:0;">
                <input type="checkbox" id="toggle-manual-inv" onchange="toggleInv(this)" style="position:absolute;opacity:0;width:0;height:0;">
                <span class="inv-sl"></span>
              </label>
              <div>
                <div style="font-size:11.5px;font-weight:700;color:#374151;" id="inv-toggle-label">Auto</div>
                <div style="font-size:10px;color:#94a3b8;" id="inv-toggle-sub">Klik untuk manual</div>
              </div>
            </label>
          </div>
          <div id="inv-preview" style="margin-top:5px;font-size:10.5px;color:#64748b;">
            <i class="fa fa-circle-info" style="color:#f97316;"></i> Nomor: <span id="inv-preview-val" style="font-family:monospace;color:#c2410c;font-weight:700;">memuat…</span>
          </div>
        </div>

        <div class="modal-sec"><i class="fa fa-id-card"></i> Identitas Aset</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Nama Aset <span style="color:#ef4444;">*</span></label>
            <input type="text" name="nama_aset" id="f-nama_aset" required class="f-inp" placeholder="Contoh: ATEM Lobby Utama">
          </div>
          <div>
            <label class="f-label">No. Aset RS <small style="color:#94a3b8;font-weight:400;">(nomor registrasi internal RS)</small></label>
            <input type="text" name="no_aset_rs" id="f-no_aset_rs" class="f-inp" placeholder="Contoh: RS-ATM-001" style="font-family:'Courier New',monospace;">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Kategori</label>
            <input type="text" name="kategori" id="f-kategori" class="f-inp" placeholder="Pilih atau ketik kategori…" list="kat-dl-atem">
            <datalist id="kat-dl-atem"><?php foreach($kat_saran as $k): ?><option value="<?= clean($k) ?>"><?php endforeach; ?></datalist>
          </div>
          <div>
            <label class="f-label">Kondisi</label>
            <select name="kondisi" id="f-kondisi" class="f-inp">
              <option value="Baik">✅ Baik</option>
              <option value="Dalam Perbaikan">🔧 Dalam Perbaikan</option>
              <option value="Rusak">❌ Rusak</option>
              <option value="Tidak Aktif">⛔ Tidak Aktif</option>
            </select>
          </div>
        </div>

        <div style="margin-bottom:12px;">
          <label class="f-label"><i class="fa fa-circle-dot" style="color:#f97316;"></i> Status Pemakaian <span style="color:#ef4444;">*</span></label>
          <select name="status_pakai" id="f-status_pakai" class="f-inp val-terpakai" onchange="updateStatusPakaiStyle(this)">
            <option value="Terpakai">🔵 Terpakai — Aset sedang aktif digunakan</option>
            <option value="Tidak Terpakai">🟢 Tidak Terpakai — Aset belum/tidak digunakan</option>
            <option value="Dipinjam">🟡 Dipinjam — Aset sedang dipinjam</option>
          </select>
          <div id="sp-preview-bar" style="margin-top:6px;padding:7px 11px;border-radius:5px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;">
            <i id="sp-preview-icon" class="fa fa-circle-dot"></i><span id="sp-preview-txt">Baris tabel akan tampil normal</span>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
          <div><label class="f-label">Merek / Pabrikan</label><input type="text" name="merek" id="f-merek" class="f-inp" placeholder="Advantech, Elo, Zebra…"></div>
          <div><label class="f-label">Model / Tipe</label><input type="text" name="model_aset" id="f-model_aset" class="f-inp" placeholder="Model / tipe"></div>
          <div><label class="f-label">Serial Number</label><input type="text" name="serial_number" id="f-serial_number" class="f-inp" placeholder="Nomor seri" style="font-family:'Courier New',monospace;"></div>
        </div>

        <div class="grs-modal"></div>
        <div class="modal-sec"><i class="fa fa-location-dot"></i> Lokasi & Penanggung Jawab</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Lokasi Penempatan</label>
            <input type="text" name="lokasi" id="f-lokasi" class="f-inp" placeholder="Contoh: Lobby Utama, Poli Anak Lt.1" list="lok-dl">
            <datalist id="lok-dl"><?php foreach(array_unique(array_filter(array_column($aset_store,'lokasi'))) as $l): ?><option value="<?= clean($l) ?>"><?php endforeach; ?></datalist>
          </div>
          <div>
            <label class="f-label">Penanggung Jawab</label>
            <input type="text" name="penanggung_jawab" id="f-penanggung_jawab" class="f-inp" placeholder="Nama teknisi / PIC" list="pj-dl">
            <datalist id="pj-dl"><?php foreach($pj_saran as $p): ?><option value="<?= clean($p) ?>"><?php endforeach; ?></datalist>
          </div>
        </div>

        <div class="grs-modal"></div>
        <div class="modal-sec"><i class="fa fa-receipt"></i> Data Pengadaan</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
          <div><label class="f-label">Tanggal Pembelian</label><input type="date" name="tanggal_beli" id="f-tanggal_beli" class="f-inp"></div>
          <div><label class="f-label">Harga Perolehan (Rp)</label><input type="number" name="harga_beli" id="f-harga_beli" class="f-inp" placeholder="0" min="0"></div>
          <div><label class="f-label">Garansi Sampai</label><input type="date" name="garansi_sampai" id="f-garansi_sampai" class="f-inp"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label class="f-label">Sumber Dana</label>
            <input type="text" name="sumber_dana" id="f-sumber_dana" class="f-inp" placeholder="APBN, APBD, BLUD, Hibah…" list="sumber-dl">
            <datalist id="sumber-dl"><?php foreach($sumber_saran as $s): ?><option value="<?= $s ?>"><?php endforeach; ?></datalist>
          </div>
          <div>
            <label class="f-label">No. BAST <small style="color:#94a3b8;font-weight:400;">(Berita Acara Serah Terima)</small></label>
            <input type="text" name="no_bast" id="f-no_bast" class="f-inp" placeholder="Nomor BAST" style="font-family:'Courier New',monospace;">
          </div>
        </div>

        <div>
          <label class="f-label">Keterangan / Catatan Tambahan</label>
          <textarea name="keterangan" id="f-keterangan" rows="3" class="f-inp" placeholder="Catatan tambahan tentang kondisi, riwayat, atau informasi lain…" style="resize:vertical;"></textarea>
        </div>
      </div>

      <div style="padding:12px 20px;border-top:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
        <span style="font-size:11px;color:#94a3b8;"><i class="fa fa-asterisk" style="color:#ef4444;font-size:8px;"></i> Wajib diisi</span>
        <div style="display:flex;gap:8px;">
          <button type="button" onclick="tutupModal()" style="padding:7px 16px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
          <button type="submit" style="padding:7px 18px;background:linear-gradient(135deg,#f97316,#ea580c);border:none;border-radius:5px;font-size:12px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;"><i class="fa fa-save"></i> <span id="btn-submit-label">Simpan Aset</span></button>
        </div>
      </div>
    </form>
  </div>
</div>


<!-- ═══════════ MODAL HAPUS ═══════════ -->
<div class="modal-ov" id="m-hapus-aset" style="align-items:center;justify-content:center;">
  <div style="background:#fff;width:100%;max-width:380px;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;animation:mIn .2s ease;">
    <div style="padding:20px 22px 14px;text-align:center;">
      <div style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;"><i class="fa fa-trash" style="color:#ef4444;font-size:22px;"></i></div>
      <div style="font-size:15px;font-weight:700;color:#1e293b;">Hapus Aset?</div>
      <div style="font-size:12.5px;color:#64748b;margin-top:6px;">Aset <strong id="hapus-nama"></strong> akan dihapus permanen.</div>
    </div>
    <form method="POST" action="<?= APP_URL ?>/pages/aset_atem.php">
      <input type="hidden" name="_action" value="hapus">
      <input type="hidden" name="id" id="hapus-id">
      <div style="padding:12px 20px;display:flex;gap:8px;justify-content:center;border-top:1px solid #f0f0f0;">
        <button type="button" onclick="closeModal('m-hapus-aset')" style="padding:8px 20px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;font-size:12.5px;cursor:pointer;color:#64748b;font-family:inherit;">Batal</button>
        <button type="submit" style="padding:8px 20px;background:#ef4444;border:none;border-radius:5px;font-size:12.5px;cursor:pointer;color:#fff;font-family:inherit;font-weight:700;"><i class="fa fa-trash"></i> Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>


<!-- ═══════════ JAVASCRIPT ═══════════ -->
<script>
const APP_URL  = '<?= APP_URL ?>';
const PAGE_URL = APP_URL + '/pages/aset_atem.php';

/* Dropdown Cetak */
function toggleCetakDrop(e) { e.stopPropagation(); document.getElementById('cetak-drop').classList.toggle('open'); }
document.addEventListener('click', function(e) {
    const w = document.getElementById('wrap-cetak');
    if (w && !w.contains(e.target)) document.getElementById('cetak-drop').classList.remove('open');
});

/* Status Pakai preview */
function updateStatusPakaiStyle(sel) {
    const val = sel.value, bar = document.getElementById('sp-preview-bar');
    const ico = document.getElementById('sp-preview-icon'), txt = document.getElementById('sp-preview-txt');
    const base = 'margin-top:6px;padding:7px 11px;border-radius:5px;font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;';
    sel.className = 'f-inp';
    if (val === 'Tidak Terpakai') {
        sel.classList.add('val-tidak');
        bar.style.cssText = base + 'background:#d1fae5;color:#065f46;border:1px solid #86efac;';
        ico.className = 'fa fa-circle'; txt.textContent = 'Baris tabel akan berwarna HIJAU + nama aset dicoret';
    } else if (val === 'Dipinjam') {
        sel.classList.add('val-dipinjam');
        bar.style.cssText = base + 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;';
        ico.className = 'fa fa-hand-holding'; txt.textContent = 'Baris tabel akan berwarna KUNING';
    } else {
        sel.classList.add('val-terpakai');
        bar.style.cssText = base + 'background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;';
        ico.className = 'fa fa-circle-dot'; txt.textContent = 'Baris tabel akan tampil normal';
    }
}

/* Toggle Auto/Manual No. Inventaris */
function toggleInv(chk) {
    const inp = document.getElementById('f-no_inventaris'), icon = document.getElementById('inv-icon');
    const lbl = document.getElementById('inv-toggle-label'), sub = document.getElementById('inv-toggle-sub');
    const prev = document.getElementById('inv-preview');
    document.getElementById('f-auto-inv').value = chk.checked ? '0' : '1';
    if (chk.checked) {
        inp.disabled = false; inp.placeholder = 'Ketik nomor inventaris…'; inp.value = ''; inp.focus();
        icon.className = 'fa fa-pen'; icon.style.color = '#f97316';
        lbl.textContent = 'Manual'; sub.textContent = 'Klik untuk auto'; prev.style.display = 'none';
    } else {
        inp.disabled = true; inp.placeholder = 'Akan digenerate otomatis…'; inp.value = '';
        icon.className = 'fa fa-lock'; icon.style.color = '#94a3b8';
        lbl.textContent = 'Auto'; sub.textContent = 'Klik untuk manual'; prev.style.display = '';
        previewNoInv();
    }
}
function previewNoInv() {
    fetch(PAGE_URL + '?preview_no_inv=1').then(r => r.json())
        .then(d => { document.getElementById('inv-preview-val').textContent = d.no; });
}

/* Tambah / Edit / Hapus */
function bukaModalTambah() { resetFormAset(); openModal('m-tambah-aset'); previewNoInv(); }

function editAset(id) {
    fetch(PAGE_URL + '?get_aset=' + id).then(r => r.json()).then(d => {
        if (!d) return;
        resetFormAset();
        document.getElementById('modal-title').textContent      = 'Edit Aset ATEM';
        document.getElementById('modal-icon').className         = 'fa fa-pen';
        document.getElementById('btn-submit-label').textContent = 'Perbarui Aset';
        document.getElementById('f-action').value = 'edit';
        document.getElementById('f-id').value     = d.id;

        const chk = document.getElementById('toggle-manual-inv');
        chk.checked = true; toggleInv(chk);
        document.getElementById('f-no_inventaris').value = d.no_inventaris || '';

        ['nama_aset','no_aset_rs','kategori','kondisi','merek','model_aset','serial_number','lokasi','penanggung_jawab',
         'tanggal_beli','harga_beli','garansi_sampai','sumber_dana','no_bast','keterangan'].forEach(f => {
            const el = document.getElementById('f-' + f);
            if (el) el.value = d[f] || '';
        });
        if (!d.kondisi) document.getElementById('f-kondisi').value = 'Baik';
        const sp = document.getElementById('f-status_pakai');
        sp.value = d.status_pakai || 'Terpakai'; updateStatusPakaiStyle(sp);
        openModal('m-tambah-aset');
    });
}

function hapusAset(id, nama) {
    document.getElementById('hapus-id').value = id;
    document.getElementById('hapus-nama').textContent = nama;
    openModal('m-hapus-aset');
}

function tutupModal() { closeModal('m-tambah-aset'); resetFormAset(); }
function resetFormAset() {
    document.getElementById('form-aset').reset();
    document.getElementById('f-action').value               = 'tambah';
    document.getElementById('f-id').value                   = '';
    document.getElementById('modal-title').textContent      = 'Tambah Aset ATEM Baru';
    document.getElementById('modal-icon').className         = 'fa fa-plus';
    document.getElementById('btn-submit-label').textContent = 'Simpan Aset';
    const chk = document.getElementById('toggle-manual-inv');
    chk.checked = false; toggleInv(chk);
    const sp = document.getElementById('f-status_pakai');
    sp.value = 'Terpakai'; updateStatusPakaiStyle(sp);
}

/* Batch cetak label */
function toggleAllCb(master) {
    document.querySelectorAll('.cb-aset').forEach(cb => cb.checked = master.checked);
    updateBatchBar();
}
function updateBatchBar() {
    const n = document.querySelectorAll('.cb-aset:checked').length;
    const bar = document.getElementById('batch-label-bar');
    if (!bar) return;
    bar.style.display = n > 0 ? 'flex' : 'none';
    document.getElementById('batch-label-count').textContent = n + ' aset dipilih';
}
function resetBatchCb() {
    document.querySelectorAll('.cb-aset').forEach(cb => cb.checked = false);
    const m = document.getElementById('cb-all');
    if (m) { m.checked = false; m.indeterminate = false; }
    updateBatchBar();
}
function cetakBatchLabel() {
    const ids = [...document.querySelectorAll('.cb-aset:checked')].map(cb => cb.value);
    if (!ids.length) { alert('Pilih minimal 1 aset terlebih dahulu.'); return; }
    window.open(APP_URL + '/pages/cetak_label_aset_atem.php?ids=' + ids.join(','), '_blank');
}
document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('cb-aset')) return;
    const all = document.querySelectorAll('.cb-aset').length;
    const chk = document.querySelectorAll('.cb-aset:checked').length;
    const m = document.getElementById('cb-all');
    if (m) { m.checked = chk > 0 && chk === all; m.indeterminate = chk > 0 && chk < all; }
    updateBatchBar();
});

document.getElementById('m-tambah-aset').addEventListener('click', function(e) { if (e.target === this) tutupModal(); });
resetFormAset();
</script>

<!-- Floating batch cetak label -->
<div id="batch-label-bar"
     style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#7c2d12,#ea580c);color:#fff;padding:10px 18px;border-radius:30px;box-shadow:0 6px 24px rgba(0,0,0,.25);z-index:9999;align-items:center;gap:14px;font-size:13px;font-weight:700;white-space:nowrap;">
  <i class="fa fa-tags"></i>
  <span id="batch-label-count">0 aset dipilih</span>
  <button onclick="cetakBatchLabel()" style="padding:5px 16px;background:#fff;color:#c2410c;border:none;border-radius:20px;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;"><i class="fa fa-print"></i> Cetak Label</button>
  <button onclick="resetBatchCb()" title="Batal pilihan" style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:11px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fa fa-times"></i></button>
</div>

<?php include '../includes/footer.php'; ?>