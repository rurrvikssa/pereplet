<?php
require_once __DIR__ . '/../includes/connect.php';

@session_start();

// определяем роль текущего пользователя (2 = админ)
$CURRENT_ROLE = 0;
if (isset($_SESSION['user']['role'])) {
    $CURRENT_ROLE = (int)$_SESSION['user']['role'];
} elseif (isset($_SESSION['role'])) {
    $CURRENT_ROLE = (int)$_SESSION['role'];
} elseif (!empty($_SESSION['user_id'])) {
    try {
        $stRole = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stRole->execute([(int)$_SESSION['user_id']]);
        if ($rowR = $stRole->fetch()) {
            $CURRENT_ROLE = (int)$rowR['role'];
        }
    } catch (Throwable $e) {
        $CURRENT_ROLE = 0;
    }
}

if ($CURRENT_ROLE !== 2) {
    header('Location: ./account.php');
    exit;
}

// Обработка действий администратора по заказам
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $orderId = (int)$_POST['order_id'];
    $action  = $_POST['action'];

    if ($orderId > 0 && in_array($action, ['approve', 'cancel'], true)) {
        $newStatus = ($action === 'approve') ? 'confirmed' : 'cancelled';
        try {
            $st = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $st->execute([$newStatus, $orderId]);
        } catch (Throwable $e) {
            // игнорируем, просто не меняем статус
        }
    }

    header('Location: ./admin-orders.php');
    exit;
}

// Загружаем заказы по статусам
$pendingOrders   = [];
$confirmedOrders = [];
$cancelledOrders = [];

try {
    $sql = 'SELECT o.id, o.fullname, o.phone, COALESCE(SUM(oi.quantity), 0) AS items_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.status = :status
            GROUP BY o.id, o.fullname, o.phone
            ORDER BY o.id DESC';

    $stmt = $pdo->prepare($sql);

    $stmt->execute(['status' => 'pending']);
    $pendingOrders = $stmt->fetchAll();

    $stmt->execute(['status' => 'confirmed']);
    $confirmedOrders = $stmt->fetchAll();

    $stmt->execute(['status' => 'cancelled']);
    $cancelledOrders = $stmt->fetchAll();
} catch (Throwable $e) {
    $pendingOrders   = [];
    $confirmedOrders = [];
    $cancelledOrders = [];
}
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - админ-панель</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="shortcut icon" href="../assets/media/icons/logo.svg" type="image/x-icon">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <nav class="breadcrumbs">
            <div class="container breadcrumbs__inner">
                <ol class="breadcrumbs__list">
                    <li><a href="./index.php" class="breadcrumbs__link">главная</a></li>
                    <li class="breadcrumbs__sep">/</li>
                    <li><a href="./account.php" class="breadcrumbs__link">профиль</a></li>
                    <li class="breadcrumbs__sep">/</li>
                    <li><a href="./admin.php" class="breadcrumbs__link">админ-панель</a></li>
                    <li class="breadcrumbs__sep">/</li>
                    <li class="breadcrumbs__current">заказы</li>
                </ol>
            </div>
        </nav>
        <section class="admin">
            <div class="container admin__inner">
                <aside class="account__sidebar">
                    <nav class="account-menu">
                        <a href="./admin.php" class="account-menu__item">
                            <img src="../assets/media/icons/add_icon.svg" alt="">
                            <span>добавление</span>
                        </a>
                        <a href="./admin-clients.php" class="account-menu__item">
                            <img src="../assets/media/icons/clients_icon.svg" alt="">
                            <span>пользователи</span>
                        </a>
                        <a href="./admin-orders.php" class="account-menu__item is-active">
                            <img src="../assets/media/icons/orders_icon.svg" alt="">
                            <span>заказы</span>
                        </a>
                        <a href="#" class="account-menu__item account-menu__item--logout">
                            <img src="../assets/media/icons/exit_icon.svg" alt="">
                            <span>выйти</span>
                        </a>
                    </nav>
                </aside>

                <div class="admin__content">
                    <h1 class="orders__title">заказы</h1>

                    <div class="orders-group">
                        <div class="orders-group__title">ожидают подтверждения:</div>
                        <div class="orders-grid">
                            <?php if (!empty($pendingOrders)): ?>
                                <?php foreach ($pendingOrders as $order): ?>
                                    <div class="order-card2">
                                        <div class="order-card2__name">Заказ № <?= (int)$order['id'] ?></div>
                                        <dl class="order-card2__list">
                                            <div class="order-card2__row"><dt>товаров:</dt><dd><?= (int)$order['items_count'] ?></dd></div>
                                            <div class="order-card2__row"><dt>ФИО:</dt><dd><?= htmlspecialchars($order['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd></div>
                                            <div class="order-card2__row"><dt>номер телефона:</dt><dd><?= htmlspecialchars($order['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd></div>
                                        </dl>
                                        <form class="order-card2__actions" method="post">
                                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                            <button class="order-act order-act--ok" type="submit" name="action" value="approve">
                                                <img src="../assets/media/icons/check_icon.svg" alt=""><span>подтвердить</span>
                                            </button>
                                            <button class="order-act order-act--cancel" type="submit" name="action" value="cancel">
                                                <img src="../assets/media/icons/xmark_icon.svg" alt=""><span>отменить</span>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Нет заказов, ожидающих подтверждения.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="orders-group">
                        <div class="orders-group__title">подтвержденные:</div>
                        <div class="orders-grid">
                            <?php if (!empty($confirmedOrders)): ?>
                                <?php foreach ($confirmedOrders as $order): ?>
                                    <div class="order-card2">
                                        <div class="order-card2__name">Заказ № <?= (int)$order['id'] ?></div>
                                        <dl class="order-card2__list">
                                            <div class="order-card2__row"><dt>товаров:</dt><dd><?= (int)$order['items_count'] ?></dd></div>
                                            <div class="order-card2__row"><dt>ФИО:</dt><dd><?= htmlspecialchars($order['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd></div>
                                            <div class="order-card2__row"><dt>номер телефона:</dt><dd><?= htmlspecialchars($order['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd></div>
                                        </dl>
                                        <div class="order-card2__status order-card2__status--ok">
                                            <img src="../assets/media/icons/check_icon.svg" alt=""><span>подтвержден</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Нет подтвержденных заказов.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="orders-group">
                        <div class="orders-group__title">отменены:</div>
                        <div class="orders-grid">
                            <?php if (!empty($cancelledOrders)): ?>
                                <?php foreach ($cancelledOrders as $order): ?>
                                    <div class="order-card2">
                                        <div class="order-card2__name">Заказ № <?= (int)$order['id'] ?></div>
                                        <dl class="order-card2__list">
                                            <div class="order-card2__row"><dt>товаров:</dt><dd><?= (int)$order['items_count'] ?></dd></div>
                                            <div class="order-card2__row"><dt>ФИО:</dt><dd><?= htmlspecialchars($order['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd></div>
                                            <div class="order-card2__row"><dt>номер телефона:</dt><dd><?= htmlspecialchars($order['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></dd></div>
                                        </dl>
                                        <div class="order-card2__status order-card2__status--cancel">
                                            <img src="../assets/media/icons/xmark_icon.svg" alt=""><span>отменён</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Нет отменённых заказов.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </main>
</body>
</html>
