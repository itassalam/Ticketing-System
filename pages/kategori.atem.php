<?php
// pages/kategori_atem.php
session_start(); require_once '../config.php'; requireLogin();
if (!hasRole(['admin','teknisi'])) { setFlash('danger','Akses ditolak.'); redirect(APP_URL.'/dashboard.php'); }
$page_title = 'Kategori ATEM'; $active_menu = 'kategori_atem';

/* ════════════════════════════════════════════════════════════════════════════
   MODE VIEW (TANPA DATABASE)
   Data kategori disimpan di $_SESSION['atem_demo_kat'].
   Jumlah aset per kategori dihitung dari data halaman Aset ATEM (session).
   Buka  ?reset_demo=1  untuk mengembalikan data contoh.
════════════════════════════════════════════════════════════════════════════ */
if (!isset($_SESSION['atem_demo_kat']) || isset($_GET['reset_demo'])) {
    $_SESSION['atem_demo_kat'] = [
        ['id'=>1,'nama'=>'Anjungan',              'deskripsi'=>'Unit anjungan ATEM lengkap',            'icon'=>'fa-desktop',        'sla_jam'=>24,'sla_respon_jam'=>4],
        ['id'=>2,'nama'=>'Printer Tiket',         'deskripsi'=>'Printer thermal pencetak nomor antrian','icon'=>'fa-print',          'sla_jam'=>24,'sla_respon_jam'=>4],
        ['id'=>3,'nama'=>'Touchscreen / Monitor', 'deskripsi'=>'Layar sentuh dan monitor anjungan',      'icon'=>'fa-hand-pointer',   'sla_jam'=>48,'sla_respon_jam'=>8],
        ['id'=>4,'nama'=>'Barcode / QR Scanner',  'deskripsi'=>'Pemindai kartu / barcode / QR pasien',  'icon'=>'fa-qrcode',         'sla_jam'=>24,'sla_respon_jam'=>4],
        ['id'=>5,'nama'=>'Mini PC / CPU',         'deskripsi'=>'Unit komputer di dalam anjungan',       'icon'=>'fa-microchip',      'sla_jam'=>48,'sla_respon_jam'=>8],
        ['id'=>6,'nama'=>'Jaringan (Switch / AP)','deskripsi'=>'Perangkat jaringan pendukung ATEM',     'icon'=>'fa-network-wired',  'sla_jam'=>24,'sla_respon_jam'=>4],
        ['id'=>7,'nama'=>'UPS / Kelistrikan',     'deskripsi'=>'UPS, stabilizer, dan catu daya',        'icon'=>'fa-plug',           'sla_jam'=>72,'sla_respon_jam'=>12],
        ['id'=>8,'nama'=>'Lainnya',               'deskripsi'=>'',                                      'icon'=>'fa-tag',            'sla_jam'=>48,'sla_respon_jam'=>8],
    ];
    if (isset($_GET['reset_demo'])) { setFlash('success','Data kategori contoh dikembalikan ke awal.'); redirect(APP_URL.'/pages/kategori_atem.php'); }
}
$kat_store = &$_SESSION['atem_demo_kat'];

// Jumlah aset per kategori (dari data Aset ATEM)
function atemHitungAset(string $nama): int {
    $n = 0;
    foreach (($_SESSION['atem_demo_aset'] ?? []) as $a) if (strcasecmp(trim($a['kategori'] ?? ''), $nama) === 0) $n++;
    return $n;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['action']         ?? '';
    $nama  = trim($_POST['nama']      ?? '');
    $desc  = trim($_POST['deskripsi'] ?? '');
    $icon  = $_POST['icon']           ?? 'fa-desktop';
    $sla   = max(1,(int)($_POST['sla_jam']        ?? 24));
    $sla_r = max(1,(int)($_POST['sla_respon_jam'] ?? 4));
    $id    = (int)($_POST['id'] ?? 0);

    $dupe = false;
    foreach ($kat_store as $r) if (strcasecmp($r['nama'],$nama) === 0 && (int)$r['id'] !== $id) $dupe = true;

    if ($act === 'tambah' && $nama) {
        if ($dupe) {
            setFlash('warning','Kategori dengan nama tersebut sudah ada.');
        } else {
            $newId = $kat_store ? max(array_column($kat_store,'id')) + 1 : 1;
            $kat_store[] = ['id'=>$newId,'nama'=>$nama,'deskripsi'=>$desc,'icon'=>$icon,'sla_jam'=>$sla,'sla_respon_jam'=>$sla_r];
            setFlash('success','Kategori ATEM berhasil ditambahkan.');
        }
    } elseif ($act === 'edit' && $nama) {
        if ($dupe) {
            setFlash('warning','Kategori dengan nama tersebut sudah ada.');
        } else {
            foreach ($kat_store as $k => $r) if ((int)$r['id'] === $id) {
                $old = $r['nama'];
                $kat_store[$k] = array_merge($r, ['nama'=>$nama,'deskripsi'=>$desc,'icon'=>$icon,'sla_jam'=>$sla,'sla_respon_jam'=>$sla_r]);
                // sinkronkan nama kategori pada data Aset ATEM
                if ($old !== $nama && isset($_SESSION['atem_demo_aset']))
                    foreach ($_SESSION['atem_demo_aset'] as $i => $a)
                        if (strcasecmp(trim($a['kategori'] ?? ''), $old) === 0) $_SESSION['atem_demo_aset'][$i]['kategori'] = $nama;
            }
            setFlash('success','Kategori ATEM berhasil diperbarui.');
        }
    } elseif ($act === 'hapus') {
        $target = null;
        foreach ($kat_store as $r) if ((int)$r['id'] === $id) $target = $r;
        if ($target && atemHitungAset($target['nama']) > 0) {
            setFlash('warning','Kategori tidak dapat dihapus, masih ada aset yang menggunakan kategori ini.');
        } else {
            $kat_store = array_values(array_filter($kat_store, fn($r) => (int)$r['id'] !== $id));
            setFlash('success','Kategori ATEM berhasil dihapus.');
        }
    }
    redirect(APP_URL . '/pages/kategori_atem.php');
}

// Data list + jumlah aset
$list = $kat_store;
usort($list, fn($a,$b) => strcasecmp($a['nama'],$b['nama']));
foreach ($list as $i => $k) $list[$i]['total'] = atemHitungAset($k['nama']);

$all_icons = [
    'fa-desktop'          => 'Anjungan / ATEM',
    'fa-ticket'           => 'Tiket Antrian',
    'fa-print'            => 'Printer',
    'fa-hand-pointer'     => 'Touchscreen',
    'fa-display'          => 'Monitor',
    'fa-qrcode'           => 'QR Scanner',
    'fa-barcode'          => 'Barcode',
    'fa-credit-card'      => 'Pembaca Kartu',
    'fa-microchip'        => 'Mini PC / CPU',
    'fa-keyboard'         => 'Input / Keypad',
    'fa-network-wired'    => 'Jaringan Kabel',
    'fa-wifi'             => 'WiFi / AP',
    'fa-plug'             => 'Kelistrikan',
    'fa-battery-full'     => 'UPS / Baterai',
    'fa-gear'             => 'Mekanikal',
    'fa-screwdriver-wrench'=> 'Servis',
    'fa-lock'             => 'Casing / Kunci',
    'fa-tag'              => 'Lainnya',
];

// Stats
$total_kat   = count($list);
$total_aset  = array_sum(array_column($list, 'total'));
$sla_cepat   = count(array_filter($list, fn($k) => $k['sla_jam'] <= 24));
$tanpa_aset  = count(array_filter($list, fn($k) => (int)$k['total'] === 0));

$palettes = [
    ['#fff7ed','#c2410c'], ['#eff6ff','#1d4ed8'], ['#f0fdf4','#15803d'],
    ['#fdf2f8','#a21caf'], ['#fef3c7','#d97706'], ['#f0f9ff','#0369a1'],
];

include '../includes/header.php';
?>

<style>
/* ── Modal bulletproof ── */
#atemKatModal {
    visibility: hidden !important; opacity: 0 !important;
    position: fixed !important; top: 0 !important; left: 0 !important;
    width: 100% !important; height: 100% !important;
    background: rgba(0,0,0,.5) !important; z-index: 99999 !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    transition: opacity .22s ease, visibility .22s ease !important; pointer-events: none !important;
}
#atemKatModal.im-open { visibility: visible !important; opacity: 1 !important; pointer-events: auto !important; }
#atemKatModal .im-box {
    background: #fff !important; border-radius: 12px !important; width: 100% !important; max-width: 540px !important;
    margin: 16px !important; box-shadow: 0 12px 48px rgba(0,0,0,.22) !important; overflow: hidden !important;
    transform: translateY(22px) scale(.98); transition: transform .22s ease !important;
    max-height: 90vh !important; display: flex !important; flex-direction: column !important;
}
#atemKatModal.im-open .im-box { transform: translateY(0) scale(1) !important; }
#atemKatModal .im-hd {
    display: flex !important; align-items: center !important; justify-content: space-between !important;
    padding: 16px 22px !important; border-bottom: 1px solid #f0f0f0 !important; background: #fafafa !important; flex-shrink: 0 !important;
}
#atemKatModal .im-hd h5 { margin: 0 !important; font-size: 15px !important; font-weight: 700 !important; color: #1e293b !important; display: flex !important; align-items: center !important; gap: 8px !important; }
#atemKatModal .im-close {
    background: none !important; border: none !important; font-size: 22px !important; line-height: 1 !important; color: #94a3b8 !important;
    cursor: pointer !important; padding: 0 !important; width: 32px !important; height: 32px !important;
    display: flex !important; align-items: center !important; justify-content: center !important;
    border-radius: 6px !important; transition: background .15s, color .15s !important;
}
#atemKatModal .im-close:hover { background: #fee2e2 !important; color: #dc2626 !important; }
#atemKatModal .im-body { padding: 22px !important; overflow-y: auto !important; flex: 1 !important; }
#atemKatModal .im-foot {
    padding: 14px 22px !important; border-top: 1px solid #f0f0f0 !important; display: flex !important; gap: 8px !important;
    justify-content: flex-end !important; background: #fafafa !important; flex-shrink: 0 !important;
}

/* ── Icon picker ── */
.icon-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 5px; margin-top: 8px; }
.icon-grid-item { border: 2px solid #e5e7eb; border-radius: 7px; padding: 7px 4px; text-align: center; cursor: pointer; transition: border-color .15s, background .15s, transform .12s; background: #f8fafc; }
.icon-grid-item:hover { border-color: #f97316; background: #fff7ed; transform: scale(1.08); }
.icon-grid-item.selected { border-color: #f97316 !important; background: #ffedd5 !important; }
.icon-grid-item i { color: #ea580c; font-size: 14px; }
.icon-grid-item .icon-lbl { font-size: 8px; color: #94a3b8; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.sla-hint { display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 700; color: #64748b; background: #f1f5f9; border-radius: 4px; padding: 2px 7px; margin-top: 4px; }
.sla-bar { display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; color:#475569; }
.sla-bar .sla-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
</style>

<div class="page-header">
    <h4><i class="fa fa-tags" style="color:#f97316;"></i> &nbsp;Kategori ATEM</h4>
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
        <span class="sep">/</span>
        <span class="cur">Kategori ATEM</span>
    </div>
</div>

<div class="content">
    <?= showFlash() ?>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:8px;padding:9px 14px;margin-bottom:14px;font-size:12px;display:flex;align-items:center;justify-content:space-between;gap:10px;">
        <span><i class="fa fa-circle-info"></i> <strong>Mode View</strong> — memakai data contoh (belum terhubung database). Perubahan hanya tersimpan di session.</span>
        <a href="?reset_demo=1" style="color:#1e40af;font-weight:700;text-decoration:underline;white-space:nowrap;">Reset data contoh</a>
    </div>

    <!-- Stats -->
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
        <?php foreach([
            [$total_kat,  'Total Kategori',     'fa-tags',        '#fff7ed','#f97316'],
            [$total_aset, 'Aset Terkategori',   'fa-desktop',     '#dbeafe','#1d4ed8'],
            [$sla_cepat,  'SLA ≤ 24 jam',       'fa-bolt',        '#dcfce7','#16a34a'],
            [$tanpa_aset, 'Belum Dipakai Aset', 'fa-box-open',     '#f1f5f9','#64748b'],
        ] as [$val,$lbl,$ico,$bg,$clr]): ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:12px;min-width:140px;">
            <div style="width:38px;height:38px;border-radius:9px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa <?= $ico ?>" style="color:<?= $clr ?>;font-size:15px;"></i>
            </div>
            <div>
                <div style="font-size:22px;font-weight:800;color:#111827;line-height:1;"><?= $val ?></div>
                <div style="font-size:11px;color:#9ca3af;margin-top:2px;"><?= $lbl ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Panel Tabel -->
    <div class="panel">
        <div class="panel-hd" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <h5>
                <i class="fa fa-list" style="color:#f97316;"></i> &nbsp;Daftar Kategori ATEM
                <span style="color:#aaa;font-weight:400;">(<?= $total_kat ?>)</span>
            </h5>
            <div style="display:flex;gap:6px;align-items:center;">
                <input type="text" id="kat-cari" placeholder="Cari kategori…" oninput="filterKat(this.value)"
                       style="padding:6px 11px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;font-family:inherit;width:180px;">
                <button class="btn btn-primary btn-sm" onclick="imOpen()"><i class="fa fa-plus"></i> Tambah</button>
            </div>
        </div>
        <div class="panel-bd np tbl-wrap">
            <table id="tbl-kat">
                <thead>
                    <tr>
                        <th style="width:50px;text-align:center;">Ikon</th>
                        <th>Nama Kategori</th>
                        <th style="width:130px;">Target SLA</th>
                        <th style="width:130px;">Target Respon</th>
                        <th style="width:75px;text-align:center;">Aset</th>
                        <th style="width:110px;text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#aaa;padding:40px 20px;">
                            <div style="width:52px;height:52px;background:#f1f5f9;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                                <i class="fa fa-tags" style="font-size:22px;color:#cbd5e1;"></i>
                            </div>
                            <div style="font-weight:600;color:#64748b;margin-bottom:4px;">Belum ada kategori ATEM</div>
                            <small style="color:#94a3b8;">Klik <strong>+ Tambah</strong> untuk memulai.</small>
                        </td>
                    </tr>
                    <?php else:
                        $idx = 0;
                        foreach ($list as $k):
                            [$pal_bg,$pal_tc] = $palettes[$idx % count($palettes)]; $idx++;
                            $has_aset  = (int)$k['total'] > 0;
                            $sla_color = $k['sla_jam']        <= 24 ? '#22c55e' : ($k['sla_jam']        <= 48 ? '#f59e0b' : '#ef4444');
                            $res_color = $k['sla_respon_jam'] <= 4  ? '#22c55e' : ($k['sla_respon_jam'] <= 8  ? '#f59e0b' : '#ef4444');
                    ?>
                    <tr data-nama="<?= clean(mb_strtolower($k['nama'].' '.$k['deskripsi'])) ?>">
                        <td style="text-align:center;">
                            <div style="width:36px;height:36px;background:<?= $pal_bg ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;margin:0 auto;">
                                <i class="fa <?= clean($k['icon']) ?>" style="color:<?= $pal_tc ?>;font-size:15px;"></i>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600;font-size:13px;color:#111827;"><?= clean($k['nama']) ?></div>
                            <?php if ($k['deskripsi']): ?><div style="font-size:11px;color:#9ca3af;margin-top:2px;"><?= clean($k['deskripsi']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <div class="sla-bar"><span class="sla-dot" style="background:<?= $sla_color ?>;"></span>
                                <span style="font-weight:700;color:#1e293b;"><?= (int)$k['sla_jam'] ?></span><span style="color:#94a3b8;">jam</span></div>
                        </td>
                        <td>
                            <div class="sla-bar"><span class="sla-dot" style="background:<?= $res_color ?>;"></span>
                                <span style="font-weight:700;color:#1e293b;"><?= (int)$k['sla_respon_jam'] ?></span><span style="color:#94a3b8;">jam</span></div>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($has_aset): ?>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;color:#ea580c;">
                                <i class="fa fa-desktop" style="font-size:10px;"></i> <?= (int)$k['total'] ?>
                            </span>
                            <?php else: ?><span style="color:#e2e8f0;font-size:12px;">0</span><?php endif; ?>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <button class="btn btn-warning btn-sm" onclick="imOpen(<?= htmlspecialchars(json_encode($k), ENT_QUOTES) ?>)" title="Edit">
                                <i class="fa fa-edit"></i>
                            </button>
                            <?php if (!$has_aset): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus kategori <?= addslashes(clean($k['nama'])) ?>?')">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Hapus"><i class="fa fa-trash"></i></button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-sm btn-default" disabled title="Tidak dapat dihapus — masih ada <?= (int)$k['total'] ?> aset">
                                <i class="fa fa-lock" style="color:#d1d5db;"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Legend -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 18px;margin-top:10px;display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <span style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px;">
            <i class="fa fa-info-circle" style="color:#64748b;"></i> Keterangan SLA:
        </span>
        <?php foreach([['#22c55e','≤ 24 jam','Cepat'],['#f59e0b','25–48 jam','Normal'],['#ef4444','> 48 jam','Lambat']] as [$c,$r,$l]): ?>
        <span style="display:flex;align-items:center;gap:5px;font-size:11px;color:#475569;">
            <span style="width:8px;height:8px;border-radius:50%;background:<?= $c ?>;"></span><strong><?= $r ?></strong> — <?= $l ?>
        </span>
        <?php endforeach; ?>
        <span style="margin-left:auto;font-size:11px;color:#94a3b8;"><i class="fa fa-link"></i> Kategori ini muncul sebagai saran pada form <a href="<?= APP_URL ?>/pages/aset_atem.php" style="color:#c2410c;font-weight:600;">Aset ATEM</a>.</span>
    </div>

</div><!-- /.content -->


<!-- ═══════════ MODAL TAMBAH / EDIT KATEGORI ATEM ═══════════ -->
<div id="atemKatModal">
    <div class="im-box">
        <div class="im-hd">
            <h5>
                <i class="fa fa-tags" id="im-ico" style="color:#f97316;"></i>
                <span id="im-title">Tambah Kategori ATEM</span>
            </h5>
            <button class="im-close" onclick="imClose()" title="Tutup">&times;</button>
        </div>

        <div class="im-body">
            <form method="POST" id="im-form">
                <input type="hidden" name="action" value="tambah" id="fa">
                <input type="hidden" name="id"     value=""       id="fid">
                <input type="hidden" name="icon"   value="fa-desktop" id="fi_hidden">

                <div class="form-group">
                    <label>Nama Kategori <span class="req">*</span></label>
                    <input type="text" name="nama" id="fn" class="form-control" required placeholder="Contoh: Printer Tiket, Touchscreen, UPS…">
                </div>

                <div class="form-group">
                    <label>Deskripsi <span style="font-size:10px;color:#aaa;font-weight:400;">(opsional)</span></label>
                    <textarea name="deskripsi" id="fd" class="form-control" rows="2" placeholder="Keterangan singkat kategori ini…" style="resize:vertical;"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Target SLA (jam)</label>
                        <input type="number" name="sla_jam" id="fs" class="form-control" value="24" min="1">
                        <div class="sla-hint"><i class="fa fa-clock"></i> Waktu penyelesaian perbaikan</div>
                    </div>
                    <div class="form-group">
                        <label>Target Respon (jam)</label>
                        <input type="number" name="sla_respon_jam" id="fr" class="form-control" value="4" min="1">
                        <div class="sla-hint"><i class="fa fa-bolt"></i> Waktu respons pertama</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Pilih Ikon</label>
                    <div class="icon-grid" id="igrid">
                        <?php foreach ($all_icons as $ic => $label): ?>
                        <div class="icon-grid-item <?= $ic==='fa-desktop'?'selected':'' ?>" data-icon="<?= $ic ?>" onclick="pickIcon(this)" title="<?= $label ?>">
                            <i class="fa <?= $ic ?>"></i>
                            <div class="icon-lbl"><?= $label ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
                        <div style="width:36px;height:36px;background:#fff7ed;border-radius:8px;display:flex;align-items:center;justify-content:center;border:1px solid #fed7aa;">
                            <i class="fa fa-desktop" id="icon-preview" style="color:#ea580c;font-size:16px;"></i>
                        </div>
                        <span style="font-size:11px;color:#64748b;">Ikon terpilih: <strong id="icon-preview-lbl">fa-desktop</strong></span>
                    </div>
                </div>
            </form>
        </div>

        <div class="im-foot">
            <button type="button" class="btn btn-default" onclick="imClose()"><i class="fa fa-times"></i> Batal</button>
            <button type="submit" form="im-form" class="btn btn-primary" id="im-btn"><i class="fa fa-save"></i> Simpan</button>
        </div>
    </div>
</div>

<script>
var _im = document.getElementById('atemKatModal');

function imOpen(k) {
    if (k) {
        document.getElementById('im-title').textContent = 'Edit: ' + k.nama;
        document.getElementById('im-ico').style.color   = '#f59e0b';
        document.getElementById('fa').value   = 'edit';
        document.getElementById('fid').value  = k.id;
        document.getElementById('fn').value   = k.nama;
        document.getElementById('fd').value   = k.deskripsi || '';
        document.getElementById('fs').value   = k.sla_jam;
        document.getElementById('fr').value   = k.sla_respon_jam;
        document.getElementById('im-btn').innerHTML = '<i class="fa fa-save"></i> Update';
        imSelectIcon(k.icon || 'fa-desktop');
    } else {
        document.getElementById('im-title').textContent = 'Tambah Kategori ATEM';
        document.getElementById('im-ico').style.color   = '#f97316';
        document.getElementById('fa').value   = 'tambah';
        document.getElementById('fid').value  = '';
        document.getElementById('fn').value   = '';
        document.getElementById('fd').value   = '';
        document.getElementById('fs').value   = 24;
        document.getElementById('fr').value   = 4;
        document.getElementById('im-btn').innerHTML = '<i class="fa fa-save"></i> Simpan';
        imSelectIcon('fa-desktop');
    }
    _im.classList.add('im-open');
    setTimeout(function(){ document.getElementById('fn').focus(); }, 80);
}

function imClose() { _im.classList.remove('im-open'); }
_im.addEventListener('click', function(e){ if (e.target === _im) imClose(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') imClose(); });

function pickIcon(el) { imSelectIcon(el.dataset.icon); }
function imSelectIcon(iconVal) {
    document.querySelectorAll('.icon-grid-item').forEach(function(i){
        i.classList.toggle('selected', i.dataset.icon === iconVal);
    });
    document.getElementById('fi_hidden').value = iconVal;
    document.getElementById('icon-preview').className = 'fa ' + iconVal;
    document.getElementById('icon-preview-lbl').textContent = iconVal;
}

/* Cari kategori (client-side) */
function filterKat(q) {
    q = (q || '').toLowerCase().trim();
    document.querySelectorAll('#tbl-kat tbody tr[data-nama]').forEach(function(r){
        r.style.display = (!q || r.dataset.nama.indexOf(q) !== -1) ? '' : 'none';
    });
}
</script>

<?php include '../includes/footer.php'; ?>