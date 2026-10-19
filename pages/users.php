<?php
// pages/users.php
session_start();
require_once '../config.php';
requireLogin();
if (!hasRole(['admin','hrd'])) { setFlash('danger', 'Akses ditolak.'); redirect(APP_URL . '/dashboard.php'); }
$page_title  = 'Pengguna';
$active_menu = 'users';

// ── Role yang bisa diberikan lewat halaman ini (Pokja/Akreditasi sudah dihapus dari sistem) ──
define('VALID_ROLES', ['user','teknisi','teknisi_ipsrs','teknisi_atem','admin','hrd','keuangan']);

$role_labels = [
    'admin'         => 'Admin',
    'teknisi'       => 'Teknisi IT',
    'teknisi_ipsrs' => 'Teknisi IPSRS',
    'teknisi_atem'  => 'Teknisi ATEM',
    'hrd'           => 'HRD',
    'keuangan'      => 'Keuangan',
    'akreditasi'    => 'Tim Akreditasi',   // role lama, hanya untuk tampilan
    'user'          => 'User',
];

$is_hrd   = ($_SESSION['user_role'] ?? '') === 'hrd';
$is_admin = ($_SESSION['user_role'] ?? '') === 'admin';

// HRD tidak bisa assign role admin atau keuangan
$assignable_roles = $is_hrd
    ? ['user','teknisi','teknisi_ipsrs','hrd']
    : VALID_ROLES;

// ── Migrate: pastikan kolom role sudah mendukung 'keuangan' ──────────────────
try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN `role` ENUM('user','teknisi','teknisi_ipsrs','teknisi_atem','admin','hrd','keuangan','akreditasi') NOT NULL DEFAULT 'user'");
} catch (Exception $e) {} // Abaikan jika sudah ada

// ── POST HANDLER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'tambah') {
        if ($is_hrd && !hasRole('admin')) {
            setFlash('danger','HRD tidak berwenang menambah akun Admin atau Keuangan.');
            redirect(APP_URL.'/pages/users.php');
        }
        $n = trim($_POST['nama']     ?? '');
        $u = strtolower(trim($_POST['username'] ?? ''));
        $p = $_POST['password']      ?? '';
        $r = $_POST['role']          ?? 'user';
        if (!in_array($r, $assignable_roles)) $r = 'user';

        if (!$n || !$u || strlen($p) < 6) {
            setFlash('danger','Harap isi nama, username, dan password (min 6 karakter).');
        } elseif (!preg_match('/^[a-z0-9._]{3,30}$/', $u)) {
            setFlash('warning','Username 3–30 karakter: huruf kecil, angka, titik, underscore.');
        } else {
            $st = $pdo->prepare("SELECT id FROM users WHERE username=?");
            $st->execute([$u]);
            if ($st->fetch()) {
                setFlash('warning','Username sudah digunakan.');
            } else {
                $pdo->prepare("INSERT INTO users (nama,username,email,password,role,status) VALUES (?,?,NULL,?,?,'aktif')")
                    ->execute([$n,$u,password_hash($p,PASSWORD_BCRYPT),$r]);
                setFlash('success',"Pengguna <strong>".clean($n)."</strong> berhasil ditambahkan.");
            }
        }
    }

    elseif ($act === 'edit') {
        $id = (int)($_POST['id']   ?? 0);
        $n  = trim($_POST['nama']  ?? '');
        $r  = $_POST['role']       ?? '';

        if ($is_hrd) {
            $tgt = $pdo->prepare("SELECT role FROM users WHERE id=?");
            $tgt->execute([$id]);
            $tgt_role = $tgt->fetchColumn();
            if (in_array($tgt_role, ['admin','keuangan'])) {
                setFlash('danger','HRD tidak berwenang mengubah akun Admin atau Keuangan.');
                redirect(APP_URL.'/pages/users.php');
            }
        }
        if (!in_array($r, $assignable_roles)) {
            setFlash('danger','Role tidak valid.');
            redirect(APP_URL.'/pages/users.php');
        }
        if (!$id || !$n || !$r) {
            setFlash('danger','Data tidak lengkap.');
        } elseif ($id === (int)$_SESSION['user_id'] && $r !== $_SESSION['user_role']) {
            setFlash('warning','Tidak bisa mengubah role akun Anda sendiri.');
        } else {
            $pdo->prepare("UPDATE users SET nama=?,role=? WHERE id=?")->execute([$n,$r,$id]);
            setFlash('success',"Data pengguna <strong>".clean($n)."</strong> berhasil diperbarui.");
        }
    }

    elseif ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            setFlash('warning','Tidak bisa menonaktifkan akun Anda sendiri.');
        } else {
            $pdo->prepare("UPDATE users SET status=IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
            setFlash('success','Status pengguna berhasil diubah.');
        }
    }

    elseif ($act === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $p  = $_POST['np'] ?? '';
        if (!$id)            setFlash('danger','ID tidak valid.');
        elseif (strlen($p)<6) setFlash('danger','Password minimal 6 karakter.');
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($p,PASSWORD_BCRYPT),$id]);
            setFlash('success','Password berhasil direset.');
        }
    }

    redirect(APP_URL.'/pages/users.php');
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
$users = $pdo->query("
    SELECT u.*,
           COALESCE(u.status,'aktif') AS status,
           (SELECT COUNT(*) FROM tiket       WHERE user_id    = u.id) AS req,
           (SELECT COUNT(*) FROM tiket       WHERE teknisi_id = u.id) AS handle,
           (SELECT COUNT(*) FROM tiket_ipsrs WHERE teknisi_id = u.id) AS handle_ipsrs,
           (SELECT COUNT(*) FROM tiket_ipsrs WHERE user_id    = u.id) AS req_ipsrs,
           (SELECT COUNT(*) FROM tiket_atem  WHERE teknisi_id = u.id) AS handle_atem,
           (SELECT COUNT(*) FROM tiket_atem  WHERE user_id    = u.id) AS req_atem
    FROM users u
    ORDER BY FIELD(u.role,'admin','hrd','keuangan','akreditasi','teknisi','teknisi_ipsrs','teknisi_atem','user'), u.nama
")->fetchAll();

$cnt_roles = ['admin'=>0,'teknisi'=>0,'teknisi_ipsrs'=>0,'teknisi_atem'=>0,'hrd'=>0,'keuangan'=>0,'user'=>0,'nonaktif'=>0];
$bagian_set = [];
$has_legacy_akr = false;
foreach ($users as $u) {
    if (($u['role'] ?? '') === 'akreditasi') $has_legacy_akr = true;
    $dv = trim((string)($u['divisi'] ?? ''));
    if ($dv !== '') $bagian_set[$dv] = true;
    if (($u['status']??'aktif') === 'nonaktif') { $cnt_roles['nonaktif']++; continue; }
    if (isset($cnt_roles[$u['role']])) $cnt_roles[$u['role']]++;
}
$bagian_opts = array_keys($bagian_set);
sort($bagian_opts, SORT_NATURAL | SORT_FLAG_CASE);

// Opsi filter role (role lama 'akreditasi' hanya muncul bila masih ada datanya)
$filter_roles = VALID_ROLES;
if ($has_legacy_akr) $filter_roles[] = 'akreditasi';

$rstyle = [
    'admin'         => ['bg'=>'#ede9fe','color'=>'#7c3aed','av'=>'av-purple','icon'=>'fa-shield-alt'],
    'teknisi'       => ['bg'=>'#dbeafe','color'=>'#1d4ed8','av'=>'av-blue',  'icon'=>'fa-wrench'],
    'teknisi_ipsrs' => ['bg'=>'#d1fae5','color'=>'#065f46','av'=>'av-green', 'icon'=>'fa-screwdriver-wrench'],
    'teknisi_atem'  => ['bg'=>'#e0f2fe','color'=>'#0369a1','av'=>'av-blue',  'icon'=>'fa-heart-pulse'],
    'hrd'           => ['bg'=>'#fce7f3','color'=>'#9d174d','av'=>'av-pink',  'icon'=>'fa-people-group'],
    'keuangan'      => ['bg'=>'#ecfeff','color'=>'#0e7490','av'=>'av-cyan',  'icon'=>'fa-coins'],
    'akreditasi'    => ['bg'=>'#fef9c3','color'=>'#854d0e','av'=>'',         'icon'=>'fa-medal'],
    'user'          => ['bg'=>'#f3f4f6','color'=>'#374151','av'=>'',         'icon'=>'fa-user'],
];

include '../includes/header.php';
?>

<style>
/* ── Kartu statistik (juga berfungsi sebagai filter cepat) ── */
.u-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:16px;}
.u-stat{background:#fff;border:1px solid #eef0f3;border-radius:10px;padding:11px 14px;display:flex;align-items:center;gap:11px;
  cursor:pointer;text-align:left;font-family:inherit;width:100%;transition:border-color .15s,box-shadow .15s,transform .15s;}
.u-stat:hover{border-color:#cbd5e1;box-shadow:0 2px 8px rgba(15,23,42,.07);transform:translateY(-1px);}
.u-stat.on{border-color:var(--c);box-shadow:0 0 0 3px var(--cbg);}
.u-stat-ic{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.u-stat-n{font-size:22px;font-weight:700;color:#1e293b;line-height:1;}
.u-stat-l{font-size:11px;color:#94a3b8;margin-top:3px;white-space:nowrap;}

/* ── Bar filter ── */
.u-filter{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 16px;border-bottom:1px solid #f1f5f9;background:#fafbfc;}
.u-search{position:relative;flex:1 1 230px;min-width:190px;}
.u-search i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px;pointer-events:none;}
.u-search input{padding-left:32px;}
.u-filter .form-control{height:36px;font-size:13px;}
.u-sel{flex:0 1 165px;min-width:135px;}
.u-reset{height:36px;white-space:nowrap;}
.u-count{margin-left:auto;font-size:12px;color:#64748b;white-space:nowrap;}
.u-count strong{color:#1e293b;}

/* ── Tabel ── */
.u-tbl td{vertical-align:middle;}
.u-name{display:flex;align-items:center;gap:10px;font-weight:600;color:#1e293b;}
.u-empty td{text-align:center;color:#94a3b8;padding:36px 20px;}
.u-empty .ic{width:50px;height:50px;border-radius:12px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;}

/* ── Modal tambah / edit ── */
.um-box{background:#fff;width:100%;max-width:820px;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.22);animation:mIn .2s ease;
  overflow:hidden;display:flex;flex-direction:column;max-height:92vh;}
.um-box form{display:flex;flex-direction:column;min-height:0;flex:1;}
.um-grid{display:grid;grid-template-columns:1fr 1fr;min-height:0;overflow-y:auto;}
.um-l{padding:20px;border-right:1px solid #f1f5f9;display:flex;flex-direction:column;gap:12px;}
.um-r{padding:20px;background:#fafbfc;display:flex;flex-direction:column;gap:10px;}
.um-sec{font-size:10px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;padding-bottom:6px;border-bottom:1.5px solid #f1f5f9;}
.um-lbl{font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:4px;}
.um-hint{font-size:10.5px;color:#94a3b8;margin-top:3px;}
.um-note{border-radius:7px;padding:9px 12px;font-size:11px;display:flex;align-items:flex-start;gap:7px;line-height:1.5;}
.um-note.warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
.um-note.ok{background:#f0fdf9;border:1px solid #a7f3d0;color:#065f46;margin-top:auto;}
.um-note i{margin-top:2px;flex-shrink:0;}
.um-roles{display:flex;flex-direction:column;gap:7px;}
.um-role{border:2px solid #e5e7eb;border-radius:8px;padding:8px 10px;cursor:pointer;transition:all .15s;display:flex;gap:9px;align-items:center;background:#fff;}
.um-role .ri{width:28px;height:28px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;}
.um-role .rt{font-size:12px;font-weight:700;}
.um-role .rd{font-size:10px;color:#94a3b8;line-height:1.3;}
@media(max-width:720px){
  .um-grid{grid-template-columns:1fr;}
  .um-l{border-right:none;border-bottom:1px solid #f1f5f9;}
  .u-count{margin-left:0;width:100%;}
  .u-sel{flex:1 1 calc(50% - 5px);}
}
</style>

<div class="page-header">
  <h4><i class="fa fa-users text-primary"></i> &nbsp;Pengguna</h4>
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span class="cur">Pengguna</span>
  </div>
</div>

<div class="content">
  <?= showFlash() ?>

  <!-- Stat cards (klik untuk memfilter) -->
  <div class="u-stats">
    <?php foreach ([
      ['admin',        'Admin',          'fa-shield-alt',          '#ede9fe','#7c3aed'],
      ['teknisi',      'Teknisi IT',     'fa-wrench',              '#dbeafe','#1d4ed8'],
      ['teknisi_ipsrs','Teknisi IPSRS',  'fa-screwdriver-wrench',  '#d1fae5','#065f46'],
      ['teknisi_atem', 'Teknisi ATEM',   'fa-heart-pulse',         '#e0f2fe','#0369a1'],
      ['hrd',          'HRD',            'fa-people-group',        '#fce7f3','#9d174d'],
      ['keuangan',     'Keuangan',       'fa-coins',               '#ecfeff','#0e7490'],
      ['user',         'User',           'fa-user',                '#f3f4f6','#374151'],
      ['nonaktif',     'Nonaktif',       'fa-ban',                 '#fee2e2','#991b1b'],
    ] as [$key,$lbl,$ic,$bg,$col]): ?>
    <button type="button" class="u-stat" data-k="<?=$key?>" style="--c:<?=$col?>;--cbg:<?=$bg?>;" title="Klik untuk memfilter">
      <span class="u-stat-ic" style="background:<?=$bg?>;"><i class="fa <?=$ic?>" style="color:<?=$col?>;font-size:14px;"></i></span>
      <span>
        <div class="u-stat-n"><?=$cnt_roles[$key]??0?></div>
        <div class="u-stat-l"><?=$lbl?></div>
      </span>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- Tabel -->
  <div class="panel">
    <div class="panel-hd">
      <h5>Daftar Pengguna <span style="color:#94a3b8;font-weight:400;">(<?=count($users)?>)</span></h5>
      <button class="btn btn-primary btn-sm" onclick="openModal('m-add')"><i class="fa fa-plus"></i> Tambah Pengguna</button>
    </div>

    <!-- Filter -->
    <div class="u-filter">
      <div class="u-search">
        <i class="fa fa-search"></i>
        <input type="text" id="f-q" class="form-control" placeholder="Cari nama atau username…" autocomplete="off">
      </div>
      <select id="f-role" class="form-control u-sel">
        <option value="">Semua Role</option>
        <?php foreach ($filter_roles as $fr): ?>
        <option value="<?=clean($fr)?>"><?=clean($role_labels[$fr] ?? ucfirst($fr))?></option>
        <?php endforeach; ?>
      </select>
      <select id="f-status" class="form-control u-sel">
        <option value="">Semua Status</option>
        <option value="aktif">Aktif</option>
        <option value="nonaktif">Nonaktif</option>
      </select>
      <select id="f-bagian" class="form-control u-sel">
        <option value="">Semua Bagian</option>
        <?php foreach ($bagian_opts as $bg): ?>
        <option value="<?=clean($bg)?>"><?=clean($bg)?></option>
        <?php endforeach; ?>
        <option value="__none">Tanpa bagian</option>
      </select>
      <button type="button" class="btn btn-default btn-sm u-reset" id="f-reset"><i class="fa fa-rotate-left"></i> Reset</button>
      <div class="u-count" id="u-count">Menampilkan <strong><?=count($users)?></strong> dari <?=count($users)?> pengguna</div>
    </div>

    <div class="panel-bd np tbl-wrap">
      <table class="u-tbl">
        <thead>
          <tr>
            <th style="width:46px;">No</th><th>Nama</th><th>Username</th><th>Bagian</th>
            <th>Role</th><th>Aktivitas</th><th>Status</th>
            <th style="text-align:center;width:170px;">Aksi</th>
          </tr>
        </thead>
        <tbody id="u-body">
          <?php foreach ($users as $u):
            $role   = $u['role']   ?? 'user';
            $status = $u['status'] ?? 'aktif';
            $rs     = $rstyle[$role] ?? $rstyle['user'];
            $rl     = $role_labels[$role] ?? ucfirst($role);
            $can_edit = !($is_hrd && in_array($role, ['admin','keuangan']));
            $divisi = trim((string)($u['divisi'] ?? ''));
            $search = mb_strtolower(($u['nama'] ?? '') . ' ' . ($u['username'] ?? ''));
          ?>
          <tr data-row
              data-q="<?=clean($search)?>"
              data-role="<?=clean($role)?>"
              data-status="<?=clean($status)?>"
              data-bagian="<?=clean($divisi)?>">
            <td class="u-no" style="color:#cbd5e1;"></td>
            <td>
              <div class="u-name">
                <div class="av av-sm <?=$rs['av']?>"><?=getInitials($u['nama'])?></div>
                <span><?=clean($u['nama'])?></span>
              </div>
            </td>
            <td style="font-family:monospace;font-size:12px;color:#64748b;">@<?=clean($u['username'])?></td>
            <td style="font-size:12px;color:#475569;"><?=$divisi !== '' ? clean($divisi) : '—'?></td>
            <td>
              <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:<?=$rs['bg']?>;color:<?=$rs['color']?>;white-space:nowrap;">
                <i class="fa <?=$rs['icon']?>" style="font-size:10px;"></i> <?=$rl?>
              </span>
            </td>
            <td style="font-size:12px;white-space:nowrap;">
              <?php if ($role==='teknisi'): ?><span style="color:#1d4ed8;"><i class="fa fa-wrench"></i> <?=(int)$u['handle']?> tiket IT</span>
              <?php elseif ($role==='teknisi_ipsrs'): ?><span style="color:#065f46;"><i class="fa fa-screwdriver-wrench"></i> <?=(int)$u['handle_ipsrs']?> tiket IPSRS</span>
              <?php elseif ($role==='teknisi_atem'): ?><span style="color:#0369a1;"><i class="fa fa-heart-pulse"></i> <?=(int)$u['handle_atem']?> tiket ATEM</span>
              <?php elseif ($role==='admin'): ?><span style="color:#7c3aed;"><i class="fa fa-shield-alt"></i> Administrator</span>
              <?php elseif ($role==='hrd'): ?><span style="color:#9d174d;"><i class="fa fa-people-group"></i> SDM &amp; Kepegawaian</span>
              <?php elseif ($role==='keuangan'): ?><span style="color:#0e7490;"><i class="fa fa-coins"></i> Penggajian &amp; Keuangan</span>
              <?php else: ?><span style="color:#1d4ed8;"><i class="fa fa-paper-plane"></i> <?=(int)$u['req']+(int)$u['req_ipsrs']+(int)$u['req_atem']?> tiket</span><?php endif; ?>
            </td>
            <td>
              <?php if ($status==='aktif'): ?>
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;background:#d1fae5;color:#065f46;">
                <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;"></span> Aktif
              </span>
              <?php else: ?>
              <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;background:#fee2e2;color:#991b1b;">
                <span style="width:6px;height:6px;border-radius:50%;background:#ef4444;"></span> Nonaktif
              </span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;white-space:nowrap;">
              <?php if ($u['id'] != (int)$_SESSION['user_id'] && $can_edit): ?>
              <button class="btn btn-warning btn-sm" onclick='editUser(<?=json_encode([
                "id"       => (int)$u["id"],
                "nama"     => $u["nama"],
                "username" => $u["username"],
                "role"     => $u["role"],
              ])?>)'>
                <i class="fa fa-edit"></i> Edit
              </button>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?=(int)$u['id']?>">
                <button type="submit" class="btn btn-sm <?=$status==='aktif'?'btn-default':'btn-success'?>"
                  title="<?=$status==='aktif'?'Nonaktifkan':'Aktifkan'?>"
                  onclick="return confirm('<?=$status==='aktif'?'Nonaktifkan':'Aktifkan'?> pengguna ini?')">
                  <i class="fa <?=$status==='aktif'?'fa-ban':'fa-check'?>"></i>
                </button>
              </form>
              <button class="btn btn-info btn-sm" title="Reset password" onclick="resetPwd(<?=(int)$u['id']?>,'<?=addslashes(clean($u['nama']))?>')">
                <i class="fa fa-key"></i>
              </button>
              <?php elseif ($u['id'] == (int)$_SESSION['user_id']): ?>
              <span style="font-size:11px;color:#94a3b8;font-style:italic;">Akun Anda</span>
              <?php else: ?>
              <span style="font-size:11px;color:#cbd5e1;"><i class="fa fa-lock"></i></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>

          <!-- Tampil saat hasil filter kosong / belum ada data -->
          <tr class="u-empty" id="u-empty" style="<?=empty($users)?'':'display:none;'?>">
            <td colspan="8">
              <div class="ic"><i class="fa fa-user-slash" style="font-size:20px;color:#cbd5e1;"></i></div>
              <div style="font-weight:600;color:#64748b;margin-bottom:3px;" id="u-empty-t"><?=empty($users)?'Belum ada pengguna':'Tidak ada pengguna yang cocok'?></div>
              <small id="u-empty-s"><?=empty($users)?'Klik + Tambah Pengguna untuk memulai.':'Ubah kata kunci atau klik Reset untuk menampilkan semua.'?></small>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>


<!-- ══ MODAL: Tambah ══ -->
<div class="modal-ov" id="m-add">
  <div class="um-box">
    <div class="modal-hd">
      <h5><i class="fa fa-user-plus"></i> Tambah Pengguna</h5>
      <button class="mc" onclick="closeModal('m-add')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="tambah">
      <div class="um-grid">

        <!-- Kiri: info akun -->
        <div class="um-l">
          <div class="um-sec"><i class="fa fa-id-card" style="color:#3b82f6;"></i> &nbsp;Informasi Akun</div>
          <div>
            <label class="um-lbl">Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" class="form-control" required placeholder="Nama lengkap">
          </div>
          <div>
            <label class="um-lbl">Username <span class="req">*</span></label>
            <input type="text" name="username" class="form-control" required autocomplete="off" autocapitalize="none"
                   maxlength="30" pattern="[a-zA-Z0-9._]{3,30}" placeholder="contoh: budi.santoso">
            <div class="um-hint">3–30 karakter: huruf, angka, titik, underscore.</div>
          </div>
          <div>
            <label class="um-lbl">Password <span class="req">*</span></label>
            <div style="position:relative;">
              <input type="password" name="password" id="a-pwd" class="form-control" placeholder="Min. 6 karakter" required autocomplete="new-password">
              <button type="button" onclick="togglePwd('a-pwd','a-eye')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:13px;">
                <i class="fa fa-eye" id="a-eye"></i>
              </button>
            </div>
          </div>
          <div class="um-note ok">
            <i class="fa fa-user-tie"></i>
            <div><strong>Atasan Langsung</strong> tidak diisi di sini. Karyawan memilih sendiri via <strong>Profil Saya</strong>.</div>
          </div>
        </div>

        <!-- Kanan: role -->
        <div class="um-r">
          <div class="um-sec"><i class="fa fa-shield-halved" style="color:#7c3aed;"></i> &nbsp;Pilih Role <span class="req">*</span></div>
          <div class="um-roles">
            <?php
            $role_opts = [
              ['user',         'User',           'fa-user',               '#f3f4f6','#374151','Buat & pantau tiket sendiri',       true],
              ['teknisi',      'Teknisi IT',     'fa-wrench',             '#dbeafe','#1d4ed8','Proses & selesaikan tiket IT',      false],
              ['teknisi_ipsrs','Teknisi IPSRS',  'fa-screwdriver-wrench', '#d1fae5','#065f46','Proses tiket Sarpras/IPSRS',        false],
              ['teknisi_atem', 'Teknisi ATEM',   'fa-heart-pulse',        '#e0f2fe','#0369a1','Proses tiket peralatan ATEM',      false],
              ['hrd',          'HRD',            'fa-people-group',       '#fce7f3','#9d174d','Manajemen SDM & kepegawaian',       false],
              ['keuangan',     'Keuangan',       'fa-coins',              '#ecfeff','#0e7490','Akses modul penggajian & keuangan', false],
              ['admin',        'Admin',          'fa-shield-alt',         '#ede9fe','#7c3aed','Akses penuh ke semua fitur',        false],
            ];
            if ($is_hrd) {
                $role_opts = array_filter($role_opts, fn($r) => !in_array($r[0], ['admin','keuangan']));
            }
            foreach ($role_opts as [$rv,$rl,$ri,$rbg,$rc,$rdesc,$checked]):
            ?>
            <label id="albl-<?=$rv?>" class="um-role" style="background:<?=$checked?$rbg:'#fff'?>;">
              <input type="radio" name="role" value="<?=$rv?>" <?=$checked?'checked':''?> style="flex-shrink:0;" onchange="hilightRoleAdd()">
              <span class="ri" style="background:<?=$rbg?>;"><i class="fa <?=$ri?>" style="color:<?=$rc?>;font-size:11px;"></i></span>
              <div style="min-width:0;">
                <div class="rt" style="color:<?=$rc?>;"><?=$rl?></div>
                <div class="rd"><?=$rdesc?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-add')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>


<!-- ══ MODAL: Edit ══ -->
<div class="modal-ov" id="m-edit">
  <div class="um-box">
    <div class="modal-hd">
      <h5><i class="fa fa-user-edit"></i> Edit Pengguna</h5>
      <button class="mc" onclick="closeModal('m-edit')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="e-id">
      <div class="um-grid">

        <!-- Kiri -->
        <div class="um-l">
          <div class="um-sec"><i class="fa fa-id-card" style="color:#3b82f6;"></i> &nbsp;Informasi Akun</div>
          <div class="um-note warn">
            <i class="fa fa-triangle-exclamation"></i>
            <div>Mengubah <strong>Role</strong> akan langsung mengubah hak akses di seluruh sistem.</div>
          </div>
          <div>
            <label class="um-lbl">Username</label>
            <input type="text" id="e-username" class="form-control" disabled style="background:#f8fafc;color:#64748b;font-family:monospace;">
          </div>
          <div>
            <label class="um-lbl">Nama Lengkap <span class="req">*</span></label>
            <input type="text" name="nama" id="e-nama" class="form-control" required>
          </div>
          <div class="um-note ok">
            <i class="fa fa-user-tie"></i>
            <div><strong>Atasan Langsung</strong> diatur mandiri via <strong>Profil Saya → Data Kepegawaian</strong>.</div>
          </div>
        </div>

        <!-- Kanan -->
        <div class="um-r">
          <div class="um-sec"><i class="fa fa-shield-halved" style="color:#7c3aed;"></i> &nbsp;Pilih Role <span class="req">*</span></div>
          <div class="um-roles" id="edit-role-grid">
            <?php
            $edit_role_opts = [
              ['user',         'User',           'fa-user',               '#f3f4f6','#374151','Buat & pantau tiket sendiri'],
              ['teknisi',      'Teknisi IT',     'fa-wrench',             '#dbeafe','#1d4ed8','Proses & selesaikan tiket IT'],
              ['teknisi_ipsrs','Teknisi IPSRS',  'fa-screwdriver-wrench', '#d1fae5','#065f46','Proses tiket Sarpras/IPSRS'],
              ['teknisi_atem', 'Teknisi ATEM',   'fa-heart-pulse',        '#e0f2fe','#0369a1','Proses tiket peralatan ATEM'],
              ['hrd',          'HRD',            'fa-people-group',       '#fce7f3','#9d174d','Manajemen SDM & kepegawaian'],
              ['keuangan',     'Keuangan',       'fa-coins',              '#ecfeff','#0e7490','Akses modul penggajian & keuangan'],
              ['admin',        'Admin',          'fa-shield-alt',         '#ede9fe','#7c3aed','Akses penuh ke semua fitur'],
            ];
            if ($is_hrd) {
                $edit_role_opts = array_filter($edit_role_opts, fn($r) => !in_array($r[0], ['admin','keuangan']));
            }
            foreach ($edit_role_opts as [$rv,$rl,$ri,$rbg,$rc,$rdesc]):
            ?>
            <label id="lbl-<?=$rv?>" class="um-role">
              <input type="radio" name="role" value="<?=$rv?>" style="flex-shrink:0;" onchange="hilightRole()">
              <span class="ri" style="background:<?=$rbg?>;"><i class="fa <?=$ri?>" style="color:<?=$rc?>;font-size:11px;"></i></span>
              <div style="min-width:0;">
                <div class="rt" style="color:<?=$rc?>;"><?=$rl?></div>
                <div class="rd"><?=$rdesc?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-edit')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>


<!-- ══ MODAL: Reset Password ══ -->
<div class="modal-ov" id="m-reset">
  <div class="modal-box sm">
    <div class="modal-hd">
      <h5><i class="fa fa-key"></i> Reset Password</h5>
      <button class="mc" onclick="closeModal('m-reset')"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="reset">
      <input type="hidden" name="id" id="r-id">
      <div class="modal-bd">
        <p style="font-size:13px;margin-bottom:13px;color:#475569;">
          <i class="fa fa-user" style="color:var(--primary);"></i> Reset password untuk: <strong id="r-nama"></strong>
        </p>
        <div class="form-group">
          <label>Password Baru <span class="req">*</span></label>
          <div style="position:relative;">
            <input type="password" name="np" id="r-pwd" class="form-control" placeholder="Min. 6 karakter" required autocomplete="new-password">
            <button type="button" onclick="togglePwd('r-pwd','r-eye')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:13px;">
              <i class="fa fa-eye" id="r-eye"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="modal-ft">
        <button type="button" class="btn btn-default" onclick="closeModal('m-reset')">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-key"></i> Reset</button>
      </div>
    </form>
  </div>
</div>


<script>
const roleColors = {
    user:         {border:'#d1d5db', bg:'#f9fafb'},
    teknisi:      {border:'#93c5fd', bg:'#eff6ff'},
    teknisi_ipsrs:{border:'#6ee7b7', bg:'#f0fdf4'},
    teknisi_atem: {border:'#7dd3fc', bg:'#f0f9ff'},
    hrd:          {border:'#f9a8d4', bg:'#fdf2f8'},
    keuangan:     {border:'#67e8f9', bg:'#ecfeff'},
    admin:        {border:'#c4b5fd', bg:'#faf5ff'},
};

function editUser(u) {
    document.getElementById('e-id').value       = u.id;
    document.getElementById('e-nama').value     = u.nama;
    document.getElementById('e-username').value = '@' + u.username;
    let found = false;
    document.querySelectorAll('#m-edit [name=role]').forEach(function(r) {
        r.checked = (r.value === u.role);
        if (r.checked) found = true;
    });
    if (!found) {
        const fb = document.querySelector('#m-edit [name=role][value=user]');
        if (fb) fb.checked = true;
    }
    hilightRole();
    openModal('m-edit');
}

function hilightRole() {
    document.querySelectorAll('#m-edit [name=role]').forEach(function(r) {
        const lbl = document.getElementById('lbl-' + r.value);
        if (!lbl) return;
        const c = roleColors[r.value] || {border:'#e5e7eb', bg:'#fff'};
        lbl.style.borderColor = r.checked ? c.border : '#e5e7eb';
        lbl.style.background  = r.checked ? c.bg     : '#fff';
    });
}

function hilightRoleAdd() {
    document.querySelectorAll('#m-add [name=role]').forEach(function(r) {
        const lbl = document.getElementById('albl-' + r.value);
        if (!lbl) return;
        const c = roleColors[r.value] || {border:'#e5e7eb', bg:'#fff'};
        lbl.style.borderColor = r.checked ? c.border : '#e5e7eb';
        lbl.style.background  = r.checked ? c.bg     : '#fff';
    });
}
hilightRoleAdd();

function resetPwd(id, nama) {
    document.getElementById('r-id').value = id;
    document.getElementById('r-nama').textContent = nama;
    document.getElementById('r-pwd').value = '';
    openModal('m-reset');
}

function togglePwd(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!inp) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
    } else {
        inp.type = 'password';
        if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
    }
}

/* ═════════ FILTER PENGGUNA ═════════ */
(function () {
    var rows   = Array.prototype.slice.call(document.querySelectorAll('#u-body tr[data-row]'));
    var total  = rows.length;
    var fQ     = document.getElementById('f-q');
    var fRole  = document.getElementById('f-role');
    var fStat  = document.getElementById('f-status');
    var fBag   = document.getElementById('f-bagian');
    var cnt    = document.getElementById('u-count');
    var empty  = document.getElementById('u-empty');
    var emptyT = document.getElementById('u-empty-t');
    var emptyS = document.getElementById('u-empty-s');
    var stats  = document.querySelectorAll('.u-stat');

    function apply() {
        var q = fQ.value.trim().toLowerCase(), role = fRole.value, st = fStat.value, bg = fBag.value;
        var n = 0;
        rows.forEach(function (tr) {
            var d = tr.dataset;
            var ok = (!q    || d.q.indexOf(q) !== -1)
                  && (!role || d.role === role)
                  && (!st   || d.status === st)
                  && (!bg   || (bg === '__none' ? d.bagian === '' : d.bagian === bg));
            tr.style.display = ok ? '' : 'none';
            if (ok) { n++; tr.querySelector('.u-no').textContent = n; }
        });
        cnt.innerHTML = 'Menampilkan <strong>' + n + '</strong> dari ' + total + ' pengguna';
        if (total === 0) {
            empty.style.display = '';
        } else if (n === 0) {
            emptyT.textContent = 'Tidak ada pengguna yang cocok';
            emptyS.textContent = 'Ubah kata kunci atau klik Reset untuk menampilkan semua.';
            empty.style.display = '';
        } else {
            empty.style.display = 'none';
        }
        syncStats();
    }

    function syncStats() {
        stats.forEach(function (b) {
            var k = b.dataset.k;
            var on = (k === 'nonaktif')
                ? (fStat.value === 'nonaktif' && fRole.value === '')
                : (fRole.value === k && fStat.value === 'aktif');
            b.classList.toggle('on', on);
        });
    }

    stats.forEach(function (b) {
        b.addEventListener('click', function () {
            var k = b.dataset.k;
            if (b.classList.contains('on')) { fRole.value = ''; fStat.value = ''; }
            else if (k === 'nonaktif')      { fRole.value = ''; fStat.value = 'nonaktif'; }
            else                            { fRole.value = k;  fStat.value = 'aktif'; }
            apply();
        });
    });

    fQ.addEventListener('input', apply);
    [fRole, fStat, fBag].forEach(function (el) { el.addEventListener('change', apply); });
    document.getElementById('f-reset').addEventListener('click', function () {
        fQ.value = ''; fRole.value = ''; fStat.value = ''; fBag.value = '';
        apply();
    });

    apply();
})();
</script>

<?php include '../includes/footer.php'; ?>