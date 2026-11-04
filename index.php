<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['public_ticket_csrf'])) {
    $_SESSION['public_ticket_csrf'] = bin2hex(random_bytes(32));
}

$categories = [
    'it' => $pdo->query("SELECT id, nama FROM kategori ORDER BY nama")->fetchAll(),
    'atem' => $pdo->query("SELECT id, nama FROM kategori_atem WHERE aktif=1 ORDER BY nama")->fetchAll(),
    'ipsrs' => $pdo->query("SELECT id, nama FROM kategori_ipsrs WHERE jenis='Non-Medis' ORDER BY nama")->fetchAll(),
    'atk' => $pdo->query("SELECT id, nama FROM atk_kategori WHERE aktif=1 ORDER BY nama")->fetchAll(),
];
$departments = $pdo->query("
    SELECT id, nama, lokasi FROM bagian
    WHERE status='aktif' AND lokasi IS NOT NULL AND lokasi != ''
    ORDER BY urutan ASC, nama ASC
")->fetchAll();
$service_names = ['it' => 'IT', 'atem' => 'ATEM', 'ipsrs' => 'IPSRS', 'atk' => 'ATK'];
$service = (string)($_POST['layanan'] ?? 'it');
$category_id = (int)($_POST['kategori_id'] ?? 0);
$department_id = (int)($_POST['bagian_id'] ?? 0);
$priority = (string)($_POST['prioritas'] ?? 'Sedang');
$error = '';
$error_ref = '';
$success = $_SESSION['public_ticket_success'] ?? null;
unset($_SESSION['public_ticket_success']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim((string)($_POST['nama_pemohon'] ?? ''));
    $phone = trim((string)($_POST['telepon'] ?? ''));
    $division = '';
    $location = '';
    $title = trim((string)($_POST['judul'] ?? ''));
    $description = trim((string)($_POST['deskripsi'] ?? ''));
    if (!in_array($service, ['it', 'atem', 'ipsrs', 'atk'], true)) {
        $error = 'Pilih salah satu layanan yang tersedia.';
    } elseif (!hash_equals($_SESSION['public_ticket_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba kembali.';
    } elseif (!empty($_POST['website'] ?? '')) {
        $error = 'Permintaan tidak dapat diproses.';
    } elseif ($department_id < 1) {
        $error = 'Pilih Bagian / unit dari daftar Master Bagian.';
    } elseif ($name === '') {
        $error = 'Formulir pemohon: Nama pemohon wajib diisi.';
    } elseif (strlen($name) > 100) {
        $error = 'Formulir pemohon: Nama pemohon maksimal 100 karakter.';
    } elseif ($phone === '') {
        $error = 'Formulir pemohon: Nomor telepon / WhatsApp wajib diisi.';
    } elseif (strlen($phone) > 30) {
        $error = 'Formulir pemohon: Nomor telepon maksimal 30 karakter.';
    } elseif (!in_array($priority, ['Rendah', 'Sedang', 'Tinggi'], true)) {
        $error = 'Prioritas yang dipilih tidak valid.';
    } elseif (!empty($_SESSION['public_ticket_last_submit']) && time() - (int)$_SESSION['public_ticket_last_submit'] < 15) {
        $error = 'Mohon tunggu sebentar sebelum mengirim permintaan berikutnya.';
    } else {
        $user_id = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
        $ticket_number = '';
        $operation = 'membuka transaksi database';

        try {
            $pdo->beginTransaction();

            $department_check = $pdo->prepare("
                SELECT nama, lokasi FROM bagian
                WHERE id=? AND status='aktif' AND lokasi IS NOT NULL AND lokasi != ''
            ");
            $department_check->execute([$department_id]);
            $department = $department_check->fetch(PDO::FETCH_ASSOC);
            if (!$department) {
                throw new InvalidArgumentException('Bagian / unit yang dipilih tidak aktif atau belum memiliki lokasi di Master Bagian. Pilih bagian lain atau hubungi administrator.');
            }
            $division = $department['nama'];
            $location = $department['lokasi'];

            if ($service === 'atk') {
                $item_name = trim((string)($_POST['nama_barang'] ?? ''));
                $atk_category_id = $category_id;
                $unit = trim((string)($_POST['satuan'] ?? ''));
                $quantity = filter_var($_POST['jumlah'] ?? null, FILTER_VALIDATE_INT);
                $reason = trim((string)($_POST['alasan'] ?? ''));
                $operation = 'memeriksa subkategori pada formulir ATK';
                $category_check = $pdo->prepare('SELECT id FROM atk_kategori WHERE id=? AND aktif=1');
                $category_check->execute([$atk_category_id]);
                if (!$category_check->fetchColumn()) throw new InvalidArgumentException('Formulir ATK: Pilih subkategori yang aktif.');
                if ($item_name === '') throw new InvalidArgumentException('Formulir ATK: Nama barang wajib diisi.');
                if (strlen($item_name) > 150) throw new InvalidArgumentException('Formulir ATK: Nama barang maksimal 150 karakter.');
                if ($unit === '') throw new InvalidArgumentException('Formulir ATK: Satuan wajib diisi.');
                if (strlen($unit) > 40) throw new InvalidArgumentException('Formulir ATK: Satuan maksimal 40 karakter.');
                if ($quantity === false || $quantity < 1 || $quantity > 10000) throw new InvalidArgumentException('Formulir ATK: Jumlah harus berupa angka antara 1 dan 10.000.');
                if ($reason === '') throw new InvalidArgumentException('Formulir ATK: Keperluan / alasan pengajuan wajib diisi.');
                if (strlen($reason) > 10000) throw new InvalidArgumentException('Formulir ATK: Keperluan / alasan maksimal 10.000 karakter.');

                $ticket_number = 'REQ-PUB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $operation = 'menyimpan pengajuan barang pada formulir ATK';
                $stmt = $pdo->prepare("
                    INSERT INTO atk_pengajuan
                        (nomor, user_id, pemohon_nama, pemohon_telepon, pemohon_divisi, atk_kategori_id, barang_id, nama_barang, satuan, jumlah, alasan)
                    VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)
                ");
                $stmt->execute([$ticket_number, $user_id, $name, $phone, $division, $atk_category_id, $item_name, $unit, $quantity, $reason]);
            } else {
                $form_name = 'Formulir ' . $service_names[$service];
                if ($title === '') throw new InvalidArgumentException($form_name . ': Judul permintaan wajib diisi.');
                if (strlen($title) > 255) throw new InvalidArgumentException($form_name . ': Judul maksimal 255 karakter.');
                if ($description === '') throw new InvalidArgumentException($form_name . ': Uraian kendala atau kebutuhan wajib diisi.');
                if (strlen($description) > 10000) throw new InvalidArgumentException($form_name . ': Uraian maksimal 10.000 karakter.');

                $category_table = ['it' => 'kategori', 'atem' => 'kategori_atem', 'ipsrs' => 'kategori_ipsrs'][$service];
                $operation = 'memeriksa subkategori pada ' . $form_name;
                $category_sql = "SELECT id FROM {$category_table} WHERE id=?";
                if ($service === 'atem') $category_sql .= ' AND aktif=1';
                if ($service === 'ipsrs') $category_sql .= " AND jenis='Non-Medis'";
                $category_check = $pdo->prepare($category_sql);
                $category_check->execute([$category_id]);
                if (!$category_check->fetchColumn()) {
                    throw new InvalidArgumentException('Pilih subkategori yang valid.');
                }

                if ($service === 'it') {
                    $ticket_number = 'TKT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                    $operation = 'menyimpan tiket pada formulir IT';
                    $stmt = $pdo->prepare("
                        INSERT INTO tiket
                            (nomor, judul, deskripsi, kategori_id, prioritas, user_id, lokasi, pemohon_nama, pemohon_telepon, pemohon_divisi, waktu_submit)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$ticket_number, $title, $description, $category_id, $priority, $user_id, $location, $name, $phone, $division]);
                    $ticket_id = (int)$pdo->lastInsertId();
                    $operation = 'mencatat log tiket IT';
                    $pdo->prepare("
                        INSERT INTO tiket_log (tiket_id, user_id, status_dari, status_ke, keterangan)
                        VALUES (?, ?, NULL, 'menunggu', ?)
                    ")->execute([$ticket_id, $user_id, 'Tiket dibuat melalui formulir publik oleh ' . $name]);
                } elseif ($service === 'atem') {
                    $operation = 'menyimpan tiket pada formulir ATEM';
                    $stmt = $pdo->prepare("
                        INSERT INTO tiket_atem
                            (judul, deskripsi, kategori_id, prioritas, user_id, lokasi, waktu_submit, nomor, pemohon_nama, pemohon_telepon, pemohon_divisi)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL, ?, ?, ?)
                    ");
                    $stmt->execute([$title, $description, $category_id, $priority, $user_id, $location, $name, $phone, $division]);
                    $ticket_id = (int)$pdo->lastInsertId();
                    $ticket_number = 'TKT-ATEM-' . date('Y') . '-' . str_pad((string)$ticket_id, 6, '0', STR_PAD_LEFT);
                    $operation = 'membuat nomor tiket ATEM';
                    $pdo->prepare('UPDATE tiket_atem SET nomor=? WHERE id=?')->execute([$ticket_number, $ticket_id]);
                    $operation = 'mencatat log tiket ATEM';
                    $pdo->prepare("
                        INSERT INTO tiket_atem_log (tiket_id, user_id, status_dari, status_ke, keterangan)
                        VALUES (?, ?, NULL, 'menunggu', ?)
                    ")->execute([$ticket_id, $user_id, 'Tiket dibuat melalui formulir publik oleh ' . $name]);
                } else {
                    $year = date('Y');
                    $operation = 'membuat nomor tiket IPSRS';
                    $number_query = $pdo->prepare("
                        SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(nomor, '-', -1) AS UNSIGNED)), 0)
                        FROM tiket_ipsrs WHERE nomor LIKE ?
                    ");
                    $number_query->execute(["TKT-IPSRS-{$year}-%"]);
                    $next_number = (int)$number_query->fetchColumn() + 1;
                    $ticket_number = 'TKT-IPSRS-' . $year . '-' . str_pad((string)$next_number, 4, '0', STR_PAD_LEFT);
                    $operation = 'menyimpan tiket pada formulir IPSRS';
                    $stmt = $pdo->prepare("
                        INSERT INTO tiket_ipsrs
                            (nomor, judul, deskripsi, kategori_id, jenis_tiket, prioritas, user_id, lokasi, pemohon_nama, pemohon_telepon, pemohon_divisi, waktu_submit)
                        VALUES (?, ?, ?, ?, 'Non-Medis', ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$ticket_number, $title, $description, $category_id, $priority, $user_id, $location, $name, $phone, $division]);
                    $ticket_id = (int)$pdo->lastInsertId();
                    $operation = 'mencatat log tiket IPSRS';
                    $pdo->prepare("
                        INSERT INTO tiket_ipsrs_log (tiket_id, user_id, status_dari, status_ke, keterangan)
                        VALUES (?, ?, NULL, 'menunggu', ?)
                    ")->execute([$ticket_id, $user_id, 'Tiket dibuat melalui formulir publik oleh ' . $name]);
                }
            }

            $operation = 'menyelesaikan transaksi database';
            $pdo->commit();
            $_SESSION['public_ticket_last_submit'] = time();
            $_SESSION['public_ticket_success'] = [
                'nomor' => $ticket_number,
                'layanan' => $service_names[$service],
            ];
            redirect(APP_URL . '/index.php?terkirim=1');
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_ref = strtoupper(bin2hex(random_bytes(4)));
            $sql_state = (string)($e->errorInfo[0] ?? $e->getCode());
            $detail = (string)($e->errorInfo[2] ?? $e->getMessage());
            error_log(sprintf(
                'Public ticket failure ref=%s service=%s stage=%s sqlstate=%s detail=%s',
                $error_ref,
                $service_names[$service],
                $operation,
                $sql_state,
                $detail
            ));

            if (preg_match("/Unknown column ['\x60]?pemohon_|Column ['\x60]?user_id['\x60]? cannot be null/i", $detail)) {
                $error = 'Skema database belum siap untuk tiket tanpa login. Jalankan migrasi database/migrations/20261103_public_ticketing.sql satu kali pada database aplikasi.';
            } elseif (preg_match("/Table ['\x60]?.*doesn't exist|Base table or view not found/i", $detail)) {
                $error = 'Tabel yang diperlukan formulir ' . $service_names[$service] . ' tidak ditemukan. Periksa database yang dipilih dan pastikan skema layanan sudah diimpor.';
            } else {
                $error = 'Database menolak penyimpanan pada tahap "' . $operation . '". Periksa log PHP/server dengan kode referensi ini atau hubungi administrator.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_ref = strtoupper(bin2hex(random_bytes(4)));
            error_log(sprintf(
                'Public ticket failure ref=%s service=%s stage=%s detail=%s',
                $error_ref,
                $service_names[$service],
                $operation,
                $e->getMessage()
            ));
            $error = 'Terjadi kesalahan pada tahap "' . $operation . '". Hubungi administrator dan sertakan kode referensi ini.';
        }
    }
}

$page_categories = $categories[$service] ?? [];
$selected_department_id = $department_id;
if ($selected_department_id < 1 && !empty($_SESSION['user_divisi'])) {
    foreach ($departments as $department) {
        if ($department['nama'] === $_SESSION['user_divisi']) {
            $selected_department_id = (int)$department['id'];
            break;
        }
    }
}
$selected_location = '';
foreach ($departments as $department) {
    if ((int)$department['id'] === $selected_department_id) {
        $selected_location = $department['lokasi'];
        break;
    }
}
$category_json = json_encode($categories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$department_json = json_encode($departments, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// Data tampilan
$service_tabs = [
    'it'    => 'fa-laptop-code',
    'atem'  => 'fa-heart-pulse',
    'ipsrs' => 'fa-screwdriver-wrench',
    'atk'   => 'fa-paperclip',
];
$service_idx = array_search($service, array_keys($service_tabs), true);
if ($service_idx === false) $service_idx = 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#07261a">
  <title>Beranda Helpdesk | <?= clean(APP_NAME) ?></title>
  <link rel="icon" type="image/jpeg" href="<?= APP_URL ?>/uploads/logo.jpeg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root{
      --mint:#c4ecd7; --ink:#05241a; --edge:#c9d9ce; --edge2:#b2c7b8;
      --lime:#0e9455; --lime-t:#0a7040; --lime-dim:rgba(14,148,85,.10);
      --rose:#be123c; --amber:#b45309;
      --text:#17291f; --text2:#42594b; --text3:#667f70;
      --ease:cubic-bezier(.2,.7,.2,1);
      font-family:"Outfit",sans-serif;color:var(--text);background:#07261a;font-synthesis:none;
    }
    *{box-sizing:border-box}
    html{-webkit-text-size-adjust:100%}
    body{position:relative;margin:0;min-height:100vh;min-height:100dvh;padding-bottom:calc(38px + env(safe-area-inset-bottom,0px));background:#07261a;color:var(--text);overflow-x:hidden;-webkit-font-smoothing:antialiased}
    body::before,body::after{position:fixed;inset:0;z-index:0;content:"";pointer-events:none}
    body::before{background:#07261a url("<?= APP_URL ?>/uploads/bglogin.png") center/cover no-repeat}
    body::after{background:linear-gradient(105deg,rgba(5,36,26,.96) 0%,rgba(7,58,39,.90) 46%,rgba(10,100,64,.66) 100%)}
    a{color:inherit;text-decoration:none}
    [hidden]{display:none!important}
    :focus-visible{outline:2px solid var(--lime);outline-offset:2px}
    .blob{position:fixed;z-index:0;border-radius:50%;filter:blur(90px);pointer-events:none}
    .b1{width:520px;height:520px;left:-160px;top:-200px;background:rgba(40,200,125,.20);animation:drift1 22s ease-in-out infinite alternate}
    .b2{width:460px;height:460px;right:-140px;bottom:-140px;background:rgba(130,235,175,.18);animation:drift2 26s ease-in-out infinite alternate}
    @keyframes drift1{to{transform:translate(70px,50px) scale(1.12)}}
    @keyframes drift2{to{transform:translate(-60px,-40px) scale(1.1)}}
    .topbar,.layout{position:relative;z-index:1}

    /* Animasi masuk: satu rangkaian saat halaman dimuat */
    .rv{opacity:0;animation:rise .7s var(--ease) forwards;animation-delay:calc(var(--d,0) * 90ms + 100ms)}
    @keyframes rise{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}

    /* Topbar */
    .topbar{max-width:1120px;margin:0 auto;height:78px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 24px}
    .brand{display:flex;align-items:center;gap:12px;min-width:0;color:#fff;font-weight:700;font-size:18px}
    .brand span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .brand-mark{width:48px;height:48px;border-radius:12px;object-fit:contain;background:#fff;padding:3px;flex-shrink:0;transition:transform .3s var(--ease)}
    .brand:hover .brand-mark{transform:rotate(-4deg) scale(1.06)}
    .nav{display:flex;align-items:center;gap:14px;font-size:14px;font-weight:700;flex-shrink:0}
    .nav-login{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(255,255,255,.25);border-radius:10px;padding:10px 15px;color:#f0fff7;background:rgba(255,255,255,.08);white-space:nowrap;transition:background .2s,border-color .2s,transform .2s}
    .nav-login:hover{border-color:rgba(255,255,255,.45);background:rgba(255,255,255,.16);transform:translateY(-1px)}

    /* Layout */
    .layout{max-width:1120px;margin:14px auto 64px;padding:0 24px;display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:24px;align-items:start}
    .card{background:rgba(246,249,247,.94);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.55);border-radius:22px;box-shadow:0 30px 70px -28px rgba(0,20,10,.6),0 1px 0 rgba(255,255,255,.65) inset}
    .form-card{padding:30px 32px 32px}

    /* Kepala kartu + progres */
    .card-heading{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .heading-icon{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#13ad66,#0b7f49);color:#fff;display:grid;place-items:center;font-size:19px;flex-shrink:0;box-shadow:0 10px 20px -10px rgba(14,148,85,.9)}
    h2{font:800 24px/1.15 Outfit,sans-serif;letter-spacing:-.6px;margin:0 0 4px;color:var(--text)}
    .muted{color:var(--text2);font-size:13px;line-height:1.55}
    .progress{margin-bottom:6px}
    .progress-top{display:flex;justify-content:space-between;gap:10px;font-size:11.5px;color:var(--text3);margin-bottom:7px}
    .progress-top strong{color:var(--lime-t);font-weight:600}
    .progress-track{height:5px;border-radius:99px;background:rgba(14,148,85,.12);overflow:hidden}
    .progress-fill{display:block;height:100%;width:0;border-radius:99px;background:linear-gradient(90deg,#13ad66,#7ddca8);transition:width .5s var(--ease)}
    .progress.done .progress-fill{background:linear-gradient(90deg,#0b7f49,#13ad66)}

    /* Seksi & field */
    .sec{display:flex;align-items:center;gap:10px;margin:26px 0 14px;font-size:13px;font-weight:700;color:var(--lime-t)}
    .sec-ic{width:26px;height:26px;border-radius:8px;background:var(--lime-dim);display:grid;place-items:center;font-size:11px}
    .sec::after{content:"";flex:1;height:1px;background:var(--edge)}
    .sec small{order:3;font-size:11px;font-weight:500;color:var(--text3)}
    .sec small + ::after{display:none}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:16px}
    .field{min-width:0;margin-bottom:16px}
    .field.full{grid-column:1/-1}
    label,.lbl{display:block;font-size:12.5px;font-weight:600;color:var(--text2);margin-bottom:7px}
    .req{color:var(--rose)}
    .iw{position:relative}
    .ii{position:absolute;left:14px;top:23px;transform:translateY(-50%);color:var(--text3);font-size:13px;pointer-events:none;z-index:1;transition:color .2s,transform .25s var(--ease)}
    .iw:focus-within .ii{color:var(--lime);transform:translateY(-50%) scale(1.15)}
    .control{width:100%;height:46px;border:1px solid var(--edge2);border-radius:13px;background:#fff;padding:0 14px 0 40px;font:14px "Outfit",sans-serif;color:var(--text);outline:none;appearance:none;-webkit-appearance:none;transition:border-color .2s,box-shadow .2s,background .3s}
    .control:hover{border-color:#9fb5a7}
    .control:focus{border-color:var(--lime);box-shadow:0 0 0 4px var(--lime-dim)}
    .control::placeholder{color:var(--text3)}
    .control[readonly]{background:#eef4f0;color:var(--text2);cursor:default}
    select.control{cursor:pointer;padding-right:38px;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1.5l5 5 5-5' fill='none' stroke='%23667f70' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") no-repeat right 14px center}
    textarea.control{height:auto;min-height:124px;padding:12px 14px;line-height:1.55;resize:vertical}
    .hint{display:flex;align-items:center;gap:6px;font-size:11.5px;color:var(--amber);margin-top:6px}

    /* Tab layanan dengan pill yang meluncur */
    .seg{position:relative;isolation:isolate;display:grid;grid-template-columns:repeat(4,1fr);background:rgba(14,148,85,.10);border-radius:14px;padding:4px;margin-bottom:18px}
    .seg::before{content:"";position:absolute;z-index:0;top:4px;bottom:4px;left:4px;width:calc((100% - 8px) / 4);border-radius:11px;background:#fff;box-shadow:0 2px 10px rgba(7,42,29,.15);transform:translateX(calc(var(--idx,0) * 100%));transition:transform .4s cubic-bezier(.4,1.25,.5,1)}
    .seg input{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
    .seg label{position:relative;z-index:1;margin:0;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;gap:8px;font-size:13.5px;font-weight:600;color:var(--text2);cursor:pointer;transition:color .25s;-webkit-tap-highlight-color:transparent}
    .seg label:hover{color:var(--lime-t)}
    .seg input:checked + label{color:var(--lime-t)}
    .seg input:checked + label i{animation:iconpop .4s cubic-bezier(.34,1.56,.64,1)}
    .seg input:focus-visible + label{outline:2px solid var(--lime);outline-offset:1px}
    @keyframes iconpop{from{transform:scale(.6) rotate(-12deg)}to{transform:none}}

    /* Prioritas */
    .priority-options{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
    .priority-option{position:relative;margin:0;cursor:pointer;-webkit-tap-highlight-color:transparent}
    .priority-option input{position:absolute;width:1px;height:1px;opacity:0}
    .priority-option span{display:flex;align-items:center;justify-content:center;gap:7px;min-height:44px;width:100%;border:1px solid;border-radius:12px;padding:8px 12px;font-size:13px;font-weight:600;transition:background .2s,color .2s,border-color .2s,box-shadow .2s,transform .2s}
    .priority-option span::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor;opacity:.7}
    .priority-option:hover span{transform:translateY(-1px)}
    .priority-option input[value="Rendah"]+span{border-color:#86c99b;background:#edf8f0;color:#176b35}
    .priority-option input[value="Sedang"]+span{border-color:#e7c65a;background:#fff8df;color:#805d00}
    .priority-option input[value="Tinggi"]+span{border-color:#e99a96;background:#fff0ef;color:#a52e28}
    .priority-option input[value="Rendah"]:checked+span{border-color:#258447;background:#258447;color:#fff;box-shadow:0 8px 16px -8px #258447}
    .priority-option input[value="Sedang"]:checked+span{border-color:#eab308;background:#facc15;color:#713f12;box-shadow:0 8px 16px -8px #eab308}
    .priority-option input[value="Tinggi"]:checked+span{border-color:#c43d36;background:#c43d36;color:#fff;box-shadow:0 8px 16px -8px #c43d36}
    .priority-option input:checked+span{animation:chip .35s cubic-bezier(.34,1.56,.64,1)}
    .priority-option input:focus-visible+span{outline:3px solid currentColor;outline-offset:2px}
    @keyframes chip{from{transform:scale(.94)}to{transform:none}}

    /* Efek perubahan */
    .flash{animation:flash .9s ease}
    @keyframes flash{0%{box-shadow:0 0 0 0 rgba(14,148,85,.5);background-color:#dff3e8}100%{box-shadow:0 0 0 12px rgba(14,148,85,0)}}
    .swap{animation:swap .45s var(--ease) both}
    @keyframes swap{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}

    /* Tombol kirim */
    .submit{position:relative;overflow:hidden;width:100%;height:54px;margin-top:10px;border:0;border-radius:14px;display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(135deg,#13ad66 0%,#0b7f49 100%);color:#fff;font:600 15px "Outfit",sans-serif;cursor:pointer;box-shadow:0 14px 26px -12px rgba(14,148,85,.8);transition:transform .2s,box-shadow .2s,filter .2s;-webkit-tap-highlight-color:transparent}
    .submit::after{content:"";position:absolute;top:0;bottom:0;left:-60%;width:40%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg)}
    .submit:hover::after{animation:sheen .8s ease}
    @keyframes sheen{to{left:130%}}
    .submit:hover{filter:brightness(1.05);transform:translateY(-1px);box-shadow:0 18px 30px -12px rgba(14,148,85,.9)}
    .submit:active{transform:scale(.99)}
    .submit:disabled{opacity:.7;cursor:not-allowed;transform:none}
    .submit i{transition:transform .2s}
    .submit:hover i{transform:translateX(3px)}
    .form-foot{margin-top:14px;display:flex;align-items:center;justify-content:center;gap:7px;font-size:11.5px;color:var(--text3);text-align:center}
    .form-foot i{color:var(--lime)}

    /* Notifikasi */
    .notice{display:flex;gap:10px;border-radius:12px;padding:12px 14px;margin:0 0 18px;font-size:13px;line-height:1.55}
    .notice>i{margin-top:3px;flex-shrink:0}
    .notice strong{display:block;margin-bottom:2px;font-size:13.5px}
    .notice.error{background:rgba(190,18,60,.08);color:#9f1239;border:1px solid rgba(190,18,60,.25);animation:shake .45s ease both}
    .notice.success{background:rgba(4,120,87,.09);color:#065f46;border:1px solid rgba(4,120,87,.25);animation:pop .5s cubic-bezier(.34,1.56,.64,1) both}
    .ticket-no{font-family:ui-monospace,Menlo,Consolas,monospace;font-weight:600;background:#fff;border:1px solid rgba(4,120,87,.25);border-radius:6px;padding:1px 7px;word-break:break-all}
    @keyframes shake{20%{transform:translateX(-5px)}40%{transform:translateX(5px)}60%{transform:translateX(-3px)}80%{transform:translateX(3px)}}
    @keyframes pop{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:none}}
    .honeypot{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}

    /* Sidebar */
    .side-col{position:sticky;top:20px;display:grid;gap:16px}
    .side{padding:24px}
    .side h3{font:700 16px Outfit,sans-serif;margin:0 0 20px;color:var(--text)}
    .timeline{list-style:none;margin:0;padding:0}
    .timeline li{position:relative;padding:0 0 22px 48px;min-height:46px}
    .timeline li:last-child{padding-bottom:0}
    .timeline li::before{content:"";position:absolute;left:16px;top:36px;bottom:2px;width:2px;border-radius:2px;background:linear-gradient(var(--edge2),var(--edge))}
    .timeline li:last-child::before{display:none}
    .step-icon{position:absolute;left:0;top:0;width:34px;height:34px;border-radius:50%;background:var(--mint);color:var(--lime-t);display:grid;place-items:center;font-size:13px;transition:transform .3s var(--ease)}
    .timeline li:hover .step-icon{transform:scale(1.12)}
    .timeline li:first-child .step-icon{background:linear-gradient(135deg,#13ad66,#0b7f49);color:#fff;animation:ring 2.8s ease-out infinite}
    @keyframes ring{0%{box-shadow:0 0 0 0 rgba(14,148,85,.5)}70%,100%{box-shadow:0 0 0 10px rgba(14,148,85,0)}}
    .timeline strong{display:block;font-size:14px;margin:2px 0 3px}
    .timeline span.d{display:block;font-size:12.5px;line-height:1.55;color:var(--text3)}
    .tip{display:flex;gap:12px;align-items:flex-start;padding:18px 20px}
    .tip-ic{width:34px;height:34px;border-radius:10px;background:var(--mint);color:var(--lime-t);display:grid;place-items:center;font-size:13px;flex-shrink:0}
    .tip strong{display:block;font-size:13.5px;margin-bottom:2px}
    .tip span{font-size:12.5px;line-height:1.55;color:var(--text3)}

    /* Ticker */
    .footer{position:fixed;inset:auto 0 0;z-index:2;height:calc(38px + env(safe-area-inset-bottom,0px));padding-bottom:env(safe-area-inset-bottom,0px);overflow:hidden;display:flex;align-items:center;background:rgba(3,24,16,.76);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border-top:1px solid rgba(255,255,255,.12)}
    .ticker-track{display:flex;width:max-content;align-items:center;animation:helpdesk-tick 70s linear infinite}
    .ticker-group{display:flex;flex-shrink:0;align-items:center;white-space:nowrap}
    .ticker-item{padding:0 18px;font-size:12px;color:rgba(232,245,238,.72)}
    .ticker-separator{font-size:8px;color:var(--mint);opacity:.65}
    @keyframes helpdesk-tick{to{transform:translateX(-50%)}}

    /* Responsif */
    @media(max-width:960px){
      .layout{grid-template-columns:minmax(0,1fr);margin-top:8px;max-width:720px}
      .side-col{position:static;order:2;grid-template-columns:1fr 1fr;align-items:stretch}
    }
    @media(max-width:760px){
      .topbar{padding:0 16px;height:70px}
      .brand{font-size:16px}
      .brand-mark{width:42px;height:42px}
      .side-col{grid-template-columns:1fr}
    }
    @media(max-width:560px){
      .layout{padding:0 12px;gap:16px;margin-bottom:40px}
      .form-card{padding:20px 16px 22px;border-radius:20px}
      .card-heading{gap:11px}
      .heading-icon{width:42px;height:42px;font-size:17px;border-radius:12px}
      h2{font-size:20px}
      .muted{font-size:12px}
      .grid{grid-template-columns:minmax(0,1fr)}
      .grid.keep{grid-template-columns:1fr 1fr;column-gap:10px}
      .sec{margin-top:22px}
      .sec small{display:none}
      .nav-login{padding:9px 12px;font-size:12.5px}
      /* Tab layanan: ikon di atas teks */
      .seg label{flex-direction:column;gap:3px;height:56px;font-size:12px}
      .seg label i{font-size:15px}
      /* 16px mencegah iOS memperbesar layar saat fokus */
      .control{font-size:16px;height:48px}
      textarea.control{min-height:112px}
      .ii{top:24px}
      .priority-options{gap:6px}
      .priority-option span{padding:8px 6px;font-size:12.5px}
      .submit{height:56px}
      .ticker-item{font-size:11px;padding:0 14px}
      .side{padding:20px}
    }
    @media(max-width:380px){
      .nav-login span{display:none}
      .nav-login{width:42px;height:42px;justify-content:center;padding:0}
    }
    @media(prefers-reduced-motion:reduce){
      *,*::before,*::after{animation:none!important;transition:none!important}
      .rv{opacity:1!important}
    }
  </style>
</head>
<body>
  <div class="blob b1"></div><div class="blob b2"></div>

  <header class="topbar rv" style="--d:0">
    <a class="brand" href="<?= APP_URL ?>/index.php">
      <img class="brand-mark" src="<?= APP_URL ?>/uploads/logo.jpeg" alt="">
      <span><?= clean(APP_NAME) ?></span>
    </a>
    <nav class="nav">
      <?php if (isLoggedIn()): ?>
        <a class="nav-login" href="<?= APP_URL ?>/dashboard.php" aria-label="Dashboard"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
      <?php else: ?>
        <a class="nav-login" href="<?= APP_URL ?>/login.php" aria-label="Login Petugas"><i class="fa-solid fa-right-to-bracket"></i> <span>Login Petugas</span></a>
      <?php endif; ?>
    </nav>
  </header>

  <main class="layout">
    <section class="card form-card rv" style="--d:1">
      <div class="card-heading">
        <div class="heading-icon"><i class="fa-solid fa-circle-info"></i></div>
        <div>
          <div class="muted">Isi form berikut ini agar tim dapat menindaklanjuti laporan Anda.</div>
        </div>
      </div>

      <div class="progress" id="progress" aria-live="polite">
        <div class="progress-top"><span id="progress-label">Kelengkapan formulir</span><strong id="progress-pct">0%</strong></div>
        <div class="progress-track"><span class="progress-fill" id="progress-fill"></span></div>
      </div>

      <?php if ($error !== ''): ?>
        <div class="notice error" role="alert" style="margin-top:18px">
          <i class="fa-solid fa-circle-exclamation"></i>
          <div>
            <strong><?= clean('Formulir ' . ($service_names[$service] ?? 'Helpdesk') . ' gagal dikirim') ?></strong>
            <?= clean($error) ?>
            <?php if ($error_ref !== ''): ?><div style="margin-top:5px;font-size:12px;">Kode referensi: <strong style="display:inline"><?= clean($error_ref) ?></strong></div><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
      <?php if (is_array($success)): ?>
        <div class="notice success" role="status" style="margin-top:18px">
          <i class="fa-solid fa-circle-check"></i>
          <div>
            <strong>Permintaan berhasil dikirim</strong>
            Nomor <span class="ticket-no"><?= clean($success['nomor']) ?></span> untuk layanan <?= clean($success['layanan']) ?>. Simpan nomor ini untuk referensi saat menghubungi petugas.
          </div>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= APP_URL ?>/index.php" id="public-ticket-form">
        <input type="hidden" name="csrf" value="<?= clean($_SESSION['public_ticket_csrf']) ?>">
        <div class="honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>

        <div class="sec"><span class="sec-ic"><i class="fa-solid fa-user"></i></span> Data pemohon <small>* Wajib diisi</small></div>
        <div class="grid">
          <div class="field">
            <label for="nama_pemohon">Nama pemohon <span class="req" aria-hidden="true">*</span></label>
            <div class="iw">
              <i class="fa-solid fa-user ii"></i>
              <input class="control" id="nama_pemohon" name="nama_pemohon" maxlength="100" value="<?= clean($_POST['nama_pemohon'] ?? ($_SESSION['user_nama'] ?? '')) ?>" required autocomplete="name">
            </div>
          </div>
          <div class="field">
            <label for="telepon">Nomor telepon / WhatsApp <span class="req" aria-hidden="true">*</span></label>
            <div class="iw">
              <i class="fa-solid fa-phone ii"></i>
              <input class="control" id="telepon" name="telepon" type="tel" inputmode="tel" maxlength="30" value="<?= clean($_POST['telepon'] ?? '') ?>" placeholder="08xxxxxxxxxx" required autocomplete="tel">
            </div>
          </div>
          <div class="field">
            <label for="bagian_id">Bagian / unit <span class="req" aria-hidden="true">*</span></label>
            <div class="iw">
              <i class="fa-solid fa-building ii"></i>
              <select class="control" id="bagian_id" name="bagian_id" required>
                <option value="">Pilih bagian / unit</option>
                <?php foreach ($departments as $department): ?>
                  <option value="<?= (int)$department['id'] ?>" <?= $selected_department_id === (int)$department['id'] ? 'selected' : '' ?>><?= clean($department['nama']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (!$departments): ?><div class="hint"><i class="fa-solid fa-triangle-exclamation"></i> Belum ada bagian aktif dengan lokasi. Periksa Master Bagian.</div><?php endif; ?>
          </div>
          <div class="field">
            <label for="lokasi">Lokasi / ruangan <span class="req" aria-hidden="true">*</span></label>
            <div class="iw">
              <i class="fa-solid fa-location-dot ii"></i>
              <input class="control" id="lokasi" name="lokasi" value="<?= clean($selected_location) ?>" placeholder="Terisi otomatis dari Master Bagian" readonly required>
            </div>
          </div>
        </div>

        <div class="sec"><span class="sec-ic"><i class="fa-solid fa-clipboard-list"></i></span> Layanan dan detail permintaan</div>
        <div class="seg" id="service-seg" style="--idx:<?= (int)$service_idx ?>" role="radiogroup" aria-label="Layanan">
          <?php foreach ($service_tabs as $key => $icon): ?>
            <input type="radio" name="layanan" id="layanan-<?= $key ?>" value="<?= $key ?>" <?= $service === $key ? 'checked' : '' ?> required>
            <label for="layanan-<?= $key ?>"><i class="fa-solid <?= $icon ?>"></i><?= clean($service_names[$key]) ?></label>
          <?php endforeach; ?>
        </div>

        <div class="field" id="subcategory-field">
          <label for="kategori_id">Kategori <span class="req" aria-hidden="true">*</span></label>
          <div class="iw">
            <i class="fa-solid fa-layer-group ii"></i>
            <select class="control" id="kategori_id" name="kategori_id" required>
              <option value="">Pilih subkategori</option>
              <?php foreach ($page_categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= $category_id === (int)$category['id'] ? 'selected' : '' ?>><?= clean($category['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div id="ticket-fields" <?= $service === 'atk' ? 'hidden' : '' ?>>
          <div class="field">
            <label for="judul">Judul permintaan <span class="req" aria-hidden="true">*</span></label>
            <div class="iw">
              <i class="fa-solid fa-heading ii"></i>
              <input class="control" id="judul" name="judul" maxlength="255" value="<?= clean($_POST['judul'] ?? '') ?>" placeholder="Contoh: Komputer di ruang pendaftaran tidak menyala" <?= $service === 'atk' ? '' : 'required' ?>>
            </div>
          </div>
          <div class="field">
            <label for="deskripsi">Uraian kendala atau kebutuhan <span class="req" aria-hidden="true">*</span></label>
            <textarea class="control" id="deskripsi" name="deskripsi" maxlength="10000" placeholder="Jelaskan kendala, waktu kejadian, dan informasi lain yang membantu." <?= $service === 'atk' ? '' : 'required' ?>><?= clean($_POST['deskripsi'] ?? '') ?></textarea>
          </div>
          <div class="field">
            <span class="lbl" id="prioritas-label">Prioritas</span>
            <div class="priority-options" role="radiogroup" aria-labelledby="prioritas-label">
              <?php foreach (['Rendah', 'Sedang', 'Tinggi'] as $priority_option): ?>
                <label class="priority-option">
                  <input type="radio" name="prioritas" value="<?= $priority_option ?>" <?= $priority === $priority_option ? 'checked' : '' ?>>
                  <span><?= $priority_option ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div id="atk-fields" <?= $service === 'atk' ? '' : 'hidden' ?>>
          <div class="field">
            <label for="nama_barang">Nama barang ATK <span class="req" aria-hidden="true">*</span></label>
            <div class="iw">
              <i class="fa-solid fa-box ii"></i>
              <input class="control" id="nama_barang" name="nama_barang" maxlength="150" value="<?= clean($_POST['nama_barang'] ?? '') ?>" placeholder="Contoh: Kertas A4" <?= $service === 'atk' ? 'required' : '' ?>>
            </div>
          </div>
          <div class="grid keep">
            <div class="field">
              <label for="jumlah">Jumlah <span class="req" aria-hidden="true">*</span></label>
              <div class="iw">
                <i class="fa-solid fa-hashtag ii"></i>
                <input class="control" id="jumlah" name="jumlah" type="number" inputmode="numeric" min="1" max="10000" value="<?= clean($_POST['jumlah'] ?? '1') ?>" <?= $service === 'atk' ? 'required' : '' ?>>
              </div>
            </div>
            <div class="field">
              <label for="satuan">Satuan <span class="req" aria-hidden="true">*</span></label>
              <div class="iw">
                <i class="fa-solid fa-ruler ii"></i>
                <input class="control" id="satuan" name="satuan" maxlength="40" value="<?= clean($_POST['satuan'] ?? '') ?>" placeholder="rim, buah, pak" <?= $service === 'atk' ? 'required' : '' ?>>
              </div>
            </div>
          </div>
          <div class="field">
            <label for="alasan">Keperluan / alasan pengajuan <span class="req" aria-hidden="true">*</span></label>
            <textarea class="control" id="alasan" name="alasan" maxlength="10000" placeholder="Jelaskan kebutuhan barang dan unit pemakai." <?= $service === 'atk' ? 'required' : '' ?>><?= clean($_POST['alasan'] ?? '') ?></textarea>
          </div>
        </div>

        <button class="submit" type="submit" id="btn-submit"><span>Kirim permintaan</span></button>
      </form>
    </section>

    <div class="side-col">
      <aside class="card side rv" style="--d:2">
        <h3>Proses bantuan</h3>
        <ol class="timeline">
          <li><span class="step-icon"><i class="fa-solid fa-pen-to-square"></i></span><strong>Isi formulir</strong><span class="d">Pilih unit layanan dan ceritakan kebutuhan Anda.</span></li>
          <li><span class="step-icon"><i class="fa-solid fa-inbox"></i></span><strong>Tiket diteruskan</strong><span class="d">Laporan masuk ke antrean unit terkait untuk ditinjau.</span></li>
          <li><span class="step-icon"><i class="fa-solid fa-phone"></i></span><strong>Petugas menghubungi</strong><span class="d">Pastikan nomor telepon dan lokasi dapat dihubungi dengan benar.</span></li>
        </ol>
      </aside>
    </div>
  </main>

  <footer class="footer" aria-label="Informasi layanan helpdesk">
    <div class="ticker-track">
      <?php for ($ticker_copy = 0; $ticker_copy < 2; $ticker_copy++): ?>
        <div class="ticker-group" <?= $ticker_copy === 1 ? 'aria-hidden="true"' : '' ?>>
          <?php foreach (['Pilih layanan yang sesuai dengan kebutuhan', 'Jelaskan kendala atau barang yang dibutuhkan dengan rinci', 'Pastikan nomor telepon dan WhatsApp dapat dihubungi', 'Bagian dan lokasi diisi berdasarkan Master Bagian', 'Tiket yang dikirim akan diteruskan ke unit layanan terkait'] as $ticker_message): ?>
            <span class="ticker-item"><?= clean($ticker_message) ?></span><span class="ticker-separator">◆</span>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
  </footer>

  <script>
    const categoryData = <?= $category_json ?: '{}' ?>;
    const departmentData = <?= $department_json ?: '[]' ?>;
    const form = document.getElementById('public-ticket-form');
    const serviceRadios = Array.from(document.querySelectorAll('input[name="layanan"]'));
    const serviceSeg = document.getElementById('service-seg');
    const categorySelect = document.getElementById('kategori_id');
    const departmentSelect = document.getElementById('bagian_id');
    const locationInput = document.getElementById('lokasi');
    const ticketFields = document.getElementById('ticket-fields');
    const atkFields = document.getElementById('atk-fields');
    const progressBox = document.getElementById('progress');
    const progressFill = document.getElementById('progress-fill');
    const progressPct = document.getElementById('progress-pct');
    const progressLabel = document.getElementById('progress-label');

    function replay(el, cls) {
      el.classList.remove(cls);
      void el.offsetWidth;
      el.classList.add(cls);
    }

    function currentService() {
      const checked = document.querySelector('input[name="layanan"]:checked');
      return checked ? checked.value : 'it';
    }

    function updateProgress() {
      const fields = Array.from(form.querySelectorAll('input[required], select[required], textarea[required]'))
        .filter((field) => field.type !== 'radio' && field.id !== 'website' && !field.closest('[hidden]'));
      const filled = fields.filter((field) => field.value.trim() !== '').length;
      const pct = fields.length ? Math.round((filled / fields.length) * 100) : 0;
      progressFill.style.width = pct + '%';
      progressPct.textContent = pct + '%';
      progressLabel.textContent = pct === 100 ? 'Formulir siap dikirim' : filled + ' dari ' + fields.length + ' kolom wajib terisi';
      progressBox.classList.toggle('done', pct === 100);
    }

    function updateDepartmentLocation(animate = false) {
      const selectedDepartment = departmentData.find((department) => String(department.id) === departmentSelect.value);
      locationInput.value = selectedDepartment ? selectedDepartment.lokasi : '';
      if (animate && selectedDepartment) replay(locationInput, 'flash');
      updateProgress();
    }

    function updateServiceFields(refreshCategories = true) {
      const service = currentService();
      const isAtk = service === 'atk';
      ticketFields.hidden = isAtk;
      atkFields.hidden = !isAtk;
      categorySelect.required = true;
      document.querySelectorAll('#ticket-fields input, #ticket-fields textarea').forEach((field) => {
        field.required = !isAtk && (field.id === 'judul' || field.id === 'deskripsi');
      });
      document.querySelectorAll('#atk-fields input, #atk-fields textarea').forEach((field) => {
        field.required = isAtk;
      });
      serviceSeg.style.setProperty('--idx', Math.max(0, serviceRadios.findIndex((radio) => radio.checked)));
      if (refreshCategories) {
        const categories = categoryData[service] || [];
        categorySelect.replaceChildren(new Option('Pilih subkategori', ''));
        categories.forEach((category) => categorySelect.add(new Option(category.nama, category.id)));
        replay(categorySelect, 'flash');
        replay(isAtk ? atkFields : ticketFields, 'swap');
      }
      updateProgress();
    }

    serviceRadios.forEach((radio) => radio.addEventListener('change', () => updateServiceFields(true)));
    departmentSelect.addEventListener('change', () => updateDepartmentLocation(true));
    form.addEventListener('input', updateProgress);
    form.addEventListener('change', updateProgress);
    updateServiceFields(false);
    updateDepartmentLocation(false);

    form.addEventListener('submit', function () {
      const button = document.getElementById('btn-submit');
      if (button && this.checkValidity()) {
        setTimeout(() => { button.disabled = true; button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Mengirim...</span>'; }, 0);
      }
    });
  </script>
</body>
</html>