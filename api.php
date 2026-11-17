<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function reply(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function readInput(): array {
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

function cleanText($value, int $maxLength): string {
    if (!is_string($value) && !is_numeric($value)) {
        return '';
    }

    $value = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function textLength(string $value): int {
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

function validPhone(string $phone): bool {
    return (bool)preg_match('/^09[0-9]{9}$/', $phone);
}

function newTrackingCode(string $prefix): string {
    return $prefix . '-' . random_int(100000, 999999);
}

$action = (string)($_GET['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$sessionKey = session_id();

if ($action === 'state' && $method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT mood, mood_seen, mood_skipped
         FROM visitor_state WHERE session_key = ?'
    );
    $stmt->execute([$sessionKey]);
    $state = $stmt->fetch();

    reply([
        'ok' => true,
        'state' => $state ?: [
            'mood' => '',
            'mood_seen' => 0,
            'mood_skipped' => 0
        ]
    ]);
}

if ($action === 'state_set' && $method === 'POST') {
    $data = readInput();
    $mood = cleanText($data['mood'] ?? '', 30);
    $moodSeen = !empty($data['mood_seen']) ? 1 : 0;
    $moodSkipped = !empty($data['mood_skipped']) ? 1 : 0;

    $allowed = ['', 'happy', 'confused', 'sad', 'anxious', 'angry', 'tired'];
    if (!in_array($mood, $allowed, true)) {
        reply(['ok' => false, 'error' => 'حال واردشده معتبر نیست'], 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO visitor_state (session_key, mood, mood_seen, mood_skipped)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
         mood = VALUES(mood),
         mood_seen = VALUES(mood_seen),
         mood_skipped = VALUES(mood_skipped)'
    );
    $stmt->execute([$sessionKey, $mood, $moodSeen, $moodSkipped]);

    reply(['ok' => true]);
}

if ($action === 'journal' && $method === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT entry_text AS text,
                DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%sZ') AS date
         FROM journal_entries
         WHERE session_key = ?
         ORDER BY id DESC
         LIMIT 100"
    );
    $stmt->execute([$sessionKey]);
    reply(['ok' => true, 'entries' => array_reverse($stmt->fetchAll())]);
}

if ($action === 'journal_add' && $method === 'POST') {
    $data = readInput();
    $text = cleanText($data['text'] ?? '', 140);

    if (textLength($text) < 2) {
        reply(['ok' => false, 'error' => 'متن ژورنال کوتاه است'], 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO journal_entries (session_key, entry_text) VALUES (?, ?)'
    );
    $stmt->execute([$sessionKey, $text]);

    reply(['ok' => true]);
}

if ($action === 'wall' && $method === 'GET') {
    $stmt = $pdo->query(
        "SELECT note_text AS text, author_label AS who
         FROM wall_notes
         WHERE status = 'approved'
         ORDER BY id DESC
         LIMIT 100"
    );
    reply(['ok' => true, 'notes' => $stmt->fetchAll()]);
}

if ($action === 'wall_add' && $method === 'POST') {
    $data = readInput();
    $text = cleanText($data['text'] ?? '', 120);

    if (textLength($text) < 3) {
        reply(['ok' => false, 'error' => 'پیام کوتاه است'], 422);
    }

    // پیام عمومی ابتدا برای بازبینی ذخیره می‌شود.
    $stmt = $pdo->prepare(
        "INSERT INTO wall_notes (note_text, author_label, status)
         VALUES (?, 'ناشناس', 'pending')"
    );
    $stmt->execute([$text]);

    reply(['ok' => true, 'message' => 'پیام برای بازبینی ثبت شد']);
}

if ($action === 'booking' && $method === 'POST') {
    $data = readInput();

    $therapists = [
        'zeinab' => ['name' => 'زینب صادقیان', 'price' => 450000],
        'arzoo' => ['name' => 'ارزو سمسار', 'price' => 450000],
        'reyhaneh' => ['name' => 'ریحانه رحیمیان', 'price' => 500000],
        'mahla' => ['name' => 'مهلا استقامت', 'price' => 450000],
        'leila' => ['name' => 'لیلا همتی', 'price' => 500000],
        'mahnaz' => ['name' => 'مهناز همتی', 'price' => 520000],
        'hadi' => ['name' => 'هادی فخار', 'price' => 550000],
    ];

    $therapistId = cleanText($data['therapist_id'] ?? '', 40);
    $mode = cleanText($data['mode'] ?? '', 20);
    $date = cleanText($data['date'] ?? '', 120);
    $time = cleanText($data['time'] ?? '', 30);
    $name = cleanText($data['name'] ?? '', 120);
    $phone = cleanText($data['phone'] ?? '', 20);
    $note = cleanText($data['note'] ?? '', 200);

    if (!isset($therapists[$therapistId])) {
        reply(['ok' => false, 'error' => 'درمانگر معتبر نیست'], 422);
    }
    if (!in_array($mode, ['حضوری', 'آنلاین'], true)) {
        reply(['ok' => false, 'error' => 'نوع جلسه معتبر نیست'], 422);
    }
    if ($date === '' || $time === '' || textLength($name) < 3 || !validPhone($phone)) {
        reply(['ok' => false, 'error' => 'اطلاعات رزرو کامل یا معتبر نیست'], 422);
    }

    $therapist = $therapists[$therapistId];
    $code = newTrackingCode('NK');

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO bookings
             (tracking_code, therapist_id, therapist_name, mode, session_date,
              session_time, client_name, phone, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $code, $therapistId, $therapist['name'], $mode, $date,
            $time, $name, $phone, $note
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            reply(['ok' => false, 'error' => 'این زمان قبلاً رزرو شده است؛ زمان دیگری انتخاب کنید.'], 409);
        }
        error_log($e->getMessage());
        reply(['ok' => false, 'error' => 'ثبت رزرو انجام نشد'], 500);
    }

    reply([
        'ok' => true,
        'tracking_code' => $code,
        'therapist_name' => $therapist['name'],
        'price' => $therapist['price']
    ]);
}

if ($action === 'workshop' && $method === 'POST') {
    $data = readInput();
    $workshopId = cleanText($data['workshop_id'] ?? '', 20);
    $name = cleanText($data['name'] ?? '', 120);
    $phone = cleanText($data['phone'] ?? '', 20);

    if (textLength($name) < 3 || !validPhone($phone)) {
        reply(['ok' => false, 'error' => 'نام یا شماره موبایل معتبر نیست'], 422);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM workshops WHERE id = ? FOR UPDATE');
        $stmt->execute([$workshopId]);
        $workshop = $stmt->fetch();

        if (!$workshop) {
            $pdo->rollBack();
            reply(['ok' => false, 'error' => 'کارگاه پیدا نشد'], 404);
        }

        if ((int)$workshop['reserved'] >= (int)$workshop['capacity']) {
            $pdo->rollBack();
            reply(['ok' => false, 'error' => 'ظرفیت کارگاه تکمیل شده است'], 409);
        }

        $code = newTrackingCode('WK');

        $stmt = $pdo->prepare(
            'INSERT INTO workshop_registrations
             (tracking_code, workshop_id, workshop_title, instructor,
              start_date, price, client_name, phone)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $code, $workshop['id'], $workshop['title'], $workshop['instructor'],
            $workshop['start_date'], $workshop['price'], $name, $phone
        ]);

        $stmt = $pdo->prepare(
            'UPDATE workshops SET reserved = reserved + 1 WHERE id = ?'
        );
        $stmt->execute([$workshopId]);

        $pdo->commit();

        reply([
            'ok' => true,
            'tracking_code' => $code,
            'workshop_title' => $workshop['title'],
            'instructor' => $workshop['instructor'],
            'start_date' => $workshop['start_date'],
            'price' => (int)$workshop['price']
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        reply(['ok' => false, 'error' => 'ثبت‌نام انجام نشد؛ دوباره تلاش کنید'], 500);
    }
}

if ($action === 'letter' && $method === 'POST') {
    $data = readInput();
    $name = cleanText($data['name'] ?? '', 40);
    $email = cleanText($data['email'] ?? '', 190);
    $text = cleanText($data['text'] ?? '', 600);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || textLength($text) < 10) {
        reply(['ok' => false, 'error' => 'ایمیل یا متن نامه معتبر نیست'], 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO future_letters
         (session_key, client_name, email, letter_text, deliver_after)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 MONTH))'
    );
    $stmt->execute([$sessionKey, $name, $email, $text]);

    // نامه ذخیره می‌شود؛ ارسال خودکار ایمیل نیاز به سرویس ایمیل و زمان‌بندی دارد.
    reply(['ok' => true]);
}

reply(['ok' => false, 'error' => 'درخواست ناشناخته است'], 404);