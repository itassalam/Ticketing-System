<?php
session_start();
require_once '../config.php';
requireLogin();
if (!hasExactRole(['admin', 'teknisi_atem'])) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title = 'Kategori ATEM';
$active_menu = 'kategori_atem';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'tambah' || $action === 'edit') {
        $name = trim($_POST['nama'] ?? '');
        $description = trim($_POST['deskripsi'] ?? '');
        $icon = $_POST['icon'] ?? 'fa-heart-pulse';
        $sla = (int)($_POST['sla_jam'] ?? 24);
        $response_sla = (int)($_POST['sla_respon_jam'] ?? 4);

        if ($name === '' || $sla < 1 || $response_sla < 1) {
            setFlash('danger', 'Nama kategori dan target SLA minimal 1 jam wajib diisi.');
        } else {
            try {
                if ($action === 'edit' && $id) {
                    $stmt = $pdo->prepare('UPDATE kategori_atem SET nama=?, deskripsi=?, icon=?, sla_jam=?, sla_respon_jam=? WHERE id=?');
                    $stmt->execute([$name, $description ?: null, $icon, $sla, $response_sla, $id]);
                    setFlash('success', 'Kategori ATEM berhasil diperbarui.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO kategori_atem (nama, deskripsi, icon, sla_jam, sla_respon_jam) VALUES (?,?,?,?,?)');
                    $stmt->execute([$name, $description ?: null, $icon, $sla, $response_sla]);
                    setFlash('success', 'Kategori ATEM berhasil ditambahkan.');
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    setFlash('danger', 'Nama kategori tersebut sudah digunakan.');
                } else {
                    throw $e;
                }
            }
        }
        redirect(APP_URL . '/pages/kategori_atem.php');
    } elseif ($action === 'toggle' && $id) {
        $pdo->prepare('UPDATE kategori_atem SET aktif=IF(aktif=1,0,1) WHERE id=?')->execute([$id]);
        setFlash('success', 'Status kategori ATEM berhasil diperbarui.');
        redirect(APP_URL . '/pages/kategori_atem.php');
    } elseif ($action === 'hapus' && $id) {
        $pdo->beginTransaction();
        try {
            $categoryStmt = $pdo->prepare('SELECT id FROM kategori_atem WHERE id=? FOR UPDATE');
            $categoryStmt->execute([$id]);
            if (!$categoryStmt->fetchColumn()) {
                $pdo->commit();
                setFlash('warning', 'Kategori tidak ditemukan.');
            } else {
                $ticketStmt = $pdo->prepare('SELECT COUNT(*) FROM tiket_atem WHERE kategori_id=?');
                $ticketStmt->execute([$id]);
                if ((int)$ticketStmt->fetchColumn() > 0) {
                    $pdo->commit();
                    setFlash('warning', 'Kategori tidak dapat dihapus karena masih digunakan tiket.');
                } else {
                    $pdo->prepare('DELETE FROM kategori_atem WHERE id=?')->execute([$id]);
                    $pdo->commit();
                    setFlash('success', 'Kategori ATEM berhasil dihapus.');
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        redirect(APP_URL . '/pages/kategori_atem.php');
    }
}

$list = $pdo->query("
    SELECT k.*, COUNT(t.id) AS jumlah_tiket
    FROM kategori_atem k
    LEFT JOIN tiket_atem t ON t.kategori_id=k.id
    GROUP BY k.id
    ORDER BY k.nama
")->fetchAll();

$total_kat = count($list);
$total_tiket = array_sum(array_column($list, 'jumlah_tiket'));
$avg_sla = $total_kat > 0 ? round(array_sum(array_column($list, 'sla_jam')) / $total_kat) : 0;
$avg_respon = $total_kat > 0 ? round(array_sum(array_column($list, 'sla_respon_jam')) / $total_kat) : 0;
$icons = [
    'fa-desktop', 'fa-ticket', 'fa-print', 'fa-hand-pointer', 'fa-display',
    'fa-qrcode', 'fa-barcode', 'fa-credit-card', 'fa-microchip', 'fa-keyboard',
    'fa-network-wired', 'fa-wifi', 'fa-plug', 'fa-battery-full', 'fa-gear',
    'fa-screwdriver-wrench', 'fa-lock', 'fa-heart-pulse', 'fa-tag',
    'fa-kit-medical', 'fa-lungs', 'fa-flask-vial', 'fa-radiation',
    'fa-syringe', 'fa-stethoscope', 'fa-tooth', 'fa-eye', 'fa-baby',
    'fa-bandage',
];
$icon_labels = [
    'fa-desktop' => 'Komputer', 'fa-ticket' => 'Anjungan',
    'fa-print' => 'Printer', 'fa-hand-pointer' => 'Layar Sentuh',
    'fa-display' => 'Monitor', 'fa-qrcode' => 'QR Code',
    'fa-barcode' => 'Barcode', 'fa-credit-card' => 'Pembayaran',
    'fa-microchip' => 'Elektronik', 'fa-keyboard' => 'Keyboard',
    'fa-network-wired' => 'Jaringan', 'fa-wifi' => 'Wi-Fi',
    'fa-plug' => 'Kabel / Konektor', 'fa-battery-full' => 'Baterai',
    'fa-gear' => 'Mekanisme', 'fa-screwdriver-wrench' => 'Perbaikan',
    'fa-lock' => 'Keamanan', 'fa-heart-pulse' => 'Peralatan Medis',
    'fa-tag' => 'Lainnya', 'fa-kit-medical' => 'Medis Umum',
    'fa-lungs' => 'Paru / Ventilator', 'fa-flask-vial' => 'Laboratorium',
    'fa-radiation' => 'Radiologi', 'fa-syringe' => 'Infus / Suntik',
    'fa-stethoscope' => 'Diagnostik', 'fa-tooth' => 'Peralatan Gigi',
    'fa-eye' => 'Peralatan Mata', 'fa-baby' => 'Inkubator / Bayi',
    'fa-bandage' => 'Bedah',
];
$icon_palettes = [
    ['#fef3c7','#d97706'], ['#dbeafe','#1d4ed8'], ['#d1fae5','#065f46'],
    ['#ede9fe','#5b21b6'], ['#fce7f3','#9d174d'], ['#f0fdf4','#15803d'],
    ['#fff7ed','#c2410c'], ['#f0f9ff','#0369a1'],
];
include '../includes/header.php';
?>

<style>
.atem-kat-modal {
    visibility:hidden !important; opacity:0 !important;
    position:fixed !important; top:0 !important; left:0 !important;
    width:100% !important; height:100% !important;
    background:rgba(0,0,0,.5) !important; z-index:99999 !important;
    display:flex !important; align-items:center !important; justify-content:center !important;
    transition:opacity .22s ease,visibility .22s ease !important; pointer-events:none !important;
}
.atem-kat-modal.open { visibility:visible !important; opacity:1 !important; pointer-events:auto !important; }
.atem-kat-box {
    background:#fff !important; border-radius:12px !important; width:100% !important;
    max-width:540px !important; margin:16px !important;
    box-shadow:0 12px 48px rgba(0,0,0,.22) !important; overflow:hidden !important;
    transform:translateY(22px) scale(.98); transition:transform .22s ease !important;
    max-height:90vh !important; display:flex !important; flex-direction:column !important;
}
.atem-kat-modal.open .atem-kat-box { transform:translateY(0) scale(1) !important; }
.atem-kat-hd {
    display:flex !important; align-items:center !important; justify-content:space-between !important;
    padding:16px 22px !important; border-bottom:1px solid #f0f0f0 !important;
    background:#fafafa !important; flex-shrink:0 !important;
}
.atem-kat-hd h5 { margin:0 !important; font-size:15px !important; font-weight:700 !important; color:#1e293b !important; display:flex !important; align-items:center !important; gap:8px !important; }
.atem-kat-close { border:0 !important; background:none !important; color:#94a3b8 !important; font-size:22px !important; cursor:pointer !important; width:32px !important; height:32px !important; border-radius:6px !important; }
.atem-kat-close:hover { background:#fee2e2 !important; color:#dc2626 !important; }
.atem-kat-body { padding:22px !important; overflow-y:auto !important; flex:1 !important; }
.atem-kat-ft { display:flex !important; align-items:center !important; justify-content:flex-end !important; padding:14px 22px !important; border-top:1px solid #f0f0f0 !important; gap:8px !important; background:#fafafa !important; flex-shrink:0 !important; }
.atem-icon-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:5px; margin-top:8px; }
.atem-icon-option { border:2px solid #e5e7eb; border-radius:7px; padding:7px 4px; text-align:center; cursor:pointer; transition:border-color .15s,background .15s,transform .12s; background:#f8fafc; }
.atem-icon-option:hover { border-color:var(--primary,#2563eb); background:#eff6ff; transform:scale(1.08); }
.atem-icon-option.selected { border-color:var(--primary,#2563eb) !important; background:#dbeafe !important; }
.atem-icon-option i { color:var(--primary,#2563eb); font-size:14px; }
.atem-icon-option .icon-lbl { font-size:8px; color:#94a3b8; margin-top:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.atem-sla-hint { display:inline-flex; align-items:center; gap:5px; margin-top:4px; padding:2px 7px; border-radius:4px; background:#f1f5f9; color:#64748b; font-size:10px; font-weight:700; }
.sla-bar { display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; color:#475569; }
.sla-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
@media(max-width:600px) { .atem-icon-grid { grid-template-columns:repeat(5,1fr); } }
</style>

<div class="page-header">
  <h4><i class="fa fa-tags" style="color:var(--primary);"></i> &nbsp;Kategori ATEM</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Kategori ATEM</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>
  <?php include '../includes/category_navigation.php'; ?>

  <div class="category-stats">
    <?php foreach ([
      [$total_kat, 'Total Kategori', 'fa-tags', '#f0fdf4', '#00c896'],
      [$total_tiket, 'Total Tiket', 'fa-ticket', '#dbeafe', '#1d4ed8'],
      [$avg_sla, 'Rata-rata SLA', 'fa-clock', '#fef3c7', '#d97706'],
      [$avg_respon, 'Rata-rata Respon', 'fa-bolt', '#ede9fe', '#7c3aed'],
    ] as [$value, $label, $icon, $background, $color]): ?>
    <div class="category-stat-card">
      <div class="category-stat-icon" style="background:<?= $background ?>;">
        <i class="fa <?= $icon ?>" style="color:<?= $color ?>;font-size:15px;"></i>
      </div>
      <div>
        <div class="category-stat-value"><?= $value ?><?= in_array($label, ['Rata-rata SLA', 'Rata-rata Respon'], true) ? '<span style="font-size:12px;font-weight:600;color:#9ca3af;">j</span>' : '' ?></div>
        <div class="category-stat-label"><?= $label ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
      <h5><i class="fa fa-list text-primary"></i> &nbsp;Daftar Kategori ATEM <span style="color:#aaa;font-weight:400;">(<?= $total_kat ?>)</span></h5>
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
        <button type="button" class="btn btn-primary btn-sm" onclick="atemCategoryOpen()"><i class="fa fa-plus"></i> Tambah</button>
      </div>
    </div>
    <form class="category-table-tools" data-category-filter>
      <div class="category-table-search">
        <i class="fa fa-search"></i>
        <input type="search" class="form-control" data-category-search placeholder="Cari nama atau deskripsi kategori..." aria-label="Cari kategori">
      </div>
      <select class="form-control" data-category-status aria-label="Filter status">
        <option value="">Semua Status</option>
        <option value="aktif">Aktif</option>
        <option value="nonaktif">Nonaktif</option>
      </select>
      <select class="form-control category-table-page-size" data-category-page-size aria-label="Jumlah data per halaman">
        <option value="10" selected>10 data</option>
        <option value="25">25 data</option>
        <option value="50">50 data</option>
        <option value="all">Semua</option>
      </select>
      <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-filter"></i> Terapkan</button>
      <button type="button" class="btn btn-default btn-sm" data-category-reset hidden><i class="fa fa-rotate-left"></i> Reset</button>
    </form>
    <div class="panel-bd np tbl-wrap">
      <table class="category-data-table" data-category-table>
        <thead><tr>
          <th style="width:50px;text-align:center;">Ikon</th>
          <th>Nama Kategori</th>
          <th style="width:100px;text-align:center;">Jenis</th>
          <th style="width:105px;text-align:center;">Status</th>
          <th style="width:130px;">Target SLA</th>
          <th style="width:130px;">Target Respon</th>
          <th style="width:80px;text-align:center;">Tiket</th>
          <th style="width:115px;text-align:center;">Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (!$list): ?>
          <tr data-empty-category><td colspan="8" style="text-align:center;color:#aaa;padding:40px 20px;">
            <div style="width:52px;height:52px;background:#f1f5f9;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;"><i class="fa fa-tags" style="font-size:22px;color:#cbd5e1;"></i></div>
            <div style="font-weight:600;color:#64748b;margin-bottom:4px;">Belum ada kategori ATEM</div>
            <small style="color:#94a3b8;">Klik <strong>+ Tambah Kategori</strong> untuk memulai.</small>
          </td></tr>
        <?php else: foreach ($list as $index => $category):
            [$icon_bg, $icon_color] = $icon_palettes[$index % count($icon_palettes)];
            $has_tickets = (int)$category['jumlah_tiket'] > 0;
            $sla_color = $category['sla_jam'] <= 24 ? '#22c55e' : ($category['sla_jam'] <= 48 ? '#f59e0b' : '#ef4444');
            $response_color = $category['sla_respon_jam'] <= 4 ? '#22c55e' : ($category['sla_respon_jam'] <= 8 ? '#f59e0b' : '#ef4444');
        ?>
          <tr data-category-row data-status="<?= $category['aktif'] ? 'aktif' : 'nonaktif' ?>">
            <td style="text-align:center;"><div style="width:36px;height:36px;background:<?= $icon_bg ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;margin:0 auto;"><i class="fa <?= clean($category['icon']) ?>" style="color:<?= $icon_color ?>;font-size:15px;"></i></div></td>
            <td>
              <div style="font-weight:600;font-size:13px;color:#111827;"><?= clean($category['nama']) ?></div>
              <?php if (!empty($category['deskripsi'])): ?><div style="font-size:11px;color:#9ca3af;margin-top:2px;"><?= clean($category['deskripsi']) ?></div><?php endif; ?>
            </td>
            <td style="text-align:center;">
              <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:10.5px;font-weight:700;background:#fce7f3;color:#9d174d;">
                <i class="fa fa-kit-medical" style="font-size:9px;"></i> Medis
              </span>
            </td>
            <td style="text-align:center;">
              <form method="POST" style="display:inline;" onsubmit="return confirm('<?= $category['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> kategori ini?')">
                <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                <button type="submit" style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border:0;border-radius:20px;font-size:10.5px;font-weight:700;background:<?= $category['aktif'] ? '#dcfce7' : '#fee2e2' ?>;color:<?= $category['aktif'] ? '#15803d' : '#b91c1c' ?>;cursor:pointer;" title="Klik untuk <?= $category['aktif'] ? 'menonaktifkan' : 'mengaktifkan' ?>">
                  <i class="fa <?= $category['aktif'] ? 'fa-circle-check' : 'fa-ban' ?>" style="font-size:9px;"></i> <?= $category['aktif'] ? 'Aktif' : 'Nonaktif' ?>
                </button>
              </form>
            </td>
            <td><div class="sla-bar"><span class="sla-dot" style="background:<?= $sla_color ?>;"></span><strong style="color:#1e293b;"><?= (int)$category['sla_jam'] ?></strong><span style="color:#94a3b8;">jam</span></div></td>
            <td><div class="sla-bar"><span class="sla-dot" style="background:<?= $response_color ?>;"></span><strong style="color:#1e293b;"><?= (int)$category['sla_respon_jam'] ?></strong><span style="color:#94a3b8;">jam</span></div></td>
            <td style="text-align:center;"><?php if ($has_tickets): ?><span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;color:#00c896;"><i class="fa fa-ticket" style="font-size:10px;"></i> <?= (int)$category['jumlah_tiket'] ?></span><?php else: ?><span style="color:#e2e8f0;font-size:12px;">0</span><?php endif; ?></td>
            <td style="text-align:center;white-space:nowrap;">
              <button type="button" class="btn btn-warning btn-sm" onclick='atemCategoryOpen(<?= json_encode($category, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)' title="Edit"><i class="fa fa-edit"></i></button>
              <?php if (!$has_tickets): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus kategori ini?')">
                <input type="hidden" name="action" value="hapus"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" title="Hapus"><i class="fa fa-trash"></i></button>
              </form>
              <?php else: ?>
              <button type="button" class="btn btn-sm btn-default" disabled title="Tidak dapat dihapus — masih ada <?= (int)$category['jumlah_tiket'] ?> tiket">
                <i class="fa fa-lock" style="color:#d1d5db;"></i>
              </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="category-table-foot">
      <div data-category-summary></div>
      <div class="category-table-pager" data-category-pager aria-label="Navigasi halaman"></div>
    </div>
  </div>

  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 18px;margin-top:10px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
    <span style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px;"><i class="fa fa-info-circle" style="color:#64748b;"></i> Keterangan SLA:</span>
    <?php foreach ([['#22c55e','≤ 24 jam','Cepat'], ['#f59e0b','25–48 jam','Normal'], ['#ef4444','> 48 jam','Lambat']] as [$color, $range, $label]): ?>
    <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#475569;">
      <span style="width:8px;height:8px;border-radius:50%;background:<?= $color ?>;"></span><strong><?= $range ?></strong> — <?= $label ?>
    </span>
    <?php endforeach; ?>
  </div>
</div>

<div id="atem-kat-modal" class="atem-kat-modal">
  <div class="atem-kat-box">
    <div class="atem-kat-hd">
      <h5><i class="fa fa-tags" id="atem-kat-modal-icon" style="color:var(--primary);"></i><span id="atem-kat-modal-title">Tambah Kategori ATEM</span></h5>
      <button type="button" class="atem-kat-close" onclick="atemCategoryClose()" aria-label="Tutup">&times;</button>
    </div>
    <div class="atem-kat-body">
      <form method="POST" id="atem-kat-form">
        <input type="hidden" name="action" id="atem-kat-action" value="tambah">
        <input type="hidden" name="id" id="atem-kat-id" value="">
        <input type="hidden" name="icon" id="atem-kat-icon" value="fa-heart-pulse">
        <div class="form-group"><label for="atem-kat-name">Nama Kategori <span class="req">*</span></label><input type="text" name="nama" id="atem-kat-name" class="form-control" maxlength="100" required placeholder="Contoh: Anjungan, Printer Tiket"></div>
        <div class="form-group"><label for="atem-kat-description">Deskripsi <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label><textarea name="deskripsi" id="atem-kat-description" class="form-control" rows="2" placeholder="Keterangan singkat kategori ini..."></textarea></div>
        <div class="form-row">
          <div class="form-group"><label for="atem-kat-sla">Target SLA (jam)</label><input type="number" name="sla_jam" id="atem-kat-sla" class="form-control" min="1" value="24" required><div class="atem-sla-hint"><i class="fa fa-clock"></i> Waktu penyelesaian tiket</div></div>
          <div class="form-group"><label for="atem-kat-response">Target Respon (jam)</label><input type="number" name="sla_respon_jam" id="atem-kat-response" class="form-control" min="1" value="4" required><div class="atem-sla-hint"><i class="fa fa-bolt"></i> Waktu respons pertama</div></div>
        </div>
        <div class="form-group">
          <label>Pilih Ikon</label>
          <div class="atem-icon-grid">
            <?php foreach ($icons as $icon): ?><button type="button" class="atem-icon-option <?= $icon === 'fa-heart-pulse' ? 'selected' : '' ?>" data-icon="<?= $icon ?>" onclick="atemCategoryPickIcon(this)" title="<?= clean($icon_labels[$icon]) ?>"><i class="fa <?= $icon ?>"></i><div class="icon-lbl"><?= clean($icon_labels[$icon]) ?></div></button><?php endforeach; ?>
          </div>
          <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
            <div style="width:36px;height:36px;background:#f0f4ff;border-radius:8px;display:flex;align-items:center;justify-content:center;border:1px solid #e0e7ff;">
              <i class="fa fa-heart-pulse" id="atem-kat-icon-preview" style="color:var(--primary,#2563eb);font-size:16px;"></i>
            </div>
            <span style="font-size:11px;color:#64748b;">Ikon terpilih: <strong id="atem-kat-icon-label">fa-heart-pulse</strong></span>
          </div>
        </div>
      </form>
    </div>
    <div class="atem-kat-ft">
      <button type="button" class="btn btn-default" onclick="atemCategoryClose()"><i class="fa fa-times"></i> Batal</button>
      <button type="submit" form="atem-kat-form" class="btn btn-primary" id="atem-kat-submit"><i class="fa fa-save"></i> Simpan</button>
    </div>
  </div>
</div>

<script>
var atemCategoryModal = document.getElementById('atem-kat-modal');

function atemCategoryOpen(category) {
    var editing = Boolean(category);
    document.getElementById('atem-kat-modal-title').textContent = editing ? 'Edit: ' + category.nama : 'Tambah Kategori ATEM';
    document.getElementById('atem-kat-modal-icon').style.color = editing ? '#f59e0b' : 'var(--primary)';
    document.getElementById('atem-kat-action').value = editing ? 'edit' : 'tambah';
    document.getElementById('atem-kat-id').value = editing ? category.id : '';
    document.getElementById('atem-kat-name').value = editing ? category.nama : '';
    document.getElementById('atem-kat-description').value = editing ? (category.deskripsi || '') : '';
    document.getElementById('atem-kat-sla').value = editing ? category.sla_jam : 24;
    document.getElementById('atem-kat-response').value = editing ? category.sla_respon_jam : 4;
    document.getElementById('atem-kat-submit').innerHTML = editing ? '<i class="fa fa-save"></i> Update' : '<i class="fa fa-save"></i> Simpan';
    atemCategorySelectIcon(editing ? category.icon : 'fa-heart-pulse');
    atemCategoryModal.classList.add('open');
    setTimeout(function () { document.getElementById('atem-kat-name').focus(); }, 80);
}

function atemCategoryClose() {
    atemCategoryModal.classList.remove('open');
}

function atemCategoryPickIcon(button) {
    atemCategorySelectIcon(button.dataset.icon);
}

function atemCategorySelectIcon(icon) {
    document.getElementById('atem-kat-icon').value = icon;
    document.getElementById('atem-kat-icon-preview').className = 'fa ' + icon;
    document.getElementById('atem-kat-icon-label').textContent = icon;
    document.querySelectorAll('.atem-icon-option').forEach(function (button) {
        button.classList.toggle('selected', button.dataset.icon === icon);
    });
}

atemCategoryModal.addEventListener('click', function (event) {
    if (event.target === atemCategoryModal) atemCategoryClose();
});
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') atemCategoryClose();
});
</script>

<script src="<?= APP_URL ?>/assets/js/category-table.js"></script>
<?php include '../includes/footer.php'; ?>
