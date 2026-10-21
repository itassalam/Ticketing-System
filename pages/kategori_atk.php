<?php
session_start();
require_once '../config.php';
requireLogin();

$can_manage = hasRole(['admin', 'teknisi_atem', 'keuangan']);
if (!$can_manage) {
    setFlash('danger', 'Akses ditolak.');
    redirect(APP_URL . '/dashboard.php');
}

$page_title = 'Kategori Barang ATK';
$active_menu = 'kategori_atk';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS atk_barang (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kode VARCHAR(40) NOT NULL UNIQUE,
        nama VARCHAR(150) NOT NULL,
        satuan VARCHAR(40) NOT NULL,
        stok INT UNSIGNED NOT NULL DEFAULT 0,
        stok_minimum INT UNSIGNED NOT NULL DEFAULT 0,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS atk_kategori (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL UNIQUE,
        keterangan VARCHAR(255) NULL,
        sla_jam INT UNSIGNED NOT NULL DEFAULT 24,
        sla_respon_jam INT UNSIGNED NOT NULL DEFAULT 4,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='atk_barang'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('kategori_id', $columns, true)) {
        $pdo->exec('ALTER TABLE atk_barang ADD COLUMN kategori_id INT UNSIGNED NULL AFTER nama');
    }
    $category_columns = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='atk_kategori'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('icon', $category_columns, true)) {
        $pdo->exec("ALTER TABLE atk_kategori ADD COLUMN icon VARCHAR(50) NOT NULL DEFAULT 'fa-tags' AFTER nama");
    }
    if (!in_array('sla_jam', $category_columns, true)) {
        $pdo->exec('ALTER TABLE atk_kategori ADD COLUMN sla_jam INT UNSIGNED NOT NULL DEFAULT 24');
    }
    if (!in_array('sla_respon_jam', $category_columns, true)) {
        $pdo->exec('ALTER TABLE atk_kategori ADD COLUMN sla_respon_jam INT UNSIGNED NOT NULL DEFAULT 4');
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Gagal menyiapkan kategori ATK: ' . clean($e->getMessage()));
}

if (empty($_SESSION['atk_csrf'])) {
    $_SESSION['atk_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['atk_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($csrf, (string)$_POST['csrf'])) {
        http_response_code(419);
        exit('Sesi formulir tidak valid. Muat ulang halaman dan coba kembali.');
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'simpan') {
            $nama = trim($_POST['nama'] ?? '');
            $keterangan = trim($_POST['keterangan'] ?? '');
            $icon = $_POST['icon'] ?? 'fa-tags';
            $sla = filter_var($_POST['sla_jam'] ?? null, FILTER_VALIDATE_INT);
            $sla_respon = filter_var($_POST['sla_respon_jam'] ?? null, FILTER_VALIDATE_INT);
            $allowed_icons = [
                'fa-tags', 'fa-pencil', 'fa-pen', 'fa-highlighter', 'fa-book',
                'fa-book-open', 'fa-file-lines', 'fa-folder', 'fa-paperclip',
                'fa-scissors', 'fa-ruler', 'fa-clipboard', 'fa-stapler',
                'fa-box', 'fa-boxes-stacked', 'fa-envelope', 'fa-note-sticky',
            ];
            if ($nama === '') {
                throw new RuntimeException('Nama kategori wajib diisi.');
            }
            if (!in_array($icon, $allowed_icons, true)) {
                throw new RuntimeException('Ikon kategori tidak valid.');
            }
            if ($sla === false || $sla < 1 || $sla_respon === false || $sla_respon < 1) {
                throw new RuntimeException('Target SLA dan target respon harus berupa angka minimal 1 jam.');
            }

            if ($id) {
                $stmt = $pdo->prepare('UPDATE atk_kategori SET nama=?, icon=?, keterangan=?, sla_jam=?, sla_respon_jam=? WHERE id=?');
                $stmt->execute([$nama, $icon, $keterangan ?: null, $sla, $sla_respon, $id]);
                if (!$stmt->rowCount()) {
                    $check = $pdo->prepare('SELECT id FROM atk_kategori WHERE id=?');
                    $check->execute([$id]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('Kategori tidak ditemukan.');
                    }
                }
                setFlash('success', 'Kategori barang berhasil diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO atk_kategori (nama,icon,keterangan,sla_jam,sla_respon_jam,aktif) VALUES (?,?,?,?,?,1)')
                    ->execute([$nama, $icon, $keterangan ?: null, $sla, $sla_respon]);
                setFlash('success', 'Kategori barang berhasil ditambahkan.');
            }
        } elseif ($action === 'toggle' && $id) {
            $stmt = $pdo->prepare('UPDATE atk_kategori SET aktif=IF(aktif=1,0,1) WHERE id=?');
            $stmt->execute([$id]);
            if (!$stmt->rowCount()) {
                $check = $pdo->prepare('SELECT id FROM atk_kategori WHERE id=?');
                $check->execute([$id]);
                if (!$check->fetchColumn()) {
                    throw new RuntimeException('Kategori tidak ditemukan.');
                }
            }
            setFlash('success', 'Status kategori berhasil diperbarui.');
        } else {
            throw new RuntimeException('Aksi kategori tidak valid.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            setFlash('danger', 'Nama kategori sudah digunakan.');
        } else {
            throw $e;
        }
    } catch (RuntimeException $e) {
        setFlash('danger', $e->getMessage());
    }
    redirect(APP_URL . '/pages/kategori_atk.php');
}

$categories = $pdo->query("
    SELECT k.*, COUNT(b.id) AS jumlah_barang
    FROM atk_kategori k
    LEFT JOIN atk_barang b ON b.kategori_id=k.id
    GROUP BY k.id
    ORDER BY k.nama
")->fetchAll();
$total_categories = count($categories);
$categorized_items = array_sum(array_column($categories, 'jumlah_barang'));
$average_sla = $total_categories > 0
    ? (int)round(array_sum(array_column($categories, 'sla_jam')) / $total_categories)
    : 0;
$average_response = $total_categories > 0
    ? (int)round(array_sum(array_column($categories, 'sla_respon_jam')) / $total_categories)
    : 0;
$category_icons = [
    'fa-pencil', 'fa-pen', 'fa-highlighter', 'fa-book', 'fa-book-open',
    'fa-file-lines', 'fa-folder', 'fa-paperclip', 'fa-scissors', 'fa-ruler',
    'fa-clipboard', 'fa-stapler', 'fa-box', 'fa-boxes-stacked',
    'fa-envelope', 'fa-note-sticky', 'fa-tags',
];

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-tags" style="color:#0891b2;"></i> &nbsp;Kategori Barang ATK</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Menu Kategori</span>
    <span class="sep">/</span>
    <span class="cur">Kategori Barang ATK</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>
  <?php include '../includes/category_navigation.php'; ?>

  <div class="category-stats">
    <?php foreach ([
        [$total_categories, 'Total Kategori', 'fa-tags', '#ecfeff', '#0891b2'],
        [$categorized_items, 'Barang Berkategori', 'fa-boxes-stacked', '#dbeafe', '#1d4ed8'],
        [$average_sla, 'Rata-rata SLA', 'fa-clock', '#fef3c7', '#d97706'],
        [$average_response, 'Rata-rata Respon', 'fa-bolt', '#ede9fe', '#7c3aed'],
    ] as [$value, $label, $icon, $background, $color]): ?>
    <div class="category-stat-card">
      <div class="category-stat-icon" style="background:<?= $background ?>;">
        <i class="fa <?= $icon ?>" style="color:<?= $color ?>;font-size:15px;"></i>
      </div>
      <div>
        <div class="category-stat-value"><?= (int)$value ?><?= in_array($label, ['Rata-rata SLA', 'Rata-rata Respon'], true) ? '<span style="font-size:12px;font-weight:600;color:#9ca3af;">j</span>' : '' ?></div>
        <div class="category-stat-label"><?= clean($label) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
      <h5><i class="fa fa-list text-primary"></i> &nbsp;Daftar Kategori Barang ATK <span style="color:#aaa;font-weight:400;">(<?= $total_categories ?>)</span></h5>
      <button type="button" class="btn btn-primary btn-sm" onclick="atkCategoryOpen()"><i class="fa fa-plus"></i> Tambah Kategori</button>
    </div>

    <form class="category-table-tools" data-category-filter>
      <div class="category-table-search">
        <i class="fa fa-search"></i>
        <input type="search" class="form-control" data-category-search placeholder="Cari nama atau keterangan kategori..." aria-label="Cari kategori barang">
      </div>
      <select class="form-control" data-category-status aria-label="Filter status kategori">
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
          <th style="width:60px;text-align:center;">Ikon</th>
          <th>Nama Kategori</th>
          <th>Keterangan</th>
          <th style="width:115px;">Target SLA</th>
          <th style="width:125px;">Target Respon</th>
          <th style="text-align:center;">Barang</th>
          <th style="text-align:center;">Status</th>
          <th style="text-align:center;width:150px;">Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (!$categories): ?>
          <tr data-empty-category><td colspan="8" style="text-align:center;color:#64748b;padding:32px;">Belum ada kategori barang ATK.</td></tr>
        <?php else: foreach ($categories as $category): ?>
          <tr data-category-row data-status="<?= (int)$category['aktif'] ? 'aktif' : 'nonaktif' ?>">
            <td style="text-align:center;"><div style="width:36px;height:36px;margin:0 auto;border-radius:9px;background:#ecfeff;display:flex;align-items:center;justify-content:center;"><i class="fa <?= clean($category['icon'] ?? 'fa-tags') ?>" style="color:#0891b2;font-size:15px;"></i></div></td>
            <td><strong><?= clean($category['nama']) ?></strong></td>
            <td><?= clean($category['keterangan'] ?? '-') ?></td>
            <td><div class="sla-bar"><span class="sla-dot" style="background:<?= (int)$category['sla_jam'] <= 24 ? '#22c55e' : ((int)$category['sla_jam'] <= 48 ? '#f59e0b' : '#ef4444') ?>;"></span><strong><?= (int)$category['sla_jam'] ?></strong><span style="color:#94a3b8;">jam</span></div></td>
            <td><div class="sla-bar"><span class="sla-dot" style="background:<?= (int)$category['sla_respon_jam'] <= 4 ? '#22c55e' : ((int)$category['sla_respon_jam'] <= 8 ? '#f59e0b' : '#ef4444') ?>;"></span><strong><?= (int)$category['sla_respon_jam'] ?></strong><span style="color:#94a3b8;">jam</span></div></td>
            <td style="text-align:center;"><?= (int)$category['jumlah_barang'] ?></td>
            <td style="text-align:center;">
              <span style="display:inline-flex;padding:3px 9px;border-radius:20px;font-size:10.5px;font-weight:700;background:<?= (int)$category['aktif'] ? '#dcfce7' : '#fee2e2' ?>;color:<?= (int)$category['aktif'] ? '#15803d' : '#b91c1c' ?>;">
                <?= (int)$category['aktif'] ? 'Aktif' : 'Nonaktif' ?>
              </span>
            </td>
            <td style="text-align:center;white-space:nowrap;">
              <button type="button" class="btn btn-warning btn-sm" onclick='atkCategoryOpen(<?= json_encode($category, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)' title="Ubah"><i class="fa fa-edit"></i></button>
              <form method="post" action="<?= APP_URL ?>/pages/kategori_atk.php" style="display:inline;" onsubmit="return confirm('<?= (int)$category['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> kategori ini?')">
                <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                <button type="submit" class="btn btn-default btn-sm" title="<?= (int)$category['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                  <i class="fa <?= (int)$category['aktif'] ? 'fa-ban' : 'fa-circle-check' ?>"></i>
                </button>
              </form>
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
</div>

<style>
#atk-category-modal {
    visibility: hidden !important;
    opacity: 0 !important;
    position: fixed !important;
    inset: 0 !important;
    z-index: 99999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 16px !important;
    background: rgba(0,0,0,.5) !important;
    pointer-events: none !important;
    transition: opacity .22s ease, visibility .22s ease !important;
}
#atk-category-modal.open {
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}
#atk-category-modal .atk-category-box {
    width: 100%;
    max-width: 540px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 12px 48px rgba(0,0,0,.22);
    transform: translateY(22px) scale(.98);
    transition: transform .22s ease;
}
#atk-category-modal.open .atk-category-box { transform: translateY(0) scale(1); }
.atk-category-hd, .atk-category-ft {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 14px 20px;
    background: #fafafa;
    flex-shrink: 0;
}
.atk-category-hd { border-bottom: 1px solid #f0f0f0; }
.atk-category-hd h5 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    color: #1e293b;
    font-size: 15px;
    font-weight: 700;
}
.atk-category-close {
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #94a3b8;
    font-size: 22px;
    cursor: pointer;
}
.atk-category-close:hover { background: #fee2e2; color: #dc2626; }
.atk-category-body { padding: 20px; overflow-y: auto; }
.atk-category-ft { justify-content: flex-end; border-top: 1px solid #f0f0f0; }
.atk-category-body .form-group { margin-bottom: 14px; }
.atk-category-body label { display: block; margin-bottom: 6px; color: #475569; font-size: 12px; font-weight: 600; }
.atk-category-form-row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
.atk-category-hint { display:inline-flex; align-items:center; gap:5px; margin-top:4px; padding:2px 7px; border-radius:4px; background:#f1f5f9; color:#64748b; font-size:10px; font-weight:700; }
.atk-category-required { color:#ef4444; }
.atk-icon-grid { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:6px; }
.atk-icon-option { aspect-ratio:1; min-width:0; padding:9px 4px; border:2px solid #e5e7eb; border-radius:8px; background:#f8fafc; color:#64748b; cursor:pointer; transition:border-color .15s,background .15s,color .15s; }
.atk-icon-option:hover { border-color:#00c896; background:#ecfdf5; color:#00a77e; }
.atk-icon-option.selected { border-color:#00c896; background:#d1fae5; color:#00a77e; }
.atk-icon-option i { display:block; font-size:20px; }
@media(max-width:480px) { .atk-icon-grid { grid-template-columns:repeat(4,minmax(0,1fr)); } }
</style>

<div id="atk-category-modal" role="dialog" aria-modal="true" aria-labelledby="atk-category-title">
  <div class="atk-category-box">
    <div class="atk-category-hd">
      <h5><i class="fa fa-tags" id="atk-category-icon" style="color:#0891b2;"></i><span id="atk-category-title">Tambah Kategori Barang</span></h5>
      <button type="button" class="atk-category-close" onclick="atkCategoryClose()" aria-label="Tutup">&times;</button>
    </div>
    <div class="atk-category-body">
      <form method="post" action="<?= APP_URL ?>/pages/kategori_atk.php" id="atk-category-form">
        <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
        <input type="hidden" name="action" id="atk-category-action" value="simpan">
        <input type="hidden" name="id" id="atk-category-id" value="">
        <input type="hidden" name="icon" id="atk-category-selected-icon" value="fa-tags">
        <div class="form-group">
          <label for="atk-category-name">Nama Kategori <span class="atk-category-required">*</span></label>
          <input class="form-control" id="atk-category-name" name="nama" maxlength="100" required placeholder="Contoh: Alat Tulis, Kertas">
        </div>
        <div class="form-group">
          <label for="atk-category-description">Keterangan <span style="color:#94a3b8;font-weight:400;">(opsional)</span></label>
          <textarea class="form-control" id="atk-category-description" name="keterangan" maxlength="255" rows="3" placeholder="Keterangan singkat kategori ini..."></textarea>
        </div>
        <div class="atk-category-form-row">
          <div class="form-group">
            <label for="atk-category-sla">Target SLA (jam)</label>
            <input class="form-control" type="number" id="atk-category-sla" name="sla_jam" min="1" value="24" required>
            <div class="atk-category-hint"><i class="fa fa-clock"></i> Waktu pemenuhan ATK</div>
          </div>
          <div class="form-group">
            <label for="atk-category-response">Target Respon (jam)</label>
            <input class="form-control" type="number" id="atk-category-response" name="sla_respon_jam" min="1" value="4" required>
            <div class="atk-category-hint"><i class="fa fa-bolt"></i> Waktu respons pertama</div>
          </div>
        </div>
        <div class="form-group">
          <label>Pilih Ikon Kategori ATK</label>
          <div class="atk-icon-grid">
            <?php foreach ($category_icons as $icon): ?>
            <button type="button" class="atk-icon-option" data-icon="<?= clean($icon) ?>" onclick="atkCategorySelectIcon(this)" aria-label="<?= clean($icon) ?>" title="<?= clean($icon) ?>">
              <i class="fa <?= clean($icon) ?>"></i>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </form>
    </div>
    <div class="atk-category-ft">
      <button type="button" class="btn btn-default" onclick="atkCategoryClose()"><i class="fa fa-times"></i> Batal</button>
      <button type="submit" form="atk-category-form" class="btn btn-primary" id="atk-category-submit"><i class="fa fa-save"></i> Simpan</button>
    </div>
  </div>
</div>

<script>
const atkCategoryModal = document.getElementById('atk-category-modal');

function atkCategoryOpen(category) {
    const editing = Boolean(category);
    document.getElementById('atk-category-title').textContent = editing
        ? 'Edit: ' + category.nama
        : 'Tambah Kategori Barang';
    document.getElementById('atk-category-icon').style.color = editing ? '#f59e0b' : '#0891b2';
    document.getElementById('atk-category-id').value = editing ? category.id : '';
    document.getElementById('atk-category-name').value = editing ? category.nama : '';
    document.getElementById('atk-category-description').value = editing ? (category.keterangan || '') : '';
    document.getElementById('atk-category-sla').value = editing ? (category.sla_jam || 24) : 24;
    document.getElementById('atk-category-response').value = editing ? (category.sla_respon_jam || 4) : 4;
    atkCategorySetIcon(editing ? (category.icon || 'fa-tags') : 'fa-tags');
    document.getElementById('atk-category-submit').innerHTML = editing
        ? '<i class="fa fa-save"></i> Update'
        : '<i class="fa fa-save"></i> Simpan';
    atkCategoryModal.classList.add('open');
    setTimeout(() => document.getElementById('atk-category-name').focus(), 80);
}

function atkCategorySelectIcon(button) {
    atkCategorySetIcon(button.dataset.icon);
}

function atkCategorySetIcon(icon) {
    document.getElementById('atk-category-selected-icon').value = icon;
    document.getElementById('atk-category-icon').className = 'fa ' + icon;
    document.querySelectorAll('.atk-icon-option').forEach(button => {
        button.classList.toggle('selected', button.dataset.icon === icon);
    });
}

function atkCategoryClose() {
    atkCategoryModal.classList.remove('open');
}

atkCategoryModal.addEventListener('click', event => {
    if (event.target === atkCategoryModal) atkCategoryClose();
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') atkCategoryClose();
});
</script>
<script src="<?= APP_URL ?>/assets/js/category-table.js"></script>
<?php include '../includes/footer.php'; ?>
