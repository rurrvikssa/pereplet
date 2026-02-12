<?php
require_once __DIR__ . '/../includes/connect.php';
@session_start();

// считаем количество товаров и сумму как в корзине
$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
if (empty($_SESSION['cart_sid'])) {
    $_SESSION['cart_sid'] = session_id();
}
$sessionId = $_SESSION['cart_sid'];

$orderTotalQty = 0;
$orderTotalSum = 0.0;
try {
    $params = [];
    if ($userId) {
        $sql = 'SELECT quantity, price FROM cart_items WHERE user_id = ?';
        $params[] = $userId;
    } else {
        $sql = 'SELECT quantity, price FROM cart_items WHERE session_id = ?';
        $params[] = $sessionId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll() as $row) {
        $q = (int)($row['quantity'] ?? 0);
        $p = (float)($row['price'] ?? 0);
        $orderTotalQty += $q;
        $orderTotalSum += $q * $p;
    }
} catch (Throwable $e) {
    $orderTotalQty = 0;
    $orderTotalSum = 0.0;
}
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - оформление заказа</title>
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
                    <li><a href="./cart.php" class="breadcrumbs__link">корзина</a></li>
                    <li class="breadcrumbs__sep">/</li>
                    <li class="breadcrumbs__current">оформление заказа</li>
                </ol>
            </div>
        </nav>

        <h1 class="order__title container">оформление заказа</h1>
        <section class="order">
            <div class="container order__inner">
                <div class="order__box">
                    <div class="order__inputs">
                        <div class="order__inputs-content">
                            <div class="order__group">
                                <h3 class="order__heading">адрес доставки</h3>
                                <div class="order__row order__row--two">
                                    <input class="order-input order-input--w626" type="text" id="orderCity" placeholder="город">
                                    <input class="order-input order-input--w626" type="text" id="orderStreet" placeholder="улица">
                                </div>
                                <div class="order__row order__row--four">
                                    <input class="order-input order-input--w176" type="text" id="orderHouse" placeholder="дом">
                                    <input class="order-input order-input--w176" type="text" id="orderFlat" placeholder="квартира">
                                    <input class="order-input order-input--w176" type="text" id="orderEntrance" placeholder="подъезд">
                                    <input class="order-input order-input--w176" type="text" id="orderFloor" placeholder="этаж">
                                </div>
                            </div>

                            <div class="order__group">
                                <h3 class="order__heading">контакты</h3>
                                <div class="order__row order__row--two">
                                    <input class="order-input order-input--w626" type="text" name="order_fullname" id="orderFullname" placeholder="ФИО">
                                    <input class="order-input order-input--w754" type="tel" name="order_phone" id="orderPhone" placeholder="телефон">
                                </div>
                            </div>

                            <div class="order__group">
                                <h3 class="order__heading">комментарий к заказу</h3>
                                <textarea class="order-textarea order-textarea--w626" name="order_comment" id="orderComment" placeholder="Доставить заказ совой..." rows="6"></textarea>
                            </div>
                        </div>
                    </div>

                    <img class="order__decor" src="../assets/media/images/order/order_img.png" alt="">

                    <div class="order__summary">
                        <div class="order__summary-rows">
                            <div class="order__summary-row"><span><?= $orderTotalQty ?: 0 ?> товар<?= ($orderTotalQty % 10 == 1 && $orderTotalQty % 100 != 11) ? '' : 'ов' ?></span><span><?= number_format($orderTotalSum, 0, '.', ' ') ?> ₽</span></div>
                            <div class="order__summary-row"><span>скидка</span><span>0 ₽</span></div>
                            <div class="order__summary-row"><span>доставка</span><span><?= $orderTotalQty ? 'бесплатно' : '—' ?></span></div>
                            <div class="order__summary-row"><span>бонусы</span><span>-</span></div>
                        </div>
                        <div class="order__summary-total">
                            <span class="order__summary-total-label">итого</span>
                            <span class="order__summary-total-price"><?= number_format($orderTotalSum, 0, '.', ' ') ?> ₽</span>
                        </div>
                        <a class="order__summary-btn" href="#" id="orderPayBtn" role="button">перейти к оплате</a>
                    </div>
                    <form id="orderProcessForm" action="./order-process.php" method="post" style="display:none;">
                        <input type="hidden" name="fullname" id="opFullname">
                        <input type="hidden" name="phone" id="opPhone">
                        <input type="hidden" name="comment" id="opComment">
                    </form>
                </div>
            </div>
        </section>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </main>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=YOUR_YANDEX_MAPS_API_KEY"></script>
    <script src="../assets/js/script.js"></script>
    <script>
    (function(){
        var btn = document.getElementById('orderPayBtn');
        if (!btn) return;

        function isDigits(val){ return /^\d+$/.test(val); }

        btn.addEventListener('click', function(e){
            e.preventDefault();
            var city  = document.getElementById('orderCity');
            var street= document.getElementById('orderStreet');
            var house = document.getElementById('orderHouse');
            var flat  = document.getElementById('orderFlat');
            var ent   = document.getElementById('orderEntrance');
            var floor = document.getElementById('orderFloor');
            var fn    = document.getElementById('orderFullname');
            var ph    = document.getElementById('orderPhone');
            var com   = document.getElementById('orderComment');
            var f     = document.getElementById('orderProcessForm');
            if (!f) return;

            var errors = [];

            function trim(v){ return (v || '').trim(); }

            if (!city || trim(city.value).length < 3) errors.push('город (минимум 3 символа)');
            if (!street || trim(street.value).length < 3) errors.push('улица (минимум 3 символа)');

            if (!fn || trim(fn.value).length < 3) errors.push('ФИО (минимум 3 символа)');

            var phoneVal = ph ? trim(ph.value) : '';
            if (!phoneVal || !isDigits(phoneVal)) errors.push('телефон (только цифры)');

            function checkNumField(el, label){
                if (!el) return;
                var v = trim(el.value);
                if (v !== '' && !isDigits(v)) {
                    errors.push(label + ' (только цифры)');
                }
            }

            checkNumField(house, 'дом');
            checkNumField(flat, 'квартира');
            checkNumField(ent, 'подъезд');
            checkNumField(floor, 'этаж');

            if (errors.length) {
                alert('Проверьте поля:\n- ' + errors.join('\n- '));
                return;
            }

            document.getElementById('opFullname').value = fn ? fn.value : '';
            document.getElementById('opPhone').value    = ph ? ph.value : '';
            document.getElementById('opComment').value  = com ? com.value : '';
            f.submit();
        });
    })();
    </script>
</body>
</html>
