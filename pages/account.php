<?php
require_once __DIR__ . '/../includes/connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ./login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([(int) $_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: ./login.php');
    exit;
}

$displayName = $user['username'] ?: ($user['email'] ?? 'Пользователь');
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';

// исходное значение статуса из базы (приводим к строке)
$statusFromDb = isset($user['status']) ? (string)$user['status'] : '';
$statusFromDb = trim($statusFromDb);

// базовый статус: если в базе пусто, считаем пользователем-"читателем"
$status = $statusFromDb !== '' ? $statusFromDb : 'читатель';

// нормализуем строку статуса для сравнения
$statusNorm = mb_strtolower($status, 'UTF-8');
$statusNorm = preg_replace('/переплета/u', 'переплёта', $statusNorm);
if ($statusNorm === 'читатель') {
    $status = 'читатель';
} elseif ($statusNorm === 'книголюб') {
    $status = 'книголюб';
} elseif ($statusNorm === 'легенда переплёта') {
    $status = 'Легенда Переплёта';
} else {
    $status = 'читатель';
}

$statusIcon = '../assets/media/icons/book_icon.svg';
if ($status === 'книголюб') {
    $statusIcon = '../assets/media/icons/heart_icon.svg';
} elseif ($status === 'Легенда Переплёта') {
    $statusIcon = '../assets/media/icons/book_icon.svg';
}

$allStatuses = ['читатель', 'книголюб', 'Легенда Переплёта'];
$statusLineParts = array_map(function ($s) use ($status) {
    return $s === $status ? '<span class="is-accent">' . htmlspecialchars($s) . '</span>' : htmlspecialchars($s);
}, $allStatuses);
$statusLine = implode(' / ', $statusLineParts);

$privileges = [];
switch ($status) {
    case 'книголюб':
        $privileges = [
            'Кэшбек 5% бонусными рублями',
            'Бесплатная доставка от 1 500 ₽',
            'Персональные рекомендации',
            'Доступ к закрытым распродажам',
        ];
        break;
    case 'Легенда Переплёта':
        $privileges = [
            'Бесплатная доставка',
            'Кешбэк 10% бонусными рублями',
            'Персональные рекомендации',
            'Доступ к закрытым распродажам',
        ];
        break;
    default: // читатель
        $privileges = [
            'Кэшбек 2% бонусными рублями',
            'Бесплатная доставка при заказе от 2 500 ₽',
        ];
}

$bonusPoints = (int) ($user['bonus_points'] ?? 0);

// last order status notification
$orderNotify = null;
try {
    $stOrder = $pdo->prepare('SELECT id, status, total_amount FROM orders WHERE user_id = ? AND status IN ("confirmed", "cancelled") ORDER BY id DESC LIMIT 1');
    $stOrder->execute([(int)$_SESSION['user_id']]);
    $orderNotify = $stOrder->fetch();
} catch (Throwable $e) {
    $orderNotify = null;
}

$userOrders = [];
try {
    $stList = $pdo->prepare('SELECT o.id, o.total_amount, o.status, COALESCE(SUM(oi.quantity), 0) AS items_count
                              FROM orders o
                              LEFT JOIN order_items oi ON oi.order_id = o.id
                              WHERE o.user_id = ? AND o.status = "confirmed"
                              GROUP BY o.id, o.total_amount, o.status
                              ORDER BY o.id DESC');
    $stList->execute([(int)$_SESSION['user_id']]);
    $userOrders = $stList->fetchAll();
} catch (Throwable $e) {
    $userOrders = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $newUsername = trim($_POST['username'] ?? '');
    $newPhone    = trim($_POST['phone'] ?? '');
    $newEmail    = trim($_POST['email'] ?? '');

    $updateErrors = [];
    if ($newEmail !== '' && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $updateErrors['email'] = 'Некорректная почта';
    }

    if (!$updateErrors) {
        $stmt = $pdo->prepare('UPDATE users SET username = ?, phone = ?, email = ? WHERE id = ?');
        $stmt->execute([
            $newUsername !== '' ? $newUsername : null,
            $newPhone !== '' ? $newPhone : null,
            $newEmail !== '' ? $newEmail : null,
            (int)$_SESSION['user_id']
        ]);
        header('Location: ./account.php?updated=1');
        exit;
    } else {
        $user['username'] = $newUsername;
        $user['phone'] = $newPhone;
        $user['email'] = $newEmail;
        $displayName = $newUsername ?: ($newEmail ?: 'Пользователь');
        $email = $newEmail;
        $phone = $newPhone;
    }
}
?><!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - профиль</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="shortcut icon" href="../assets/media/icons/logo.svg" type="image/x-icon">
</head>

<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <?php if (!empty($orderNotify)): ?>
            <?php
            $isConfirmed = ($orderNotify['status'] ?? '') === 'confirmed';
            $notifyClass = $isConfirmed ? 'notify--success' : 'notify--error';
            $notifyTitle = $isConfirmed ? 'заказ подтвержден' : 'заказ отменен';
            $notifyText  = 'Заказ № ' . (int)$orderNotify['id'];
            ?>
            <div class="notify-wrap">
                <div class="notify <?= $notifyClass ?>">
                    <div class="notify__title"><?= htmlspecialchars($notifyTitle, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="notify__text"><?= htmlspecialchars($notifyText, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        <?php endif; ?>
        <nav class="breadcrumbs">
            <div class="container breadcrumbs__inner">
                <ol class="breadcrumbs__list">
                    <li><a href="./index.php" class="breadcrumbs__link">главная</a></li>
                    <li class="breadcrumbs__sep">/</li>
                    <li class="breadcrumbs__current">профиль</li>
                </ol>
            </div>
        </nav>

        <section class="account">
            <div class="container account__inner">
                <aside class="account__sidebar">
                    <nav class="account-menu">
                        <a href="#" class="account-menu__item">
                            <img src="../assets/media/icons/face_img.svg" alt="">
                            <span>личные данные</span>
                        </a>
                        <a href="#" class="account-menu__item">
                            <img src="../assets/media/icons/box_icon.svg" alt="">
                            <span>заказы</span>
                        </a>
                        <a href="#" class="account-menu__item">
                            <img src="../assets/media/icons/logo.svg" alt="">
                            <span>Статус Переплёта</span>
                        </a>
                        <a href="./logout.php" class="account-menu__item account-menu__item--logout">
                            <img src="../assets/media/icons/exit_icon.svg" alt="">
                            <span>выйти</span>
                        </a>
                    </nav>
                </aside>
                <div class="account__content">
                    <div class="account__header">
                        <h1 class="account__name"><?= htmlspecialchars($displayName) ?></h1>
                        <div class="account__role">
                            <img class="account__role-icon" src="<?= $statusIcon ?>" alt="">
                            <span class="account__role-text"><?= htmlspecialchars($status) ?></span>
                        </div>
                    </div>
                    <div class="account-privileges">
                        <div class="account-privileges__content">
                            <div class="account-privileges__line"><?= $statusLine ?></div>
                            <div class="account-privileges__title">ваши привилегии</div>
                            <ul class="account-privileges__list">
                                <?php foreach ($privileges as $p) { ?>
                                    <li><?= htmlspecialchars($p) ?></li>
                                <?php } ?>
                            </ul>
                        </div>
                        <img class="account-privileges__image"
                            src="../assets/media/images/account/account_layout_img.png" alt="">

                        <div class="account-bonuses">
                            <div class="account-bonuses__value"><?= htmlspecialchars((string) $bonusPoints) ?></div>
                            <div class="account-bonuses__label">накопленных<br>бонусов</div>
                            <div class="account-bonuses__note">1 бонус = 1 рубль</div>
                        </div>
                    </div>
                    <div class="account-form">
                        <div class="account-form__title">личные данные</div>
                        <form class="account-form__rows" action="" method="post">
                            <div class="account-form__row">
                                <label class="account-field">
                                    <span class="account-field__label">имя пользователя</span>
                                    <input class="account-input account-input--w600" type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" placeholder="Введите имя">
                                </label>
                                <label class="account-field">
                                    <span class="account-field__label">номер телефона</span>
                                    <input class="account-input account-input--w432" type="tel" name="phone" value="<?= htmlspecialchars($phone) ?>" placeholder="+7 ___ ___ __ __">
                                </label>
                            </div>
                            <div class="account-form__row">
                                <label class="account-field">
                                    <span class="account-field__label">почта</span>
                                    <input class="account-input account-input--w600" type="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="email@example.com">
                                </label>
                                <div class="account-form__actions">
                                    <input type="hidden" name="action" value="update_profile">
                                    <button type="submit" class="account-update-btn">
                                        <img src="../assets/media/icons/pen_icon.svg" alt="">
                                        <span>изменить</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="account-orders">
                        <div class="account-orders__title">заказы</div>
                        <div class="account-orders__grid">
                            <?php if (!empty($userOrders)): ?>
                                <?php foreach ($userOrders as $o): ?>
                                    <div class="order-card">
                                        <div class="order-card__top">
                                            <div class="order-card__title">заказ № <?= (int)$o['id'] ?></div>
                                            <ul class="order-card__list">
                                                <li>товаров: <?= (int)$o['items_count'] ?></li>
                                                <li>сумма заказа: <span class="order-card__price"><br><?= number_format((float)$o['total_amount'], 0, '.', ' ') ?> ₽</span></li>
                                            </ul>
                                        </div>
                                        <button class="order-card__btn" type="button">подробнее</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>У вас пока нет заказов.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </main>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=YOUR_YANDEX_MAPS_API_KEY"></script>
    <script src="../assets/js/script.js"></script>
    <script>
    (function(){
        var n = document.querySelector('.notify-wrap');
        if (!n) return;
        setTimeout(function(){
            if (n && n.parentNode) {
                n.parentNode.removeChild(n);
            }
        }, 6000);
    })();
    </script>
</body>

</html>