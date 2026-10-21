<?php
session_start();
require_once '../config.php';
requireLogin();

$can_manage = hasRole(['admin', 'teknisi_atem', 'keuangan']);
$uid = (int)$_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'stok';
$tabs = ['stok', 'barang_masuk', 'kartu_stok', 'opname', 'supplier', 'permintaan', 'pengajuan', 'laporan'];
if (!in_array($tab, $tabs, true)) {
    $tab = 'stok';
}
$menu_by_tab = [
    'stok' => 'atk_stok',
    'barang_masuk' => 'atk_barang_masuk',
    'kartu_stok' => 'atk_kartu_stok',
    'opname' => 'atk_opname',
    'supplier' => 'atk_supplier',
    'permintaan' => 'atk_permintaan',
    'pengajuan' => 'atk_pengajuan',
    'laporan' => 'atk_laporan',
];
$title_by_tab = [
    'stok' => 'Data Stok Barang ATK',
    'barang_masuk' => 'Barang Masuk',
    'kartu_stok' => 'Kartu Stok',
    'opname' => 'Stok Opname',
    'supplier' => 'Supplier',
    'permintaan' => 'Permintaan ATK',
    'pengajuan' => 'Pengajuan Pembelian ATK',
    'laporan' => 'Laporan ATK',
];
$page_title = $title_by_tab[$tab];
$active_menu = $menu_by_tab[$tab];
if (($_GET['tab'] ?? '') === 'kategori') {
    redirect(APP_URL . '/pages/kategori_atk.php');
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS atk_barang (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kode VARCHAR(40) NOT NULL UNIQUE,
        nama VARCHAR(150) NOT NULL,
        kategori_id INT UNSIGNED NULL,
        satuan VARCHAR(40) NOT NULL,
        stok INT UNSIGNED NOT NULL DEFAULT 0,
        stok_minimum INT UNSIGNED NOT NULL DEFAULT 0,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS atk_permintaan (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nomor VARCHAR(40) NOT NULL UNIQUE,
        user_id INT UNSIGNED NOT NULL,
        barang_id INT UNSIGNED NOT NULL,
        jumlah INT UNSIGNED NOT NULL,
        keperluan TEXT NOT NULL,
        status ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
        catatan_approval TEXT NULL,
        approved_by INT UNSIGNED NULL,
        approved_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id), INDEX (barang_id), INDEX (status), INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS atk_pengajuan (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nomor VARCHAR(40) NOT NULL UNIQUE,
        user_id INT UNSIGNED NOT NULL,
        barang_id INT UNSIGNED NULL,
        nama_barang VARCHAR(150) NOT NULL,
        satuan VARCHAR(40) NOT NULL,
        jumlah INT UNSIGNED NOT NULL,
        alasan TEXT NOT NULL,
        status ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
        catatan_approval TEXT NULL,
        approved_by INT UNSIGNED NULL,
        approved_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id), INDEX (barang_id), INDEX (status), INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS atk_mutasi (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        barang_id INT UNSIGNED NOT NULL,
        jenis ENUM('masuk','keluar','penyesuaian') NOT NULL,
        jumlah INT UNSIGNED NOT NULL,
        catatan VARCHAR(255) NOT NULL,
        supplier_id INT UNSIGNED NULL,
        user_id INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (barang_id), INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS atk_kategori (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(100) NOT NULL UNIQUE,
        icon VARCHAR(50) NOT NULL DEFAULT 'fa-tags',
        keterangan VARCHAR(255) NULL,
        sla_jam INT UNSIGNED NOT NULL DEFAULT 24,
        sla_respon_jam INT UNSIGNED NOT NULL DEFAULT 4,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS atk_supplier (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(150) NOT NULL,
        kontak VARCHAR(100) NULL,
        telepon VARCHAR(40) NULL,
        alamat TEXT NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (nama), INDEX (aktif)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS atk_opname (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nomor VARCHAR(40) NOT NULL UNIQUE,
        catatan VARCHAR(255) NULL,
        user_id INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS atk_opname_detail (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        opname_id INT UNSIGNED NOT NULL,
        barang_id INT UNSIGNED NOT NULL,
        stok_sistem INT UNSIGNED NOT NULL,
        stok_fisik INT UNSIGNED NOT NULL,
        selisih INT NOT NULL,
        INDEX (opname_id), INDEX (barang_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = $pdo->query("SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('atk_barang','atk_mutasi')")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('atk_barang.kategori_id', $columns, true)) {
        $pdo->exec('ALTER TABLE atk_barang ADD COLUMN kategori_id INT UNSIGNED NULL AFTER nama');
    }
    if (!in_array('atk_mutasi.supplier_id', $columns, true)) {
        $pdo->exec('ALTER TABLE atk_mutasi ADD COLUMN supplier_id INT UNSIGNED NULL AFTER catatan');
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Gagal menyiapkan modul ATK: ' . clean($e->getMessage()));
}

if (empty($_SESSION['atk_csrf'])) {
    $_SESSION['atk_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['atk_csrf'];
$atk_url = APP_URL . '/pages/atk.php';

function atk_redirect(string $url): void
{
    redirect($url);
}

function atk_number(string $prefix): string
{
    return $prefix . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function atk_require_csrf(string $expected): void
{
    if (!isset($_POST['csrf']) || !hash_equals($expected, (string)$_POST['csrf'])) {
        http_response_code(419);
        exit('Sesi formulir tidak valid. Muat ulang halaman dan coba kembali.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    atk_require_csrf($csrf);
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'simpan_barang') {
            if (!$can_manage) {
                throw new RuntimeException('Anda tidak memiliki izin mengelola stok.');
            }
            $id = (int)($_POST['id'] ?? 0);
            $kode = strtoupper(trim($_POST['kode'] ?? ''));
            $nama = trim($_POST['nama'] ?? '');
            $satuan = trim($_POST['satuan'] ?? '');
            $kategori_id = (int)($_POST['kategori_id'] ?? 0) ?: null;
            $stok_minimum = filter_var($_POST['stok_minimum'] ?? null, FILTER_VALIDATE_INT);
            $stok_awal = filter_var($_POST['stok_awal'] ?? null, FILTER_VALIDATE_INT);
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            if ($nama === '' || $satuan === '' || $stok_minimum === false || $stok_minimum < 0) {
                throw new RuntimeException('Nama, satuan, dan stok minimum harus diisi dengan benar.');
            }
            if (!$id && ($stok_awal === false || $stok_awal < 0)) {
                throw new RuntimeException('Stok awal harus berupa angka nol atau lebih.');
            }
            if (!$id && $kode === '') {
                $kode = 'ATK-' . strtoupper(bin2hex(random_bytes(3)));
            }
            if ($id && $kode === '') {
                throw new RuntimeException('Kode barang wajib diisi.');
            }
            if ($kategori_id) {
                $check_category = $pdo->prepare('SELECT id FROM atk_kategori WHERE id=? AND aktif=1');
                $check_category->execute([$kategori_id]);
                if (!$check_category->fetchColumn()) {
                    throw new RuntimeException('Kategori tidak ditemukan atau tidak aktif.');
                }
            }
            if ($id) {
                $stmt = $pdo->prepare('UPDATE atk_barang SET kode=?, nama=?, kategori_id=?, satuan=?, stok_minimum=?, aktif=?, updated_at=NOW() WHERE id=?');
                $stmt->execute([$kode, $nama, $kategori_id, $satuan, $stok_minimum, $aktif, $id]);
                if (!$stmt->rowCount() && !$pdo->query("SELECT 1 FROM atk_barang WHERE id=" . $id)->fetchColumn()) {
                    throw new RuntimeException('Barang tidak ditemukan.');
                }
                setFlash('success', 'Data barang berhasil diperbarui.');
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO atk_barang (kode,nama,kategori_id,satuan,stok,stok_minimum,aktif) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$kode, $nama, $kategori_id, $satuan, $stok_awal, $stok_minimum, $aktif]);
                $barang_id = (int)$pdo->lastInsertId();
                if ($stok_awal > 0) {
                    $pdo->prepare("INSERT INTO atk_mutasi (barang_id,jenis,jumlah,catatan,user_id) VALUES (?,'masuk',?,'Stok awal',?)")
                        ->execute([$barang_id, $stok_awal, $uid]);
                }
                $pdo->commit();
                setFlash('success', 'Barang ATK berhasil ditambahkan.');
            }
            atk_redirect($atk_url . '?tab=stok');
        }

        if ($action === 'tambah_stok') {
            if (!$can_manage) {
                throw new RuntimeException('Anda tidak memiliki izin mengelola stok.');
            }
            $barang_id = (int)($_POST['barang_id'] ?? 0);
            $jumlah = filter_var($_POST['jumlah'] ?? null, FILTER_VALIDATE_INT);
            $catatan = trim($_POST['catatan'] ?? '');
            $supplier_id = (int)($_POST['supplier_id'] ?? 0) ?: null;
            if (!$barang_id || $jumlah === false || $jumlah < 1 || $catatan === '') {
                throw new RuntimeException('Pilih barang, isi jumlah minimal 1, dan tulis sumber stok masuk.');
            }
            if ($supplier_id) {
                $check_supplier = $pdo->prepare('SELECT id FROM atk_supplier WHERE id=? AND aktif=1');
                $check_supplier->execute([$supplier_id]);
                if (!$check_supplier->fetchColumn()) {
                    throw new RuntimeException('Supplier tidak ditemukan atau tidak aktif.');
                }
            }
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT id FROM atk_barang WHERE id=? AND aktif=1 FOR UPDATE');
            $lock->execute([$barang_id]);
            if (!$lock->fetchColumn()) {
                throw new RuntimeException('Barang tidak ditemukan atau tidak aktif.');
            }
            $pdo->prepare('UPDATE atk_barang SET stok=stok+?, updated_at=NOW() WHERE id=?')->execute([$jumlah, $barang_id]);
            $pdo->prepare("INSERT INTO atk_mutasi (barang_id,jenis,jumlah,catatan,supplier_id,user_id) VALUES (?,'masuk',?,?,?,?)")
                ->execute([$barang_id, $jumlah, $catatan, $supplier_id, $uid]);
            $pdo->commit();
            setFlash('success', 'Stok berhasil ditambahkan.');
            atk_redirect($atk_url . '?tab=barang_masuk');
        }

        if ($action === 'simpan_supplier') {
            if (!$can_manage) {
                throw new RuntimeException('Anda tidak memiliki izin mengelola supplier.');
            }
            $id = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $kontak = trim($_POST['kontak'] ?? '');
            $telepon = trim($_POST['telepon'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            if ($nama === '') {
                throw new RuntimeException('Nama supplier wajib diisi.');
            }
            if ($id) {
                $stmt = $pdo->prepare('UPDATE atk_supplier SET nama=?, kontak=?, telepon=?, alamat=?, aktif=? WHERE id=?');
                $stmt->execute([$nama, $kontak ?: null, $telepon ?: null, $alamat ?: null, $aktif, $id]);
                if (!$stmt->rowCount()) {
                    $check = $pdo->prepare('SELECT id FROM atk_supplier WHERE id=?');
                    $check->execute([$id]);
                    if (!$check->fetchColumn()) {
                        throw new RuntimeException('Supplier tidak ditemukan.');
                    }
                }
                setFlash('success', 'Data supplier berhasil diperbarui.');
            } else {
                $pdo->prepare('INSERT INTO atk_supplier (nama,kontak,telepon,alamat,aktif) VALUES (?,?,?,?,1)')
                    ->execute([$nama, $kontak ?: null, $telepon ?: null, $alamat ?: null]);
                setFlash('success', 'Supplier berhasil ditambahkan.');
            }
            atk_redirect($atk_url . '?tab=supplier');
        }

        if ($action === 'simpan_opname') {
            if (!$can_manage) {
                throw new RuntimeException('Anda tidak memiliki izin melakukan stok opname.');
            }
            $physical = $_POST['stok_fisik'] ?? [];
            $catatan = trim($_POST['catatan'] ?? '');
            if (!is_array($physical) || !$physical) {
                throw new RuntimeException('Isi stok fisik untuk minimal satu barang.');
            }
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO atk_opname (nomor,catatan,user_id) VALUES (?,?,?)')
                ->execute([atk_number('OPN-'), $catatan ?: null, $uid]);
            $opname_id = (int)$pdo->lastInsertId();
            $detail_count = 0;
            foreach ($physical as $raw_id => $raw_count) {
                $barang_id = filter_var($raw_id, FILTER_VALIDATE_INT);
                $stok_fisik = filter_var($raw_count, FILTER_VALIDATE_INT);
                if ($barang_id === false || $barang_id < 1 || $stok_fisik === false || $stok_fisik < 0) {
                    throw new RuntimeException('Jumlah stok fisik harus berupa angka nol atau lebih.');
                }
                $lock = $pdo->prepare('SELECT stok FROM atk_barang WHERE id=? AND aktif=1 FOR UPDATE');
                $lock->execute([$barang_id]);
                $stok_sistem = $lock->fetchColumn();
                if ($stok_sistem === false) {
                    continue;
                }
                $stok_sistem = (int)$stok_sistem;
                $selisih = $stok_fisik - $stok_sistem;
                $pdo->prepare('INSERT INTO atk_opname_detail (opname_id,barang_id,stok_sistem,stok_fisik,selisih) VALUES (?,?,?,?,?)')
                    ->execute([$opname_id, $barang_id, $stok_sistem, $stok_fisik, $selisih]);
                $detail_count++;
                if ($selisih !== 0) {
                    $pdo->prepare('UPDATE atk_barang SET stok=?, updated_at=NOW() WHERE id=?')
                        ->execute([$stok_fisik, $barang_id]);
                    $jenis = $selisih > 0 ? 'masuk' : 'keluar';
                    $pdo->prepare('INSERT INTO atk_mutasi (barang_id,jenis,jumlah,catatan,user_id) VALUES (?,?,?,?,?)')
                        ->execute([$barang_id, $jenis, abs($selisih), 'Penyesuaian stok opname ' . $opname_id . ' (selisih ' . $selisih . ')', $uid]);
                }
            }
            if ($detail_count === 0) {
                throw new RuntimeException('Tidak ada barang aktif yang diproses untuk opname.');
            }
            $pdo->commit();
            setFlash('success', 'Stok opname berhasil disimpan dan stok sistem telah disesuaikan.');
            atk_redirect($atk_url . '?tab=opname');
        }

        if ($action === 'kirim_permintaan') {
            $barang_id = (int)($_POST['barang_id'] ?? 0);
            $jumlah = filter_var($_POST['jumlah'] ?? null, FILTER_VALIDATE_INT);
            $keperluan = trim($_POST['keperluan'] ?? '');
            $check = $pdo->prepare('SELECT id FROM atk_barang WHERE id=? AND aktif=1');
            $check->execute([$barang_id]);
            if (!$check->fetchColumn() || $jumlah === false || $jumlah < 1 || $keperluan === '') {
                throw new RuntimeException('Pilih barang aktif, jumlah minimal 1, dan isi keperluan.');
            }
            $pdo->prepare("INSERT INTO atk_permintaan (nomor,user_id,barang_id,jumlah,keperluan) VALUES (?,?,?,?,?)")
                ->execute([atk_number('REQ-'), $uid, $barang_id, $jumlah, $keperluan]);
            setFlash('success', 'Permintaan ATK berhasil dikirim untuk persetujuan.');
            atk_redirect($atk_url . '?tab=permintaan');
        }

        if ($action === 'kirim_pengajuan') {
            $barang_id = (int)($_POST['barang_id'] ?? 0);
            $jumlah = filter_var($_POST['jumlah'] ?? null, FILTER_VALIDATE_INT);
            $nama_barang = trim($_POST['nama_barang'] ?? '');
            $satuan = trim($_POST['satuan'] ?? '');
            $alasan = trim($_POST['alasan'] ?? '');
            if ($jumlah === false || $jumlah < 1 || $alasan === '') {
                throw new RuntimeException('Jumlah minimal 1 dan alasan pengajuan wajib diisi.');
            }
            if ($barang_id) {
                $check = $pdo->prepare('SELECT nama,satuan FROM atk_barang WHERE id=? AND aktif=1');
                $check->execute([$barang_id]);
                $barang = $check->fetch();
                if (!$barang) {
                    throw new RuntimeException('Barang yang dipilih tidak ditemukan atau tidak aktif.');
                }
                $nama_barang = $barang['nama'];
                $satuan = $barang['satuan'];
            } elseif ($nama_barang === '' || $satuan === '') {
                throw new RuntimeException('Untuk barang baru, nama barang dan satuan wajib diisi.');
            }
            $pdo->prepare('INSERT INTO atk_pengajuan (nomor,user_id,barang_id,nama_barang,satuan,jumlah,alasan) VALUES (?,?,?,?,?,?,?)')
                ->execute([atk_number('BUY-'), $uid, $barang_id ?: null, $nama_barang, $satuan, $jumlah, $alasan]);
            setFlash('success', 'Pengajuan pembelian berhasil dikirim untuk persetujuan.');
            atk_redirect($atk_url . '?tab=pengajuan');
        }

        if ($action === 'putuskan_permintaan' || $action === 'putuskan_pengajuan') {
            if (!$can_manage) {
                throw new RuntimeException('Anda tidak memiliki izin menyetujui pengajuan.');
            }
            $id = (int)($_POST['id'] ?? 0);
            $keputusan = $_POST['keputusan'] ?? '';
            $catatan = trim($_POST['catatan_approval'] ?? '');
            if (!$id || !in_array($keputusan, ['disetujui', 'ditolak'], true)) {
                throw new RuntimeException('Keputusan persetujuan tidak valid.');
            }
            $is_request = $action === 'putuskan_permintaan';
            $table = $is_request ? 'atk_permintaan' : 'atk_pengajuan';
            $pdo->beginTransaction();
            $select = $pdo->prepare("SELECT * FROM {$table} WHERE id=? FOR UPDATE");
            $select->execute([$id]);
            $row = $select->fetch();
            if (!$row || $row['status'] !== 'menunggu') {
                throw new RuntimeException('Data tidak ditemukan atau sudah diproses.');
            }
            if ($keputusan === 'disetujui' && $is_request) {
                $stock = $pdo->prepare('SELECT stok FROM atk_barang WHERE id=? AND aktif=1 FOR UPDATE');
                $stock->execute([(int)$row['barang_id']]);
                $stok = $stock->fetchColumn();
                if ($stok === false || (int)$stok < (int)$row['jumlah']) {
                    throw new RuntimeException('Stok barang tidak mencukupi untuk menyetujui permintaan ini.');
                }
                $pdo->prepare('UPDATE atk_barang SET stok=stok-?, updated_at=NOW() WHERE id=?')
                    ->execute([(int)$row['jumlah'], (int)$row['barang_id']]);
                $pdo->prepare("INSERT INTO atk_mutasi (barang_id,jenis,jumlah,catatan,user_id) VALUES (?,'keluar',?,?,?)")
                    ->execute([(int)$row['barang_id'], (int)$row['jumlah'], 'Permintaan ' . $row['nomor'], $uid]);
            } elseif ($keputusan === 'disetujui') {
                if ($row['barang_id']) {
                    $stock = $pdo->prepare('SELECT id FROM atk_barang WHERE id=? FOR UPDATE');
                    $stock->execute([(int)$row['barang_id']]);
                    if (!$stock->fetchColumn()) {
                        throw new RuntimeException('Barang yang ditautkan ke pengajuan tidak ditemukan.');
                    }
                    $barang_id = (int)$row['barang_id'];
                    $pdo->prepare('UPDATE atk_barang SET stok=stok+?, aktif=1, updated_at=NOW() WHERE id=?')
                        ->execute([(int)$row['jumlah'], $barang_id]);
                } else {
                    $kode = 'ATK-' . strtoupper(bin2hex(random_bytes(3)));
                    $pdo->prepare('INSERT INTO atk_barang (kode,nama,satuan,stok,stok_minimum,aktif) VALUES (?,?,?,?,0,1)')
                        ->execute([$kode, $row['nama_barang'], $row['satuan'], (int)$row['jumlah']]);
                    $barang_id = (int)$pdo->lastInsertId();
                    $pdo->prepare('UPDATE atk_pengajuan SET barang_id=? WHERE id=?')->execute([$barang_id, $id]);
                }
                $pdo->prepare("INSERT INTO atk_mutasi (barang_id,jenis,jumlah,catatan,user_id) VALUES (?,'masuk',?,?,?)")
                    ->execute([$barang_id, (int)$row['jumlah'], 'Pengajuan ' . $row['nomor'], $uid]);
            }
            $pdo->prepare("UPDATE {$table} SET status=?, catatan_approval=?, approved_by=?, approved_at=NOW() WHERE id=?")
                ->execute([$keputusan, $catatan ?: null, $uid, $id]);
            $pdo->commit();
            setFlash('success', $keputusan === 'disetujui' ? 'Data berhasil disetujui.' : 'Data berhasil ditolak.');
            atk_redirect($atk_url . '?tab=' . ($is_request ? 'permintaan' : 'pengajuan'));
        }

        throw new RuntimeException('Aksi tidak dikenal.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $e instanceof PDOException && $e->getCode() === '23000'
            ? 'Data duplikat terdeteksi. Periksa kembali nama kategori, supplier, atau kode barang.'
            : $e->getMessage();
        setFlash('danger', $message);
        atk_redirect($atk_url . '?tab=' . urlencode($tab));
    }
}

$edit_item = null;
if ($tab === 'stok' && $can_manage && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM atk_barang WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit_item = $stmt->fetch() ?: null;
}
$edit_supplier = null;
if ($tab === 'supplier' && $can_manage && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM atk_supplier WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit_supplier = $stmt->fetch() ?: null;
}
$items = $pdo->query("SELECT b.*,k.nama kategori_nama FROM atk_barang b
    LEFT JOIN atk_kategori k ON k.id=b.kategori_id" . ($can_manage ? '' : ' WHERE b.aktif=1') . ' ORDER BY b.nama')->fetchAll();
$active_items = array_values(array_filter($items, static fn($item) => (int)$item['aktif'] === 1));
$categories = $pdo->query('SELECT * FROM atk_kategori' . ($can_manage ? '' : ' WHERE aktif=1') . ' ORDER BY nama')->fetchAll();
$active_categories = array_values(array_filter($categories, static fn($category) => (int)$category['aktif'] === 1));
$suppliers = $pdo->query('SELECT * FROM atk_supplier' . ($can_manage ? '' : ' WHERE aktif=1') . ' ORDER BY nama')->fetchAll();
$active_suppliers = array_values(array_filter($suppliers, static fn($supplier) => (int)$supplier['aktif'] === 1));
$scope = $can_manage ? '' : ' WHERE x.user_id=' . $uid;

$request_sql = "SELECT x.*, b.nama barang_nama, b.satuan, u.nama pemohon, a.nama approver
    FROM atk_permintaan x LEFT JOIN atk_barang b ON b.id=x.barang_id
    LEFT JOIN users u ON u.id=x.user_id LEFT JOIN users a ON a.id=x.approved_by";
$request_rows = $pdo->query($request_sql . $scope . ' ORDER BY x.created_at DESC LIMIT 500')->fetchAll();
$proposal_sql = "SELECT x.*, u.nama pemohon, a.nama approver
    FROM atk_pengajuan x LEFT JOIN users u ON u.id=x.user_id LEFT JOIN users a ON a.id=x.approved_by";
$proposal_rows = $pdo->query($proposal_sql . $scope . ' ORDER BY x.created_at DESC LIMIT 500')->fetchAll();
$movement_rows = [];
if (in_array($tab, ['kartu_stok', 'laporan'], true)) {
    $movement_rows = $pdo->query("SELECT m.*,b.kode,b.nama,b.satuan,b.stok stok_sekarang,u.nama petugas,s.nama supplier_nama FROM atk_mutasi m
        JOIN atk_barang b ON b.id=m.barang_id LEFT JOIN users u ON u.id=m.user_id
        LEFT JOIN atk_supplier s ON s.id=m.supplier_id
        ORDER BY m.created_at ASC,m.id ASC")->fetchAll();
}
$opname_rows = [];
if ($tab === 'opname') {
    $opname_rows = $pdo->query("SELECT o.nomor,o.created_at,o.catatan,o.user_id,u.nama petugas,
        b.kode,b.nama barang_nama,b.satuan,d.stok_sistem,d.stok_fisik,d.selisih
        FROM atk_opname_detail d JOIN atk_opname o ON o.id=d.opname_id
        JOIN atk_barang b ON b.id=d.barang_id LEFT JOIN users u ON u.id=o.user_id
        ORDER BY o.created_at DESC,d.id DESC LIMIT 500")->fetchAll();
}
if ($movement_rows) {
    $running_balances = [];
    $current_stocks = [];
    foreach ($movement_rows as $movement) {
        $item_id = (int)$movement['barang_id'];
        $signed_quantity = $movement['jenis'] === 'keluar' ? -(int)$movement['jumlah'] : (int)$movement['jumlah'];
        $running_balances[$item_id] = ($running_balances[$item_id] ?? 0) + $signed_quantity;
        $current_stocks[$item_id] = (int)$movement['stok_sekarang'];
    }
    foreach ($running_balances as $item_id => $movement_total) {
        $running_balances[$item_id] = $current_stocks[$item_id] - $movement_total;
    }
    foreach ($movement_rows as &$movement) {
        $item_id = (int)$movement['barang_id'];
        $quantity = (int)$movement['jumlah'];
        $movement['saldo'] = $movement['jenis'] === 'keluar'
            ? $running_balances[$item_id] - $quantity
            : $running_balances[$item_id] + $quantity;
        $running_balances[$item_id] = $movement['saldo'];
    }
    unset($movement);
    $movement_rows = array_reverse($movement_rows);
}

include '../includes/header.php';
?>

<div class="page-header">
  <h4><i class="fa fa-boxes-stacked" style="color:#0891b2;"></i> &nbsp;<?= clean($page_title) ?></h4>
  <div class="breadcrumb"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a><span class="sep">/</span><span class="cur">ATK</span></div>
</div>

<style>
.atk-content .atk-form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px 18px;
    align-items: start;
}
.atk-content .atk-form-grid > label {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 6px;
    margin: 0;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}
.atk-content .atk-form-grid .form-control {
    min-height: 38px;
    border-radius: 6px;
}
.atk-content .atk-form-grid textarea.form-control {
    min-height: 88px;
}
.atk-content .atk-form-wide { grid-column: 1 / -1; }
.atk-content .atk-form-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    grid-column: 1 / -1;
    padding-top: 2px;
}
.atk-content .atk-checkbox {
    display: flex !important;
    min-height: 38px;
    flex-direction: row !important;
    align-items: center;
    gap: 9px !important;
    padding: 0 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #f8fafc;
}
.atk-content .atk-checkbox input { margin: 0; accent-color: #00c896; }
.atk-content .atk-form-grid .btn { min-height: 38px; }
.atk-content .atk-opname-note {
    display: flex;
    max-width: 520px;
    flex-direction: column;
    gap: 6px;
    margin: 0 0 14px !important;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}
.atk-content .atk-opname-note .form-control { max-width: 100%; }
.atk-content .atk-approval-form { display: grid; min-width: 170px; gap: 7px; }
.atk-content .atk-approval-actions { display: flex; flex-wrap: wrap; gap: 5px; }
@media (max-width: 900px) {
    .atk-content .atk-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 600px) {
    .atk-content .atk-form-grid { grid-template-columns: minmax(0, 1fr); gap: 13px; }
    .atk-content .atk-form-wide, .atk-content .atk-form-actions { grid-column: auto; }
    .atk-content .atk-form-actions { flex-wrap: wrap; }
}
</style>

<div class="content atk-content">
  <?= showFlash() ?>

  <?php if ($tab === 'stok'): ?>
    <?php if ($can_manage): ?>
    <div class="panel" style="margin-bottom:14px;">
      <div class="panel-hd"><h5><?= $edit_item ? 'Ubah Data Barang' : 'Tambah Barang ATK' ?></h5></div>
      <div class="panel-bd">
        <form method="post" action="<?= $atk_url ?>?tab=stok" class="atk-form-grid">
          <input type="hidden" name="csrf" value="<?= clean($csrf) ?>">
          <input type="hidden" name="action" value="simpan_barang">
          <input type="hidden" name="id" value="<?= (int)($edit_item['id'] ?? 0) ?>">
          <label>Kode barang<input class="form-control" name="kode" value="<?= clean($edit_item['kode'] ?? '') ?>" placeholder="Otomatis jika kosong"></label>
          <label>Nama barang<input class="form-control" name="nama" required value="<?= clean($edit_item['nama'] ?? '') ?>"></label>
          <label>Kategori<select class="form-control" name="kategori_id"><option value="0">Tanpa kategori</option><?php foreach ($active_categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)($edit_item['kategori_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>><?= clean($category['nama']) ?></option><?php endforeach; ?></select></label>
          <label>Satuan<input class="form-control" name="satuan" required value="<?= clean($edit_item['satuan'] ?? '') ?>" placeholder="pcs, rim, pak"></label>
          <?php if (!$edit_item): ?><label>Stok awal<input class="form-control" type="number" min="0" name="stok_awal" value="0" required></label><?php endif; ?>
          <label>Stok minimum<input class="form-control" type="number" min="0" name="stok_minimum" value="<?= (int)($edit_item['stok_minimum'] ?? 0) ?>" required></label>
          <label class="atk-checkbox"><input type="checkbox" name="aktif" value="1" <?= $edit_item ? ((int)$edit_item['aktif'] ? 'checked' : '') : 'checked' ?>> Barang aktif</label>
          <div class="atk-form-actions"><button class="btn btn-success" type="submit"><i class="fa fa-save"></i> <?= $edit_item ? 'Simpan Perubahan' : 'Tambah Barang' ?></button><?php if ($edit_item): ?><a class="btn btn-default" href="<?= $atk_url ?>?tab=stok">Batal</a><?php endif; ?></div>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <div class="panel">
      <div class="panel-hd"><h5>Daftar Stok Barang <span style="color:#94a3b8;font-weight:400;">(<?= count($items) ?>)</span></h5></div>
      <div class="panel-bd np tbl-wrap">
        <table><thead><tr><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Satuan</th><th>Stok</th><th>Minimum</th><th>Status</th><?php if ($can_manage): ?><th>Aksi</th><?php endif; ?></tr></thead><tbody>
        <?php foreach ($items as $item): ?>
          <tr><td><?= clean($item['kode']) ?></td><td><?= clean($item['nama']) ?></td><td><?= clean($item['kategori_nama'] ?? '-') ?></td><td><?= clean($item['satuan']) ?></td>
          <td><strong style="color:<?= (int)$item['stok'] <= (int)$item['stok_minimum'] ? '#dc2626' : '#15803d' ?>"><?= (int)$item['stok'] ?></strong></td>
          <td><?= (int)$item['stok_minimum'] ?></td><td><?= (int)$item['aktif'] ? 'Aktif' : 'Nonaktif' ?></td>
          <?php if ($can_manage): ?><td><a class="btn btn-default btn-sm" href="<?= $atk_url ?>?tab=stok&amp;edit=<?= (int)$item['id'] ?>">Ubah</a></td><?php endif; ?></tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="<?= $can_manage ? 8 : 7 ?>" style="text-align:center;padding:24px;">Belum ada data barang ATK.</td></tr><?php endif; ?>
        </tbody></table>
      </div>
    </div>
  <?php elseif ($tab === 'barang_masuk'): ?>
    <?php if ($can_manage): ?>
    <div class="panel">
      <div class="panel-hd"><h5>Catat Barang Masuk</h5></div>
      <div class="panel-bd">
        <form method="post" action="<?= $atk_url ?>?tab=barang_masuk" class="atk-form-grid">
          <input type="hidden" name="csrf" value="<?= clean($csrf) ?>"><input type="hidden" name="action" value="tambah_stok">
          <label>Barang<select class="form-control" name="barang_id" required><option value="">Pilih barang</option><?php foreach ($active_items as $item): ?><option value="<?= (int)$item['id'] ?>"><?= clean($item['kode'] . ' - ' . $item['nama']) ?> (stok <?= (int)$item['stok'] ?> <?= clean($item['satuan']) ?>)</option><?php endforeach; ?></select></label>
          <label>Jumlah masuk<input class="form-control" type="number" name="jumlah" min="1" required></label>
          <label>Supplier<select class="form-control" name="supplier_id"><option value="0">Tidak ditentukan</option><?php foreach ($active_suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>"><?= clean($supplier['nama']) ?></option><?php endforeach; ?></select></label>
          <label>Sumber / catatan<input class="form-control" name="catatan" required placeholder="Nomor nota, hibah, dll."></label>
          <div class="atk-form-actions"><button class="btn btn-success" type="submit"><i class="fa fa-circle-down"></i> Simpan Barang Masuk</button></div>
        </form>
        <?php if (!$active_items): ?><div class="alert alert-warning" style="margin-top:12px;">Belum ada barang aktif yang bisa dipilih. Aktifkan barang melalui <strong>Data Stok Barang</strong>.</div><?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="panel"><div class="panel-bd">Anda tidak memiliki izin untuk mencatat barang masuk.</div></div>
    <?php endif; ?>
  <?php elseif ($tab === 'kartu_stok'): ?>
    <div class="panel"><div class="panel-hd"><h5>Kartu Stok - Riwayat Mutasi</h5></div>
      <div class="panel-bd np tbl-wrap"><table><thead><tr><th>Tanggal</th><th>Kode</th><th>Barang</th><th>Jenis</th><th>Jumlah</th><th>Saldo</th><th>Supplier</th><th>Catatan</th><th>Petugas</th></tr></thead><tbody>
      <?php foreach ($movement_rows as $row): ?>
      <tr><td><?= clean(formatTanggal($row['created_at'], true)) ?></td><td><?= clean($row['kode']) ?></td><td><?= clean($row['nama']) ?></td><td><?= $row['jenis'] === 'masuk' ? 'Masuk' : ($row['jenis'] === 'keluar' ? 'Keluar' : 'Penyesuaian') ?></td><td><?= (int)$row['jumlah'] ?> <?= clean($row['satuan']) ?></td><td><strong><?= (int)$row['saldo'] ?></strong></td><td><?= clean($row['supplier_nama'] ?? '-') ?></td><td><?= clean($row['catatan']) ?></td><td><?= clean($row['petugas'] ?? '-') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$movement_rows): ?><tr><td colspan="9" style="text-align:center;padding:24px;">Belum ada riwayat mutasi stok.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  <?php elseif ($tab === 'opname'): ?>
    <?php if ($can_manage): ?>
    <div class="panel" style="margin-bottom:14px;"><div class="panel-hd"><h5>Input Stok Fisik</h5></div><div class="panel-bd">
      <form method="post" action="<?= $atk_url ?>?tab=opname">
        <input type="hidden" name="csrf" value="<?= clean($csrf) ?>"><input type="hidden" name="action" value="simpan_opname">
        <label class="atk-opname-note">Catatan opname<input class="form-control" name="catatan" placeholder="Contoh: Opname gudang Oktober 2026"></label>
        <div class="tbl-wrap" style="margin:12px 0;"><table><thead><tr><th>Kode</th><th>Barang</th><th>Stok Sistem</th><th>Stok Fisik</th><th>Satuan</th></tr></thead><tbody>
        <?php foreach ($active_items as $item): ?><tr><td><?= clean($item['kode']) ?></td><td><?= clean($item['nama']) ?></td><td><?= (int)$item['stok'] ?></td><td><input class="form-control" type="number" min="0" name="stok_fisik[<?= (int)$item['id'] ?>]" value="<?= (int)$item['stok'] ?>" required style="max-width:130px;"></td><td><?= clean($item['satuan']) ?></td></tr><?php endforeach; ?>
        <?php if (!$active_items): ?><tr><td colspan="5" style="text-align:center;padding:20px;">Belum ada barang aktif untuk dihitung.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($active_items): ?><div class="atk-form-actions"><button class="btn btn-success" type="submit"><i class="fa fa-clipboard-check"></i> Simpan Hasil Opname</button></div><?php endif; ?>
      </form>
    </div></div>
    <?php endif; ?>
    <div class="panel"><div class="panel-hd"><h5>Riwayat Stok Opname</h5></div><div class="panel-bd np tbl-wrap">
      <table><thead><tr><th>Nomor</th><th>Tanggal</th><th>Kode</th><th>Barang</th><th>Stok Sistem</th><th>Stok Fisik</th><th>Selisih</th><th>Petugas</th><th>Catatan</th></tr></thead><tbody>
      <?php foreach ($opname_rows as $row): ?><tr><td><?= clean($row['nomor']) ?></td><td><?= clean(formatTanggal($row['created_at'], true)) ?></td><td><?= clean($row['kode']) ?></td><td><?= clean($row['barang_nama']) ?></td><td><?= (int)$row['stok_sistem'] ?></td><td><?= (int)$row['stok_fisik'] ?> <?= clean($row['satuan']) ?></td><td style="color:<?= (int)$row['selisih'] === 0 ? '#15803d' : '#dc2626' ?>"><?= (int)$row['selisih'] ?></td><td><?= clean($row['petugas'] ?? '-') ?></td><td><?= clean($row['catatan'] ?? '-') ?></td></tr><?php endforeach; ?>
      <?php if (!$opname_rows): ?><tr><td colspan="9" style="text-align:center;padding:24px;">Belum ada riwayat stok opname.</td></tr><?php endif; ?>
      </tbody></table>
    </div></div>
  <?php elseif ($tab === 'supplier'): ?>
    <?php if ($can_manage): ?><div class="panel" style="margin-bottom:14px;"><div class="panel-hd"><h5><?= $edit_supplier ? 'Ubah Data Supplier' : 'Tambah Supplier' ?></h5></div><div class="panel-bd">
      <form method="post" action="<?= $atk_url ?>?tab=supplier" class="atk-form-grid">
        <input type="hidden" name="csrf" value="<?= clean($csrf) ?>"><input type="hidden" name="action" value="simpan_supplier"><input type="hidden" name="id" value="<?= (int)($edit_supplier['id'] ?? 0) ?>">
        <label>Nama supplier<input class="form-control" name="nama" required value="<?= clean($edit_supplier['nama'] ?? '') ?>"></label>
        <label>Nama kontak<input class="form-control" name="kontak" value="<?= clean($edit_supplier['kontak'] ?? '') ?>"></label>
        <label>Telepon<input class="form-control" name="telepon" value="<?= clean($edit_supplier['telepon'] ?? '') ?>"></label>
        <label>Alamat<input class="form-control" name="alamat" value="<?= clean($edit_supplier['alamat'] ?? '') ?>"></label>
        <?php if ($edit_supplier): ?><label class="atk-checkbox"><input type="checkbox" name="aktif" value="1" <?= (int)$edit_supplier['aktif'] ? 'checked' : '' ?>> Supplier aktif</label><?php endif; ?>
        <div class="atk-form-actions"><button class="btn btn-success" type="submit"><i class="fa fa-save"></i> <?= $edit_supplier ? 'Simpan Perubahan' : 'Tambah Supplier' ?></button><?php if ($edit_supplier): ?><a class="btn btn-default" href="<?= $atk_url ?>?tab=supplier">Batal</a><?php endif; ?></div>
      </form>
    </div></div><?php endif; ?>
    <div class="panel"><div class="panel-hd"><h5>Daftar Supplier</h5></div><div class="panel-bd np tbl-wrap">
      <table><thead><tr><th>Nama Supplier</th><th>Kontak</th><th>Telepon</th><th>Alamat</th><th>Status</th><?php if ($can_manage): ?><th>Aksi</th><?php endif; ?></tr></thead><tbody>
      <?php foreach ($suppliers as $supplier): ?><tr><td><?= clean($supplier['nama']) ?></td><td><?= clean($supplier['kontak'] ?? '-') ?></td><td><?= clean($supplier['telepon'] ?? '-') ?></td><td><?= clean($supplier['alamat'] ?? '-') ?></td><td><?= (int)$supplier['aktif'] ? 'Aktif' : 'Nonaktif' ?></td><?php if ($can_manage): ?><td><a class="btn btn-default btn-sm" href="<?= $atk_url ?>?tab=supplier&amp;edit=<?= (int)$supplier['id'] ?>">Ubah</a></td><?php endif; ?></tr><?php endforeach; ?>
      <?php if (!$suppliers): ?><tr><td colspan="<?= $can_manage ? 6 : 5 ?>" style="text-align:center;padding:24px;">Belum ada supplier.</td></tr><?php endif; ?>
      </tbody></table>
    </div></div>
  <?php elseif ($tab === 'permintaan'): ?>
    <div class="panel" style="margin-bottom:14px;">
      <div class="panel-hd"><h5>Buat Permintaan Barang</h5></div>
      <div class="panel-bd">
        <form method="post" action="<?= $atk_url ?>?tab=permintaan" class="atk-form-grid">
          <input type="hidden" name="csrf" value="<?= clean($csrf) ?>"><input type="hidden" name="action" value="kirim_permintaan">
          <label>Barang<select class="form-control" name="barang_id" required><option value="">Pilih barang</option><?php foreach ($active_items as $item): ?><option value="<?= (int)$item['id'] ?>"><?= clean($item['nama']) ?> (tersedia <?= (int)$item['stok'] ?> <?= clean($item['satuan']) ?>)</option><?php endforeach; ?></select></label>
          <label>Jumlah<input class="form-control" type="number" name="jumlah" min="1" required></label>
          <label class="atk-form-wide">Keperluan<textarea class="form-control" name="keperluan" required rows="3" placeholder="Jelaskan keperluan barang yang diminta"></textarea></label>
          <div class="atk-form-actions"><button class="btn btn-success" type="submit"><i class="fa fa-paper-plane"></i> Kirim Permintaan</button></div>
        </form>
      </div>
    </div>
    <div class="panel"><div class="panel-hd"><h5><?= $can_manage ? 'Daftar Permintaan ATK' : 'Riwayat Permintaan Saya' ?></h5></div>
      <div class="panel-bd np tbl-wrap"><table><thead><tr><th>No.</th><th>Tanggal</th><?php if ($can_manage): ?><th>Pemohon</th><?php endif; ?><th>Barang</th><th>Jumlah</th><th>Keperluan</th><th>Status</th><th>Catatan</th><?php if ($can_manage): ?><th>Persetujuan</th><?php endif; ?></tr></thead><tbody>
      <?php foreach ($request_rows as $row): ?>
        <tr><td><?= clean($row['nomor']) ?></td><td><?= clean(formatTanggal($row['created_at'], true)) ?></td><?php if ($can_manage): ?><td><?= clean($row['pemohon'] ?? '-') ?></td><?php endif; ?><td><?= clean($row['barang_nama'] ?? 'Barang dihapus') ?></td><td><?= (int)$row['jumlah'] ?> <?= clean($row['satuan'] ?? '') ?></td><td><?= clean($row['keperluan']) ?></td><td><?= clean(ucfirst($row['status'])) ?></td><td><?= clean($row['catatan_approval'] ?? '-') ?></td>
        <?php if ($can_manage): ?><td><?php if ($row['status'] === 'menunggu'): ?><form method="post" action="<?= $atk_url ?>?tab=permintaan" class="atk-approval-form"><input type="hidden" name="csrf" value="<?= clean($csrf) ?>"><input type="hidden" name="action" value="putuskan_permintaan"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input class="form-control" name="catatan_approval" placeholder="Catatan (opsional)"><div class="atk-approval-actions"><button class="btn btn-success btn-sm" name="keputusan" value="disetujui">Setujui</button><button class="btn btn-danger btn-sm" name="keputusan" value="ditolak">Tolak</button></div></form><?php else: ?><?= clean($row['approver'] ?? '-') ?><?php endif; ?></td><?php endif; ?></tr>
      <?php endforeach; ?>
      <?php if (!$request_rows): ?><tr><td colspan="<?= $can_manage ? 9 : 7 ?>" style="text-align:center;padding:24px;">Belum ada permintaan ATK.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  <?php elseif ($tab === 'pengajuan'): ?>
    <div class="panel" style="margin-bottom:14px;">
      <div class="panel-hd"><h5>Buat Pengajuan Pembelian</h5></div>
      <div class="panel-bd">
        <form method="post" action="<?= $atk_url ?>?tab=pengajuan" class="atk-form-grid">
          <input type="hidden" name="csrf" value="<?= clean($csrf) ?>"><input type="hidden" name="action" value="kirim_pengajuan">
          <label>Jenis Barang<select class="form-control" name="barang_id" id="atk-barang-beli"><option value="0">Barang baru</option><?php foreach ($active_items as $item): ?><option value="<?= (int)$item['id'] ?>"><?= clean($item['nama']) ?> (<?= clean($item['satuan']) ?>)</option><?php endforeach; ?></select></label>
          <label>Nama barang baru<input class="form-control" name="nama_barang" id="atk-nama-beli"></label>
          <label>Satuan barang baru<input class="form-control" name="satuan" id="atk-satuan-beli" placeholder="pcs, rim, pak"></label>
          <label>Jumlah<input class="form-control" type="number" name="jumlah" min="1" required></label>
          <label class="atk-form-wide">Alasan pengajuan<textarea class="form-control" name="alasan" required rows="3" placeholder="Jelaskan alasan dan kebutuhan pembelian"></textarea></label>
          <div class="atk-form-actions"><button class="btn btn-success" type="submit"><i class="fa fa-paper-plane"></i> Kirim Pengajuan</button></div>
        </form>
      </div>
    </div>
    <div class="panel"><div class="panel-hd"><h5><?= $can_manage ? 'Daftar Pengajuan Pembelian' : 'Riwayat Pengajuan Saya' ?></h5></div>
      <div class="panel-bd np tbl-wrap"><table><thead><tr><th>No.</th><th>Tanggal</th><?php if ($can_manage): ?><th>Pemohon</th><?php endif; ?><th>Barang</th><th>Jumlah</th><th>Alasan</th><th>Status</th><th>Catatan</th><?php if ($can_manage): ?><th>Persetujuan</th><?php endif; ?></tr></thead><tbody>
      <?php foreach ($proposal_rows as $row): ?>
        <tr><td><?= clean($row['nomor']) ?></td><td><?= clean(formatTanggal($row['created_at'], true)) ?></td><?php if ($can_manage): ?><td><?= clean($row['pemohon'] ?? '-') ?></td><?php endif; ?><td><?= clean($row['nama_barang']) ?></td><td><?= (int)$row['jumlah'] ?> <?= clean($row['satuan']) ?></td><td><?= clean($row['alasan']) ?></td><td><?= clean(ucfirst($row['status'])) ?></td><td><?= clean($row['catatan_approval'] ?? '-') ?></td>
        <?php if ($can_manage): ?><td><?php if ($row['status'] === 'menunggu'): ?><form method="post" action="<?= $atk_url ?>?tab=pengajuan" class="atk-approval-form"><input type="hidden" name="csrf" value="<?= clean($csrf) ?>"><input type="hidden" name="action" value="putuskan_pengajuan"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input class="form-control" name="catatan_approval" placeholder="Catatan (opsional)"><div class="atk-approval-actions"><button class="btn btn-success btn-sm" name="keputusan" value="disetujui">Setujui</button><button class="btn btn-danger btn-sm" name="keputusan" value="ditolak">Tolak</button></div></form><?php else: ?><?= clean($row['approver'] ?? '-') ?><?php endif; ?></td><?php endif; ?></tr>
      <?php endforeach; ?>
      <?php if (!$proposal_rows): ?><tr><td colspan="<?= $can_manage ? 9 : 7 ?>" style="text-align:center;padding:24px;">Belum ada pengajuan ATK.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  <?php else: ?>
    <div class="panel" style="margin-bottom:14px;"><div class="panel-hd"><h5>Laporan Permintaan ATK</h5></div>
      <div class="panel-bd np tbl-wrap"><table><thead><tr><th>No.</th><th>Tanggal</th><?php if ($can_manage): ?><th>Pemohon</th><?php endif; ?><th>Barang</th><th>Jumlah</th><th>Keperluan</th><th>Status</th><th>Diproses oleh</th></tr></thead><tbody>
      <?php foreach ($request_rows as $row): ?><tr><td><?= clean($row['nomor']) ?></td><td><?= clean(formatTanggal($row['created_at'], true)) ?></td><?php if ($can_manage): ?><td><?= clean($row['pemohon'] ?? '-') ?></td><?php endif; ?><td><?= clean($row['barang_nama'] ?? 'Barang dihapus') ?></td><td><?= (int)$row['jumlah'] ?> <?= clean($row['satuan'] ?? '') ?></td><td><?= clean($row['keperluan']) ?></td><td><?= clean(ucfirst($row['status'])) ?></td><td><?= clean($row['approver'] ?? '-') ?></td></tr><?php endforeach; ?>
      <?php if (!$request_rows): ?><tr><td colspan="<?= $can_manage ? 8 : 7 ?>" style="text-align:center;padding:24px;">Tidak ada data permintaan.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <div class="panel" style="margin-bottom:14px;"><div class="panel-hd"><h5>Laporan Pengajuan Pembelian ATK</h5></div>
      <div class="panel-bd np tbl-wrap"><table><thead><tr><th>No.</th><th>Tanggal</th><?php if ($can_manage): ?><th>Pemohon</th><?php endif; ?><th>Barang</th><th>Jumlah</th><th>Alasan</th><th>Status</th><th>Diproses oleh</th></tr></thead><tbody>
      <?php foreach ($proposal_rows as $row): ?><tr><td><?= clean($row['nomor']) ?></td><td><?= clean(formatTanggal($row['created_at'], true)) ?></td><?php if ($can_manage): ?><td><?= clean($row['pemohon'] ?? '-') ?></td><?php endif; ?><td><?= clean($row['nama_barang']) ?></td><td><?= (int)$row['jumlah'] ?> <?= clean($row['satuan']) ?></td><td><?= clean($row['alasan']) ?></td><td><?= clean(ucfirst($row['status'])) ?></td><td><?= clean($row['approver'] ?? '-') ?></td></tr><?php endforeach; ?>
      <?php if (!$proposal_rows): ?><tr><td colspan="<?= $can_manage ? 8 : 7 ?>" style="text-align:center;padding:24px;">Tidak ada data pengajuan.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <div class="panel"><div class="panel-hd"><h5>Riwayat Pergerakan Stok <?= $can_manage ? '' : 'Terbaru' ?></h5></div>
      <div class="panel-bd np tbl-wrap"><table><thead><tr><th>Tanggal</th><th>Kode</th><th>Barang</th><th>Jenis</th><th>Jumlah</th><th>Supplier</th><th>Catatan</th><?php if ($can_manage): ?><th>Petugas</th><?php endif; ?></tr></thead><tbody>
      <?php foreach (array_reverse($movement_rows) as $row): ?><tr><td><?= clean(formatTanggal($row['created_at'], true)) ?></td><td><?= clean($row['kode']) ?></td><td><?= clean($row['nama']) ?></td><td><?= $row['jenis'] === 'masuk' ? 'Masuk' : ($row['jenis'] === 'keluar' ? 'Keluar' : 'Penyesuaian') ?></td><td><?= (int)$row['jumlah'] ?> <?= clean($row['satuan']) ?></td><td><?= clean($row['supplier_nama'] ?? '-') ?></td><td><?= clean($row['catatan']) ?></td><?php if ($can_manage): ?><td><?= clean($row['petugas'] ?? '-') ?></td><?php endif; ?></tr><?php endforeach; ?>
      <?php if (!$movement_rows): ?><tr><td colspan="<?= $can_manage ? 8 : 7 ?>" style="text-align:center;padding:24px;">Belum ada riwayat stok.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
  <?php endif; ?>
</div>

<script>
(() => {
  const choice = document.getElementById('atk-barang-beli');
  const name = document.getElementById('atk-nama-beli');
  const unit = document.getElementById('atk-satuan-beli');
  if (!choice || !name || !unit) return;
  const update = () => {
    const isNew = choice.value === '0';
    name.required = isNew;
    unit.required = isNew;
    name.disabled = !isNew;
    unit.disabled = !isNew;
  };
  choice.addEventListener('change', update);
  update();
})();
</script>

<?php include '../includes/footer.php'; ?>
