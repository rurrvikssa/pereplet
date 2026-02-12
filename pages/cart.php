<?php
require_once __DIR__ . '/../includes/connect.php';
@session_start();

// определяем владельца корзины: либо user_id, либо session_id
$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// если user_id есть в сессии, но пользователя уже нет в БД (удалён и т.п.),
// считаем корзину гостевой, чтобы не ломать внешний ключ cart_items.user_id
if ($userId) {
    try {
        $checkUser = $pdo->prepare('SELECT 1 FROM users WHERE id = ? LIMIT 1');
        $checkUser->execute([$userId]);
        if (!$checkUser->fetchColumn()) {
            $userId = null;
            unset($_SESSION['user_id']);
        }
    } catch (Throwable $e) {
        // в случае ошибки проверки просто не используем user_id
        $userId = null;
    }
}

if (empty($_SESSION['cart_sid'])) {
    $_SESSION['cart_sid'] = session_id();
}
$sessionId = $_SESSION['cart_sid'];

// небольшая обёртка, чтобы в запросах не дублировать условия
function cart_where_clause(&$params, $userId, $sessionId) {
    if ($userId) {
        $params[] = $userId;
        return 'user_id = ?';
    }
    $params[] = $sessionId;
    return 'session_id = ?';
}

// обработка действий с корзиной
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $bookId = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

    try {
        if ($action === 'add' && $bookId > 0) {
            // узнаём актуальную цену книги
            $price = 0;
            $st = $pdo->prepare('SELECT price FROM books WHERE id = ?');
            $st->execute([$bookId]);
            if ($row = $st->fetch()) {
                $price = (float)$row['price'];
            }

            // есть ли уже такая позиция в корзине
            $params = [];
            $where = cart_where_clause($params, $userId, $sessionId);
            $params[] = $bookId;
            $st = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE $where AND book_id = ?");
            $st->execute($params);
            if ($exist = $st->fetch()) {
                $upd = $pdo->prepare('UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?');
                $upd->execute([(int)$exist['id']]);
            } else {
                $ins = $pdo->prepare('INSERT INTO cart_items (user_id, session_id, book_id, quantity, price) VALUES (?, ?, ?, 1, ?)');
                $ins->execute([$userId, $sessionId, $bookId, $price]);
            }
        } elseif (($action === 'inc' || $action === 'dec') && $itemId > 0) {
            // изменить количество конкретной позиции по её id
            $delta = $action === 'inc' ? 1 : -1;
            // сначала получим текущее количество
            $st = $pdo->prepare('SELECT quantity FROM cart_items WHERE id = ?');
            $st->execute([$itemId]);
            if ($row = $st->fetch()) {
                $qty = (int)$row['quantity'] + $delta;
                if ($qty <= 0) {
                    $del = $pdo->prepare('DELETE FROM cart_items WHERE id = ?');
                    $del->execute([$itemId]);
                } else {
                    $upd = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
                    $upd->execute([$qty, $itemId]);
                }
            }
        } elseif ($action === 'remove' && $itemId > 0) {
            $params = [];
            $whereOwner = cart_where_clause($params, $userId, $sessionId);
            $params[] = $itemId;
            $del = $pdo->prepare("DELETE FROM cart_items WHERE $whereOwner AND id = ?");
            $del->execute($params);
        } elseif ($action === 'clear') {
            $params = [];
            $whereOwner = cart_where_clause($params, $userId, $sessionId);
            $del = $pdo->prepare("DELETE FROM cart_items WHERE $whereOwner");
            $del->execute($params);
        }
    } catch (Throwable $e) {
        // тихо игнорируем, чтобы страница корзины всё равно открывалась
    }

    // после любого POST — редирект, чтобы не было повторных отправок формы
    header('Location: ./cart.php');
    exit;
}

// загрузка текущей корзины
$items = [];
$totalQty = 0;
$totalSum = 0.0;
try {
    $params = [];
    $whereOwner = cart_where_clause($params, $userId, $sessionId);
    $sql = "SELECT ci.id AS item_id, ci.book_id, ci.quantity, ci.price, b.title, b.author, b.image_url
            FROM cart_items ci
            JOIN books b ON b.id = ci.book_id
            WHERE $whereOwner
            ORDER BY ci.id DESC";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll();
    foreach ($items as $it) {
        $totalQty += (int)$it['quantity'];
        $totalSum += (float)$it['price'] * (int)$it['quantity'];
    }
} catch (Throwable $e) {
    $items = [];
}

function fmt_price($v) {
    return number_format((float)$v, 0, '.', ' ');
}
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - корзина</title>
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
                    <li class="breadcrumbs__current">корзина</li>
                </ol>
            </div>
        </nav>

        <section class="cart-header">
            <div class="container cart-header__inner">
                <div class="cart-header__left">
                    <h1 class="cart-header__title">корзина</h1>
                    <div class="cart-header__count"><?= $totalQty ?> товар<?= ($totalQty % 10 == 1 && $totalQty % 100 != 11) ? '' : 'ов' ?></div>
                </div>
                <?php if ($items) { ?>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="clear">
                        <button class="cart-header__clear-btn" type="submit">
                            <img src="../assets/media/icons/trash_icon.svg" alt="">
                            <span>очистить корзину</span>
                        </button>
                    </form>
                <?php } ?>
            </div>
        </section>

        <section class="cart-main">
            <div class="container cart-main__inner">
                <div class="cart-main__left">
                    <?php if ($items) { ?>
                        <?php foreach ($items as $it) { 
                            $img = !empty($it['image_url']) ? $it['image_url'] : '../assets/media/images/card_img.png';
                            $unitPrice = (float)$it['price'];
                            $lineTotal = $unitPrice * (int)$it['quantity']; // используется только в итогах справа
                        ?>
                        <article class="cart-item">
                            <div class="cart-item__media">
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($it['title']) ?>">
                            </div>
                            <div class="cart-item__body">
                                <div class="cart-item__top">
                                    <h3 class="cart-item__title"><?= htmlspecialchars($it['title']) ?></h3>
                                    <div class="cart-item__price"><?= fmt_price($unitPrice) ?> ₽</div>
                                </div>
                                <div class="cart-item__author"><?= htmlspecialchars($it['author']) ?></div>
                                <div class="cart-item__bottom">
                                    <div class="cart-item__qty" role="group" aria-label="quantity">
                                        <form method="post" action="" style="display:inline-block">
                                            <input type="hidden" name="action" value="dec">
                                            <input type="hidden" name="item_id" value="<?= (int)$it['item_id'] ?>">
                                            <button type="submit" class="cart-item__qty-btn" aria-label="minus">−</button>
                                        </form>
                                        <div class="cart-item__qty-value"><?= (int)$it['quantity'] ?></div>
                                        <form method="post" action="" style="display:inline-block">
                                            <input type="hidden" name="action" value="inc">
                                            <input type="hidden" name="item_id" value="<?= (int)$it['item_id'] ?>">
                                            <button type="submit" class="cart-item__qty-btn" aria-label="plus">+</button>
                                        </form>
                                    </div>
                                    <div class="cart-item__actions">
                                        <a href="#" class="cart-item__action">
                                            <img src="../assets/media/icons/bookmark_icon.svg" alt="">
                                            <span>в избранное</span>
                                        </a>
                                        <form method="post" action="" class="cart-item__del">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="item_id" value="<?= (int)$it['item_id'] ?>">
                                            <button type="submit" class="cart-item__action">
                                                <img src="../assets/media/icons/trash_icon.svg" alt="">
                                                <span>удалить</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                        <?php } ?>
                    <?php } else { ?>
                        <p>Ваша корзина пуста.</p>
                    <?php } ?>
                </div>
                <aside class="cart-main__right">
                    <div class="cart-summary">
                        <div class="cart-summary__rows">
                            <div class="cart-summary__row"><span><?= $totalQty ?> товар<?= ($totalQty % 10 == 1 && $totalQty % 100 != 11) ? '' : 'ов' ?></span><span><?= fmt_price($totalSum) ?> ₽</span></div>
                            <div class="cart-summary__row"><span>скидка</span><span>0 ₽</span></div>
                            <div class="cart-summary__row"><span>доставка</span><span><?= $items ? 'бесплатно' : '—' ?></span></div>
                        </div>
                        <div class="cart-summary__total">
                            <span class="cart-summary__total-label">итого</span>
                            <span class="cart-summary__total-price"><?= fmt_price($totalSum) ?> ₽</span>
                        </div>
                        <div class="cart-summary__bonus-label">применить бонусы</div>
                        <label class="cart-summary__input-wrap">
                            <input type="number" class="cart-summary__input" placeholder="введите количество">
                        </label>
                        <a class="cart-summary__btn" href="./order.php" role="button">
                            к оформлению
                        </a>
                    </div>
                </aside>
            </div>
        </section>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </main>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=YOUR_YANDEX_MAPS_API_KEY"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
