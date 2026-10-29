<?php
// pages/kategori_ipsrs.php
session_start(); require_once '../config.php'; requireLogin();
if (!hasRole(['admin','teknisi'])) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title = 'Kategori IPSRS'; $active_menu = 'kategori_ipsrs';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['action']         ?? '';
    $nama  = trim($_POST['nama']      ?? '');
    $desc  = trim($_POST['deskripsi'] ?? '');
    $icon  = $_POST['icon']           ?? 'fa-toolbox';
    $jenis = 'Non-Medis';
    $sla   = (int)($_POST['sla_jam']        ?? 24);
    $sla_r = (int)($_POST['sla_respon_jam'] ?? 4);

    if ($act === 'tambah' && $nama) {
        $pdo->prepare("INSERT INTO kategori_ipsrs (nama, deskripsi, icon, jenis, sla_jam, sla_respon_jam) VALUES (?,?,?,?,?,?)")
            ->execute([$nama, $desc, $icon, $jenis, $sla, $sla_r]);
        setFlash('success', 'Kategori IPSRS berhasil ditambahkan.');
    } elseif ($act === 'edit' && $nama) {
        $pdo->prepare("UPDATE kategori_ipsrs SET nama=?, deskripsi=?, icon=?, jenis=?, sla_jam=?, sla_respon_jam=? WHERE id=? AND jenis='Non-Medis'")
            ->execute([$nama, $desc, $icon, $jenis, $sla, $sla_r, (int)$_POST['id']]);
        setFlash('success', 'Kategori IPSRS berhasil diperbarui.');
    } elseif ($act === 'hapus') {
        $cek = 0;
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM tiket_ipsrs WHERE kategori_id=? AND jenis_tiket='Non-Medis'");
            $st->execute([(int)$_POST['id']]); $cek = (int)$st->fetchColumn();
        } catch (Exception $e) { $cek = 0; }
        if ($cek > 0) {
            setFlash('warning', 'Kategori tidak dapat dihapus, masih ada tiket yang menggunakan kategori ini.');
        } else {
            $pdo->prepare("DELETE FROM kategori_ipsrs WHERE id=? AND jenis='Non-Medis'")->execute([(int)$_POST['id']]);
            setFlash('success', 'Kategori IPSRS berhasil dihapus.');
        }
    }
    redirect(APP_URL . '/pages/kategori_ipsrs.php');
}

// Query aman — fallback jika tabel tiket_ipsrs belum ada
try {
    $list = $pdo->query("
        SELECT k.*, COUNT(t.id) AS total
        FROM kategori_ipsrs k
        LEFT JOIN tiket_ipsrs t ON t.kategori_id = k.id
        WHERE k.jenis = 'Non-Medis'
        GROUP BY k.id ORDER BY k.nama
    ")->fetchAll();
} catch (Exception $e) {
    $list = $pdo->query("SELECT k.*, 0 AS total FROM kategori_ipsrs k WHERE k.jenis = 'Non-Medis' ORDER BY k.nama")->fetchAll();
}

$icons_non_medis = [
    'fa-toolbox'           => 'Umum / Servis',
    'fa-bolt'              => 'Listrik',
    'fa-plug-circle-bolt'  => 'Generator / Panel',
    'fa-wind'              => 'HVAC / AC',
    'fa-droplet'           => 'Sanitasi / Plumbing',
    'fa-fire-extinguisher' => 'Proteksi Kebakaran',
    'fa-utensils'          => 'Dapur / Gizi',
    'fa-shirt'             => 'Laundry',
    'fa-broom'             => 'Kebersihan',
    'fa-car'               => 'Kendaraan / Ambulans',
    'fa-dolly'             => 'Alat Angkat',
    'fa-gear'              => 'Mekanikal',
    'fa-camera'            => 'Keamanan / CCTV',
    'fa-tag'               => 'Lainnya',
];

// Stats
$total_kat    = count($list);
$total_tiket  = array_sum(array_column($list, 'total'));
$avg_sla      = $total_kat > 0 ? round(array_sum(array_column($list, 'sla_jam')) / $total_kat) : 0;
$avg_respon   = $total_kat > 0 ? round(array_sum(array_column($list, 'sla_respon_jam')) / $total_kat) : 0;
$palettes_non = [
    ['#eff6ff','#1d4ed8'],
    ['#f0fdf4','#15803d'],
    ['#fef3c7','#d97706'],
    ['#f0f9ff','#0369a1'],
    ['#f1f5f9','#475569'],
];

include '../includes/header.php';
?>

<style>
/* ── Modal bulletproof ── */
#ipsrsModal {
    visibility: hidden !important;
    opacity: 0 !important;
    position: fixed !important;
    top: 0 !important; left: 0 !important;
    width: 100% !important; height: 100% !important;
    background: rgba(0,0,0,.5) !important;
    z-index: 99999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: opacity .22s ease, visibility .22s ease !important;
    pointer-events: none !important;
}
#ipsrsModal.im-open {
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}
#ipsrsModal .im-box {
    background: #fff !important;
    border-radius: 12px !important;
    width: 100% !important;
    max-width: 540px !important;
    margin: 16px !important;
    box-shadow: 0 12px 48px rgba(0,0,0,.22) !important;
    overflow: hidden !important;
    transform: translateY(22px) scale(.98);
    transition: transform .22s ease !important;
    max-height: 90vh !important;
    display: flex !important;
    flex-direction: column !important;
}
#ipsrsModal.im-open .im-box {
    transform: translateY(0) scale(1) !important;
}
#ipsrsModal .im-hd {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 16px 22px !important;
    border-bottom: 1px solid #f0f0f0 !important;
    background: #fafafa !important;
    flex-shrink: 0 !important;
}
#ipsrsModal .im-hd h5 {
    margin: 0 !important; font-size: 15px !important;
    font-weight: 700 !important; color: #1e293b !important;
    display: flex !important; align-items: center !important; gap: 8px !important;
}
#ipsrsModal .im-close {
    background: none !important; border: none !important;
    font-size: 22px !important; line-height: 1 !important; color: #94a3b8 !important;
    cursor: pointer !important; padding: 0 !important;
    width: 32px !important; height: 32px !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    border-radius: 6px !important; transition: background .15s, color .15s !important;
}
#ipsrsModal .im-close:hover { background: #fee2e2 !important; color: #dc2626 !important; }
#ipsrsModal .im-body {
    padding: 22px !important;
    overflow-y: auto !important;
    flex: 1 !important;
}
#ipsrsModal .im-foot {
    padding: 14px 22px !important;
    border-top: 1px solid #f0f0f0 !important;
    display: flex !important; gap: 8px !important;
    justify-content: flex-end !important;
    background: #fafafa !important;
    flex-shrink: 0 !important;
}

/* ── Jenis toggle button ── */
.jenis-btn {
    flex: 1; padding: 9px 10px; border-radius: 8px;
    font-size: 12px; font-weight: 700; cursor: pointer;
    border: 2px solid #e5e7eb; background: #f8fafc; color: #64748b;
    transition: all .18s; text-align: center;
}
.jenis-btn.active-medis {
    border-color: #db2777; background: #fdf2f8; color: #9d174d;
}
.jenis-btn.active-non {
    border-color: #2563eb; background: #eff6ff; color: #1d4ed8;
}

/* ── Icon picker grid ── */
.icon-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 5px;
    margin-top: 8px;
}
.icon-grid-item {
    border: 2px solid #e5e7eb; border-radius: 7px;
    padding: 7px 4px; text-align: center; cursor: pointer;
    transition: border-color .15s, background .15s, transform .12s;
    background: #f8fafc;
}
.icon-grid-item:hover { border-color: var(--primary,#2563eb); background: #eff6ff; transform: scale(1.08); }
.icon-grid-item.selected { border-color: var(--primary,#2563eb) !important; background: #dbeafe !important; }
.icon-grid-item i { color: var(--primary,#2563eb); font-size: 14px; }
.icon-grid-item .icon-lbl {
    font-size: 8px; color: #94a3b8; margin-top: 3px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* ── SLA hint ── */
.sla-hint {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 10px; font-weight: 700; color: #64748b;
    background: #f1f5f9; border-radius: 4px; padding: 2px 7px; margin-top: 4px;
}
.sla-bar { display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; color:#475569; }
.sla-bar .sla-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
</style>

<div class="page-header">
    <h4><i class="fa fa-tags" style="color:var(--primary);"></i> &nbsp;Kategori IPSRS</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
        <span class="sep">/</span>
        <span class="cur">Kategori IPSRS</span>
    </div>
</div>

<div class="content">
    <?= showFlash() ?>
    <?php include '../includes/category_navigation.php'; ?>

    <!-- ── Stats Row ── -->
    <div class="category-stats">
        <?php foreach([
            [$total_kat,   'Total Kategori',  'fa-tags',   '#f0fdf4','#00c896'],
            [$total_tiket, 'Total Tiket',      'fa-ticket', '#dbeafe','#1d4ed8'],
            [$avg_sla,     'Rata-rata SLA',    'fa-clock',  '#fef3c7','#d97706'],
            [$avg_respon,  'Rata-rata Respon', 'fa-bolt',   '#ede9fe','#7c3aed'],
        ] as [$val,$lbl,$ico,$bg,$clr]): ?>
        <div class="category-stat-card">
            <div class="category-stat-icon" style="background:<?= $bg ?>;">
                <i class="fa <?= $ico ?>" style="color:<?= $clr ?>;font-size:15px;"></i>
            </div>
            <div>
                <div class="category-stat-value"><?= $val ?><?= in_array($lbl, ['Rata-rata SLA','Rata-rata Respon'], true) ? '<span style="font-size:12px;font-weight:600;color:#9ca3af;">j</span>' : '' ?></div>
                <div class="category-stat-label"><?= $lbl ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Panel Tabel ── -->
    <div class="panel">
        <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <h5>
                <i class="fa fa-list text-primary"></i> &nbsp;Daftar Kategori IPSRS
                <span style="color:#aaa;font-weight:400;">(<?= $total_kat ?>)</span>
            </h5>
            <div style="display:flex;gap:6px;align-items:center;">
                <span style="display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:20px;font-size:10.5px;font-weight:700;background:#eff6ff;color:#1e40af;">
                    <i class="fa fa-screwdriver-wrench" style="font-size:9px;"></i> Non-Medis
                </span>
                <button class="btn btn-primary btn-sm" onclick="imOpen()">
                    <i class="fa fa-plus"></i> Tambah
                </button>
            </div>
        </div>
        <form class="category-table-tools" data-category-filter>
            <div class="category-table-search">
                <i class="fa fa-search"></i>
                <input type="search" class="form-control" data-category-search placeholder="Cari nama atau deskripsi kategori..." aria-label="Cari kategori">
            </div>
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
            <table id="tbl-kat" class="category-data-table" data-category-table>
                <thead>
                    <tr>
                        <th style="width:50px;text-align:center;">Ikon</th>
                        <th>Nama Kategori</th>
                        <th style="width:110px;text-align:center;">Jenis</th>
                        <th style="width:130px;">Target SLA</th>
                        <th style="width:130px;">Target Respon</th>
                        <th style="width:75px;text-align:center;">Tiket</th>
                        <th style="width:110px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list)): ?>
                    <tr data-empty-category>
                        <td colspan="7" style="text-align:center;color:#aaa;padding:40px 20px;">
                            <div style="width:52px;height:52px;background:#f1f5f9;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                                <i class="fa fa-tags" style="font-size:22px;color:#cbd5e1;"></i>
                            </div>
                            <div style="font-weight:600;color:#64748b;margin-bottom:4px;">Belum ada kategori IPSRS</div>
                            <small style="color:#94a3b8;">Klik <strong>+ Tambah</strong> untuk memulai.</small>
                        </td>
                    </tr>
                    <?php else:
                        foreach ($list as $idx => $k):
                            [$pal_bg,$pal_tc] = $palettes_non[$idx % count($palettes_non)];
                            $has_tiket  = (int)$k['total'] > 0;
                            $sla_color  = $k['sla_jam']        <= 24 ? '#22c55e' : ($k['sla_jam']        <= 48 ? '#f59e0b' : '#ef4444');
                            $res_color  = $k['sla_respon_jam'] <= 4  ? '#22c55e' : ($k['sla_respon_jam'] <= 8  ? '#f59e0b' : '#ef4444');
                    ?>
                    <tr data-category-row>

                        <!-- Ikon -->
                        <td style="text-align:center;">
                            <div style="width:36px;height:36px;background:<?= $pal_bg ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;margin:0 auto;">
                                <i class="fa <?= clean($k['icon']) ?>" style="color:<?= $pal_tc ?>;font-size:15px;"></i>
                            </div>
                        </td>

                        <!-- Nama -->
                        <td>
                            <div style="font-weight:600;font-size:13px;color:#111827;"><?= clean($k['nama']) ?></div>
                            <?php if ($k['deskripsi']): ?>
                            <div style="font-size:11px;color:#9ca3af;margin-top:2px;"><?= clean($k['deskripsi']) ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Jenis badge -->
                        <td style="text-align:center;">
                            <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:10.5px;font-weight:700;background:#eff6ff;color:#1e40af;">
                                <i class="fa fa-screwdriver-wrench" style="font-size:9px;"></i> Non-Medis
                            </span>
                        </td>

                        <!-- SLA -->
                        <td>
                            <div class="sla-bar">
                                <span class="sla-dot" style="background:<?= $sla_color ?>;"></span>
                                <span style="font-weight:700;color:#1e293b;"><?= $k['sla_jam'] ?></span>
                                <span style="color:#94a3b8;">jam</span>
                            </div>
                        </td>

                        <!-- Respon -->
                        <td>
                            <div class="sla-bar">
                                <span class="sla-dot" style="background:<?= $res_color ?>;"></span>
                                <span style="font-weight:700;color:#1e293b;"><?= $k['sla_respon_jam'] ?></span>
                                <span style="color:#94a3b8;">jam</span>
                            </div>
                        </td>

                        <!-- Tiket count -->
                        <td style="text-align:center;">
                            <?php if ($has_tiket): ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;color:#00c896;">
                                <i class="fa fa-ticket" style="font-size:10px;"></i> <?= $k['total'] ?>
                            </span>
                            <?php else: ?>
                            <span style="color:#e2e8f0;font-size:12px;">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- Aksi -->
                        <td style="text-align:center;white-space:nowrap;">
                            <button class="btn btn-warning btn-sm" onclick='imOpen(<?= json_encode($k) ?>)' title="Edit">
                                <i class="fa fa-edit"></i>
                            </button>
                            <?php if (!$has_tiket): ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Hapus kategori <?= addslashes(clean($k['nama'])) ?>?')">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-sm btn-default" disabled
                                title="Tidak dapat dihapus — masih ada <?= $k['total'] ?> tiket">
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

    <!-- ── Legend ── -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 18px;margin-top:10px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <span style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px;">
            <i class="fa fa-info-circle" style="color:#64748b;"></i> Keterangan SLA:
        </span>
        <?php foreach([
            ['#22c55e','≤ 24 jam','Cepat'],
            ['#f59e0b','25–48 jam','Normal'],
            ['#ef4444','> 48 jam','Lambat'],
        ] as [$c,$r,$l]): ?>
        <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#475569;">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= $c ?>;"></span>
            <strong><?= $r ?></strong> — <?= $l ?>
        </span>
        <?php endforeach; ?>
    </div>

</div><!-- /.content -->


<!-- ══════════════════════════════════════════════
     MODAL TAMBAH / EDIT KATEGORI IPSRS
══════════════════════════════════════════════ -->
<div id="ipsrsModal">
    <div class="im-box">
        <div class="im-hd">
            <h5>
                <i class="fa fa-tags" id="im-ico" style="color:var(--primary,#2563eb);"></i>
                <span id="im-title">Tambah Kategori IPSRS</span>
            </h5>
            <button class="im-close" onclick="imClose()" title="Tutup">&times;</button>
        </div>

        <div class="im-body">
            <form method="POST" id="im-form">
                <input type="hidden" name="action" value="tambah" id="fa">
                <input type="hidden" name="id"     value=""        id="fid">
                <input type="hidden" name="icon"   value="fa-toolbox" id="fi_hidden">

                <!-- Nama -->
                <div class="form-group">
                    <label>Nama Kategori <span class="req">*</span></label>
                    <input type="text" name="nama" id="fn" class="form-control" required
                           placeholder="Contoh: Ventilator, Pompa Air, HVAC…">
                </div>

                <!-- Deskripsi -->
                <div class="form-group">
                    <label>Deskripsi <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
                    <textarea name="deskripsi" id="fd" class="form-control" rows="2"
                              placeholder="Keterangan singkat kategori ini…" style="resize:vertical;"></textarea>
                </div>

                <!-- SLA -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Target SLA (jam)</label>
                        <input type="number" name="sla_jam" id="fs" class="form-control" value="24" min="1">
                        <div class="sla-hint"><i class="fa fa-clock"></i> Waktu penyelesaian tiket</div>
                    </div>
                    <div class="form-group">
                        <label>Target Respon (jam)</label>
                        <input type="number" name="sla_respon_jam" id="fr" class="form-control" value="4" min="1">
                        <div class="sla-hint"><i class="fa fa-bolt"></i> Waktu respons pertama</div>
                    </div>
                </div>

                <!-- Icon Picker -->
                <div class="form-group">
                    <label>Pilih Ikon</label>

                    <div class="icon-grid">
                        <?php foreach ($icons_non_medis as $ic => $label): ?>
                        <div class="icon-grid-item <?= $ic==='fa-toolbox'?'selected':'' ?>"
                             data-icon="<?= $ic ?>" onclick="pickIcon(this)" title="<?= $label ?>">
                            <i class="fa <?= $ic ?>"></i>
                            <div class="icon-lbl"><?= str_replace('fa-','',$ic) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Preview ikon terpilih -->
                    <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
                        <div style="width:36px;height:36px;background:#f0f4ff;border-radius:8px;display:flex;align-items:center;justify-content:center;border:1px solid #e0e7ff;">
                            <i class="fa fa-toolbox" id="icon-preview" style="color:var(--primary,#2563eb);font-size:16px;"></i>
                        </div>
                        <span style="font-size:11px;color:#64748b;">Ikon terpilih: <strong id="icon-preview-lbl">fa-toolbox</strong></span>
                    </div>
                </div>

            </form>
        </div>

        <div class="im-foot">
            <button type="button" class="btn btn-default" onclick="imClose()">
                <i class="fa fa-times"></i> Batal
            </button>
            <button type="submit" form="im-form" class="btn btn-primary" id="im-btn">
                <i class="fa fa-save"></i> Simpan
            </button>
        </div>
    </div>
</div>

<script>
var _im = document.getElementById('ipsrsModal');

/* ── Buka modal ── */
function imOpen(k) {
    if (k) {
        document.getElementById('im-title').textContent = 'Edit: ' + k.nama;
        document.getElementById('im-ico').style.color  = 'var(--orange,#f59e0b)';
        document.getElementById('fa').value   = 'edit';
        document.getElementById('fid').value  = k.id;
        document.getElementById('fn').value   = k.nama;
        document.getElementById('fd').value   = k.deskripsi || '';
        document.getElementById('fs').value   = k.sla_jam;
        document.getElementById('fr').value   = k.sla_respon_jam;
        document.getElementById('im-btn').innerHTML = '<i class="fa fa-save"></i> Update';
        imSelectIcon(k.icon || 'fa-toolbox');
    } else {
        document.getElementById('im-title').textContent = 'Tambah Kategori IPSRS';
        document.getElementById('im-ico').style.color  = 'var(--primary,#2563eb)';
        document.getElementById('fa').value   = 'tambah';
        document.getElementById('fid').value  = '';
        document.getElementById('fn').value   = '';
        document.getElementById('fd').value   = '';
        document.getElementById('fs').value   = 24;
        document.getElementById('fr').value   = 4;
        document.getElementById('im-btn').innerHTML = '<i class="fa fa-save"></i> Simpan';
        imSelectIcon('fa-toolbox');
    }
    _im.classList.add('im-open');
    setTimeout(function(){ document.getElementById('fn').focus(); }, 80);
}

/* ── Tutup modal ── */
function imClose() { _im.classList.remove('im-open'); }
_im.addEventListener('click', function(e){ if (e.target === _im) imClose(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') imClose(); });

/* ── Pilih ikon dari grid ── */
function pickIcon(el) {
    document.querySelectorAll('.icon-grid-item').forEach(function(i){ i.classList.remove('selected'); });
    el.classList.add('selected');
    var icon = el.dataset.icon;
    document.getElementById('fi_hidden').value = icon;
    document.getElementById('icon-preview').className = 'fa ' + icon;
    document.getElementById('icon-preview-lbl').textContent = icon;
}

function imSelectIcon(iconVal) {
    document.querySelectorAll('.icon-grid-item').forEach(function(i){
        i.classList.toggle('selected', i.dataset.icon === iconVal);
    });
    document.getElementById('fi_hidden').value = iconVal;
    document.getElementById('icon-preview').className = 'fa ' + iconVal;
    document.getElementById('icon-preview-lbl').textContent = iconVal;
}

</script>

<script src="<?= APP_URL ?>/assets/js/category-table.js"></script>
<?php include '../includes/footer.php'; ?>