<?php
// includes/header.php — $page_title, $active_menu harus sudah di-set
// Urutan file: 1) logika & data  2) <head> + CSS  3) sidebar  4) topnav  5) modal  6) script

$_in_pages = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'pages');
$_css_url  = APP_URL . '/assets/css/style.css';

/* ═══════════════════════════════════════════════════════════════════════════
   SAKLAR FITUR — ubah ke true untuk menampilkan kembali menu & notifikasinya.
   Kode menu ada di includes/sidebar_pegawai.php. Saat false, query-nya juga
   tidak dijalankan sehingga setiap halaman lebih ringan.
═══════════════════════════════════════════════════════════════════════════ */
$FITUR = [
    'pegawai'      => false,   // Management, Absensi, Cuti, Penggajian
    'berita_acara' => false,   // Berita Acara
    'akreditasi'   => false,   // Dokumen Akreditasi
];

/* ═══ ROLE & IZIN ═══ */
$_cur_role   = $_SESSION['user_role'] ?? 'user';
$_uid        = (int)($_SESSION['user_id']  ?? 0);
$_pokja_id   = (int)($_SESSION['pokja_id'] ?? 0);
$active_menu = $active_menu ?? '';

$_role_map = [
    'admin'         => 'Admin',
    'teknisi'       => 'Teknisi IT',
    'teknisi_ipsrs' => 'Teknisi IPSRS',
    'teknisi_atem'  => 'Teknisi ATEM',
    'management_umum' => 'Management Umum',
    'wadir_umum'      => 'Wadir Umum',
    'hrd'             => 'Management Umum',
    'akreditasi'    => 'Tim Akreditasi',
    'keuangan'      => 'Keuangan',
    'user'          => 'User',
];
$_role_display = $_role_map[$_cur_role] ?? ucfirst($_cur_role);

$_is_admin               = ($_cur_role === 'admin');
$_can_manage_tiket_it    = hasRole(['admin', 'teknisi']);
$_can_manage_tiket_ipsrs = hasRole(['admin', 'teknisi_ipsrs']);
$_can_manage_tiket_atem  = hasRole(['admin', 'teknisi_atem']);
$_can_manage_aset_it     = $_can_manage_tiket_it;
$_can_manage_aset_ipsrs  = $_can_manage_tiket_ipsrs;
$_can_manage_aset_atem   = $_can_manage_tiket_atem;
$_can_management         = hasRole(['admin', 'management_umum', 'wadir_umum']);
$_can_berita_acara       = hasRole(['admin', 'teknisi']);
$_is_akreditasi_flag     = (int)($_SESSION['is_akreditasi'] ?? 0) === 1;
$_can_akreditasi         = hasRole(['admin', 'akreditasi']) || $_is_akreditasi_flag;
$_can_penggajian         = hasRole(['admin', 'keuangan']);
// Menu kategori tersedia untuk Admin dan role Management Umum/Wadir Umum.
$_can_kategori           = $_is_admin || hasRole(['management_umum', 'wadir_umum']);

/* ═══ BADGE & NOTIFIKASI ═══ */
if (!function_exists('_hdr_count')) {
    function _hdr_count(PDO $pdo, string $sql, array $params = []): int {
        try {
            $st = $pdo->prepare($sql);
            if (!$st) return 0;
            $st->execute($params);
            return (int)$st->fetchColumn();
        } catch (Exception $e) { return 0; }
    }
}

$cnt_menunggu       = $_can_manage_tiket_it    ? _hdr_count($pdo, "SELECT COUNT(*) FROM tiket WHERE status='menunggu'")       : 0;
$cnt_ipsrs_menunggu = $_can_manage_tiket_ipsrs ? _hdr_count($pdo, "SELECT COUNT(*) FROM tiket_ipsrs WHERE status='menunggu'") : 0;
$cnt_atem_menunggu  = $_can_manage_tiket_atem  ? _hdr_count($pdo, "SELECT COUNT(*) FROM tiket_atem WHERE status='menunggu'")  : 0;

$_mine = "WHERE user_id=? AND status IN ('menunggu','diproses')";
$cnt_tiket_saya = _hdr_count($pdo, "SELECT COUNT(*) FROM tiket $_mine",       [$_uid]);
$cnt_ipsrs_saya = _hdr_count($pdo, "SELECT COUNT(*) FROM tiket_ipsrs $_mine", [$_uid]);
$cnt_atem_saya  = _hdr_count($pdo, "SELECT COUNT(*) FROM tiket_atem $_mine",  [$_uid]);

$_mnt_sql = fn(string $t) => "SELECT COUNT(DISTINCT aset_id) FROM $t
    WHERE id IN (SELECT MAX(id) FROM $t GROUP BY aset_id)
      AND tgl_maintenance_berikut BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)";
$cnt_mnt_it    = $_can_manage_aset_it    ? _hdr_count($pdo, $_mnt_sql('maintenance_it'))    : 0;
$cnt_mnt_ipsrs = $_can_manage_aset_ipsrs ? _hdr_count($pdo, $_mnt_sql('maintenance_ipsrs')) : 0;
$cnt_mnt_atem  = $_can_manage_aset_atem  ? _hdr_count($pdo, $_mnt_sql('maintenance_atem'))  : 0;

$cnt_offline = $_can_manage_aset_it ? _hdr_count($pdo,
    "SELECT COUNT(*) FROM koneksi_monitor m WHERE aktif=1
     AND (SELECT status FROM koneksi_log WHERE monitor_id=m.id ORDER BY cek_at DESC LIMIT 1)='offline'") : 0;
$cnt_mutasi  = $_can_manage_aset_it ? _hdr_count($pdo,
    "SELECT COUNT(*) FROM mutasi_aset WHERE status_mutasi='selesai'
     AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())") : 0;
$can_manage_atk = hasRole(['admin', 'teknisi_atem', 'keuangan']);
$atk_user_filter = $can_manage_atk ? '' : ' AND user_id=' . $_uid;
$cnt_atk_pending = _hdr_count($pdo,
    "SELECT
        (SELECT COUNT(*) FROM atk_permintaan WHERE status='menunggu' $atk_user_filter)
        + (SELECT COUNT(*) FROM atk_pengajuan WHERE status='menunggu' $atk_user_filter)");
$cnt_atk_low_stock = _hdr_count($pdo,
    "SELECT COUNT(*) FROM atk_barang WHERE aktif=1 AND stok<=stok_minimum");

// Fitur pegawai / akreditasi — hanya di-query bila saklarnya aktif
$cnt_belum_absen = $cnt_cuti_approve_l1 = $cnt_cuti_approve_l2 = $cnt_cuti_saya = 0;
$cnt_berkas_exp  = $cnt_gaji_pending = $cnt_akreditasi_exp = 0;
$sudah_absen     = true;

if ($FITUR['pegawai']) {
    if ($_can_management) {
        $cnt_belum_absen = _hdr_count($pdo, "SELECT COUNT(*) FROM users WHERE status='aktif'
            AND id NOT IN (SELECT user_id FROM absensi WHERE DATE(tanggal)=CURDATE())");
        $cnt_cuti_approve_l2 = _hdr_count($pdo, "SELECT COUNT(*) FROM pengajuan_cuti
            WHERE status='menunggu' AND status_approver2='menunggu'
            AND (status_approver1='disetujui' OR approver1_id IS NULL)");
    }
    $sudah_absen = _hdr_count($pdo, "SELECT COUNT(*) FROM absensi WHERE user_id=? AND DATE(tanggal)=CURDATE()", [$_uid]) > 0;
    $cnt_cuti_approve_l1 = _hdr_count($pdo, "SELECT COUNT(*) FROM pengajuan_cuti
        WHERE approver1_id=? AND status_approver1='menunggu' AND status='menunggu'", [$_uid]);
    $cnt_cuti_saya = _hdr_count($pdo, "SELECT COUNT(*) FROM pengajuan_cuti WHERE user_id=? AND status='menunggu'", [$_uid]);
    $cnt_berkas_exp = _hdr_count($pdo, "SELECT COUNT(*) FROM berkas_karyawan
        WHERE user_id=? AND tgl_exp IS NOT NULL AND tgl_exp < DATE_ADD(NOW(),INTERVAL 30 DAY) AND tgl_exp >= NOW()", [$_uid]);
    if ($_can_penggajian) {
        $cnt_gaji_pending = _hdr_count($pdo, "SELECT COUNT(*) FROM transaksi_gaji WHERE status='draft'");
    }
}
$cnt_cuti_total = $cnt_cuti_approve_l1 + $cnt_cuti_approve_l2;

if ($FITUR['akreditasi'] && $_can_akreditasi) {
    $pokja_cond = $_is_admin ? '' : ($_pokja_id ? "AND pokja_id=" . $_pokja_id : '');
    $cnt_akreditasi_exp = _hdr_count($pdo, "SELECT COUNT(*) FROM dokumen_akreditasi
        WHERE status='aktif' AND tgl_exp IS NOT NULL
        AND tgl_exp BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) $pokja_cond");
}

// Daftar notifikasi untuk ikon lonceng: [ikon, warna, teks, jumlah|null, tautan]
$_notifs = [];
if ($cnt_menunggu)       $_notifs[] = ['fa-desktop',            '#3b82f6', 'Tiket IT menunggu',            $cnt_menunggu,       '/pages/antrian.php'];
if ($cnt_ipsrs_menunggu) $_notifs[] = ['fa-toolbox',            '#16a34a', 'Tiket IPSRS menunggu',         $cnt_ipsrs_menunggu, '/pages/antrian_ipsrs.php'];
if ($cnt_atem_menunggu)  $_notifs[] = ['fa-heart-pulse',        '#0284c7', 'Tiket ATEM menunggu',          $cnt_atem_menunggu,  '/pages/antrian_atem.php'];
if ($cnt_offline)        $_notifs[] = ['fa-wifi',               '#ef4444', 'Perangkat jaringan offline',   $cnt_offline,        '/pages/cek_koneksi.php'];
if ($cnt_mnt_it)         $_notifs[] = ['fa-screwdriver-wrench', '#f59e0b', 'Maintenance IT (7 hari)',      $cnt_mnt_it,         '/pages/maintenance_it.php'];
if ($cnt_mnt_ipsrs)      $_notifs[] = ['fa-wrench',             '#f59e0b', 'Maintenance IPSRS (7 hari)',   $cnt_mnt_ipsrs,      '/pages/maintenance_ipsrs.php'];
if ($cnt_mnt_atem)       $_notifs[] = ['fa-kit-medical',        '#f59e0b', 'Maintenance ATEM (7 hari)',    $cnt_mnt_atem,       '/pages/maintenance_atem.php'];
if ($_can_management && $cnt_belum_absen) $_notifs[] = ['fa-fingerprint',   '#f59e0b', 'Karyawan belum absen',      $cnt_belum_absen,    '/pages/absensi.php'];
if (!$sudah_absen && !$_can_management)   $_notifs[] = ['fa-camera',        '#f59e0b', 'Kamu belum absen hari ini', null,                '/pages/absen_kamera.php'];
if ($cnt_cuti_total)     $_notifs[] = ['fa-calendar-check',     '#8b5cf6', 'Pengajuan cuti perlu approval', $cnt_cuti_total,    '/pages/approval_cuti.php'];
if ($_can_akreditasi && $cnt_akreditasi_exp) $_notifs[] = ['fa-medal', '#d97706', 'Dokumen akreditasi akan kedaluwarsa', $cnt_akreditasi_exp, '/pages/data_dokumen.php?status=aktif'];
if ($cnt_berkas_exp)     $_notifs[] = ['fa-folder-open',        '#ea580c', 'Berkasmu akan kedaluwarsa',    $cnt_berkas_exp,     '/pages/profil.php#berkas'];
if ($_can_penggajian && $cnt_gaji_pending)   $_notifs[] = ['fa-money-bill-wave', '#22c55e', 'Transaksi gaji masih draft', $cnt_gaji_pending, '/pages/transaksi_gaji.php'];

/* ═══ GRUP MENU YANG TERBUKA ═══ */
$grp_tiket_it    = in_array($active_menu, ['antrian','semua_tiket','sla','buat_tiket','tiket_saya']);
$grp_tiket_ipsrs = in_array($active_menu, ['antrian_ipsrs','semua_tiket_ipsrs','sla_ipsrs','buat_tiket_sarpras','tiket_saya_ipsrs']);
$grp_tiket_atem  = in_array($active_menu, ['antrian_atem','semua_tiket_atem','sla_atem','buat_tiket_atem','tiket_saya_atem']);
$grp_aset_it     = in_array($active_menu, ['aset_it','maintenance_it','cek_koneksi','server_room','lacak_aset','mutasi_aset']);
$grp_aset_ipsrs  = in_array($active_menu, ['aset_ipsrs','maintenance_ipsrs']);
$grp_aset_atem   = in_array($active_menu, ['aset_atem','maintenance_atem']);
$grp_tiket_atk   = in_array($active_menu, ['atk_permintaan','atk_pengajuan','atk_laporan']);
$grp_aset_atk    = in_array($active_menu, ['atk_stok','atk_barang_masuk','atk_kartu_stok','atk_opname','atk_supplier']);
$grp_kategori    = in_array($active_menu, ['kategori','kategori_ipsrs','kategori_atem','kategori_atk']);
$can_kategori_atk = hasRole(['admin','teknisi_atem','keuangan']);
$grp_master      = in_array($active_menu, ['bagian','users','login_log']);
$grp_setting     = in_array($active_menu, ['setting_telegram','backup']);
$grp_management  = in_array($active_menu, ['master_karyawan','berkas_karyawan','master_berkas','jabatan','shift','jadwal','absensi','laporan_absen','lokasi_absen','master_shift','master_jadwal']);
$grp_absensi     = in_array($active_menu, ['absensi_diri','laporan_absen_saya']);
$grp_cuti        = in_array($active_menu, ['pengajuan_cuti','approval_cuti','master_cuti','laporan_cuti']);
$grp_penggajian  = in_array($active_menu, ['master_penerimaan','master_potongan','masterpph21','data_gaji','transaksi_gaji']);
$grp_akreditasi  = in_array($active_menu, ['master_pokja','input_dokumen','data_dokumen']);
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= clean($page_title ?? 'Dashboard') ?> | <?= APP_NAME ?></title>
<link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/uploads/logo.jpeg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= $_css_url ?>" rel="stylesheet">
<style>
:root {
  --footer-h: 46px;
  --sb-width: 220px;
  --topnav-h: 54px;
  --primary:        #00e5b0;
  --primary-dark:   #00c896;
  --primary-soft:   rgba(0,229,176,0.12);
  --primary-border: rgba(0,229,176,0.25);
  --primary-glow:   rgba(0,229,176,0.20);
  --sb-bg:           #0a0f14;
  --sb-border:       rgba(255,255,255,0.05);
  --sb-border-light: #1e2f42;
  --sb-text:         rgba(255,255,255,0.45);
  --sb-text-hover:   rgba(255,255,255,0.88);
  --sb-text-active:  #00e5b0;
  --sb-item-hover:   rgba(255,255,255,0.05);
  --sb-active-bg:    rgba(0,229,176,0.10);
  --tn-text:   #1e2d3d;
  --tn-muted:  #7a93a8;
  --content-bg: #f0f4f8;
  --danger:    #ff4d6d;
  --radius-sm: 7px;
  --radius-md: 10px;
  --transition: .18s ease;
}
*,*::before,*::after { box-sizing: border-box; }
body,p,div,h1,h2,h3,h4,h5,h6,a,li,td,th,label,input,button,select,textarea,table { font-family: 'Space Grotesk',sans-serif !important; }
.fa,.fas,.far,.fab,.fal,.fad,[class^="fa-"],[class*=" fa-"],i.fa,i.fas,i.far,i.fab { font-family: 'Font Awesome 6 Free','Font Awesome 6 Brands' !important; }
body { padding-top: 0 !important; margin-top: 0 !important; }

/* ─── SIDEBAR ─── */
.sidebar {
  position: fixed; top: 0; left: 0;
  bottom: 0;
  width: var(--sb-width);
  background: var(--sb-bg);
  display: flex; flex-direction: column;
  overflow-y: auto; overflow-x: hidden;
  z-index: 300;
  transition: transform var(--transition);
  border-right: 1px solid var(--sb-border-light);
  scrollbar-width: thin;
  scrollbar-color: rgba(0,229,176,0.12) transparent;
}
.sidebar::-webkit-scrollbar { width: 3px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(0,229,176,0.12); border-radius: 99px; }
/* .sidebar::before {
  content: ''; position: absolute; inset: 0; pointer-events: none; z-index: 0;
  background-image:
    linear-gradient(rgba(0,229,176,0.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,229,176,0.025) 1px, transparent 1px);
  background-size: 40px 40px;
} */
.sidebar > * { position: relative; z-index: 1; }
.sidebar.collapsed { transform: translateX(calc(-1 * var(--sb-width))); }
.sb-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 299; backdrop-filter: blur(3px); }
.sb-overlay.active { display: block; }

.sb-logo { display: flex; align-items: center; gap: 10px; padding: 18px 16px 14px; border-bottom: 1px solid var(--sb-border); flex-shrink: 0; }
.sb-logo-icon { width: 42px; height: 42px; background: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
.sb-logo-icon img { display: block; width: 100%; height: 100%; object-fit: contain; }
.sb-logo-name { font-family: 'Syne',sans-serif !important; font-size: 13px; font-weight: 800; color: #e8f0f8; letter-spacing: -.3px; line-height: 1.2; }
.sb-logo-sub  { font-size: 9.5px; color: rgba(255,255,255,0.25); margin-top: 1px; }
.sb-nav { flex: 1; padding: 4px 0 12px; overflow-y: auto; overflow-x: hidden; }
.sb-section { display: flex; align-items: center; gap: 8px; padding: 14px 21px 4px; font-size: 9.5px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(0,229,176,0.45); }
.sb-section::after { content: ''; flex: 1; height: 1px; background: var(--sb-border-light); }

.nav-item a {
  display: flex; align-items: center; gap: 9px;
  padding: 7px 13px; margin: 1px 8px;
  border-radius: var(--radius-sm);
  font-size: 12.5px; font-weight: 500;
  color: var(--sb-text) !important;
  text-decoration: none !important;
  transition: background var(--transition), color var(--transition), transform var(--transition);
  white-space: nowrap; overflow: hidden;
  border-left: 2px solid transparent;
}
.nav-item a:hover { background: var(--sb-item-hover) !important; color: var(--sb-text-hover) !important; transform: translateX(2px); }
.nav-item.active a { background: var(--sb-active-bg) !important; color: var(--sb-text-active) !important; border-left-color: var(--primary); font-weight: 600; }
.nav-item a .ni { width: 16px; text-align: center; font-size: 12.5px; flex-shrink: 0; opacity: .65; }
.nav-item.active a .ni { opacity: 1; }
.nav-item a .nl { flex: 1; overflow: hidden; text-overflow: ellipsis; }
.nc { font-size: 9.5px !important; font-weight: 700 !important; background: var(--danger) !important; color: #fff !important; padding: 1px 6px !important; border-radius: 20px !important; line-height: 1.6 !important; flex-shrink: 0 !important; }

details.nav-group { border: none; }
details.nav-group > summary.nav-group-hd {
  display: flex; align-items: center; gap: 9px;
  padding: 7px 13px; margin: 1px 8px;
  border-radius: var(--radius-sm);
  font-size: 10.5px; font-weight: 700;
  color: rgba(255,255,255,0.28);
  text-transform: uppercase; letter-spacing: .7px;
  cursor: pointer; list-style: none; user-select: none;
  transition: background var(--transition), color var(--transition);
}
details.nav-group > summary.nav-group-hd::-webkit-details-marker { display: none; }
details.nav-group > summary.nav-group-hd:hover { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.55); }
details.nav-group[open] > summary.nav-group-hd { color: var(--primary); background: rgba(0,229,176,0.06); }
details.nav-group > summary.nav-group-hd .ni-grp { width: 16px; text-align: center; font-size: 12.5px; flex-shrink: 0; }
details.nav-group > summary.nav-group-hd > span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
details.nav-group > summary.nav-group-hd > span.nc-grp { flex: 0 0 auto; overflow: visible; }
.nc-grp { font-size: 9.5px; font-weight: 700; background: var(--danger); color: #fff; padding: 1px 6px; border-radius: 20px; flex-shrink: 0; }
.caret-grp { font-size: 9px; color: rgba(255,255,255,0.15); flex-shrink: 0; transition: transform .25s cubic-bezier(.4,0,.2,1), color .2s ease; }
details.nav-group[open] > summary.nav-group-hd .caret-grp { transform: rotate(180deg); color: var(--primary); }
.nav-group-bd { overflow: hidden; }
.nav-group-bd .nav-item a { padding-left: 36px !important; font-size: 12px !important; color: rgba(255,255,255,0.35) !important; }
.nav-group-bd .nav-item a:hover { padding-left: 39px !important; color: rgba(255,255,255,0.8) !important; }
.nav-group-bd .nav-item.active a { color: var(--sb-text-active) !important; }

/* ─── TOPNAV ─── */
.topnav { position: fixed; top: 0; left: var(--sb-width); right: 0; height: var(--topnav-h); background: #fff; border-bottom: 1px solid rgba(0,229,176,0.15); box-shadow: 0 1px 0 rgba(0,229,176,0.08), 0 2px 12px rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: space-between; padding: 0 16px; z-index: 200; gap: 10px; transition: left var(--transition); }
.topnav::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg,var(--primary),var(--primary-dark),transparent); opacity: 0.5; pointer-events: none; }
.topnav.sb-collapsed { left: 0; }
.tn-left  { display: flex; align-items: center; gap: 10px; min-width: 0; }
.tn-right { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.btn-toggle, .tn-bell { position: relative; background: none; border: none; cursor: pointer; width: 34px; height: 34px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--tn-muted); font-size: 15px; transition: background var(--transition), color var(--transition); flex-shrink: 0; }
.btn-toggle:hover, .tn-drop:hover .tn-bell, .tn-drop.open .tn-bell { background: rgba(0,229,176,0.08); color: var(--primary); }
.tn-title { font-size: 13.5px !important; font-weight: 600 !important; color: var(--tn-text) !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Dropdown (dipakai lonceng & profil) */
.tn-drop { position: relative; }
.tn-dropdown { display: none; position: absolute; top: calc(100% + 8px); right: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: var(--radius-md); box-shadow: 0 8px 24px rgba(0,0,0,.1); min-width: 190px; max-width: calc(100vw - 24px); overflow: hidden; z-index: 500; }
.tn-drop.open .tn-dropdown { display: block; }
.tn-dropdown a { display: flex !important; align-items: center; gap: 8px; padding: 9px 14px; font-size: 12.5px; color: #374151 !important; text-decoration: none; transition: background var(--transition); }
.tn-dropdown a:hover { background: rgba(0,229,176,0.06); color: var(--primary) !important; }
.tn-dropdown a.red-link { color: #ef4444 !important; border-top: 1px solid #f1f5f9; }
.tn-dropdown a.red-link:hover { background: #fff1f2; }
.tn-dropdown a i { width: 14px; text-align: center; flex-shrink: 0; }
.tn-dd-head { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }
.tn-dd-name { font-size: 12.5px; font-weight: 600; color: var(--tn-text); }
.tn-dd-role { font-size: 11px; color: var(--tn-muted); margin-top: 2px; }
.tn-dd-badge { background: rgba(217,119,6,.15); color: #b45309; font-size: 9px; font-weight: 800; padding: 1px 5px; border-radius: 4px; }

/* Lonceng notifikasi */
.tn-bell-cnt { position: absolute; top: 1px; right: 0; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 20px; background: var(--danger); color: #fff; font-size: 9.5px; font-weight: 700; line-height: 1; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; }
.tn-notif-dd { min-width: 280px; }
.tn-notif-lb { flex: 1; min-width: 0; }
.tn-notif-ct { background: var(--primary-soft); border: 1px solid var(--primary-border); color: #006b52; font-size: 10.5px; font-weight: 700; padding: 1px 8px; border-radius: 20px; }
.tn-notif-empty { padding: 20px 14px; text-align: center; font-size: 12px; color: var(--tn-muted); }
.tn-notif-empty i { display: block; font-size: 22px; color: #22c55e; margin-bottom: 6px; }

/* Profil */
.tn-user { cursor: pointer; user-select: none; }
.tn-user-info { display: flex; align-items: center; gap: 7px; padding: 4px 8px; border-radius: var(--radius-sm); transition: background var(--transition); }
.tn-user:hover .tn-user-info, .tn-user.open .tn-user-info { background: rgba(0,229,176,0.08); }
.tn-user-av { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg,var(--primary),var(--primary-dark)); color: #0a0f14; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.tn-user-name { font-size: 12.5px; font-weight: 500; color: var(--tn-text); max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tn-caret { font-size: 9px; color: var(--tn-muted); transition: transform .2s; }
.tn-user.open .tn-caret { transform: rotate(180deg); }

/* ─── MAIN ─── */
.main {
  margin-left: var(--sb-width);
  margin-top: var(--topnav-h) !important;
  padding-top: 0 !important;
  min-height: calc(100vh - var(--topnav-h));
  width: calc(100% - var(--sb-width)); max-width: 100%;
  background: var(--content-bg);
  display: flex; flex-direction: column;
  transition: margin-left var(--transition);
}
.main.sb-collapsed { margin-left: 0; width: 100%; }
/* Elemen pertama di dalam .main (page-header) harus menempel ke topnav */
.main > *:first-child, .main > .page-header { margin-top: 0 !important; }

/* ─── FOOTER (menempel di bawah area konten) ─── */
.app-footer {
  position: static;
  width: 100%;
  height: var(--footer-h);
  flex: 0 0 var(--footer-h);
  margin-top: auto;
  display: flex; align-items: center; justify-content: center;
  gap: 12px; padding: 0 24px;
  background: #fff;
  border-top: 1px solid rgba(0,229,176,0.15);
  box-shadow: 0 -2px 12px rgba(0,0,0,0.04);
  font-size: 11.5px; line-height: 1; color: var(--tn-muted);
  white-space: nowrap;
}
.app-footer strong { color: var(--tn-text); font-weight: 600; }
.app-footer .af-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--primary); opacity: .7; flex-shrink: 0; }
.app-footer .af-ver { background: var(--primary-soft); border: 1px solid var(--primary-border); color: #006b52; padding: 3px 9px; border-radius: 20px; font-size: 10px; font-weight: 700; }

/* ─── MODAL ─── */
.modal-ov { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 1000; align-items: center; justify-content: center; padding: 16px; backdrop-filter: blur(4px); }
@keyframes mIn { from { opacity:0; transform:translateY(12px) scale(.97); } to { opacity:1; transform:translateY(0) scale(1); } }

/* ─── RESPONSIVE ─── */
@media (max-width:1024px) and (min-width:769px) { :root { --sb-width:200px; } .tn-user-name { display:none; } }
@media (max-width:768px) {
  :root { --sb-width: 240px; }
  .sidebar { bottom: 0; transform: translateX(calc(-1 * var(--sb-width))); z-index: 400; }
  .sidebar.mobile-open { transform: translateX(0); }
  .topnav { left: 0 !important; padding: 0 12px; }
  .main { margin-left: 0 !important; width: 100% !important; }
  .tn-user-name { display: none; }
  .app-footer { padding: 0 12px; gap: 8px; font-size: 11px; }
  .app-footer .af-dot, .app-footer .af-tag { display: none; }
  #m-about > div { flex-direction: column !important; max-height: 90vh; overflow-y: auto; }
  #m-about > div > div:first-child { width: 100% !important; padding: 20px !important; flex-direction: row !important; flex-wrap: wrap; justify-content: flex-start !important; text-align: left !important; gap: 12px; }
}
@media (max-width:400px) { .topnav { padding: 0 8px; gap: 6px; } }
</style>
</head>
<body>

<div class="sb-overlay" id="sb-overlay" onclick="closeSidebarMobile()"></div>

<!-- ══════════════════════════════════════
     SIDEBAR
══════════════════════════════════════ -->
<nav class="sidebar" id="sidebar">

  <div class="sb-logo">
    <div class="sb-logo-icon"><img src="<?= APP_URL ?>/uploads/logo.jpeg" alt="Logo Rumah Sakit Assalam"></div>
    <div>
      <div class="sb-logo-name">Helpdesk Assalam</div>
      <div class="sb-logo-sub">Pengaduan Sarana &amp; Prasarana</div>
    </div>
  </div>

  <div class="sb-nav">

  <!-- Dashboard -->
  <div class="nav-item <?= $active_menu==='dashboard'?'active':'' ?>">
    <a href="<?= APP_URL ?>/dashboard.php"><i class="fa fa-home ni"></i><span class="nl">Dashboard</span></a>
  </div>

  <!-- ══ ADMINISTRASI ══ -->
  <?php if ($_is_admin): ?>
  <div class="sb-section">Menu Admin</div>
  <details class="nav-group" <?= $grp_master?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-database ni-grp"></i><span>Master Data</span>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= $active_menu==='bagian'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/bagian.php"><i class="fa fa-building ni"></i><span class="nl">Bagian / Divisi</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='users'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/users.php"><i class="fa fa-users ni"></i><span class="nl">Pengguna</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='login_log'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/login_log.php"><i class="fa fa-shield-halved ni" style="color:#8b5cf6;"></i><span class="nl">Log Login</span></a>
      </div>
    </div>
  </details>

  <details class="nav-group" <?= $grp_setting?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-cog ni-grp"></i><span>Pengaturan</span>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= $active_menu==='setting_telegram'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/setting_telegram.php">
          <i class="fa fa-paper-plane ni" style="color:#0088cc;"></i><span class="nl">Notif Telegram</span>
          <?php try { $tg=$pdo->query("SELECT value FROM settings WHERE `key`='telegram_enabled'")->fetchColumn(); echo $tg=='1'?'<span style="width:7px;height:7px;border-radius:50%;background:#00e5b0;display:inline-block;flex-shrink:0;box-shadow:0 0 6px #00e5b0;"></span>':'<span style="width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.15);display:inline-block;flex-shrink:0;"></span>'; }catch(Exception $e){} ?>
        </a>
      </div>
      <div class="nav-item <?= $active_menu==='backup'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/backup.php">
          <i class="fa fa-database ni" style="color:#26B99A;"></i><span class="nl">Backup Database</span>
          <?php try { $bk=$pdo->query("SELECT value FROM settings WHERE `key`='backup_auto_enabled'")->fetchColumn(); echo $bk=='1'?'<span style="width:7px;height:7px;border-radius:50%;background:#26B99A;display:inline-block;flex-shrink:0;box-shadow:0 0 6px #26B99A;"></span>':'<span style="width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.15);display:inline-block;flex-shrink:0;"></span>'; }catch(Exception $e){} ?>
        </a>
      </div>
    </div>
  </details>
  <?php endif; ?>

  <!-- ══ MENU IT ══ -->
  <div class="sb-section">Menu IT</div>

<!-- Tiket IT -->
  <details class="nav-group" <?= $grp_tiket_it?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-desktop ni-grp"></i>
      <span>Tiket IT</span>
      <?php $_badge_it = $_can_manage_tiket_it ? $cnt_menunggu : $cnt_tiket_saya; if ($_badge_it): ?><span class="nc-grp"><?= $_badge_it ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <?php if ($_can_manage_tiket_it): ?>
      <div class="nav-item <?= $active_menu==='antrian'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/antrian.php"><i class="fa fa-inbox ni"></i><span class="nl">Antrian Tiket</span><?php if ($cnt_menunggu): ?><span class="nc"><?= $cnt_menunggu ?></span><?php endif; ?></a>
      </div>
      <div class="nav-item <?= $active_menu==='semua_tiket'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/semua_tiket.php"><i class="fa fa-ticket-alt ni"></i><span class="nl">Semua Tiket</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='sla'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/sla.php"><i class="fa fa-chart-line ni"></i><span class="nl">Laporan SLA</span></a>
      </div>
      <?php endif; ?>
      <div class="nav-item <?= $active_menu==='buat_tiket'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/buat_tiket.php"><i class="fa fa-plus-circle ni"></i><span class="nl">Buat Tiket IT</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='tiket_saya'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/tiket_saya.php"><i class="fa fa-list-alt ni"></i><span class="nl">Tiket Saya</span><?php if ($cnt_tiket_saya): ?><span class="nc"><?= $cnt_tiket_saya ?></span><?php endif; ?></a>
      </div>
    </div>
  </details>

  <!-- ASET IT -->
   <?php if ($_can_manage_aset_it): ?>
  <details class="nav-group" <?= $grp_aset_it?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-server ni-grp"></i>
      <span>Aset IT</span>
      <?php $cnt_aset_badge = $cnt_mnt_it + $cnt_offline; if ($cnt_aset_badge): ?><span class="nc-grp"><?= $cnt_aset_badge ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= $active_menu==='aset_it'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/aset_it.php"><i class="fa fa-server ni"></i><span class="nl">Aset IT</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='lacak_aset'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/lacak_aset_it.php"><i class="fa fa-location-crosshairs ni"></i><span class="nl">Lacak Aset</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='mutasi_aset'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/mutasi_aset.php"><i class="fa fa-right-left ni"></i><span class="nl">Mutasi Aset</span><?php if ($cnt_mutasi): ?><span class="nc" style="background:#7c3aed !important;"><?= $cnt_mutasi ?></span><?php endif; ?></a>
      </div>
      <div class="nav-item <?= $active_menu==='maintenance_it'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/maintenance_it.php"><i class="fa fa-screwdriver-wrench ni"></i><span class="nl">Maintenance IT</span><?php if ($cnt_mnt_it): ?><span class="nc" style="background:#f59e0b !important;"><?= $cnt_mnt_it ?></span><?php endif; ?></a>
      </div>
      <div class="nav-item <?= $active_menu==='cek_koneksi'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/cek_koneksi.php"><i class="fa fa-wifi ni"></i><span class="nl">Monitor Koneksi</span><?php if ($cnt_offline): ?><span class="nc"><?= $cnt_offline ?></span><?php endif; ?></a>
      </div>
      <div class="nav-item <?= $active_menu==='server_room'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/server_room.php"><i class="fa fa-server ni"></i><span class="nl">Monitoring Server</span></a>
      </div>
    </div>
  </details>
  <?php endif; ?>




  <!-- ══ MENU IPSRS ══ -->
  <div class="sb-section">Menu IPSRS</div>

  <!-- Tiket IPSRS -->
  <details class="nav-group" <?= $grp_tiket_ipsrs?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-toolbox ni-grp"></i>
      <span>Tiket IPSRS</span>
      <?php $_badge_ipsrs = $_can_manage_tiket_ipsrs ? $cnt_ipsrs_menunggu : $cnt_ipsrs_saya; if ($_badge_ipsrs): ?><span class="nc-grp"><?= $_badge_ipsrs ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <?php if ($_can_manage_tiket_ipsrs): ?>
      <div class="nav-item <?= $active_menu==='antrian_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/antrian_ipsrs.php"><i class="fa fa-inbox ni"></i><span class="nl">Antrian IPSRS</span><?php if ($cnt_ipsrs_menunggu): ?><span class="nc"><?= $cnt_ipsrs_menunggu ?></span><?php endif; ?></a>
      </div>
      <div class="nav-item <?= $active_menu==='semua_tiket_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/semua_tiket_ipsrs.php"><i class="fa fa-ticket-alt ni"></i><span class="nl">Semua Tiket IPSRS</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='sla_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/sla_ipsrs.php"><i class="fa fa-chart-line ni"></i><span class="nl">Laporan SLA IPSRS</span></a>
      </div>
      <?php endif; ?>
      <div class="nav-item <?= $active_menu==='buat_tiket_sarpras'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/buat_tiket_sarpras.php"><i class="fa fa-plus-circle ni"></i><span class="nl">Buat Tiket IPSRS</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='tiket_saya_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/tiket_saya_ipsrs.php"><i class="fa fa-list-alt ni"></i><span class="nl">Tiket IPSRS Saya</span><?php if ($cnt_ipsrs_saya): ?><span class="nc"><?= $cnt_ipsrs_saya ?></span><?php endif; ?></a>
      </div>
    </div>
  </details>

  <!-- ASET IPSRS -->
   <?php if ($_can_manage_aset_ipsrs): ?>
  <details class="nav-group" <?= $grp_aset_ipsrs?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-wrench ni-grp"></i>
      <span>Aset IPSRS</span>
      <?php if ($cnt_mnt_ipsrs): ?><span class="nc-grp"><?= $cnt_mnt_ipsrs ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= $active_menu==='aset_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/aset_ipsrs.php"><i class="fa fa-toolbox ni"></i><span class="nl">Aset IPSRS</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='maintenance_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/maintenance_ipsrs.php"><i class="fa fa-wrench ni"></i><span class="nl">Maintenance IPSRS</span><?php if ($cnt_mnt_ipsrs): ?><span class="nc" style="background:#f59e0b !important;"><?= $cnt_mnt_ipsrs ?></span><?php endif; ?></a>
      </div>
    </div>
  </details>
  <?php endif; ?>


  <!-- ══ MENU ATEM ══ -->
  <div class="sb-section">Menu ATEM</div>

  <!-- Tiket ATEM -->
  <details class="nav-group" <?= $grp_tiket_atem?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-heart-pulse ni-grp"></i>
      <span>Tiket ATEM</span>
      <?php $_badge_atem = $_can_manage_tiket_atem ? $cnt_atem_menunggu : $cnt_atem_saya; if ($_badge_atem): ?><span class="nc-grp"><?= $_badge_atem ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <?php if ($_can_manage_tiket_atem): ?>
      <div class="nav-item <?= $active_menu==='antrian_atem'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/antrian_atem.php"><i class="fa fa-inbox ni"></i><span class="nl">Antrian ATEM</span><?php if ($cnt_atem_menunggu): ?><span class="nc"><?= $cnt_atem_menunggu ?></span><?php endif; ?></a>
      </div>
      <div class="nav-item <?= $active_menu==='semua_tiket_atem'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/semua_tiket_atem.php"><i class="fa fa-ticket-alt ni"></i><span class="nl">Semua Tiket ATEM</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='sla_atem'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/sla_atem.php"><i class="fa fa-chart-line ni"></i><span class="nl">Laporan SLA ATEM</span></a>
      </div>
      <?php endif; ?>
      <div class="nav-item <?= $active_menu==='buat_tiket_atem'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/buat_tiket_atem.php"><i class="fa fa-plus-circle ni"></i><span class="nl">Buat Tiket ATEM</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='tiket_saya_atem'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/tiket_saya_atem.php"><i class="fa fa-list-alt ni"></i><span class="nl">Tiket ATEM Saya</span><?php if ($cnt_atem_saya): ?><span class="nc"><?= $cnt_atem_saya ?></span><?php endif; ?></a>
      </div>
    </div>
  </details>

  <!-- ASET ATEM -->
   <?php if ($_can_manage_aset_atem): ?>
  <details class="nav-group" <?= $grp_aset_atem?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-kit-medical ni-grp"></i>
      <span>Aset ATEM</span>
      <?php if ($cnt_mnt_atem): ?><span class="nc-grp"><?= $cnt_mnt_atem ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= $active_menu==='aset_atem'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/aset_atem.php"><i class="fa fa-kit-medical ni"></i><span class="nl">Aset ATEM</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='maintenance_atem'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/maintenance_atem.php"><i class="fa fa-screwdriver-wrench ni"></i><span class="nl">Maintenance ATEM</span><?php if ($cnt_mnt_atem): ?><span class="nc" style="background:#f59e0b !important;"><?= $cnt_mnt_atem ?></span><?php endif; ?></a>
      </div>
      <?php if ($_cur_role === 'teknisi_atem'): ?>
      <div class="nav-item <?= $active_menu==='kategori_atem'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/kategori_atem.php"><i class="fa fa-tags ni" style="color:#0ea5e9;"></i><span class="nl">Kategori ATEM</span></a>
      </div>
      <?php endif; ?>
    </div>
  </details>
  <?php endif; ?>


  <!-- ══ MENU ATK ══ -->
  <div class="sb-section">Menu ATK</div>
  <details class="nav-group" <?= $grp_tiket_atk?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-ticket-alt ni-grp"></i>
      <span>Tiket ATK</span>
      <?php if ($cnt_atk_pending): ?><span class="nc-grp"><?= $cnt_atk_pending ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= $active_menu==='atk_permintaan'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/atk.php?tab=permintaan"><i class="fa fa-cart-plus ni"></i><span class="nl">Permintaan ATK</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='atk_pengajuan'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/atk.php?tab=pengajuan"><i class="fa fa-file-circle-plus ni"></i><span class="nl">Pengajuan ATK</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='atk_laporan'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/atk.php?tab=laporan"><i class="fa fa-chart-column ni"></i><span class="nl">Laporan ATK</span></a>
      </div>
    </div>
  </details>
  <details class="nav-group" <?= $grp_aset_atk?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-boxes-stacked ni-grp"></i>
      <span>Aset ATK</span>
      <?php if ($cnt_atk_low_stock): ?><span class="nc-grp"><?= $cnt_atk_low_stock ?></span><?php endif; ?>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <div class="nav-item <?= $active_menu==='atk_stok'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/atk.php?tab=stok"><i class="fa fa-boxes-stacked ni"></i><span class="nl">Data Stok Barang</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='atk_barang_masuk'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/atk.php?tab=barang_masuk"><i class="fa fa-circle-down ni"></i><span class="nl">Barang Masuk</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='atk_kartu_stok'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/atk.php?tab=kartu_stok"><i class="fa fa-clipboard-list ni"></i><span class="nl">Kartu Stok</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='atk_opname'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/atk.php?tab=opname"><i class="fa fa-clipboard-check ni"></i><span class="nl">Stok Opname</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='atk_supplier'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/atk.php?tab=supplier"><i class="fa fa-truck ni"></i><span class="nl">Supplier</span></a>
      </div>
    </div>
  </details>

  <!-- ══ MENU KATEGORI ══ -->
  <?php if ($_can_kategori || $can_kategori_atk): ?>
  <div class="sb-section">Menu Kategori</div>
  <details class="nav-group" <?= $grp_kategori?'open':'' ?>>
    <summary class="nav-group-hd">
      <i class="fa fa-tags ni-grp"></i><span>Kategori Tiket</span>
      <i class="fa fa-chevron-down caret-grp"></i>
    </summary>
    <div class="nav-group-bd">
      <?php if ($_can_kategori): ?>
      <div class="nav-item <?= $active_menu==='kategori'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/kategori.php"><i class="fa fa-tags ni"></i><span class="nl">Kategori IT</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='kategori_ipsrs'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/kategori_ipsrs.php"><i class="fa fa-tags ni" style="color:#f97316;"></i><span class="nl">Kategori IPSRS</span></a>
      </div>
      <div class="nav-item <?= $active_menu==='kategori_atem'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/kategori_atem.php"><i class="fa fa-tags ni" style="color:#0ea5e9;"></i><span class="nl">Kategori ATEM</span></a>
      </div>
      <?php endif; ?>
      <?php if ($can_kategori_atk): ?>
      <div class="nav-item <?= $active_menu==='kategori_atk'?'active':'' ?>">
        <a href="<?= APP_URL ?>/pages/kategori_atk.php"><i class="fa fa-tags ni" style="color:#02fa44;"></i><span class="nl">Kategori ATK</span></a>
      </div>
      <?php endif; ?>
    </div>
  </details>
  <?php endif; ?>


  <!-- ══ PEGAWAI, BERITA ACARA, AKREDITASI (diatur lewat $FITUR) ══ -->
  <?php if (array_filter($FITUR)) include __DIR__ . '/sidebar_pegawai.php'; ?>

  </div><!-- /sb-nav -->
</nav><!-- /sidebar -->


<!-- ══════════════════════════════════════
     TOP NAV
══════════════════════════════════════ -->
<div class="topnav" id="topnav">
  <div class="tn-left">
    <button class="btn-toggle" id="btn-toggle" onclick="handleToggle()" aria-label="Buka atau tutup sidebar">
      <i class="fa fa-bars"></i>
    </button>
    <span class="tn-title"><?= clean($page_title ?? 'Dashboard') ?></span>
  </div>

  <div class="tn-right">

    <!-- Notifikasi -->
    <div class="tn-drop tn-notif" id="tn-notif">
      <button type="button" class="tn-bell" aria-label="Notifikasi" aria-haspopup="true">
        <i class="fa fa-bell"></i>
        <?php if ($_notifs): ?><span class="tn-bell-cnt"><?= count($_notifs) ?></span><?php endif; ?>
      </button>
      <div class="tn-dropdown tn-notif-dd">
        <div class="tn-dd-head"><div class="tn-dd-name">Perlu perhatian</div></div>
        <?php if (!$_notifs): ?>
        <div class="tn-notif-empty"><i class="fa fa-circle-check"></i>Tidak ada yang perlu ditangani</div>
        <?php else: foreach ($_notifs as [$n_ic, $n_cl, $n_lb, $n_ct, $n_hr]): ?>
        <a href="<?= APP_URL . $n_hr ?>">
          <i class="fa <?= $n_ic ?>" style="color:<?= $n_cl ?>;"></i>
          <span class="tn-notif-lb"><?= $n_lb ?></span>
          <?php if ($n_ct !== null): ?><span class="tn-notif-ct"><?= (int)$n_ct ?></span><?php endif; ?>
        </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Profil -->
    <div class="tn-drop tn-user" id="tn-user">
      <div class="tn-user-info" role="button" tabindex="0" aria-haspopup="true">
        <div class="tn-user-av"><?= getInitials($_SESSION['user_nama']) ?></div>
        <span class="tn-user-name"><?= clean($_SESSION['user_nama']) ?></span>
        <i class="fa fa-chevron-down tn-caret"></i>
      </div>
      <div class="tn-dropdown">
        <div class="tn-dd-head">
          <div class="tn-dd-name"><?= clean($_SESSION['user_nama']) ?></div>
          <div class="tn-dd-role">
            <?= $_role_display ?> &mdash; <?= clean($_SESSION['user_divisi'] ?? '-') ?>
            <?php if ($_is_akreditasi_flag): ?><span class="tn-dd-badge">+ AKREDITASI</span><?php endif; ?>
          </div>
        </div>
        <a href="<?= APP_URL ?>/pages/profil.php"><i class="fa fa-user"></i> Profil Saya</a>

        <?php if ($FITUR['pegawai']): ?>
        <a href="<?= APP_URL ?>/pages/profil.php#berkas">
          <i class="fa fa-folder-open"></i> Berkas Saya
          <?php if ($cnt_berkas_exp): ?><span class="tn-notif-ct" style="margin-left:auto;"><?= $cnt_berkas_exp ?></span><?php endif; ?>
        </a>
        <a href="<?= APP_URL ?>/pages/absen_kamera.php"><i class="fa fa-fingerprint"></i> Absen Sekarang</a>
        <a href="<?= APP_URL ?>/pages/pengajuan_cuti.php"><i class="fa fa-calendar-minus"></i> Ajukan Cuti</a>
        <?php endif; ?>
        <?php if ($FITUR['akreditasi'] && $_can_akreditasi): ?>
        <a href="<?= APP_URL ?>/pages/data_dokumen.php"><i class="fa fa-medal"></i> Dokumen Akreditasi</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/logout.php" class="red-link"><i class="fa fa-sign-out-alt"></i> Keluar</a>
      </div>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════
     SCRIPTS
══════════════════════════════════════ -->
<script>
function isMobile(){ return window.innerWidth<=768; }
function handleToggle(){
  const sb=document.getElementById('sidebar'),tn=document.getElementById('topnav'),mn=document.getElementById('main'),ov=document.getElementById('sb-overlay');
  if(!sb) return;
  if(isMobile()){
    sb.classList.contains('mobile-open')?(sb.classList.remove('mobile-open'),ov.classList.remove('active')):(sb.classList.add('mobile-open'),ov.classList.add('active'));
  } else {
    const c=sb.classList.contains('collapsed');
    if(c){sb.classList.remove('collapsed');tn&&tn.classList.remove('sb-collapsed');mn&&mn.classList.remove('sb-collapsed');try{localStorage.setItem('sb_collapsed','0');}catch(e){}}
    else{sb.classList.add('collapsed');tn&&tn.classList.add('sb-collapsed');mn&&mn.classList.add('sb-collapsed');try{localStorage.setItem('sb_collapsed','1');}catch(e){}}
  }
}
function closeSidebarMobile(){
  const sb=document.getElementById('sidebar'),ov=document.getElementById('sb-overlay');
  sb&&sb.classList.remove('mobile-open'); ov&&ov.classList.remove('active');
}
(function(){
  if(isMobile()) return;
  try{
    if(localStorage.getItem('sb_collapsed')==='1'){
      const sb=document.getElementById('sidebar'),tn=document.getElementById('topnav'),mn=document.getElementById('main');
      sb&&sb.classList.add('collapsed'); tn&&tn.classList.add('sb-collapsed'); mn&&mn.classList.add('sb-collapsed');
    }
  }catch(e){}
})();
window.addEventListener('resize',function(){
  if(!isMobile()){ const sb=document.getElementById('sidebar'),ov=document.getElementById('sb-overlay'); sb&&sb.classList.remove('mobile-open'); ov&&ov.classList.remove('active'); }
});
function openModal(id){ const el=document.getElementById(id); if(el){el.style.display='flex';document.body.style.overflow='hidden';} }
function closeModal(id){ const el=document.getElementById(id); if(el){el.style.display='none';document.body.style.overflow='';} }
document.addEventListener('click',function(e){ if(e.target&&e.target.classList.contains('modal-ov')) closeModal(e.target.id); });
document.addEventListener('keydown',function(e){ if(e.key==='Escape') document.querySelectorAll('.modal-ov').forEach(function(m){ if(m.style.display==='flex') closeModal(m.id); }); });

function copyText(text,btn){
  navigator.clipboard.writeText(text).then(()=>{
    const orig=btn.innerHTML;
    btn.innerHTML='<i class="fa fa-check"></i> Disalin!';
    btn.style.cssText='background:#00e5b0;color:#0a0f14;border-color:#00e5b0;border:1px solid;border-radius:5px;padding:3px 8px;font-size:10px;cursor:pointer;font-family:inherit;';
    setTimeout(()=>{ btn.innerHTML=orig; btn.style.cssText=''; },2000);
  });
}
setTimeout(function(){
  document.querySelectorAll('.alert').forEach(function(a){
    a.style.transition='opacity .4s'; a.style.opacity='0';
    setTimeout(function(){ if(a.parentNode) a.parentNode.removeChild(a); },400);
  });
},4000);

// Grup menu sidebar dengan animasi buka-tutup
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('details.nav-group').forEach(function(det){
    const bd=det.querySelector('.nav-group-bd'); if(!bd) return;
    if(det.open){ bd.style.maxHeight=bd.scrollHeight+'px'; bd.style.opacity='1'; }
    else{ bd.style.maxHeight='0'; bd.style.opacity='0'; bd.style.overflow='hidden'; }
    det.addEventListener('click',function(e){
      if(!e.target.closest('summary')) return;
      e.preventDefault();
      const isOpen=det.open;
      if(isOpen){
        bd.style.transition='max-height .25s cubic-bezier(.4,0,.2,1),opacity .2s ease';
        bd.style.maxHeight=bd.scrollHeight+'px'; bd.offsetHeight;
        bd.style.maxHeight='0'; bd.style.opacity='0';
        bd.addEventListener('transitionend',function onC(){ bd.removeEventListener('transitionend',onC); det.removeAttribute('open'); });
      } else {
        det.setAttribute('open','');
        bd.style.transition='none'; bd.style.maxHeight='0'; bd.style.opacity='0'; bd.offsetHeight;
        bd.style.transition='max-height .28s cubic-bezier(.4,0,.2,1),opacity .22s ease';
        bd.style.maxHeight=bd.scrollHeight+'px'; bd.style.opacity='1';
        bd.addEventListener('transitionend',function onO(){ bd.removeEventListener('transitionend',onO); bd.style.maxHeight='none'; });
      }
    });
  });
});

// Dropdown topnav (lonceng & profil): hanya satu yang terbuka, tutup saat klik di luar atau tekan Esc
(function(){
  var drops = document.querySelectorAll('.tn-drop');
  function closeAll(except){ drops.forEach(function(d){ if(d!==except) d.classList.remove('open'); }); }
  drops.forEach(function(d){
    d.addEventListener('click', function(e){
      if (e.target.closest('.tn-dropdown')) return;   // klik di dalam menu: biarkan tautan berjalan
      e.stopPropagation();
      closeAll(d); d.classList.toggle('open');
    });
  });
  document.addEventListener('click', function(){ closeAll(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeAll(); });
})();
</script>

<div class="main" id="main">