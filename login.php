<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => false,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);
require_once 'config.php';
require_once 'includes/login_helper.php';

if (isLoggedIn()) redirect(APP_URL . '/dashboard.php');

// ── DROPDOWN DATA ──────────────────────────────────────────────────────────────
$divisi_list  = [];
$jabatan_list = [];
try { $divisi_list  = getBagianList($pdo); }   catch (Exception $e) {}
try { $jabatan_list = $pdo->query("SELECT id,nama,kode FROM jabatan WHERE status='aktif' ORDER BY urutan ASC,level ASC,nama ASC")->fetchAll(); } catch (Exception $e) {}

// ── RATE LIMITER (login) ───────────────────────────────────────────────────────
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_SECS', 180);
function loginKey()        { return 'login_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x'); }
function getAttempts()     { $d = $_SESSION[loginKey()] ?? null; return $d ?: ['count'=>0,'first'=>0,'locked_until'=>0]; }
function recordFail()      { $k=loginKey();$d=getAttempts();if(!$d['count'])$d['first']=time();$d['count']++;if($d['count']>=MAX_ATTEMPTS)$d['locked_until']=time()+LOCKOUT_SECS;$_SESSION[$k]=$d; }
function resetAttempts()   { unset($_SESSION[loginKey()]); }
function isLocked()        { $d=getAttempts();if($d['locked_until']>time())return true;if($d['locked_until']>0&&$d['locked_until']<=time())resetAttempts();return false; }
function remainingSeconds(){ return max(0, getAttempts()['locked_until']-time()); }
function remainingAttempts(){ return max(0, MAX_ATTEMPTS-getAttempts()['count']); }

// ── CAPTCHA GENERATORS ─────────────────────────────────────────────────────────
function makeCaptcha(string $pfx = '') {
    $ops=['+','-','+','+','-']; $op=$ops[array_rand($ops)];
    if($op==='+'){$a=rand(1,9);$b=rand(1,9);$ans=$a+$b;}
    else{$a=rand(3,9);$b=rand(1,$a);$ans=$a-$b;}
    $_SESSION[$pfx.'captcha_answer']=$ans;
    return "$a $op $b";
}
// Login captcha
if (empty($_SESSION['captcha_answer']))     $captcha_soal = makeCaptcha();
else $captcha_soal = $_SESSION['captcha_soal'] ?? makeCaptcha();
$_SESSION['captcha_soal'] = $captcha_soal;

// Register captcha
if (empty($_SESSION['reg_captcha_answer'])) $reg_captcha = makeCaptcha('reg_');
else $reg_captcha = $_SESSION['reg_captcha_soal'] ?? makeCaptcha('reg_');
$_SESSION['reg_captcha_soal'] = $reg_captcha;

// ── CSRF TOKENS ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token']))     $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
if (empty($_SESSION['csrf_token_reg'])) $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));

// ── USERNAME GENERATOR ─────────────────────────────────────────────────────────
function generateUsername(PDO $pdo, string $nama): string {
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/','',explode(' ',trim($nama))[0]));
    if (strlen($base)<3) $base='user'.$base;
    $u=$base; $i=1;
    while(true){$st=$pdo->prepare("SELECT id FROM users WHERE username=?");$st->execute([$u]);if(!$st->fetch())break;$u=$base.$i++;}
    return $u;
}

// ── STATE ─────────────────────────────────────────────────────────────────────
$login_error = '';
$login_msg   = '';
$reg_errors  = [];
$reg_success = false;
$show_reg    = false;   // auto-open modal jika register gagal

$msg_map = ['timeout'=>'Sesi Anda berakhir, silakan login kembali.','logout'=>'Anda berhasil keluar.'];
if (isset($msg_map[$_GET['msg'] ?? ''])) $login_msg = $msg_map[$_GET['msg']];

// ══════════════════════════════════════════════════════════════
// POST HANDLERS
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_act'] ?? 'login';

    /* ─── LOGIN ─── */
    if ($act === 'login') {
        $log_ip     = getClientIP();
        $log_ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log_parsed = parseUserAgent($log_ua);
        $log_input  = trim($_POST['email'] ?? '');

        if (isLocked()) {
            $sisa = remainingSeconds();
            $login_error = "Terlalu banyak percobaan. Coba lagi dalam <strong>{$sisa} detik</strong>.";
            recordLoginLog($pdo,['user_id'=>null,'username_input'=>$log_input,'status'=>'terkunci','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>"Terkunci {$sisa}s",'is_new_ip'=>0]);

        } elseif (!hash_equals($_SESSION['csrf_token']??'', $_POST['csrf_token']??'')) {
            $login_error = 'Permintaan tidak valid. Muat ulang halaman.';

        } elseif ((int)($_POST['captcha']??-999) !== (int)($_SESSION['captcha_answer']??-1)) {
            $login_error  = 'Jawaban captcha salah.';
            $captcha_soal = makeCaptcha(); $_SESSION['captcha_soal'] = $captcha_soal;
            recordFail();
            recordLoginLog($pdo,['user_id'=>null,'username_input'=>$log_input,'status'=>'gagal','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>'Captcha salah','is_new_ip'=>0]);

        } else {
            $u = trim($_POST['email']??''); $p = $_POST['password']??'';
            if (!$u || !$p) {
                $login_error = 'Email dan password wajib diisi.';
            } else {
                $st = $pdo->prepare("SELECT * FROM users WHERE email=? AND status='aktif' LIMIT 1");
                $st->execute([$u]); $user = $st->fetch();
                if ($user && password_verify($p, $user['password'])) {
                    $new_ip = isNewIPForUser($pdo,(int)$user['id'],$log_ip);
                    recordLoginLog($pdo,['user_id'=>$user['id'],'username_input'=>$u,'status'=>'berhasil','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>null,'is_new_ip'=>$new_ip?1:0]);
                    resetAttempts(); session_regenerate_id(true);
                    $_SESSION['user_id']=$user['id']; $_SESSION['user_nama']=$user['nama'];
                    $_SESSION['user_role']=$user['role']; $_SESSION['user_divisi']=$user['divisi'];
                    $_SESSION['pokja_id']=$user['pokja_id']??null;
                    $_SESSION['is_akreditasi']=(int)($user['is_akreditasi']??0);
                    $_SESSION['last_activity']=time(); $_SESSION['login_ip']=$log_ip;
                    unset($_SESSION['csrf_token'],$_SESSION['captcha_answer'],$_SESSION['captcha_soal']);
                    if ($new_ip) setFlash('warning','<i class="fa fa-shield-halved"></i> Login dari lokasi baru <strong>('.htmlspecialchars($log_ip).')</strong>.');
                    redirect(APP_URL.'/dashboard.php');
                } else {
                    recordFail();
                    $captcha_soal = makeCaptcha(); $_SESSION['captcha_soal'] = $captcha_soal;
                    $sc = remainingAttempts();
                    recordLoginLog($pdo,['user_id'=>$user['id']??null,'username_input'=>$u,'status'=>'gagal','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>$user?'Password salah':'Email tidak ditemukan','is_new_ip'=>0]);
                    if (isLocked())   { $s=remainingSeconds(); $login_error="Akun dikunci <strong>".LOCKOUT_SECS." detik</strong>."; }
                    elseif ($sc<=2)   { $login_error="Email atau password salah. <strong>Sisa: {$sc}×</strong>"; }
                    else              { $login_error = 'Email atau password salah.'; }
                }
            }
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /* ─── REGISTER ─── */
    if ($act === 'register') {
        $show_reg = true;

        if (!hash_equals($_SESSION['csrf_token_reg']??'', $_POST['csrf_token_reg']??'')) {
            $reg_errors[] = 'Permintaan tidak valid.';

        } elseif ((int)($_POST['reg_captcha']??-999) !== (int)($_SESSION['reg_captcha_answer']??-1)) {
            $reg_errors[] = 'Jawaban captcha salah.';
            $reg_captcha = makeCaptcha('reg_'); $_SESSION['reg_captcha_soal'] = $reg_captcha;

        } else {
            $nama    = trim($_POST['nama']         ?? '');
            $email   = trim($_POST['reg_email']    ?? '');
            $divisi  = $_POST['divisi']             ?? '';
            $no_hp   = trim($_POST['no_hp']        ?? '');
            $nik     = trim($_POST['nik']          ?? '');
            $pass    = $_POST['reg_password']      ?? '';
            $cnf     = $_POST['reg_confirm']       ?? '';

            if (!$nama)                                     $reg_errors[] = 'Nama lengkap wajib diisi.';
            if (!filter_var($email,FILTER_VALIDATE_EMAIL))  $reg_errors[] = 'Format email tidak valid.';
            if (!$nik)                                      $reg_errors[] = 'NIK RS wajib diisi.';
            if (strlen($pass) < 6)                          $reg_errors[] = 'Password minimal 6 karakter.';
            if ($pass !== $cnf)                             $reg_errors[] = 'Konfirmasi password tidak cocok.';

            // Cek NIK duplikat
            if (!$reg_errors && $nik) {
                try {
                    $ck = $pdo->prepare("SELECT u.nama FROM sdm_karyawan s JOIN users u ON u.id=s.user_id WHERE s.nik_rs=? LIMIT 1");
                    $ck->execute([$nik]); $who = $ck->fetchColumn();
                    if ($who) $reg_errors[] = 'NIK RS sudah digunakan oleh <strong>'.htmlspecialchars($who).'</strong>.';
                } catch (Exception $e) {}
            }

            if (empty($reg_errors)) {
                $dup = $pdo->prepare("SELECT id FROM users WHERE email=?"); $dup->execute([$email]);
                if ($dup->fetch()) {
                    $reg_errors[] = 'Email sudah digunakan. Silakan login atau gunakan email lain.';
                } else {
                    $uname = generateUsername($pdo, $nama);
                    $pdo->prepare("INSERT INTO users (nama,username,email,password,divisi,no_hp) VALUES (?,?,?,?,?,?)")
                        ->execute([$nama,$uname,$email,password_hash($pass,PASSWORD_BCRYPT),$divisi?:null,$no_hp?:null]);
                    $new_id = (int)$pdo->lastInsertId();
                    try {
                        $pdo->prepare("
                            INSERT INTO sdm_karyawan (user_id,nik_rs,no_hp,divisi,jabatan_id,updated_by)
                            VALUES (?,?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE
                                nik_rs=COALESCE(VALUES(nik_rs),nik_rs),
                                no_hp=COALESCE(VALUES(no_hp),no_hp),
                                divisi=COALESCE(VALUES(divisi),divisi)
                        ")->execute([$new_id,$nik?:null,$no_hp?:null,$divisi?:null,null,$new_id]);
                    } catch (Exception $e) {}

                    $reg_success = true;
                    $show_reg    = false;
                    $login_msg   = 'Akun berhasil dibuat! Silakan login dengan email dan password Anda.';
                    unset($_SESSION['reg_captcha_answer'],$_SESSION['reg_captcha_soal']);
                    $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
                }
            }
            if (!empty($reg_errors)) {
                $reg_captcha = makeCaptcha('reg_'); $_SESSION['reg_captcha_soal'] = $reg_captcha;
                $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
            }
        }
    }
}

$attempts_data = getAttempts(); $is_locked = isLocked(); $sisa_coba = remainingAttempts();
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Login — <?= APP_NAME ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ══ RESET ══ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);}

/* ══ PALET — hijau lembut, tidak terlalu terang ══ */
:root{
  --bg:#d9e3dc;
  --panel:#e9f0eb;
  --card:#f1f6f2;
  --field:#f6f9f7;
  --edge:#c3d3c8;
  --edge2:#aec3b5;
  --lime:#0e9455;
  --lime2:#0b7f49;
  --lime-t:#0a7040;
  --lime-dim:rgba(14,148,85,.10);
  --on-accent:#ffffff;
  --rose:#be123c;
  --amber:#b45309;
  --success:#047857;
  --text:#17291f;
  --text2:#42594b;
  --text3:#667f70;
  --mono:'DM Mono',monospace;
  --sans:'Outfit',sans-serif;
}

/* ══ BACKGROUND (tanpa grid) ══ */
.bg{position:fixed;inset:0;z-index:0;pointer-events:none;}
.bg-photo{position:absolute;inset:0;background:url('<?= APP_URL ?>/uploads/bglogin.png') center/cover no-repeat;}
.bg-photo::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,rgba(217,227,220,.95) 0%,rgba(217,227,220,.88) 55%,rgba(217,227,220,.95) 100%);}
.orb{position:absolute;border-radius:50%;filter:blur(110px);}
.o1{width:520px;height:520px;background:radial-gradient(circle,rgba(14,148,85,.10),transparent 70%);top:-160px;left:-140px;}
.o2{width:420px;height:420px;background:radial-gradient(circle,rgba(14,148,85,.07),transparent 70%);bottom:-100px;right:-80px;}
.ticker{position:absolute;bottom:0;left:0;right:0;height:32px;background:rgba(233,240,235,.9);border-top:1px solid var(--edge);overflow:hidden;display:flex;align-items:center;}
.ticker-inner{display:flex;align-items:center;animation:tick 80s linear infinite;white-space:nowrap;}
.ti{font-family:var(--mono);font-size:10px;color:var(--text3);padding:0 20px;}
.ts{color:var(--edge2);font-size:7px;}
@keyframes tick{from{transform:translateX(0);}to{transform:translateX(-50%);}}

/* ══ SHELL ══ */
.shell{position:fixed;inset:0;z-index:1;display:grid;grid-template-rows:56px 1fr 32px;grid-template-columns:1fr 440px;}

/* ── TOPBAR ── */
.topbar{grid-column:1/-1;grid-row:1;display:flex;align-items:center;justify-content:space-between;padding:0 32px;background:rgba(233,240,235,.95);backdrop-filter:blur(12px);border-bottom:1px solid var(--edge);}
.brand{display:flex;align-items:center;gap:12px;}
.brand-logo{height:34px;width:auto;display:block;border-radius:6px;background:#fff;}
.brand-name{font-size:16px;font-weight:700;color:var(--text);letter-spacing:-.2px;}
.brand-name span{color:var(--lime);}
.brand-sep{width:1px;height:20px;background:var(--edge2);}
.brand-sub{font-size:12px;color:var(--text3);}
.topbar-r{display:flex;align-items:center;gap:16px;}
.sys-pill{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:500;color:var(--lime-t);background:var(--lime-dim);border:1px solid rgba(14,148,85,.22);padding:4px 11px;border-radius:999px;}
.sdot{width:6px;height:6px;border-radius:50%;background:var(--lime);animation:blink 2.5s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.3;}}
.tclock{font-family:var(--mono);font-size:12px;color:var(--text3);min-width:62px;text-align:right;}

/* ── LEFT ── */
.left{grid-column:1;grid-row:2;display:flex;flex-direction:column;justify-content:center;padding:0 clamp(32px,6vw,88px);overflow:hidden;}
.left-in{width:100%;max-width:560px;}
.hl-eye{font-size:12px;font-weight:600;color:var(--lime-t);margin-bottom:14px;}
.hl-h1{font-size:clamp(30px,3.4vw,48px);font-weight:800;line-height:1.08;letter-spacing:-1.5px;margin-bottom:14px;}
.hl-h1 .h2{color:var(--lime);}
.hl-p{font-size:clamp(13px,1.1vw,15px);color:var(--text2);line-height:1.7;max-width:460px;margin-bottom:0;}

/* ── RIGHT — LOGIN ── */
.right{grid-column:2;grid-row:2;background:var(--panel);border-left:1px solid var(--edge);display:flex;flex-direction:column;justify-content:center;padding:24px 40px;overflow:hidden;}
.fh{margin-bottom:20px;}
.fh-t{font-size:clamp(22px,2vw,28px);font-weight:800;letter-spacing:-.8px;margin-bottom:4px;}
.fh-s{font-size:13px;color:var(--text2);}

/* Alerts */
.alert{display:flex;align-items:flex-start;gap:9px;padding:10px 12px;border-radius:8px;font-size:12.5px;line-height:1.5;margin-bottom:14px;animation:aIn .25s ease;}
@keyframes aIn{from{opacity:0;transform:translateY(-3px);}to{opacity:1;transform:none;}}
.alert i{margin-top:2px;flex-shrink:0;font-size:12px;}
.a-danger{background:rgba(190,18,60,.08);border:1px solid rgba(190,18,60,.25);color:#9f1239;}
.a-success{background:rgba(4,120,87,.09);border:1px solid rgba(4,120,87,.25);color:#065f46;}

/* Attempts */
.att-h{display:flex;justify-content:space-between;font-size:11px;color:var(--text3);margin-bottom:6px;}
.att-h strong{color:var(--text);}
.att-t{display:flex;gap:4px;margin-bottom:14px;}
.att-s{flex:1;height:3px;border-radius:2px;background:var(--edge2);transition:background .3s;}
.att-s.w{background:var(--amber);}
.att-s.d{background:var(--rose);}

/* Fields */
.field{margin-bottom:14px;}
.fl{font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px;}
.fw{position:relative;}
.fi{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:13px;pointer-events:none;transition:color .2s;z-index:1;}
.fw:focus-within .fi{color:var(--lime);}
.finp{width:100%;height:44px;background:var(--field);border:1px solid var(--edge2);border-radius:8px;color:var(--text);font-family:var(--sans);font-size:14px;padding:0 42px 0 40px;outline:none;transition:border-color .2s,box-shadow .2s;appearance:none;}
.finp:focus{border-color:var(--lime);box-shadow:0 0 0 3px var(--lime-dim);}
.finp::placeholder{color:var(--text3);}
.eye-btn{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;padding:5px;font-size:13px;transition:color .2s;}
.eye-btn:hover{color:var(--lime);}

/* Captcha login */
.cap-box{background:var(--card);border:1px solid var(--edge);border-radius:8px;padding:12px 14px;margin-bottom:14px;}
.cap-lbl{font-size:12px;font-weight:600;color:var(--text2);display:flex;align-items:center;gap:6px;margin-bottom:10px;}
.cap-lbl i{color:var(--lime);font-size:12px;}
.cap-row{display:flex;align-items:center;gap:10px;}
.cap-expr{font-family:var(--mono);font-size:20px;font-weight:500;color:var(--lime-t);background:var(--lime-dim);border:1px solid rgba(14,148,85,.2);padding:6px 16px;border-radius:6px;letter-spacing:2px;white-space:nowrap;flex-shrink:0;}
.cap-eq{font-family:var(--mono);font-size:18px;color:var(--text3);flex-shrink:0;}
.cap-inp{width:64px;height:40px;background:var(--field);border:1px solid var(--edge2);border-radius:6px;color:var(--text);font-family:var(--mono);font-size:18px;font-weight:500;text-align:center;outline:none;transition:border-color .2s,box-shadow .2s;-moz-appearance:textfield;}
.cap-inp::-webkit-outer-spin-button,.cap-inp::-webkit-inner-spin-button{-webkit-appearance:none;}
.cap-inp:focus{border-color:var(--lime);box-shadow:0 0 0 3px var(--lime-dim);}

.extras{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
.rem{display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--text2);cursor:pointer;user-select:none;}
.rem input{accent-color:var(--lime);width:14px;height:14px;}
.fgot{font-size:12.5px;font-weight:500;color:var(--lime-t);text-decoration:none;}
.fgot:hover{text-decoration:underline;}

.btn-go{width:100%;height:46px;background:var(--lime);border:none;border-radius:8px;color:var(--on-accent);font-family:var(--sans);font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;transition:background .2s,transform .2s;}
.btn-go:hover{background:var(--lime2);transform:translateY(-1px);}
.btn-go:active{transform:none;}
.btn-go:disabled{opacity:.5;cursor:not-allowed;transform:none;}

.sec-note{display:flex;align-items:center;justify-content:center;gap:7px;margin-top:16px;font-size:11px;color:var(--text3);text-align:center;}
.sec-note i{color:var(--lime);font-size:11px;flex-shrink:0;}
.reg-row{text-align:center;margin-top:14px;padding-top:14px;border-top:1px solid var(--edge);font-size:13px;color:var(--text2);}
.reg-btn{background:none;border:none;color:var(--lime-t);cursor:pointer;font-family:var(--sans);font-size:13px;font-weight:600;padding:0;}
.reg-btn:hover{text-decoration:underline;}

/* Lockout */
.lock-state{text-align:center;padding:12px 0;}
.lk-ring{width:60px;height:60px;border:2px solid var(--rose);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--rose);margin:0 auto 14px;animation:lp 2s ease-in-out infinite;}
@keyframes lp{0%,100%{box-shadow:0 0 0 0 rgba(190,18,60,.3);}50%{box-shadow:0 0 0 10px rgba(190,18,60,0);}}
.lk-t{font-size:20px;font-weight:800;letter-spacing:-.5px;margin-bottom:4px;}
.lk-cnt{font-family:var(--mono);font-size:clamp(36px,4vw,52px);font-weight:500;color:var(--lime-t);letter-spacing:-2px;line-height:1;margin:6px 0 10px;}
.lk-s{font-size:12px;color:var(--text3);line-height:1.7;}

/* ══ REGISTER MODAL ══ */
.reg-overlay{display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,35,24,.55);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:16px;}
.reg-overlay.open{display:flex;animation:ovIn .2s ease;}
@keyframes ovIn{from{opacity:0;}to{opacity:1;}}
.reg-modal{background:var(--panel);border:1px solid var(--edge2);border-radius:14px;width:100%;max-width:620px;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 28px 70px rgba(10,35,22,.3);animation:modalUp .25s ease;overflow:hidden;}
@keyframes modalUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:none;}}

.rm-head{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;background:var(--card);border-bottom:1px solid var(--edge);flex-shrink:0;}
.rm-head-l{display:flex;align-items:center;gap:12px;}
.rm-icon{width:38px;height:38px;background:var(--lime);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--on-accent);font-size:15px;flex-shrink:0;}
.rm-title{font-size:16px;font-weight:700;letter-spacing:-.3px;}
.rm-sub{font-size:12px;color:var(--text3);margin-top:2px;}
.rm-close{width:32px;height:32px;border-radius:8px;border:none;background:var(--lime-dim);color:var(--text2);cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0;}
.rm-close:hover{background:var(--rose);color:#fff;}

.rm-body{padding:20px 22px;overflow-y:auto;flex:1;scrollbar-width:thin;scrollbar-color:var(--edge2) transparent;}
.rm-body::-webkit-scrollbar{width:5px;}
.rm-body::-webkit-scrollbar-thumb{background:var(--edge2);border-radius:3px;}

.rm-alert{display:flex;align-items:flex-start;gap:9px;padding:10px 13px;border-radius:8px;font-size:12.5px;margin-bottom:14px;animation:aIn .25s ease;}
.rm-alert i{margin-top:2px;flex-shrink:0;font-size:12px;}
.rm-a-danger{background:rgba(190,18,60,.08);border:1px solid rgba(190,18,60,.25);color:#9f1239;}
.rm-alert ul{margin:4px 0 0 16px;}
.rm-alert ul li{margin-bottom:2px;}

.rm-sec{font-size:12px;font-weight:700;color:var(--lime-t);display:flex;align-items:center;gap:8px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--edge);}
.rm-sec:not(:first-child){margin-top:20px;}
.rm-sec i{font-size:11px;}

.rm-g2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.rm-fg{margin-bottom:12px;}
.rm-fl{font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px;}
.rm-fl .req{color:var(--rose);}
.rm-iw{position:relative;}
.rm-ii{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:12px;pointer-events:none;transition:color .2s;z-index:1;}
.rm-iw:focus-within .rm-ii{color:var(--lime);}
.rm-inp{width:100%;height:42px;background:var(--field);border:1px solid var(--edge2);border-radius:8px;color:var(--text);font-family:var(--sans);font-size:13.5px;padding:0 38px 0 36px;outline:none;transition:border-color .2s,box-shadow .2s;appearance:none;}
.rm-inp:focus{border-color:var(--lime);box-shadow:0 0 0 3px var(--lime-dim);}
.rm-inp::placeholder{color:var(--text3);font-size:13px;}
.rm-inp.sel{cursor:pointer;}
.rm-inp option{background:var(--field);color:var(--text);}
.rm-eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;padding:4px;font-size:12px;transition:color .2s;}
.rm-eye:hover{color:var(--lime);}
.rm-hint{font-size:11px;color:var(--text3);margin-top:4px;display:flex;align-items:center;gap:5px;line-height:1.5;}
.rm-hint i{color:var(--lime);font-size:10px;flex-shrink:0;}

.nik-box{background:var(--lime-dim);border:1px solid rgba(14,148,85,.2);border-radius:8px;padding:10px 12px;margin:2px 0 14px;font-size:12px;color:var(--text2);display:flex;gap:9px;line-height:1.6;}
.nik-box i{color:var(--lime);font-size:12px;flex-shrink:0;margin-top:3px;}
.nik-box table{border-collapse:collapse;width:100%;}
.nik-box td{padding:1px 10px 1px 0;vertical-align:top;}
.nik-box td:first-child{color:var(--lime-t);font-weight:700;white-space:nowrap;}

.rm-str-bar{height:3px;background:var(--edge2);border-radius:2px;margin-top:6px;overflow:hidden;}
.rm-str-fill{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}
.rm-str-lbl{font-size:11px;margin-top:4px;font-weight:600;}
.rm-match{font-size:11px;margin-top:4px;font-weight:600;}

.rm-cap{background:var(--card);border:1px solid var(--edge);border-radius:8px;padding:12px 14px;margin-bottom:6px;}
.rm-cap-lbl{font-size:12px;font-weight:600;color:var(--text2);display:flex;align-items:center;gap:6px;margin-bottom:10px;}
.rm-cap-lbl i{color:var(--lime);}
.rm-cap-row{display:flex;align-items:center;gap:10px;}
.rm-cap-expr{font-family:var(--mono);font-size:20px;font-weight:500;color:var(--lime-t);background:var(--lime-dim);border:1px solid rgba(14,148,85,.2);padding:6px 16px;border-radius:6px;letter-spacing:2px;white-space:nowrap;flex-shrink:0;}
.rm-cap-eq{font-family:var(--mono);font-size:18px;color:var(--text3);flex-shrink:0;}
.rm-cap-inp{width:64px;height:40px;background:var(--field);border:1px solid var(--edge2);border-radius:6px;color:var(--text);font-family:var(--mono);font-size:18px;font-weight:500;text-align:center;outline:none;transition:border-color .2s,box-shadow .2s;-moz-appearance:textfield;}
.rm-cap-inp::-webkit-outer-spin-button,.rm-cap-inp::-webkit-inner-spin-button{-webkit-appearance:none;}
.rm-cap-inp:focus{border-color:var(--lime);box-shadow:0 0 0 3px var(--lime-dim);}

.rm-foot{padding:14px 22px;border-top:1px solid var(--edge);flex-shrink:0;background:var(--card);}
.rm-btn{width:100%;height:46px;background:var(--lime);border:none;border-radius:8px;color:var(--on-accent);font-family:var(--sans);font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;transition:background .2s,transform .2s;}
.rm-btn:hover{background:var(--lime2);transform:translateY(-1px);}
.rm-btn:active{transform:none;}
.rm-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;}
.rm-back{text-align:center;margin-top:12px;font-size:12.5px;color:var(--text2);}
.rm-back-btn{background:none;border:none;color:var(--lime-t);cursor:pointer;font-family:var(--sans);font-size:12.5px;font-weight:600;padding:0;}
.rm-back-btn:hover{text-decoration:underline;}

.rm-success{text-align:center;padding:32px 20px;}
.rm-suc-ring{width:70px;height:70px;border-radius:50%;background:rgba(4,120,87,.12);border:2px solid rgba(4,120,87,.35);display:flex;align-items:center;justify-content:center;font-size:28px;color:var(--success);margin:0 auto 16px;animation:pop .4s cubic-bezier(.34,1.56,.64,1);}
@keyframes pop{from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;}}
.rm-suc-t{font-size:20px;font-weight:800;letter-spacing:-.5px;margin-bottom:8px;}
.rm-suc-s{font-size:13px;color:var(--text2);line-height:1.8;margin-bottom:20px;}
.rm-suc-btn{display:inline-flex;align-items:center;gap:8px;background:var(--lime);color:var(--on-accent);font-family:var(--sans);font-size:13.5px;font-weight:600;padding:11px 24px;border-radius:8px;border:none;cursor:pointer;transition:background .2s;}
.rm-suc-btn:hover{background:var(--lime2);}

/* ── RESPONSIVE ── */
@media(max-width:1100px){.shell{grid-template-columns:1fr 400px;}.right{padding:24px 32px;}}
@media(max-width:960px){
  .shell{grid-template-columns:1fr;}
  .left{display:none;}
  .right{grid-column:1;border-left:none;padding:24px;}
  .brand-sep,.brand-sub{display:none;}
}
@media(max-width:620px){.rm-g2{grid-template-columns:1fr;}}
@media(max-width:480px){.right{padding:18px;}.topbar{padding:0 18px;}.tclock{display:none;}}
@media(max-height:700px){
  .fh{margin-bottom:12px;}.field{margin-bottom:10px;}.finp{height:40px;}
  .cap-box{padding:9px 12px;margin-bottom:10px;}.extras{margin-bottom:12px;}
  .btn-go{height:42px;}.sec-note{margin-top:10px;}.reg-row{margin-top:10px;padding-top:10px;}
  
}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;}}
</style>
</head>
<body>

<!-- BACKGROUND -->
<div class="bg">
  <div class="bg-photo"></div>
  <div class="orb o1"></div><div class="orb o2"></div>
  <div class="ticker">
    <div class="ticker-inner">
      <?php
      $mItems=['Dashboard','Tiket IT','Antrian Tiket','Semua Tiket','Laporan SLA','Buat Tiket IT','Tiket Saya','Tiket IPSRS','Antrian IPSRS','Semua Tiket IPSRS','Laporan SLA IPSRS','Buat Tiket IPSRS','Berita Acara IT','Aset IT','Lacak Aset','Mutasi Aset','Maintenance IT','Monitoring Koneksi','Monitoring Server','Master Karyawan','Berkas Karyawan','Master Berkas','Jabatan','Shift Kerja','Jadwal','Laporan Kehadiran','Lokasi Absen','Kategori IT','Kategori IPSRS','Bagian / Divisi','Pengguna','Log Login','Notif Telegram','Backup Database','Absen Sekarang','Rekap Absensi','Ajukan Cuti','Approval Cuti','Master Cuti','Laporan Cuti','Profil Saya','Data Kepegawaian','Berkas Saya','Ganti Password'];
      $all = array_merge($mItems,$mItems);
      foreach($all as $m){echo '<span class="ti">'.htmlspecialchars($m).'</span><span class="ts">◆</span>';}
      ?>
    </div>
  </div>
</div>

<!-- SHELL -->
<div class="shell">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="brand">
      <img src="<?= APP_URL ?>/uploads/logo.jpeg" alt="Logo RS Assalam" class="brand-logo">
      <div class="brand-name">RS<span>Assalam</span></div>
      <div class="brand-sep"></div>
      <div class="brand-sub">Sistem Helpdesk RS Assalam</div>
    </div>
    <div class="topbar-r">
      <div class="sys-pill"><div class="sdot"></div>System Online</div>
      <div class="tclock" id="clock">--:--:--</div>
    </div>
  </header>

  <!-- LEFT -->
  <div class="left">
    <div class="left-in">
      <div class="hl-eye">Pusat Layanan Sarana dan Prasarana</div>
      <h1 class="hl-h1">Sistem Helpdesk<br><span class="h2">RS Assalam</span></h1>
      <p class="hl-p">Platform manajemen umum untuk aset dan inventaris IT, IPSRS, ATEM, serta laporan tiket unit dalam satu dashboard.</p>
    </div>
  </div>

  <!-- RIGHT — LOGIN -->
  <div class="right">

    <div class="fh">
      <div class="fh-t">Masuk Akun</div>
      <div class="fh-s">Gunakan email &amp; password yang terdaftar</div>
    </div>

    <?php if($login_error): ?>
    <div class="alert a-danger"><i class="fa fa-circle-exclamation"></i><span><?= $login_error ?></span></div>
    <?php endif; ?>
    <?php if($login_msg): ?>
    <div class="alert a-success"><i class="fa fa-circle-check"></i><span><?= htmlspecialchars($login_msg) ?></span></div>
    <?php endif; ?>

    <?php if($is_locked): ?>
    <div class="lock-state">
      <div class="lk-ring"><i class="fa fa-lock"></i></div>
      <div class="lk-t">Akun Dikunci Sementara</div>
      <div class="lk-cnt" id="countdown"><?= gmdate('i:s', remainingSeconds()) ?></div>
      <div class="lk-s">Terlalu banyak percobaan gagal.<br>Tunggu hingga hitungan selesai.</div>
    </div>
    <?php else: ?>

    <?php if($attempts_data['count'] > 0): ?>
    <div class="att-h">
      <span>Gagal: <strong><?= $attempts_data['count'] ?>/<?= MAX_ATTEMPTS ?></strong></span>
      <span>Sisa: <strong style="color:<?= $sisa_coba<=2?'var(--rose)':'var(--lime-t)' ?>;"><?= $sisa_coba ?>×</strong></span>
    </div>
    <div class="att-t">
      <?php for($i=0;$i<MAX_ATTEMPTS;$i++): $cls=$i<$attempts_data['count']?($sisa_coba<=2?'d':'w'):''; ?>
      <div class="att-s <?= $cls ?>"></div>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="lform" autocomplete="off">
      <input type="hidden" name="_act" value="login">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div class="field">
        <label class="fl">Email</label>
        <div class="fw">
          <i class="fa fa-at fi"></i>
          <input type="email" name="email" class="finp" placeholder="nama@rumahsakit.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autofocus autocomplete="email">
        </div>
      </div>

      <div class="field">
        <label class="fl">Password</label>
        <div class="fw">
          <i class="fa fa-key fi"></i>
          <input type="password" name="password" id="lpwd" class="finp" placeholder="••••••••••" autocomplete="current-password">
          <button type="button" class="eye-btn" onclick="tgl('lpwd','lec')" tabindex="-1"><i class="fa fa-eye" id="lec"></i></button>
        </div>
      </div>

      <div class="cap-box">
        <div class="cap-lbl"><i class="fa fa-shield-halved"></i> Verifikasi anti-bot</div>
        <div class="cap-row">
          <div class="cap-expr"><?= htmlspecialchars($captcha_soal) ?></div>
          <div class="cap-eq">=</div>
          <input type="number" name="captcha" class="cap-inp" placeholder="?" required autocomplete="off" min="0" max="99">
        </div>
      </div>

      <div class="extras">
        <label class="rem"><input type="checkbox" name="remember"> Ingat saya</label>
        <a href="#" class="fgot">Lupa password?</a>
      </div>

      <button type="submit" class="btn-go" id="btn-go">
        <i class="fa fa-arrow-right-to-bracket"></i> Masuk ke Sistem
      </button>
    </form>
    <?php endif; ?>

    <div class="sec-note">
      <i class="fa fa-shield-halved"></i>
      <span>Sesi terenkripsi • Akses berbasis peran • Log aktivitas aktif</span>
    </div>
    <div class="reg-row">
      Belum punya akun?
      <button type="button" class="reg-btn" onclick="openReg()">Daftar di sini</button>
    </div>
  </div>

</div><!-- /shell -->


<!-- ════════════════════════════════════════════
     MODAL REGISTER
════════════════════════════════════════════ -->
<div id="reg-overlay" class="reg-overlay <?= $show_reg ? 'open' : '' ?>" onclick="if(event.target===this)closeReg()">
  <div class="reg-modal">

    <!-- Header -->
    <div class="rm-head">
      <div class="rm-head-l">
        <div class="rm-icon"><i class="fa fa-user-plus"></i></div>
        <div>
          <div class="rm-title">Buat Akun Baru</div>
          <div class="rm-sub">Isi data untuk mendaftar ke sistem FixSmart</div>
        </div>
      </div>
      <button type="button" class="rm-close" onclick="closeReg()"><i class="fa fa-times"></i></button>
    </div>

    <!-- Body -->
    <div class="rm-body">

      <?php if($reg_success): ?>
      <!-- ── SUCCESS STATE ── -->
      <div class="rm-success">
        <div class="rm-suc-ring"><i class="fa fa-check"></i></div>
        <div class="rm-suc-t">Akun Berhasil Dibuat!</div>
        <div class="rm-suc-s">
          Selamat, akun Anda sudah terdaftar di sistem.<br>
          Silakan login menggunakan <strong style="color:var(--lime-t);">email</strong> dan password Anda.
        </div>
        <button type="button" class="rm-suc-btn" onclick="closeReg()">
          <i class="fa fa-sign-in-alt"></i> Tutup &amp; Login Sekarang
        </button>
      </div>

      <?php else: ?>

      <?php if(!empty($reg_errors)): ?>
      <div class="rm-alert rm-a-danger">
        <i class="fa fa-circle-exclamation"></i>
        <div>
          <strong>Harap perbaiki kesalahan berikut:</strong>
          <ul>
            <?php foreach($reg_errors as $re): ?><li><?= $re ?></li><?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" id="rform" autocomplete="off">
        <input type="hidden" name="_act" value="register">
        <input type="hidden" name="csrf_token_reg" value="<?= htmlspecialchars($_SESSION['csrf_token_reg']) ?>">

        <!-- DATA DIRI -->
        <div class="rm-sec"><i class="fa fa-user"></i> Data Diri</div>

        <div class="rm-g2">
          <div class="rm-fg">
            <label class="rm-fl">Nama Lengkap <span class="req">*</span></label>
            <div class="rm-iw">
              <i class="fa fa-id-card rm-ii"></i>
              <input type="text" name="nama" class="rm-inp" placeholder="Nama lengkap..."
                     value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
            </div>
          </div>
          <div class="rm-fg">
            <label class="rm-fl">NIK RS <span class="req">*</span></label>
            <div class="rm-iw">
              <i class="fa fa-id-badge rm-ii"></i>
              <input type="text" name="nik" class="rm-inp" placeholder="Nomor induk karyawan RS..."
                     value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>" maxlength="30">
            </div>
            <div class="rm-hint"><i class="fa fa-circle-info"></i> Bukan NIK KTP 16 digit</div>
          </div>
        </div>

        <!-- NIK info table -->
        <div class="nik-box">
          <i class="fa fa-circle-info"></i>
          <div>
            <table>
              <tr><td>NIK RS</td><td>Nomor induk internal dari rumah sakit → isi di sini</td></tr>
              <tr><td>NIK KTP</td><td>16 digit nomor identitas → dilengkapi di Data Kepegawaian</td></tr>
            </table>
          </div>
        </div>

        <div class="rm-fg">
          <label class="rm-fl">Email <span class="req">*</span></label>
          <div class="rm-iw">
            <i class="fa fa-envelope rm-ii"></i>
            <input type="email" name="reg_email" class="rm-inp" placeholder="email@domain.com"
                   value="<?= htmlspecialchars($_POST['reg_email'] ?? '') ?>">
          </div>
          <div class="rm-hint"><i class="fa fa-circle-info"></i> Email digunakan untuk login ke sistem</div>
        </div>

        <div class="rm-g2">
          <div class="rm-fg">
            <label class="rm-fl">Divisi / Unit</label>
            <div class="rm-iw">
              <i class="fa fa-building rm-ii"></i>
              <select name="divisi" class="rm-inp sel">
                <option value="">-- Pilih Divisi --</option>
                <?php foreach($divisi_list as $d): ?>
                <option value="<?= htmlspecialchars($d['nama']) ?>"
                        <?= (($_POST['divisi']??'') === $d['nama']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($d['nama']) ?><?= $d['kode'] ? ' ('.$d['kode'].')' : '' ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="rm-fg">
            <label class="rm-fl">No. HP / WhatsApp</label>
            <div class="rm-iw">
              <i class="fa fa-phone rm-ii"></i>
              <input type="text" name="no_hp" class="rm-inp" placeholder="08xxxxxxxxxx"
                     value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
            </div>
          </div>
        </div>

        <!-- KEAMANAN -->
        <div class="rm-sec"><i class="fa fa-lock"></i> Keamanan Akun</div>

        <div class="rm-g2">
          <div class="rm-fg">
            <label class="rm-fl">Password <span class="req">*</span></label>
            <div class="rm-iw">
              <i class="fa fa-lock rm-ii"></i>
              <input type="password" name="reg_password" id="rpw1" class="rm-inp"
                     placeholder="Min. 6 karakter..."
                     oninput="rmStr(this.value)" autocomplete="new-password">
              <button type="button" class="rm-eye" onclick="tgl('rpw1','re1')" tabindex="-1"><i class="fa fa-eye" id="re1"></i></button>
            </div>
            <div class="rm-str-bar"><div class="rm-str-fill" id="rm-sfill"></div></div>
            <div class="rm-str-lbl" id="rm-slbl"></div>
          </div>
          <div class="rm-fg">
            <label class="rm-fl">Konfirmasi <span class="req">*</span></label>
            <div class="rm-iw">
              <i class="fa fa-lock rm-ii"></i>
              <input type="password" name="reg_confirm" id="rpw2" class="rm-inp"
                     placeholder="Ulangi password..."
                     oninput="rmMatch()" autocomplete="new-password">
              <button type="button" class="rm-eye" onclick="tgl('rpw2','re2')" tabindex="-1"><i class="fa fa-eye" id="re2"></i></button>
            </div>
            <div class="rm-match" id="rm-match"></div>
          </div>
        </div>

        <!-- Captcha -->
        <div class="rm-cap">
          <div class="rm-cap-lbl"><i class="fa fa-robot"></i> Verifikasi anti-bot — berapa hasilnya?</div>
          <div class="rm-cap-row">
            <div class="rm-cap-expr"><?= htmlspecialchars($reg_captcha) ?></div>
            <div class="rm-cap-eq">=</div>
            <input type="number" name="reg_captcha" class="rm-cap-inp" placeholder="?" required autocomplete="off" min="0" max="99">
          </div>
        </div>

      </form>
      <?php endif; ?>

    </div><!-- /rm-body -->

    <!-- Footer (sembunyi saat success) -->
    <?php if(!$reg_success): ?>
    <div class="rm-foot">
      <button type="submit" form="rform" class="rm-btn" id="rm-btn">
        <i class="fa fa-user-plus"></i> Buat Akun Sekarang
      </button>
      <div class="rm-back">
        Sudah punya akun?
        <button type="button" class="rm-back-btn" onclick="closeReg()">Login di sini</button>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /reg-modal -->
</div><!-- /reg-overlay -->


<script>
/* ── Toggle password visibility ── */
function tgl(id, ic) {
  var e=document.getElementById(id), i=document.getElementById(ic);
  e.type = e.type==='password' ? 'text' : 'password';
  i.className = e.type==='text' ? 'fa fa-eye-slash' : 'fa fa-eye';
}

/* ── Register modal open/close ── */
function openReg() {
  document.getElementById('reg-overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeReg() {
  document.getElementById('reg-overlay').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeReg(); });

/* ── Login form loading state ── */
document.getElementById('lform')?.addEventListener('submit', function(){
  var b = document.getElementById('btn-go');
  if(b){ b.disabled=true; b.innerHTML='<i class="fa fa-spinner fa-spin"></i> Memproses...'; }
});

/* ── Register form loading state ── */
document.getElementById('rform')?.addEventListener('submit', function(){
  var b = document.getElementById('rm-btn');
  if(b){ b.disabled=true; b.innerHTML='<i class="fa fa-spinner fa-spin"></i> Mendaftar...'; }
});

/* ── Live clock ── */
(function tick(){
  var el=document.getElementById('clock'); if(!el)return;
  var n=new Date();
  el.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');
  setTimeout(tick,1000);
})();

/* ── Lockout countdown ── */
<?php if($is_locked): ?>
var _s=<?= remainingSeconds() ?>, _el=document.getElementById('countdown');
(function cd(){
  _s--;
  if(_s<=0){location.reload();return;}
  var m=Math.floor(_s/60),s=_s%60;
  _el.textContent=(m<10?'0':'')+m+':'+(s<10?'0':'')+s;
  setTimeout(cd,1000);
})();
<?php endif; ?>

/* ── Password strength ── */
function rmStr(v){
  var s=0;
  if(v.length>=6)s++;if(v.length>=10)s++;
  if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^a-zA-Z0-9]/.test(v))s++;
  var lvl=[
    {w:'20%',c:'#be123c',t:'Sangat Lemah'},{w:'40%',c:'#c2410c',t:'Lemah'},
    {w:'60%',c:'#b45309',t:'Cukup'},{w:'80%',c:'#0e9455',t:'Kuat'},{w:'100%',c:'#047857',t:'Sangat Kuat'}
  ];
  var f=document.getElementById('rm-sfill'),l=document.getElementById('rm-slbl');
  if(!v.length){f.style.width='0';l.textContent='';return;}
  var idx=Math.max(0,Math.min(s-1,4));
  f.style.width=lvl[idx].w;f.style.background=lvl[idx].c;
  l.textContent=lvl[idx].t;l.style.color=lvl[idx].c;
}
function rmMatch(){
  var p1=document.getElementById('rpw1').value,
      p2=document.getElementById('rpw2').value,
      h=document.getElementById('rm-match');
  if(!p2){h.textContent='';return;}
  if(p1===p2){h.textContent='✅ Password cocok';h.style.color='var(--lime-t)';}
  else{h.textContent='❌ Tidak cocok';h.style.color='var(--rose)';}
}

/* ── Auto-open modal if register error ── */
<?php if($show_reg): ?>
document.addEventListener('DOMContentLoaded', function(){ openReg(); });
<?php endif; ?>
</script>
</body>
</html>