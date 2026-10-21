<?php
// pages/bagian.php — Master Bagian / Divisi / Departemen
session_start();
require_once '../config.php';
requireLogin();
if (!hasExactRole('admin')) { setFlash('danger', 'Akses ditolak. Hanya Admin.'); redirect(APP_URL . '/dashboard.php'); }
$page_title  = 'Master Bagian';
$active_menu = 'bagian';

function renumberBagian(PDO $pdo): void {
    $rows = $pdo->query("SELECT id, urutan FROM bagian ORDER BY urutan ASC, nama ASC, id ASC")->fetchAll();
    $upd  = $pdo->prepare("UPDATE bagian SET urutan=? WHERE id=?");
    foreach ($rows as $i => $r) {
        $n = $i + 1;
        if ((int)$r['urutan'] !== $n) $upd->execute([$n, $r['id']]);
    }
}

// ── POST ACTIONS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['action'] ?? '';
    $nama   = trim($_POST['nama']      ?? '');
    $kode   = strtoupper(trim($_POST['kode'] ?? ''));
    $desc   = trim($_POST['deskripsi'] ?? '');
    $lokasi = trim($_POST['lokasi']    ?? '');
    $status = $_POST['status'] ?? 'aktif';

    if ($act === 'tambah') {
        if (!$nama) { setFlash('danger', 'Nama bagian wajib diisi.'); }
        else {
            $chk = $pdo->prepare("SELECT id FROM bagian WHERE nama=?");
            $chk->execute([$nama]);
            if ($chk->fetch()) { setFlash('warning', "Bagian '<strong>" . clean($nama) . "</strong>' sudah ada."); }
            else {
                renumberBagian($pdo);
                $next = (int)$pdo->query("SELECT COUNT(*) FROM bagian")->fetchColumn() + 1;
                $pdo->prepare("INSERT INTO bagian (nama,kode,deskripsi,lokasi,urutan,status) VALUES (?,?,?,?,?,?)")
                    ->execute([$nama, $kode ?: null, $desc ?: null, $lokasi ?: null, $next, $status]);
                setFlash('success', "Bagian <strong>" . clean($nama) . "</strong> berhasil ditambahkan.");
            }
        }
    } elseif ($act === 'edit') {
        $id = (int)$_POST['id'];
        if (!$nama || !$id) { setFlash('danger', 'Data tidak lengkap.'); }
        else {
            $pdo->prepare("UPDATE bagian SET nama=?,kode=?,deskripsi=?,lokasi=?,status=? WHERE id=?")
                ->execute([$nama, $kode ?: null, $desc ?: null, $lokasi ?: null, $status, $id]);
            setFlash('success', "Bagian <strong>" . clean($nama) . "</strong> berhasil diperbarui.");
        }
    } elseif ($act === 'hapus') {
        $id = (int)$_POST['id'];
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE divisi=(SELECT nama FROM bagian WHERE id=?)");
        $chk->execute([$id]);
        if ($chk->fetchColumn() > 0) {
            setFlash('warning', 'Bagian tidak dapat dihapus karena masih digunakan oleh pengguna.');
        } else {
            $pdo->prepare("DELETE FROM bagian WHERE id=?")->execute([$id]);
            setFlash('success', 'Bagian berhasil dihapus.');
        }
    } elseif ($act === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE bagian SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
        setFlash('success', 'Status bagian diubah.');
    }

    // Form diposting ke URL yang sama, jadi filter & halaman tetap terjaga setelah aksi
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    redirect(APP_URL . '/pages/bagian.php' . ($qs ? '?' . $qs : ''));
}
renumberBagian($pdo);

// ── FILTER & PAGINATION ──
$q       = trim($_GET['q'] ?? '');
$fStatus = $_GET['status'] ?? '';
if (!in_array($fStatus, ['aktif', 'nonaktif'], true)) $fStatus = '';

$allowedPer = [10, 25, 50, 0];                 // 0 = tampilkan semua
$per = isset($_GET['per']) ? (int)$_GET['per'] : 10;
if (!in_array($per, $allowedPer, true)) $per = 10;

$where  = [];
$params = [];
if ($q !== '') {
    $where[] = "(b.nama LIKE ? OR b.kode LIKE ? OR b.lokasi LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($fStatus !== '') {
    $where[]  = "b.status = ?";
    $params[] = $fStatus;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stc = $pdo->prepare("SELECT COUNT(*) FROM bagian b $sqlWhere");
$stc->execute($params);
$total_filtered = (int)$stc->fetchColumn();

$pages = $per > 0 ? max(1, (int)ceil($total_filtered / $per)) : 1;
$page  = max(1, min((int)($_GET['p'] ?? 1), $pages));
$limitSql = $per > 0 ? 'LIMIT ' . (int)$per . ' OFFSET ' . (int)(($page - 1) * $per) : '';
$offset   = $per > 0 ? ($page - 1) * $per : 0;

$st = $pdo->prepare("
    SELECT b.*, COUNT(u.id) AS jml_user
    FROM bagian b
    LEFT JOIN users u ON u.divisi = b.nama AND u.status='aktif'
    $sqlWhere
    GROUP BY b.id
    ORDER BY b.urutan ASC, b.nama ASC
    $limitSql
");
$st->execute($params);
$list = $st->fetchAll();

// ── STATISTIK (selalu dari seluruh data, bukan hasil filter) ──
$total    = (int)$pdo->query("SELECT COUNT(*) FROM bagian")->fetchColumn();
$aktif    = (int)$pdo->query("SELECT COUNT(*) FROM bagian WHERE status='aktif'")->fetchColumn();
$nonaktif = $total - $aktif;
$total_usr = (int)$pdo->query("SELECT COUNT(*) FROM users u INNER JOIN bagian b ON u.divisi=b.nama WHERE u.status='aktif'")->fetchColumn();

$from = $total_filtered ? $offset + 1 : 0;
$to   = $total_filtered ? $offset + count($list) : 0;
$isFiltered = ($q !== '' || $fStatus !== '' || $per !== 10);

// Helper URL pagination (mempertahankan filter)
function bagianUrl(array $over = []): string {
    global $q, $fStatus, $per, $page;
    $p = array_merge(['q' => $q, 'status' => $fStatus, 'per' => $per, 'p' => $page], $over);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    if (isset($p['p']) && (int)$p['p'] === 1) unset($p['p']);
    if (isset($p['per']) && (int)$p['per'] === 10) unset($p['per']);
    return APP_URL . '/pages/bagian.php' . ($p ? '?' . http_build_query($p) : '');
}

// Palette warna per baris
$palettes = [
    ['#dbeafe','#1d4ed8'],
    ['#d1fae5','#065f46'],
    ['#fef3c7','#d97706'],
    ['#ede9fe','#5b21b6'],
    ['#fce7f3','#9d174d'],
    ['#f0fdf4','#15803d'],
    ['#fff7ed','#c2410c'],
    ['#f0f9ff','#0369a1'],
];

include '../includes/header.php';
?>

<style>
/* ══ Modal ══ */
#bagianModal {
    visibility: hidden !important; opacity: 0 !important;
    position: fixed !important; top: 0 !important; left: 0 !important;
    width: 100% !important; height: 100% !important;
    background: rgba(15,23,42,.55) !important;
    z-index: 99999 !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    transition: opacity .22s ease, visibility .22s ease !important;
    pointer-events: none !important;
}
#bagianModal.bm-open { visibility: visible !important; opacity: 1 !important; pointer-events: auto !important; }
#bagianModal .bm-box {
    background: #fff !important; border-radius: 14px !important;
    width: 100% !important; max-width: 520px !important; margin: 16px !important;
    box-shadow: 0 16px 56px rgba(0,0,0,.25) !important; overflow: hidden !important;
    transform: translateY(22px) scale(.98); transition: transform .22s ease !important;
    max-height: 90vh !important; display: flex !important; flex-direction: column !important;
}
#bagianModal.bm-open .bm-box { transform: translateY(0) scale(1) !important; }
#bagianModal .bm-hd {
    display: flex !important; align-items: center !important; justify-content: space-between !important;
    padding: 16px 22px !important; border-bottom: 1px solid #eef0f3 !important;
    background: #fafbfc !important; flex-shrink: 0 !important;
}
#bagianModal .bm-hd h5 {
    margin: 0 !important; font-size: 15px !important; font-weight: 700 !important; color: #1e293b !important;
    display: flex !important; align-items: center !important; gap: 8px !important;
}
#bagianModal .bm-close {
    background: none !important; border: none !important; font-size: 22px !important; line-height: 1 !important;
    color: #94a3b8 !important; cursor: pointer !important; padding: 0 !important;
    width: 32px !important; height: 32px !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    border-radius: 6px !important; transition: background .15s, color .15s !important;
}
#bagianModal .bm-close:hover { background: #fee2e2 !important; color: #dc2626 !important; }
#bagianModal .bm-body { padding: 22px !important; overflow-y: auto !important; flex: 1 !important; }
#bagianModal .bm-foot {
    padding: 14px 22px !important; border-top: 1px solid #eef0f3 !important;
    display: flex !important; gap: 8px !important; justify-content: flex-end !important;
    background: #fafbfc !important; flex-shrink: 0 !important;
}
.bm-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 480px) { .bm-grid2 { grid-template-columns: 1fr; } }
.tips-box {
    margin-top: 6px; padding: 11px 14px; background: #f8fafc;
    border: 1px dashed #e2e8f0; border-radius: 8px;
    font-size: 11px; color: #64748b; line-height: 1.9;
}
.form-hint { font-size: 11px; color: #9ca3af; margin-top: 3px; }

/* ══ Stat cards ══ */
.bg-stats {
    display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px; margin-bottom: 16px;
}
@media (max-width: 900px) { .bg-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 480px) { .bg-stats { grid-template-columns: 1fr; } }
.bg-stat {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    padding: 14px 18px; display: flex; align-items: center; gap: 14px;
}
.bg-stat .ico {
    width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.bg-stat .val { font-size: 24px; font-weight: 800; color: #111827; line-height: 1; }
.bg-stat .lbl { font-size: 11px; color: #9ca3af; margin-top: 4px; }

/* ══ Toolbar filter ══ */
.bg-toolbar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
    padding: 14px 18px; border-bottom: 1px solid #f0f1f3; background: #fafbfc;
}
.bg-search { position: relative; flex: 1 1 240px; min-width: 200px; }
.bg-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
.bg-search input { padding-left: 34px !important; width: 100%; }
.bg-toolbar select.form-control { width: auto; min-width: 130px; }
.bg-toolbar .bg-spacer { flex: 0 0 auto; margin-left: auto; }
.bg-reset { font-size: 12px; color: #dc2626; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
.bg-reset:hover { text-decoration: underline; }

/* ══ Tabel ══ */
.bg-table { width: 100%; border-collapse: collapse; }
.bg-table thead th {
    background: #f8fafc; font-size: 11px; font-weight: 700; color: #64748b;
    text-transform: uppercase; letter-spacing: .4px; padding: 11px 14px;
    border-bottom: 1px solid #e5e7eb; white-space: nowrap;
}
.bg-table tbody td { padding: 12px 14px; vertical-align: middle; border-bottom: 1px solid #f1f3f5; }
.bg-table tbody tr:last-child td { border-bottom: none; }
.bg-table tbody tr:hover { background: #fafcff; }
.bg-act { display: inline-flex; gap: 4px; align-items: center; justify-content: center; }
.bg-act form { margin: 0; display: inline-flex; }

/* ══ Footer pagination ══ */
.bg-foot {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
    padding: 12px 18px; border-top: 1px solid #f0f1f3; background: #fafbfc;
    font-size: 12px; color: #64748b;
}
.bg-pager { display: flex; gap: 4px; flex-wrap: wrap; }
.bg-pager a, .bg-pager span {
    min-width: 30px; height: 30px; padding: 0 8px; display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid #e5e7eb; border-radius: 7px; background: #fff;
    font-size: 12px; font-weight: 600; color: #475569; text-decoration: none;
}
.bg-pager a:hover { background: #f1f5f9; }
.bg-pager .cur { background: #2563eb; border-color: #2563eb; color: #fff; }
.bg-pager .dis { opacity: .4; pointer-events: none; }
</style>

<div class="page-header">
    <h4><i class="fa fa-building text-primary"></i> &nbsp;Master Bagian / Divisi</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
        <span class="sep">/</span>
        <span class="cur">Master Bagian</span>
    </div>
</div>

<div class="content">
    <?= showFlash() ?>

    <!-- ── Stats Row ── -->
    <div class="bg-stats">
        <?php foreach ([
            [$total,     'Total Bagian',   'fa-building',     '#f0fdf4', '#00c896'],
            [$aktif,     'Aktif',          'fa-circle-check', '#d1fae5', '#065f46'],
            [$nonaktif,  'Nonaktif',       'fa-circle-xmark', '#f3f4f6', '#6b7280'],
            [$total_usr, 'Total Pengguna', 'fa-users',        '#dbeafe', '#1d4ed8'],
        ] as [$val, $lbl, $ico, $bg, $clr]): ?>
        <div class="bg-stat">
            <div class="ico" style="background:<?= $bg ?>;"><i class="fa <?= $ico ?>" style="color:<?= $clr ?>;"></i></div>
            <div>
                <div class="val"><?= $val ?></div>
                <div class="lbl"><?= $lbl ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Panel Tabel ── -->
    <div class="panel">
        <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <h5 style="margin:0;">
                <i class="fa fa-list text-primary"></i> &nbsp;Daftar Bagian
                <span style="color:#aaa;font-weight:400;">(<?= $total_filtered ?>)</span>
            </h5>
            <button class="btn btn-primary btn-sm" onclick="bmOpen()">
                <i class="fa fa-plus"></i> Tambah Bagian
            </button>
        </div>

        <!-- Toolbar filter -->
        <form method="GET" class="bg-toolbar" id="bg-filter">
            <div class="bg-search">
                <i class="fa fa-search"></i>
                <input type="text" name="q" class="form-control" value="<?= clean($q) ?>"
                       placeholder="Cari nama, kode, atau lokasi…">
            </div>

            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <option value="aktif"    <?= $fStatus === 'aktif'    ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= $fStatus === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
            </select>

            <select name="per" class="form-control" onchange="this.form.submit()" title="Jumlah data per halaman">
                <option value="10" <?= $per === 10 ? 'selected' : '' ?>>10 data</option>
                <option value="25" <?= $per === 25 ? 'selected' : '' ?>>25 data</option>
                <option value="50" <?= $per === 50 ? 'selected' : '' ?>>50 data</option>
                <option value="0"  <?= $per === 0  ? 'selected' : '' ?>>Semua</option>
            </select>

            <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-filter"></i> Terapkan</button>

            <?php if ($isFiltered): ?>
            <a href="<?= APP_URL ?>/pages/bagian.php" class="bg-reset"><i class="fa fa-rotate-left"></i> Reset</a>
            <?php endif; ?>
        </form>

        <div class="panel-bd np tbl-wrap" style="overflow-x:auto;">
            <table class="bg-table">
                <thead>
                    <tr>
                        <th style="width:70px;text-align:center;">No</th>
                        <th style="text-align:left;">Nama Bagian</th>
                        <th style="width:100px;text-align:center;">Kode</th>
                        <th style="width:180px;text-align:left;">Lokasi</th>
                        <th style="width:100px;text-align:center;">Pengguna</th>
                        <th style="width:110px;text-align:center;">Status</th>
                        <th style="width:130px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;color:#aaa;padding:44px 20px;">
                            <div style="width:52px;height:52px;background:#f1f5f9;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                                <i class="fa fa-building" style="font-size:22px;color:#cbd5e1;"></i>
                            </div>
                            <?php if ($isFiltered): ?>
                            <div style="font-weight:600;color:#64748b;margin-bottom:4px;">Tidak ada bagian yang cocok</div>
                            <small style="color:#94a3b8;">Coba ubah kata kunci atau <a href="<?= APP_URL ?>/pages/bagian.php">reset filter</a>.</small>
                            <?php else: ?>
                            <div style="font-weight:600;color:#64748b;margin-bottom:4px;">Belum ada data bagian</div>
                            <small style="color:#94a3b8;">Klik <strong>+ Tambah Bagian</strong> untuk memulai.</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: foreach ($list as $i => $b):
                        [$pal_bg, $pal_tc] = $palettes[($offset + $i) % count($palettes)];
                        $is_aktif = $b['status'] === 'aktif';
                        $in_use   = (int)$b['jml_user'] > 0;
                    ?>
                    <tr>
                        <!-- No urut -->
                        <td style="text-align:center;">
                            <span style="font-size:13px;font-weight:700;color:#cbd5e1;"><?= (int)$b['urutan'] ?></span>
                        </td>

                        <!-- Nama + deskripsi -->
                        <td>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;background:<?= $pal_bg ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fa fa-building" style="color:<?= $pal_tc ?>;font-size:14px;"></i>
                                </div>
                                <div style="min-width:0;">
                                    <div style="font-weight:600;font-size:13px;color:#111827;"><?= clean($b['nama']) ?></div>
                                    <?php if ($b['deskripsi']): ?>
                                    <div style="font-size:11px;color:#9ca3af;margin-top:2px;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                         title="<?= clean($b['deskripsi']) ?>"><?= clean($b['deskripsi']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <!-- Kode -->
                        <td style="text-align:center;">
                            <?php if ($b['kode']): ?>
                            <span style="font-family:monospace;font-size:11px;font-weight:700;background:#f0fdf4;border:1px solid #bbf7d0;
                                         padding:2px 8px;border-radius:4px;color:#16a34a;letter-spacing:.5px;"><?= clean($b['kode']) ?></span>
                            <?php else: ?>
                            <span style="color:#d1d5db;">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Lokasi -->
                        <td>
                            <?php if ($b['lokasi']): ?>
                            <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#64748b;">
                                <i class="fa fa-location-dot" style="color:#94a3b8;font-size:10px;"></i>
                                <?= clean($b['lokasi']) ?>
                            </div>
                            <?php else: ?>
                            <span style="color:#d1d5db;">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Pengguna -->
                        <td style="text-align:center;">
                            <?php if ($in_use): ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;color:#00c896;">
                                <i class="fa fa-users" style="font-size:10px;"></i> <?= (int)$b['jml_user'] ?>
                            </span>
                            <?php else: ?>
                            <span style="color:#cbd5e1;font-size:12px;">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td style="text-align:center;">
                            <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;
                                         padding:3px 11px;border-radius:20px;
                                         background:<?= $is_aktif ? '#d1fae5' : '#f3f4f6' ?>;
                                         color:<?= $is_aktif ? '#065f46' : '#6b7280' ?>;">
                                <span style="width:5px;height:5px;border-radius:50%;background:<?= $is_aktif ? '#22c55e' : '#9ca3af' ?>;"></span>
                                <?= $is_aktif ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>

                        <!-- Aksi -->
                        <td style="text-align:center;">
                            <div class="bg-act">
                                <button class="btn btn-warning btn-sm" title="Edit bagian"
                                    onclick='bmOpen(<?= json_encode([
                                        "id"        => (int)$b["id"],
                                        "nama"      => $b["nama"],
                                        "kode"      => $b["kode"] ?? "",
                                        "deskripsi" => $b["deskripsi"] ?? "",
                                        "lokasi"    => $b["lokasi"] ?? "",
                                        "urutan"    => (int)$b["urutan"],
                                        "status"    => $b["status"],
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?>)'>
                                    <i class="fa fa-edit"></i>
                                </button>

                                <form method="POST">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                    <button type="submit"
                                        class="btn btn-sm <?= $is_aktif ? 'btn-default' : 'btn-success' ?>"
                                        title="<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                        onclick="return confirm('<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?> bagian ini?')">
                                        <i class="fa <?= $is_aktif ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                    </button>
                                </form>

                                <?php if (!$in_use): ?>
                                <form method="POST" onsubmit="return confirm('Hapus bagian <?= addslashes(clean($b['nama'])) ?>?')">
                                    <input type="hidden" name="action" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus bagian">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-sm btn-default" disabled
                                    title="Tidak bisa dihapus — masih ada <?= (int)$b['jml_user'] ?> pengguna">
                                    <i class="fa fa-lock" style="color:#d1d5db;"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer: info + pagination -->
        <?php if ($total_filtered > 0): ?>
        <div class="bg-foot">
            <div>Menampilkan <strong><?= $from ?>–<?= $to ?></strong> dari <strong><?= $total_filtered ?></strong> bagian</div>

            <?php if ($pages > 1): ?>
            <div class="bg-pager">
                <a href="<?= bagianUrl(['p' => $page - 1]) ?>" class="<?= $page <= 1 ? 'dis' : '' ?>"><i class="fa fa-chevron-left"></i></a>
                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);
                if ($start > 1): ?>
                    <a href="<?= bagianUrl(['p' => 1]) ?>">1</a>
                    <?php if ($start > 2): ?><span style="border:none;background:none;">…</span><?php endif; ?>
                <?php endif;
                for ($n = $start; $n <= $end; $n++): ?>
                    <?php if ($n === $page): ?><span class="cur"><?= $n ?></span>
                    <?php else: ?><a href="<?= bagianUrl(['p' => $n]) ?>"><?= $n ?></a><?php endif; ?>
                <?php endfor;
                if ($end < $pages): ?>
                    <?php if ($end < $pages - 1): ?><span style="border:none;background:none;">…</span><?php endif; ?>
                    <a href="<?= bagianUrl(['p' => $pages]) ?>"><?= $pages ?></a>
                <?php endif; ?>
                <a href="<?= bagianUrl(['p' => $page + 1]) ?>" class="<?= $page >= $pages ? 'dis' : '' ?>"><i class="fa fa-chevron-right"></i></a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.content -->


<!-- ══════════════════════════════════════════════
     MODAL TAMBAH / EDIT BAGIAN
══════════════════════════════════════════════ -->
<div id="bagianModal">
    <div class="bm-box">
        <div class="bm-hd">
            <h5>
                <i class="fa fa-building" id="bm-ico" style="color:var(--primary,#2563eb);"></i>
                <span id="bm-title">Tambah Bagian</span>
            </h5>
            <button type="button" class="bm-close" onclick="bmClose()" title="Tutup">&times;</button>
        </div>

        <div class="bm-body">
            <form method="POST" id="bm-form">
                <input type="hidden" name="action" value="tambah" id="f-action">
                <input type="hidden" name="id"     value=""        id="f-id">

                <!-- Nama -->
                <div class="form-group">
                    <label>Nama Bagian <span class="req">*</span></label>
                    <input type="text" name="nama" id="f-nama" class="form-control" required
                           placeholder="Contoh: Keuangan, Marketing, HRD…">
                </div>

                <!-- Kode & Status (sejajar) -->
                <div class="bm-grid2">
                    <div class="form-group">
                        <label>Kode Singkat <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
                        <input type="text" name="kode" id="f-kode" class="form-control"
                               placeholder="FIN / MKT / HRD" maxlength="10"
                               style="text-transform:uppercase;font-family:monospace;font-weight:700;letter-spacing:1px;">
                        <div class="form-hint">Maks. 10 huruf.</div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="f-status" class="form-control">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                        <div class="form-hint">Nonaktif = disembunyikan dari dropdown.</div>
                    </div>
                </div>

                <!-- Lokasi -->
                <div class="form-group">
                    <label>Lokasi / Ruangan Utama <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
                    <div style="position:relative;">
                        <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;">
                            <i class="fa fa-location-dot"></i>
                        </span>
                        <input type="text" name="lokasi" id="f-lokasi" class="form-control"
                               placeholder="Contoh: Lt.2, Gedung A, Ruang Marketing"
                               style="padding-left:30px;">
                    </div>
                    <div class="form-hint">Ditampilkan saat pengguna memilih bagian ini.</div>
                </div>

                <!-- Deskripsi -->
                <div class="form-group">
                    <label>Deskripsi <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
                    <textarea name="deskripsi" id="f-desc" class="form-control" rows="2"
                              placeholder="Deskripsi singkat fungsi bagian ini…" style="resize:vertical;"></textarea>
                </div>

                <!-- Tips -->
                <div class="tips-box">
                    <i class="fa fa-lightbulb" style="color:#f59e0b;"></i>
                    <strong>Tips:</strong><br>
                    &bull; Bagian aktif muncul di dropdown Register, Edit Profil, dan Tambah Pengguna.<br>
                    &bull; Bagian yang sudah dipakai pengguna tidak bisa dihapus.<br>
                    &bull; Nonaktifkan bagian agar tidak tampil tanpa harus menghapus.
                </div>
            </form>
        </div>

        <div class="bm-foot">
            <button type="button" class="btn btn-default" onclick="bmClose()">
                <i class="fa fa-times"></i> Batal
            </button>
            <button type="submit" form="bm-form" class="btn btn-primary" id="bm-btn">
                <i class="fa fa-save"></i> Simpan
            </button>
        </div>
    </div>
</div>

<script>
var _bm = document.getElementById('bagianModal');

/* ── Buka modal ── */
function bmOpen(b) {
    if (b) {
        document.getElementById('bm-title').textContent = 'Edit: ' + b.nama;
        document.getElementById('bm-ico').style.color   = 'var(--orange,#f59e0b)';
        document.getElementById('f-action').value = 'edit';
        document.getElementById('f-id').value     = b.id;
        document.getElementById('f-nama').value   = b.nama;
        document.getElementById('f-kode').value   = b.kode      || '';
        document.getElementById('f-desc').value   = b.deskripsi || '';
        document.getElementById('f-lokasi').value = b.lokasi    || '';
        document.getElementById('f-status').value = b.status;
        document.getElementById('bm-btn').innerHTML = '<i class="fa fa-save"></i> Update';
    } else {
        document.getElementById('bm-title').textContent = 'Tambah Bagian';
        document.getElementById('bm-ico').style.color   = 'var(--primary,#2563eb)';
        document.getElementById('f-action').value = 'tambah';
        document.getElementById('f-id').value     = '';
        document.getElementById('f-nama').value   = '';
        document.getElementById('f-kode').value   = '';
        document.getElementById('f-desc').value   = '';
        document.getElementById('f-lokasi').value = '';
        document.getElementById('f-status').value = 'aktif';
        document.getElementById('bm-btn').innerHTML = '<i class="fa fa-save"></i> Simpan';
    }
    _bm.classList.add('bm-open');
    setTimeout(function(){ document.getElementById('f-nama').focus(); }, 80);
}

/* ── Tutup modal ── */
function bmClose() { _bm.classList.remove('bm-open'); }
_bm.addEventListener('click', function(e){ if (e.target === _bm) bmClose(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') bmClose(); });

/* ── Auto uppercase kode ── */
document.getElementById('f-kode').addEventListener('input', function(){
    this.value = this.value.toUpperCase();
});
</script>

<?php include '../includes/footer.php'; ?>