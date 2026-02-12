<?php
require_once __DIR__ . '/../includes/connect.php';
@session_start();

// Определяем владельца корзины (как в cart.php / order.php)
$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
if (empty($_SESSION['cart_sid'])) {
    $_SESSION['cart_sid'] = session_id();
}
$sessionId = $_SESSION['cart_sid'];

// Читаем данные из формы
$fullname = trim((string)($_POST['fullname'] ?? ''));
$phone    = trim((string)($_POST['phone'] ?? ''));
$comment  = trim((string)($_POST['comment'] ?? ''));

// Если нет обязательных полей или они не проходят базовую валидацию — возвращаемся на order.php
if ($fullname === '' || mb_strlen($fullname, 'UTF-8') < 3 || $phone === '' || !preg_match('/^\d+$/', $phone)) {
    header('Location: ./order.php');
    exit;
}

// Загружаем корзину
$items = [];
$total = 0.0;
try {
    $params = [];
    if ($userId) {
        $sql = 'SELECT book_id, quantity, price FROM cart_items WHERE user_id = ?';
        $params[] = $userId;
    } else {
        $sql = 'SELECT book_id, quantity, price FROM cart_items WHERE session_id = ?';
        $params[] = $sessionId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll();
    foreach ($items as $row) {
        $q = (int)($row['quantity'] ?? 0);
        $p = (float)($row['price'] ?? 0);
        $total += $q * $p;
    }
} catch (Throwable $e) {
    $items = [];
}

// Если корзина пустая — отправляем пользователя обратно в корзину
if (!$items || $total <= 0) {
    header('Location: ./cart.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // Создаём заказ
    $stOrd = $pdo->prepare('INSERT INTO orders (user_id, status, total_amount, fullname, phone, comment) VALUES (?, ?, ?, ?, ?, ?)');
    $stOrd->execute([
        $userId,
        'pending',
        $total,
        $fullname,
        $phone,
        $comment,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // Позиции заказа
    $stItem = $pdo->prepare('INSERT INTO order_items (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)');
    foreach ($items as $row) {
        $stItem->execute([
            $orderId,
            (int)$row['book_id'],
            (int)$row['quantity'],
            (float)$row['price'],
        ]);
    }

    // --- Юкасса по твоему примеру ---
    // создаем ключ идемпотентности
    $idempotentKey = uniqid('', true);

    // формируем массив с данными о платеже
    $paymentData = [
        'amount' => [
            'value' => number_format($total, 2, '.', ''),
            'currency' => 'RUB',
        ],
        'capture' => true,
        'confirmation' => [
            'type' => 'redirect',
            'return_url' => 'http://localhost/pereplet_php/pages/order-thank.php',
        ],
        'description' => 'Заказ №' . $orderId,
    ];

    // URL API юкасса для создания платежа
    $url = 'https://api.yookassa.ru/v3/payments';

    // Опции HTTP-запроса (без cURL)
    $option = [
        'http' => [
            'header' => "Content-Type: application/json\r\n"
                . "Idempotence-Key: " . $idempotentKey . "\r\n"
                . "Authorization: Basic " . base64_encode("1178497:test_KRP-CbJQKnnfEq2Eueb58MhSf6KAsyu8GDH3mD1V2Vk"),
            'method' => 'POST',
            'content' => json_encode($paymentData, JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
        ],
    ];

    $context = stream_context_create($option);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        // Ошибка при создании платежа — откатываем заказ и отправляем на страницу заказа
        $pdo->rollBack();
        header('Location: ./order.php');
        exit;
    }

    $response = json_decode($result, true);

    // сохраняем данные о платеже
    $paymentId       = $response['id'] ?? null;
    $confirmationUrl = $response['confirmation']['confirmation_url'] ?? null;
    $yStatus         = $response['status'] ?? null;

    $stUpd = $pdo->prepare('UPDATE orders SET yookassa_payment_id = ?, yookassa_idempotence_key = ?, yookassa_status = ?, yookassa_confirmation_url = ? WHERE id = ?');
    $stUpd->execute([
        $paymentId,
        $idempotentKey,
        $yStatus,
        $confirmationUrl,
        $orderId,
    ]);

    // очищаем корзину
    $paramsDel = [];
    if ($userId) {
        $sqlDel = 'DELETE FROM cart_items WHERE user_id = ?';
        $paramsDel[] = $userId;
    } else {
        $sqlDel = 'DELETE FROM cart_items WHERE session_id = ?';
        $paramsDel[] = $sessionId;
    }
    $stDel = $pdo->prepare($sqlDel);
    $stDel->execute($paramsDel);

    $pdo->commit();

    // редирект на страницу оплаты или на спасибо, если URL не пришёл
    if ($confirmationUrl) {
        header('Location: ' . $confirmationUrl);
    } else {
        header('Location: ./order-thank.php');
    }
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ./order.php');
    exit;
}
