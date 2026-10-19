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
                $login_error = 'Username dan password wajib diisi.';
            } else {
                $st = $pdo->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND status='aktif' LIMIT 1");
                $st->execute([$u, $u]); $user = $st->fetch();
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
                    recordLoginLog($pdo,['user_id'=>$user['id']??null,'username_input'=>$u,'status'=>'gagal','ip_address'=>$log_ip,'user_agent'=>$log_ua,'device_type'=>$log_parsed['device'],'browser'=>$log_parsed['browser'],'os'=>$log_parsed['os'],'keterangan'=>$user?'Password salah':'Username tidak ditemukan','is_new_ip'=>0]);
                    if (isLocked())   { $s=remainingSeconds(); $login_error="Akun dikunci <strong>".LOCKOUT_SECS." detik</strong>."; }
                    elseif ($sc<=2)   { $login_error="Username atau password salah. <strong>Sisa: {$sc}×</strong>"; }
                    else              { $login_error = 'Username atau password salah.'; }
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
            $username = strtolower(trim($_POST['reg_username'] ?? ''));
            $divisi   = trim($_POST['divisi'] ?? '');
            $pass     = $_POST['reg_password'] ?? '';
            $cnf      = $_POST['reg_confirm']  ?? '';

            if ($username === '') {
                $reg_errors[] = 'Username wajib diisi.';
            } elseif (!preg_match('/^[a-z0-9._]{3,30}$/', $username)) {
                $reg_errors[] = 'Username 3–30 karakter: huruf kecil, angka, titik, underscore.';
            } else {
                $st = $pdo->prepare("SELECT id FROM users WHERE username=?");
                $st->execute([$username]);
                if ($st->fetch()) $reg_errors[] = 'Username sudah digunakan, pilih yang lain.';
            }

            if (!$divisi) $reg_errors[] = 'Unit wajib dipilih.';
            elseif (!in_array($divisi, array_column($divisi_list, 'nama'), true)) $reg_errors[] = 'Unit tidak valid.';

            if (strlen($pass) < 6) $reg_errors[] = 'Password minimal 6 karakter.';
            if ($pass !== $cnf)    $reg_errors[] = 'Konfirmasi password tidak cocok.';

            if (empty($reg_errors)) {
                $pdo->prepare("INSERT INTO users (nama,username,email,password,divisi,no_hp) VALUES (?,?,?,?,?,?)")
                    ->execute([$username,$username,null,password_hash($pass,PASSWORD_BCRYPT),$divisi,null]);
                $new_id = (int)$pdo->lastInsertId();
                try {
                    $pdo->prepare("
                        INSERT INTO sdm_karyawan (user_id,divisi,jabatan_id,updated_by)
                        VALUES (?,?,NULL,?)
                        ON DUPLICATE KEY UPDATE divisi=COALESCE(VALUES(divisi),divisi)
                    ")->execute([$new_id,$divisi,$new_id]);
                } catch (Exception $e) {}

                $reg_success = true;
                $show_reg    = false;
                $login_msg   = 'Akun berhasil dibuat! Silakan login dengan username dan password Anda.';
                unset($_SESSION['reg_captcha_answer'],$_SESSION['reg_captcha_soal']);
                $_SESSION['csrf_token_reg'] = bin2hex(random_bytes(32));
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | <?= APP_NAME ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ══ RESET ══ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{width:100%;height:100%;overflow:hidden;font-family:'Outfit',sans-serif;background:#07261a;color:var(--text);}
button,input,select{font-family:inherit;}

/* ══ PALET ══ */
:root{
  --ink:#05241a;
  --mint:#c4ecd7;
  --panel:#e9f0eb;
  --card:#f6f9f7;
  --field:#ffffff;
  --edge:#c9d9ce;
  --edge2:#b2c7b8;
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
}
:focus-visible{outline:2px solid var(--lime);outline-offset:2px;}

/* ══ BACKDROP (satu adegan penuh) ══ */
.backdrop{position:fixed;inset:0;z-index:0;background:#07261a url('<?= APP_URL ?>/uploads/bglogin.png') center/cover no-repeat;}
.veil{position:fixed;inset:0;z-index:0;background:linear-gradient(105deg,rgba(5,36,26,.96) 0%,rgba(7,58,39,.90) 46%,rgba(10,100,64,.66) 100%);}
.blob{position:fixed;z-index:0;border-radius:50%;filter:blur(90px);pointer-events:none;}
.b1{width:560px;height:560px;left:-170px;top:-210px;background:rgba(40,200,125,.20);}
.b2{width:480px;height:480px;right:-140px;bottom:-150px;background:rgba(130,235,175,.20);}

/* ══ SCENE ══ */
.scene{position:fixed;inset:0;z-index:1;display:grid;grid-template-columns:minmax(0,1fr) minmax(380px,480px);grid-template-rows:auto minmax(0,1fr) 34px;}

/* Top */
.top{grid-column:1/-1;grid-row:1;display:flex;align-items:center;justify-content:space-between;padding:22px clamp(28px,5vw,80px) 0;}
.brand{display:flex;align-items:center;gap:12px;}
.brand-logo{height:38px;width:auto;display:block;border-radius:9px;background:#fff;padding:2px;}
.brand-name{font-size:18px;font-weight:700;letter-spacing:-.2px;color:#fff;}
.brand-name span{color:var(--mint);font-weight:500;}
.top-r{display:flex;align-items:center;gap:14px;}
.sys-pill{display:flex;align-items:center;gap:7px;font-size:11.5px;font-weight:500;color:#e6f7ee;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.18);backdrop-filter:blur(8px);padding:5px 12px;border-radius:999px;}
.sdot{width:6px;height:6px;border-radius:50%;background:#4ade80;box-shadow:0 0 0 0 rgba(74,222,128,.6);animation:ping 2.4s ease-out infinite;}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(74,222,128,.55);}80%,100%{box-shadow:0 0 0 8px rgba(74,222,128,0);}}
.tclock{font-family:var(--mono);font-size:12px;color:rgba(230,247,238,.75);min-width:62px;text-align:right;}

/* Left */
.lm{grid-column:1;grid-row:2;display:flex;flex-direction:column;justify-content:center;padding:0 clamp(28px,5vw,80px);min-height:0;overflow:hidden;color:#e8f5ee;}
.lm h1{font-size:clamp(34px,4vw,58px);font-weight:800;line-height:1.04;letter-spacing:-2px;margin-bottom:12px;
  background:linear-gradient(180deg,#fff 20%,#bfe8d2 120%);-webkit-background-clip:text;background-clip:text;color:transparent;}
.lm-sub{font-size:clamp(15px,1.3vw,18px);font-weight:500;color:var(--mint);margin-bottom:10px;}
.lm-p{font-size:clamp(13px,1.05vw,15px);line-height:1.75;color:rgba(232,245,238,.70);max-width:480px;}

.bento{display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:540px;margin-top:30px;}
.tile{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);backdrop-filter:blur(10px);border-radius:16px;padding:16px 18px;}
.tile.wide{grid-column:1/-1;}
.t-h{display:flex;align-items:center;gap:10px;margin-bottom:4px;}
.t-ic{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--ink);background:var(--mint);flex-shrink:0;}
.t-t{font-size:14.5px;font-weight:600;color:#fff;}
.t-d{font-size:12.5px;line-height:1.55;color:rgba(232,245,238,.62);}
.tile.wide .t-d{margin-bottom:16px;}

/* alur tiket */
.flow{display:flex;align-items:center;gap:10px;}
.step{display:flex;flex-direction:column;align-items:center;gap:7px;font-size:11.5px;font-weight:600;color:rgba(232,245,238,.75);}
.step .dot{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;border:1.5px solid rgba(255,255,255,.28);color:rgba(255,255,255,.8);}
.step.done .dot{background:var(--mint);border-color:var(--mint);color:var(--ink);}
.step.now .dot{background:#fff;border-color:#fff;color:var(--lime-t);animation:ring 2.8s ease-out infinite;}
@keyframes ring{0%{box-shadow:0 0 0 0 rgba(255,255,255,.5);}70%,100%{box-shadow:0 0 0 10px rgba(255,255,255,0);}}
.bar{flex:1;height:2px;border-radius:2px;background:rgba(255,255,255,.18);margin-bottom:24px;}
.bar.fill{background:linear-gradient(90deg,var(--mint),rgba(255,255,255,.6));}

/* Right: card */
.rc{grid-column:2;grid-row:2;display:flex;align-items:center;padding:12px clamp(20px,3vw,48px) 12px 0;overflow-y:auto;}
.card{width:100%;max-width:420px;background:rgba(246,249,247,.94);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.55);border-radius:24px;padding:26px 28px 28px;
  box-shadow:0 40px 80px -30px rgba(0,20,10,.65),0 1px 0 rgba(255,255,255,.7) inset;animation:cardIn .6s cubic-bezier(.2,.7,.2,1) both;}
@keyframes cardIn{from{opacity:0;transform:translateY(18px) scale(.985);}to{opacity:1;transform:none;}}

.seg{display:grid;grid-template-columns:1fr 1fr;background:rgba(14,148,85,.10);border-radius:13px;padding:4px;margin-bottom:22px;}
.seg button{height:38px;border:none;border-radius:10px;font-size:13.5px;font-weight:600;background:transparent;color:var(--text2);cursor:pointer;transition:color .2s,background .2s;}
.seg button:hover{color:var(--lime-t);}
.seg .on{background:#fff;color:var(--lime-t);box-shadow:0 2px 8px rgba(7,42,29,.12);cursor:default;}

.fh-t{font-size:26px;font-weight:800;letter-spacing:-.8px;line-height:1.1;}
.fh-s{font-size:13px;color:var(--text2);margin:4px 0 20px;}

/* Alerts */
.alert{display:flex;align-items:flex-start;gap:9px;padding:10px 12px;border-radius:10px;font-size:12.5px;line-height:1.5;margin-bottom:16px;animation:aIn .25s ease;}
@keyframes aIn{from{opacity:0;transform:translateY(-3px);}to{opacity:1;transform:none;}}
.alert i{margin-top:2px;flex-shrink:0;font-size:12px;}
.a-danger{background:rgba(190,18,60,.08);border:1px solid rgba(190,18,60,.25);color:#9f1239;}
.a-success{background:rgba(4,120,87,.09);border:1px solid rgba(4,120,87,.25);color:#065f46;}

/* Attempts */
.att-h{display:flex;justify-content:space-between;font-size:11.5px;color:var(--text3);margin-bottom:6px;}
.att-h strong{color:var(--text);}
.att-t{display:flex;gap:4px;margin-bottom:16px;}
.att-s{flex:1;height:3px;border-radius:2px;background:var(--edge2);transition:background .3s;}
.att-s.w{background:var(--amber);}
.att-s.d{background:var(--rose);}

/* Floating-label fields */
.ff{position:relative;margin-bottom:14px;}
.ff .fi{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:13px;pointer-events:none;transition:color .2s;z-index:1;}
.ff:focus-within .fi{color:var(--lime);}
.ff input{width:100%;height:56px;background:var(--field);border:1px solid var(--edge2);border-radius:13px;color:var(--text);font-size:14.5px;padding:20px 44px 6px 42px;outline:none;transition:border-color .2s,box-shadow .2s;appearance:none;}
.ff input:focus{border-color:var(--lime);box-shadow:0 0 0 4px var(--lime-dim);}
.ff label{position:absolute;left:42px;top:50%;transform:translateY(-50%);font-size:14px;color:var(--text3);pointer-events:none;transition:all .16s ease;}
.ff input:focus + label,
.ff input:not(:placeholder-shown) + label,
.ff input:-webkit-autofill + label{top:11px;transform:none;font-size:11px;font-weight:600;color:var(--lime-t);}
.eye-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;padding:8px;font-size:13px;transition:color .2s;z-index:2;}
.eye-btn:hover{color:var(--lime);}

/* Captcha */
.cap{display:flex;align-items:center;gap:10px;background:rgba(14,148,85,.07);border:1px solid var(--edge);border-radius:13px;padding:9px 10px 9px 14px;margin:4px 0 18px;}
.cap-lbl{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:var(--text2);margin-right:auto;}
.cap-lbl i{color:var(--lime);}
.cap-expr{font-family:var(--mono);font-size:17px;font-weight:500;color:var(--lime-t);background:var(--field);border:1px solid var(--edge);padding:5px 11px;border-radius:8px;letter-spacing:2px;white-space:nowrap;}
.cap-eq{font-family:var(--mono);font-size:16px;color:var(--text3);}
.cap-inp{width:56px;height:38px;background:var(--field);border:1px solid var(--edge2);border-radius:8px;color:var(--text);font-family:var(--mono);font-size:17px;font-weight:500;text-align:center;outline:none;transition:border-color .2s,box-shadow .2s;-moz-appearance:textfield;}
.cap-inp::-webkit-outer-spin-button,.cap-inp::-webkit-inner-spin-button{-webkit-appearance:none;}
.cap-inp:focus{border-color:var(--lime);box-shadow:0 0 0 3px var(--lime-dim);}

.btn-go{width:100%;height:52px;border:none;border-radius:13px;color:var(--on-accent);font-size:15px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;
  background:linear-gradient(135deg,#13ad66 0%,#0b7f49 100%);box-shadow:0 14px 26px -12px rgba(14,148,85,.85);transition:transform .2s,box-shadow .2s,filter .2s;}
.btn-go i{transition:transform .2s;}
.btn-go:hover{transform:translateY(-1px);filter:brightness(1.05);box-shadow:0 18px 30px -12px rgba(14,148,85,.9);}
.btn-go:hover i{transform:translateX(3px);}
.btn-go:active{transform:none;}
.btn-go:disabled{opacity:.6;cursor:not-allowed;transform:none;}

.card-foot{margin-top:18px;display:flex;align-items:center;justify-content:center;gap:7px;font-size:11.5px;color:var(--text3);}
.card-foot i{color:var(--lime);}

/* Lockout */
.lock-state{text-align:center;padding:8px 0 4px;}
.lk-ring{width:60px;height:60px;border:2px solid var(--rose);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--rose);margin:0 auto 14px;animation:lp 2s ease-in-out infinite;}
@keyframes lp{0%,100%{box-shadow:0 0 0 0 rgba(190,18,60,.3);}50%{box-shadow:0 0 0 10px rgba(190,18,60,0);}}
.lk-t{font-size:20px;font-weight:800;letter-spacing:-.5px;margin-bottom:4px;}
.lk-cnt{font-family:var(--mono);font-size:clamp(36px,4vw,52px);font-weight:500;color:var(--lime-t);letter-spacing:-2px;line-height:1;margin:6px 0 10px;}
.lk-s{font-size:12px;color:var(--text3);line-height:1.7;}

/* Ticker */
.ticker{grid-column:1/-1;grid-row:3;background:rgba(3,24,16,.55);backdrop-filter:blur(8px);border-top:1px solid rgba(255,255,255,.10);overflow:hidden;display:flex;align-items:center;}
.ticker-inner{display:flex;align-items:center;animation:tick 90s linear infinite;white-space:nowrap;}
.ti{font-size:11.5px;color:rgba(232,245,238,.6);padding:0 20px;}
.ts{color:rgba(232,245,238,.25);font-size:7px;}
@keyframes tick{from{transform:translateX(0);}to{transform:translateX(-50%);}}

/* ══ REGISTER MODAL ══ */
.reg-overlay{display:none;position:fixed;inset:0;z-index:9999;background:rgba(3,22,14,.62);backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:16px;}
.reg-overlay.open{display:flex;animation:ovIn .2s ease;}
@keyframes ovIn{from{opacity:0;}to{opacity:1;}}
.reg-modal{background:var(--panel);border:1px solid var(--edge2);border-radius:20px;width:100%;max-width:500px;max-height:92vh;max-height:92dvh;display:flex;flex-direction:column;box-shadow:0 30px 80px rgba(2,20,12,.5);animation:modalUp .25s ease;overflow:hidden;}
@keyframes modalUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:none;}}

.rm-head{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;background:var(--card);border-bottom:1px solid var(--edge);flex-shrink:0;}
.rm-head-l{display:flex;align-items:center;gap:12px;}
.rm-icon{width:40px;height:40px;background:linear-gradient(135deg,#13ad66,#0b7f49);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--on-accent);font-size:15px;flex-shrink:0;}
.rm-title{font-size:16px;font-weight:700;letter-spacing:-.3px;}
.rm-sub{font-size:12px;color:var(--text3);margin-top:2px;}
.rm-close{width:32px;height:32px;border-radius:9px;border:none;background:var(--lime-dim);color:var(--text2);cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0;}
.rm-close:hover{background:var(--rose);color:#fff;}

.rm-body{padding:20px 22px;overflow-y:auto;flex:1;min-height:0;scrollbar-width:thin;scrollbar-color:var(--edge2) transparent;}
.rm-body::-webkit-scrollbar{width:5px;}
.rm-body::-webkit-scrollbar-thumb{background:var(--edge2);border-radius:3px;}

.rm-alert{display:flex;align-items:flex-start;gap:9px;padding:10px 13px;border-radius:10px;font-size:12.5px;margin-bottom:14px;animation:aIn .25s ease;}
.rm-alert i{margin-top:2px;flex-shrink:0;font-size:12px;}
.rm-a-danger{background:rgba(190,18,60,.08);border:1px solid rgba(190,18,60,.25);color:#9f1239;}
.rm-alert ul{margin:4px 0 0 16px;}
.rm-alert ul li{margin-bottom:2px;}

.rm-sec{font-size:12.5px;font-weight:700;color:var(--lime-t);display:flex;align-items:center;gap:8px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--edge);}
.rm-sec:not(:first-child){margin-top:20px;}
.rm-sec i{font-size:11px;}

.rm-g2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.rm-fg{margin-bottom:12px;}
.rm-fl{font-size:12.5px;font-weight:600;color:var(--text2);display:block;margin-bottom:6px;}
.rm-fl .req{color:var(--rose);}
.rm-iw{position:relative;}
.rm-ii{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:12px;pointer-events:none;transition:color .2s;z-index:1;}
.rm-iw:focus-within .rm-ii{color:var(--lime);}
.rm-inp{width:100%;height:44px;background:var(--field);border:1px solid var(--edge2);border-radius:11px;color:var(--text);font-size:13.5px;padding:0 38px 0 36px;outline:none;transition:border-color .2s,box-shadow .2s;appearance:none;}
.rm-inp:focus{border-color:var(--lime);box-shadow:0 0 0 3px var(--lime-dim);}
.rm-inp::placeholder{color:var(--text3);font-size:13px;}
.rm-inp.sel{cursor:pointer;}
.rm-inp option{background:var(--field);color:var(--text);}
.rm-eye{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;padding:4px;font-size:12px;transition:color .2s;}
.rm-eye:hover{color:var(--lime);}

.rm-str-bar{height:3px;background:var(--edge2);border-radius:2px;margin-top:6px;overflow:hidden;}
.rm-str-fill{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}
.rm-str-lbl{font-size:11px;margin-top:4px;font-weight:600;}
.rm-match{font-size:11px;margin-top:4px;font-weight:600;}

.rm-cap{background:rgba(14,148,85,.07);border:1px solid var(--edge);border-radius:12px;padding:12px 14px;margin-bottom:6px;}
.rm-cap-lbl{font-size:12.5px;font-weight:600;color:var(--text2);display:flex;align-items:center;gap:6px;margin-bottom:10px;}
.rm-cap-lbl i{color:var(--lime);}
.rm-cap-row{display:flex;align-items:center;gap:10px;}
.rm-cap-expr{font-family:var(--mono);font-size:19px;font-weight:500;color:var(--lime-t);background:var(--field);border:1px solid var(--edge);padding:6px 14px;border-radius:8px;letter-spacing:2px;white-space:nowrap;flex-shrink:0;}
.rm-cap-eq{font-family:var(--mono);font-size:18px;color:var(--text3);flex-shrink:0;}
.rm-cap-inp{width:64px;height:40px;background:var(--field);border:1px solid var(--edge2);border-radius:8px;color:var(--text);font-family:var(--mono);font-size:18px;font-weight:500;text-align:center;outline:none;transition:border-color .2s,box-shadow .2s;-moz-appearance:textfield;}
.rm-cap-inp::-webkit-outer-spin-button,.rm-cap-inp::-webkit-inner-spin-button{-webkit-appearance:none;}
.rm-cap-inp:focus{border-color:var(--lime);box-shadow:0 0 0 3px var(--lime-dim);}

.rm-foot{padding:14px 22px;border-top:1px solid var(--edge);flex-shrink:0;background:var(--card);}
.rm-btn{width:100%;height:48px;background:linear-gradient(135deg,#13ad66,#0b7f49);border:none;border-radius:12px;color:var(--on-accent);font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;transition:transform .2s,filter .2s;}
.rm-btn:hover{transform:translateY(-1px);filter:brightness(1.05);}
.rm-btn:active{transform:none;}
.rm-btn:disabled{opacity:.6;cursor:not-allowed;transform:none;}
.rm-back{text-align:center;margin-top:12px;font-size:12.5px;color:var(--text2);}
.rm-back-btn{background:none;border:none;color:var(--lime-t);cursor:pointer;font-size:12.5px;font-weight:700;padding:0;}
.rm-back-btn:hover{text-decoration:underline;}

.rm-success{text-align:center;padding:32px 20px;}
.rm-suc-ring{width:70px;height:70px;border-radius:50%;background:rgba(4,120,87,.12);border:2px solid rgba(4,120,87,.35);display:flex;align-items:center;justify-content:center;font-size:28px;color:var(--success);margin:0 auto 16px;animation:pop .4s cubic-bezier(.34,1.56,.64,1);}
@keyframes pop{from{transform:scale(0);opacity:0;}to{transform:scale(1);opacity:1;}}
.rm-suc-t{font-size:20px;font-weight:800;letter-spacing:-.5px;margin-bottom:8px;}
.rm-suc-s{font-size:13px;color:var(--text2);line-height:1.8;margin-bottom:20px;}
.rm-suc-btn{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#13ad66,#0b7f49);color:var(--on-accent);font-size:13.5px;font-weight:600;padding:12px 24px;border-radius:11px;border:none;cursor:pointer;}

/* ══ RESPONSIVE ══ */
@media(max-width:1180px){.scene{grid-template-columns:minmax(0,1fr) 420px;}}
@media(max-width:1000px){
  html,body{height:auto;min-height:100%;overflow-x:hidden;overflow-y:auto;}
  .backdrop,.veil{position:fixed;}
  .scene{position:relative;inset:auto;min-height:100vh;min-height:100svh;grid-template-columns:minmax(0,1fr);grid-template-rows:auto minmax(0,1fr) 34px;}
  .lm{display:none;}
  .rc{grid-column:1;align-items:center;justify-content:center;padding:24px 18px;overflow:visible;}
  .card{width:100%;max-width:440px;}
  .veil{background:linear-gradient(160deg,rgba(5,36,26,.96),rgba(8,80,52,.86));}
  .top{padding:18px 20px 0;gap:12px;}
}
@media(max-width:620px){
  .rm-g2{grid-template-columns:1fr;}
  .top{padding:16px 16px 0;}
  .brand{gap:9px;}
  .brand-logo{height:34px;}
  .brand-name{font-size:16px;}
  .sys-pill{font-size:10px;padding:5px 9px;}
  .ticker{height:30px;}
  .scene{grid-template-rows:auto minmax(0,1fr) 30px;}
  .rc{padding:20px 14px;}
  .card{padding:22px 20px 24px;border-radius:20px;}
  .fh-t{font-size:24px;}
  .seg{margin-bottom:18px;}
  .reg-overlay{padding:10px;}
  .reg-modal{border-radius:16px;}
  .rm-head{padding:14px 16px;}
  .rm-head-l{gap:9px;min-width:0;}
  .rm-sub{font-size:11px;line-height:1.4;}
  .rm-body{padding:16px;}
  .rm-foot{padding:12px 16px;}
}
@media(max-height:760px){
  .bento{margin-top:20px;}
  .tile{padding:12px 14px;}
  .tile.wide .t-d{margin-bottom:10px;}
  .lm-p{display:none;}
}
@media(max-width:480px){
  .tclock{display:none;}
  .brand-name{font-size:16px;}
  .card{padding:20px 18px 22px;}
  .cap{gap:8px;padding:8px 8px 8px 12px;}
  .cap-expr{font-size:15px;padding:4px 8px;letter-spacing:1px;}
  .cap-inp{width:48px;}
  .cap-lbl{font-size:11.5px;gap:5px;}
  .ticker{height:28px;}
  .scene{grid-template-rows:auto minmax(0,1fr) 28px;}
  .ti{font-size:10.5px;padding:0 14px;}
}
@media(max-height:640px){
  .top{padding-top:14px;}
  .bento .tile:not(.wide) .t-d{display:none;}
  .card{padding:18px 20px 20px;}
  .seg{margin-bottom:12px;}
  .seg button{height:34px;}
  .fh-t{font-size:22px;}
  .fh-s{margin-bottom:12px;}
  .ff{margin-bottom:10px;}
  .ff input{height:50px;}
  .btn-go{height:46px;}
  .card-foot{display:none;}
}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;}}
</style>
</head>
<body>

<div class="backdrop"></div>
<div class="veil"></div>
<div class="blob b1"></div><div class="blob b2"></div>

<div class="scene">

  <!-- TOP -->
  <header class="top">
    <div class="brand">
      <img src="<?= APP_URL ?>/uploads/logo.jpeg" alt="Logo RS Assalam" class="brand-logo">
      <div class="brand-name">RS <span>Assalam</span></div>
    </div>
    <div class="top-r">
      <div class="sys-pill"><div class="sdot"></div>System Online</div>
      <div class="tclock" id="clock">--:--:--</div>
    </div>
  </header>

  <!-- LEFT -->
  <section class="lm">
    <h1>Sistem Helpdesk<br>RS Assalam</h1>
    <div class="lm-sub">Pusat Layanan Sarana dan Prasarana</div>
    <p class="lm-p">Platform manajemen umum untuk aset dan inventaris IT, IPSRS, ATEM, serta laporan tiket unit dalam satu dashboard.</p>

    <div class="bento">
      <div class="tile wide">
        <div class="t-h"><div class="t-ic"><i class="fa fa-ticket"></i></div><div class="t-t">Pengaduan Saran & Prasarana</div></div>
        <div class="t-d">Setiap laporan tercatat dan bisa dilacak dari awal sampai selesai.</div>
        <div class="flow">
          <div class="step done"><span class="dot"><i class="fa fa-flag"></i></span>Dilaporkan</div>
          <span class="bar fill"></span>
          <div class="step now"><span class="dot"><i class="fa fa-screwdriver-wrench"></i></span>Diproses</div>
          <span class="bar"></span>
          <div class="step"><span class="dot"><i class="fa fa-check"></i></span>Selesai</div>
        </div>
      </div>
      <div class="tile">
        <div class="t-h"><div class="t-ic"><i class="fa fa-boxes-stacked"></i></div><div class="t-t">Aset dan inventaris</div></div>
        <div class="t-d">Lacak, mutasi, dan jadwalkan maintenance.</div>
      </div>
      <div class="tile">
        <div class="t-h"><div class="t-ic"><i class="fa fa-gauge-high"></i></div><div class="t-t">Monitoring dan SLA</div></div>
        <div class="t-d">Pantau koneksi, server, dan target waktu layanan.</div>
      </div>
    </div>
  </section>

  <!-- RIGHT: CARD -->
  <main class="rc">
    <div class="card">

      <div class="seg" role="tablist">
        <button type="button" class="on" aria-selected="true">Masuk</button>
        <button type="button" onclick="openReg()">Daftar</button>
      </div>

      <div class="fh-t">Masuk Akun</div>
      <div class="fh-s">Gunakan username &amp; password yang terdaftar</div>

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

        <div class="ff">
          <i class="fa fa-user fi"></i>
          <input type="text" name="email" id="luser" placeholder=" "
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 autofocus autocomplete="username" autocapitalize="none">
          <label for="luser">Username</label>
        </div>

        <div class="ff">
          <i class="fa fa-key fi"></i>
          <input type="password" name="password" id="lpwd" placeholder=" " autocomplete="current-password">
          <label for="lpwd">Password</label>
          <button type="button" class="eye-btn" onclick="tgl('lpwd','lec')" tabindex="-1" aria-label="Tampilkan password"><i class="fa fa-eye" id="lec"></i></button>
        </div>

        <div class="cap">
          <div class="cap-lbl"><i class="fa fa-shield-halved"></i> Verifikasi</div>
          <div class="cap-expr"><?= htmlspecialchars($captcha_soal) ?></div>
          <div class="cap-eq">=</div>
          <input type="number" name="captcha" class="cap-inp" placeholder="?" required autocomplete="off" min="0" max="99">
        </div>

        <button type="submit" class="btn-go" id="btn-go">
          Masuk ke Sistem <i class="fa fa-arrow-right"></i>
        </button>
      </form>
      <?php endif; ?>

      <div class="card-foot"><i class="fa fa-lock"></i> Sesi terenkripsi dan aktivitas login tercatat</div>
    </div>
  </main>

  <!-- TICKER -->
  <div class="ticker" aria-hidden="true">
    <div class="ticker-inner">
      <?php
      $mItems=['Dashboard','Tiket IT','Antrian Tiket','Semua Tiket','Laporan SLA','Buat Tiket IT','Tiket Saya','Tiket IPSRS','Antrian IPSRS','Semua Tiket IPSRS','Laporan SLA IPSRS','Buat Tiket IPSRS','Berita Acara IT','Aset IT','Lacak Aset','Mutasi Aset','Maintenance IT','Monitoring Koneksi','Monitoring Server','Master Karyawan','Berkas Karyawan','Master Berkas','Jabatan','Shift Kerja','Jadwal','Laporan Kehadiran','Lokasi Absen','Kategori IT','Kategori IPSRS','Bagian / Divisi','Pengguna','Log Login','Notif Telegram','Backup Database','Absen Sekarang','Rekap Absensi','Ajukan Cuti','Approval Cuti','Master Cuti','Laporan Cuti','Profil Saya','Data Kepegawaian','Berkas Saya','Ganti Password'];
      $all = array_merge($mItems,$mItems);
      foreach($all as $m){echo '<span class="ti">'.htmlspecialchars($m).'</span><span class="ts">◆</span>';}
      ?>
    </div>
  </div>

</div><!-- /scene -->


<!-- ════════════════════════════════════════════
     MODAL REGISTER
════════════════════════════════════════════ -->
<div id="reg-overlay" class="reg-overlay <?= $show_reg ? 'open' : '' ?>" onclick="if(event.target===this)closeReg()">
  <div class="reg-modal">

    <div class="rm-head">
      <div class="rm-head-l">
        <div class="rm-icon"><i class="fa fa-user-plus"></i></div>
        <div>
          <div class="rm-title">Buat Akun Baru</div>
          <div class="rm-sub">Isi data untuk mendaftar ke sistem <?= APP_NAME ?></div>
        </div>
      </div>
      <button type="button" class="rm-close" onclick="closeReg()" aria-label="Tutup"><i class="fa fa-times"></i></button>
    </div>

    <div class="rm-body">

      <?php if($reg_success): ?>
      <div class="rm-success">
        <div class="rm-suc-ring"><i class="fa fa-check"></i></div>
        <div class="rm-suc-t">Akun Berhasil Dibuat!</div>
        <div class="rm-suc-s">
          Selamat, akun Anda sudah terdaftar di sistem.<br>
          Silakan login menggunakan <strong style="color:var(--lime-t);">username</strong> dan password Anda.
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

        <div class="rm-sec"><i class="fa fa-user"></i> Masukkan Data Diri yang Sesuai</div>

        <div class="rm-fg">
          <label class="rm-fl">Username <span class="req">*</span></label>
          <div class="rm-iw">
            <i class="fa fa-user rm-ii"></i>
            <input type="text" name="reg_username" class="rm-inp" placeholder="contoh: it.assalam"
                   value="<?= htmlspecialchars($_POST['reg_username'] ?? '') ?>"
                   maxlength="30" pattern="[a-zA-Z0-9._]{3,30}" autocapitalize="none" required>
          </div>
        </div>

        <div class="rm-fg">
          <label class="rm-fl">Divisi / Unit <span class="req">*</span></label>
          <div class="rm-iw">
            <i class="fa fa-building rm-ii"></i>
            <select name="divisi" class="rm-inp sel" required>
              <option value="">-- Pilih Unit --</option>
              <?php foreach($divisi_list as $d): ?>
              <option value="<?= htmlspecialchars($d['nama']) ?>"
                      <?= (($_POST['divisi']??'') === $d['nama']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['nama']) ?><?= $d['kode'] ? ' ('.$d['kode'].')' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

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

        <div class="rm-cap">
          <div class="rm-cap-lbl"><i class="fa fa-robot"></i> Verifikasi anti-bot: berapa hasilnya?</div>
          <div class="rm-cap-row">
            <div class="rm-cap-expr"><?= htmlspecialchars($reg_captcha) ?></div>
            <div class="rm-cap-eq">=</div>
            <input type="number" name="reg_captcha" class="rm-cap-inp" placeholder="?" required autocomplete="off" min="0" max="99">
          </div>
        </div>

      </form>
      <?php endif; ?>

    </div><!-- /rm-body -->

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