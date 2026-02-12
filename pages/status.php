<?php
require_once __DIR__ . '/../includes/connect.php';
?><!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - Статус Переплёта</title>
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
                    <li class="breadcrumbs__current">Статус Переплёта</li>
                </ol>
            </div>
        </nav>

        <section class="status-hero">
            <div class="container">
                <div class="status-hero__inner">
                    <div class="status-hero__header">
                        <img class="status-hero__icon" src="../assets/media/icons/logo.svg" alt="">
                        <h1 class="status-hero__title">Статус Переплёта</h1>
                    </div>
                    <div class="status-hero__label">что это?</div>
                    <p class="status-hero__text">Это программа лояльности нашего книжного магазина «Переплёт», которая
                        предоставляет постоянным клиентам специальные привилегии и бонусы. Программа включает 3 уровня,
                        каждый из которых открывает новые возможности для читателей.</p>
                </div>
            </div>
        </section>

        <section class="status-info">
            <div class="container status-info__inner">
                <div class="status-card status-card--how">
                    <h3 class="status-card__title">как это работает?</h3>
                    <ol class="status-card__list">
                        <li class="status-card__item">Зарегистрируйтесь на сайте и автоматически получите статус
                            «Читатель»</li>
                        <li class="status-card__item">Совершайте покупки, чтобы накапливать заказы и повышать статус
                        </li>
                        <li class="status-card__item">Используйте бонусные рубли для оплаты до 50% стоимости заказа</li>
                    </ol>
                    <div class="status-card__bookmark">
                        <img src="../assets/media/icons/logo.svg" alt="" class="status-card__bookmark-icon">
                    </div>
                </div>

                <div class="status-card status-card--where">
                    <h3 class="status-card__title">где отслеживать?</h3>
                    <ul class="status-card__list status-card__list--icon">
                        <li class="status-card__item"><img src="../assets/media/icons/hand_point_right.svg"
                                alt=""><span>В личном кабинете на сайте «Переплёта» вы можете:</span></li>
                        <li class="status-card__item"><img src="../assets/media/icons/hand_point_right.svg"
                                alt=""><span>Видеть текущий статус и прогресс до следующего уровня</span></li>
                        <li class="status-card__item"><img src="../assets/media/icons/hand_point_right.svg"
                                alt=""><span>Проверять баланс бонусных рублей</span></li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="status-levels">
            <div class="container">
                <div class="status-level status-level--legend">
                    <div class="status-level__header">
                        <div class="status-level__title-wrap">
                            <img src="../assets/media/icons/crown_icon.svg" alt="" class="status-level__icon">
                            <h2 class="status-level__title">Легенда Переплёта</h2>
                        </div>
                        <div class="status-level__badge">элитный уровень</div>
                    </div>

                    <div class="status-level__group">
                        <div class="status-level__subtitle">как получить?</div>
                        <p class="status-level__text">25+ завершенных заказов<br>или общая сумма заказов от 50 000 ₽</p>
                    </div>

                    <div class="status-level__group">
                        <div class="status-level__subtitle">привилегии:</div>
                        <ul class="status-level__list">
                            <li>Бесплатная доставка</li>
                            <li>Кэшбек 10% бонусными рублями</li>
                            <li>Персональные рекомендации</li>
                            <li>Доступ к закрытым распродажам</li>
                        </ul>
                    </div>

                    <img src="../assets/media/images/status/status_letters_pe.png" alt=""
                        class="status-level__decor status-level__decor--legend">
                </div>

                <div class="status-level status-level--booklover">
                    <div class="status-level__header">
                        <div class="status-level__title-wrap">
                            <img src="../assets/media/icons/heart_icon.svg" alt="" class="status-level__icon">
                            <h2 class="status-level__title">книголюб</h2>
                        </div>
                        <div class="status-level__badge">активный уровень</div>
                    </div>

                    <div class="status-level__group">
                        <div class="status-level__subtitle">как получить?</div>
                        <p class="status-level__text">10+ завершенных заказов<br>или общая сумма заказов от 20 000 ₽</p>
                    </div>

                    <div class="status-level__group">
                        <div class="status-level__subtitle">привилегии:</div>
                        <ul class="status-level__list">
                            <li>Кэшбек 5% бонусными рублями</li>
                            <li>Бесплатная доставка при заказе от 1 500 ₽</li>
                            <li>Персональные рекомендации</li>
                            <li>Доступ к закрытым распродажам</li>
                        </ul>
                    </div>

                    <img src="../assets/media/images/status/status_letters_rep.png" alt=""
                        class="status-level__decor status-level__decor--booklover">
                </div>

                <div class="status-level status-level--reader">
                    <div class="status-level__header">
                        <div class="status-level__title-wrap">
                            <img src="../assets/media/icons/book_icon.svg" alt="" class="status-level__icon">
                            <h2 class="status-level__title">читатель</h2>
                        </div>
                        <div class="status-level__badge">стартовый уровень</div>
                    </div>

                    <div class="status-level__group">
                        <div class="status-level__subtitle">как получить?</div>
                        <p class="status-level__text">Регистрация на сайте и первый заказ</p>
                    </div>

                    <div class="status-level__group">
                        <div class="status-level__subtitle">привилегии:</div>
                        <ul class="status-level__list">
                            <li>Кэшбек 2% бонусными рублями</li>
                            <li>Бесплатная доставка при заказе от 2 500 ₽</li>
                        </ul>
                    </div>

                    <img src="../assets/media/images/status/status_letters_let.png" alt="" class="status-level__decor">
                </div>
            </div>
        </section>

        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </main>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=YOUR_YANDEX_MAPS_API_KEY"></script>
    <script src="../assets/js/script.js"></script>
</body>

</html>