<?php
require_once __DIR__ . '/../includes/connect.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="shortcut icon" href="../assets/media/icons/logo.svg" type="image/x-icon">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <div class="notify-wrap">
            <div class="notify notify--success">
                <div class="notify__title">заказ оформлен</div>
                <div class="notify__text">мы приняли ваш заказ и скоро свяжемся с вами для подтверждения</div>
            </div>
        </div>
        <section class="order-thank">
            <div class="container order-thank__inner">
                <div class="order-thank__box">
                    <img class="order-thank__img" src="../assets/media/images/order/order_thank_img.png" alt="">
                    <h1 class="order-thank__title">спасибо за заказ!</h1>
                    <p class="order-thank__text">продолжить покупки на <a class="order-thank__link" href="./index.php">главной странице</a></p>
                    <a class="order-thank__btn" href="./index.php">
                        <img src="../assets/media/icons/hand_point_right.svg" alt="">
                        <span>на главную</span>
                    </a>
                </div>
            </div>
        </section>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </main>
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
