<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $attempts = (int)($_SESSION['admin_attempts'] ?? 0);

    if ($attempts >= 8) {
        $error = 'تلاش‌های زیاد انجام شده؛ کمی بعد دوباره امتحان کنید.';
    } elseif (hash_equals($ADMIN_PASSWORD='1233', (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['admin_login'] = true;
        $_SESSION['admin_attempts'] = 0;
        header('Location: admin.php');
        exit;
    } else {
        $_SESSION['admin_attempts'] = $attempts + 1;
        $error = 'رمز عبور نادرست است.';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_login']);
    session_regenerate_id(true);
    header('Location: admin.php');
    exit;
}

if (empty($_SESSION['admin_login'])):
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ورود مدیریت</title>
  <style>
    body{font-family:Tahoma,sans-serif;background:#fdf8f2;display:grid;place-items:center;min-height:100vh}
    form{background:white;padding:28px;border-radius:16px;box-shadow:0 10px 40px #0001;width:min(360px,90%)}
    input,button{box-sizing:border-box;width:100%;padding:12px;margin-top:12px}
    button{background:#c9624a;color:white;border:0;border-radius:8px;cursor:pointer}
    .error{color:#b84848}
  </style>
</head>
<body>
  <form method="post">
    <h2>ورود به مدیریت</h2>
    <?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
    <input type="password" name="password" placeholder="رمز مدیریت" required autocomplete="current-password">
    <button type="submit" name="login" value="1">ورود</button>
  </form>
</body>
</html>
<?php
exit;
endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_wall'])) {
    if (!hash_equals($_SESSION['admin_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('درخواست معتبر نیست');
    }

    $id = (int)($_POST['approve_wall'] ?? 0);
    $stmt = $pdo->prepare("UPDATE nn_wall_notes SET status='approved' WHERE id=?");
    $stmt->execute([$id]);

    header('Location: admin.php#wall');
    exit;
}

$bookings = $pdo->query('SELECT * FROM nn_bookings ORDER BY id DESC LIMIT 200')->fetchAll();
$registrations = $pdo->query('SELECT * FROM nn_workshop_registrations ORDER BY id DESC LIMIT 200')->fetchAll();
$letters = $pdo->query('SELECT * FROM nn_future_letters ORDER BY id DESC LIMIT 200')->fetchAll();
$wall = $pdo->query("SELECT * FROM nn_wall_notes WHERE status='pending' ORDER BY id DESC LIMIT 200")->fetchAll();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>پنل مدیریت</title>
  <style>
    body{font-family:Tahoma,sans-serif;background:#fdf8f2;color:#33261c;margin:0;padding:20px}
    main{max-width:1200px;margin:auto}
    header{display:flex;justify-content:space-between;align-items:center}
    section{background:white;padding:18px;border-radius:14px;margin:20px 0;overflow:auto}
    table{width:100%;border-collapse:collapse;min-width:700px}
    th,td{border-bottom:1px solid #eee;padding:10px;text-align:right;font-size:13px}
    th{background:#faf2e8}
    button,a{padding:8px 12px;border:0;border-radius:7px;background:#c9624a;color:white;text-decoration:none;cursor:pointer}
    form{display:inline}
  </style>
</head>
<body>
<main>
<header>
  <h1>پنل مدیریت</h1>
  <a href="admin.php?logout=1">خروج</a>
</header>

<section>
<h2>رزروهای مشاوره</h2>
<table><tr><th>کد</th><th>درمانگر</th><th>تاریخ</th><th>ساعت</th><th>نام</th><th>موبایل</th><th>توضیح</th><th>ثبت</th></tr>
<?php foreach ($bookings as $r): ?>
<tr>
<td><?= h($r['tracking_code']) ?></td><td><?= h($r['therapist_name']) ?></td>
<td><?= h($r['session_date']) ?></td><td><?= h($r['session_time']) ?></td>
<td><?= h($r['client_name']) ?></td><td><?= h($r['phone']) ?></td>
<td><?= h($r['note']) ?></td><td><?= h($r['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</section>

<section>
<h2>ثبت‌نام‌های کارگاه</h2>
<table><tr><th>کد</th><th>کارگاه</th><th>نام</th><th>موبایل</th><th>مبلغ</th><th>وضعیت پرداخت</th><th>ثبت</th></tr>
<?php foreach ($registrations as $r): ?>
<tr>
<td><?= h($r['tracking_code']) ?></td><td><?= h($r['workshop_title']) ?></td>
<td><?= h($r['client_name']) ?></td><td><?= h($r['phone']) ?></td>
<td><?= h(number_format((int)$r['amount'])) ?></td><td><?= h($r['payment_status']) ?></td>
<td><?= h($r['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</section>

<section id="wall">
<h2>پیام‌های دیوار، در انتظار بازبینی</h2>
<table><tr><th>پیام</th><th>ثبت</th><th>عملیات</th></tr>
<?php foreach ($wall as $r): ?>
<tr>
<td><?= h($r['note_text']) ?></td><td><?= h($r['created_at']) ?></td>
<td>
<form method="post">
<input type="hidden" name="csrf" value="<?= h($_SESSION['admin_csrf']) ?>">
<button name="approve_wall" value="<?= (int)$r['id'] ?>">تأیید و انتشار</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</section>

<section>
<h2>نامه‌های آینده</h2>
<table><tr><th>نام</th><th>ایمیل</th><th>متن نامه</th><th>زمان ارسال برنامه‌ریزی‌شده</th></tr>
<?php foreach ($letters as $r): ?>
<tr>
<td><?= h($r['name']) ?></td><td><?= h($r['email']) ?></td>
<td><?= h($r['letter_text']) ?></td><td><?= h($r['deliver_after']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<p>نامه‌ها ذخیره می‌شوند، اما ارسال ایمیل خودکار راه‌اندازی نشده است.</p>
</section>
</main>
</body>
</html>