<?php
require_once __DIR__ . '/../includes/connect.php';
?><!DOCTYPE html>
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
        <section class="banner">
            <div class="container banner__inner">
                <div class="banner__content">
                    <h1 class="banner__title">Ваша следующая любимая книга ждет в каталоге</h1>
                    <img class="banner__logo" src="../assets/media/icons/pereplet_icon.svg" alt="переплёта">
                    <a href="./catalog.php" class="banner__btn">
                        <img src="../assets/media/icons/eyes_icon.svg" alt="">
                        <span>перейти в каталог</span>
                    </a>
                </div>
            </div>
        </section>

        <section class="cards">
            <div class="container cards__inner">
                <div class="card card--greeting">
                    <div class="card__content">
                        <h2 class="card__title">Приветствие<br>от переплёта</h2>
                    </div>
                    <div class="card__icon-strip">
                        <img src="../assets/media/icons/logo.svg" alt="" class="card__icon">
                    </div>
                </div>
                <div class="card card--register">
                    <div class="card__content">
                        <p class="card__text">Зарегистрируйтесь и получите <span class="card__text-accent">скидку 15%</span> на первый заказ</p>
                        <a href="./register.php" class="card__btn">
                            <img src="../assets/media/icons/hand_point_right.svg" alt="">
                            <span>перейти</span>
                        </a>
                    </div>
                    <img src="../assets/media/images/main/status_layout_img.png" alt="" class="card__decor">
                </div>
            </div>
        </section>

        <div class="cards-adaptive">
            <img src="../assets/media/images/adaptive/cards_adaptive_img.png" alt="cards adaptive">
        </div>

        <section class="status">
            <div class="container status__inner">
                <div class="status__left">
                    <img src="../assets/media/icons/logo.svg" alt="" class="status__icon">
                    <div class="status__left-content">
                        <h2 class="status__title">Статус Переплёта</h2>
                        <p class="status__subtitle">Ваш путь от читателя до легенды</p>
                    </div>
                </div>
                <div class="status__divider"></div>
                <div class="status__right">
                    <p class="status__description">Накопительная система с эксклюзивными привилегиями</p>
                    <a href="./status.php" class="status__btn">
                        <img src="../assets/media/icons/eyes_icon.svg" alt="">
                        <span>подробнее</span>
                    </a>
                    <img src="../assets/media/images/main/letters_pereplet_img.png" alt="" class="status__letters">
                </div>
            </div>
        </section>

        <!-- catalog preview and selections and faq copied from original index.html -->
        <?php /* The rest of the content remains the same markup, with internal links adjusted to .php */ ?>
        <div class="status-adaptive">
            <img src="../assets/media/images/adaptive/status_adaptive_img.png" alt="status adaptive">
        </div>

        <section class="catalog">
            <div class="container catalog__inner">
                <div class="catalog__header">
                    <div class="catalog__header-top">
                        <h2 class="catalog__title">каталог</h2>
                        <button class="catalog__view-all-btn">смотреть все</button>
                    </div>
                    <form class="catalog__search" action="./catalog.php" method="get">
                        <input type="text" name="q" placeholder="найти книгу по душе...">
                        <button type="submit" class="catalog__search-btn">
                            <img src="../assets/media/icons/lupa_icon.svg" alt="">
                            <span>найти</span>
                        </button>
                    </form>
                </div>
                <div class="catalog__grid">
                    <?php
                    $homeBooks = [];
                    try {
                        $stmt = $pdo->query('SELECT id, title, author, price, image_url FROM books ORDER BY created_at DESC LIMIT 8');
                        $homeBooks = $stmt->fetchAll();
                    } catch (Throwable $e) {
                        try {
                            $stmt = $pdo->query('SELECT id, title, author, price, image_url FROM books ORDER BY id DESC LIMIT 8');
                            $homeBooks = $stmt->fetchAll();
                        } catch (Throwable $e2) { $homeBooks = []; }
                    }
                    if ($homeBooks) {
                        foreach ($homeBooks as $b) {
                            $img = !empty($b['image_url']) ? $b['image_url'] : '../assets/media/images/card_img.png';
                            $price = is_numeric($b['price']) ? number_format((float)$b['price'], 0, '.', ' ') : htmlspecialchars((string)$b['price']);
                    ?>
                    <div class="catalog__card">
                        <div class="catalog__card-image-wrapper">
                            <a href="./product.php?id=<?= (int)$b['id'] ?>">
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($b['title']) ?>" class="catalog__card-image">
                            </a>
                            <button class="catalog__card-bookmark">
                                <img src="../assets/media/icons/bookmark_icon.svg" alt="">
                            </button>
                        </div>
                        <div class="catalog__card-content">
                            <div class="catalog__card-price"><?= $price ?> ₽</div>
                            <h3 class="catalog__card-title"><a href="./product.php?id=<?= (int)$b['id'] ?>" style="color:inherit;"><?= htmlspecialchars($b['title']) ?></a></h3>
                            <p class="catalog__card-author"><?= htmlspecialchars($b['author']) ?></p>
                            <form action="./cart.php" method="post">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="book_id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="catalog__card-btn">в корзину</button>
                            </form>
                        </div>
                    </div>
                    <?php }
                    } else { ?>
                        <p>Нет товаров для отображения</p>
                    <?php } ?>
                </div>
                <div class="catalog__pagination">
                    <button class="catalog__pagination-btn"><img src="../assets/media/icons/arrow_left_icon.svg" alt=""></button>
                    <button class="catalog__pagination-btn catalog__pagination-btn--active">1</button>
                    <button class="catalog__pagination-btn">2</button>
                    <button class="catalog__pagination-btn">3</button>
                    <button class="catalog__pagination-btn catalog__pagination-btn--dots">...</button>
                    <button class="catalog__pagination-btn">50</button>
                    <button class="catalog__pagination-btn"><img src="../assets/media/icons/arrow_right_icon.svg" alt=""></button>
                </div>
            </div>
        </section>

        <div class="catalog-adaptive">
            <img src="../assets/media/images/adaptive/catalog_adaptive_img.png" alt="catalog adaptive">
        </div>

        <section class="selections">
            <div class="container selections__inner">
                <h2 class="selections__title">подборки</h2>
                <div class="selections__viewport">
                    <div class="selections__track" id="selectionsTrack">
                        <a href="#" class="selection-card selection-card--light selection-card--img-1 selection-card--text-211" style="background-image: url('../assets/media/images/main/layout_card_img.png');">
                            <span class="selection-card__text">Классика на все времена</span>
                        </a>
                        <a href="#" class="selection-card selection-card--accent selection-card--img-2 selection-card--text-276" style="background-image: url('../assets/media/images/main/layout_card2_img.png');">
                            <span class="selection-card__text">Осеннее настроение: книги для уютных вечеров</span>
                        </a>
                        <a href="#" class="selection-card selection-card--accent selection-card--text-237" style="background-image: url('../assets/media/images/main/layout_card3_img.png');">
                            <span class="selection-card__text">Хэллоуин: мистика и ужасы</span>
                        </a>
                        <a href="#" class="selection-card selection-card--light selection-card--img-1 selection-card--text-211">
                            <span class="selection-card__text">Утреннее вдохновение: книги к чашке кофе</span>
                        </a>
                        <a href="#" class="selection-card selection-card--accent selection-card--img-2 selection-card--text-276">
                            <span class="selection-card__text">Путешествия по миру: истории из разных стран</span>
                        </a>
                        <a href="#" class="selection-card selection-card--accent selection-card--text-237">
                            <span class="selection-card__text">Выгорание off: книги для восстановления ресурса</span>
                        </a>
                    </div>
                </div>
                <div class="selections__controls">
                    <button class="selections__btn selections__btn--prev" id="selectionsPrev" aria-label="prev">
                        <img src="../assets/media/icons/hand_point_icon.svg" alt="">
                    </button>
                    <button class="selections__btn selections__btn--next" id="selectionsNext" aria-label="next">
                        <img src="../assets/media/icons/hand_point_right.svg" alt="">
                    </button>
                </div>
            </div>
        </section>

        <section class="faq">
            <div class="container faq__inner">
                <h2 class="faq__title">часто задаваемые вопросы</h2>
                <div class="faq__list">
                    <div class="faq__item">
                        <button class="faq__header" type="button">
                            <span class="faq__question">Могу ли я отменить или изменить заказ?</span>
                            <img class="faq__arrow" src="../assets/media/icons/arrow_down_icon.svg" alt="">
                        </button>
                        <div class="faq__content">
                            <p>Да, вы можете отменить или изменить заказ до момента его передачи в службу доставки. Для этого свяжитесь с нашей службой поддержки.</p>
                        </div>
                    </div>
                    <div class="faq__item">
                        <button class="faq__header" type="button">
                            <span class="faq__question">Что делать, если я не получил заказ?</span>
                            <img class="faq__arrow" src="../assets/media/icons/arrow_down_icon.svg" alt="">
                        </button>
                        <div class="faq__content">
                            <p>Если срок доставки истёк, проверьте статус заказа в личном кабинете и корректность адреса. Свяжитесь с нашей службой поддержки — мы поможем найти отправление или оформим возврат средств.</p>
                        </div>
                    </div>
                    <div class="faq__item">
                        <button class="faq__header" type="button">
                            <span class="faq__question">Что делать, если я не получил заказ?</span>
                            <img class="faq__arrow" src="../assets/media/icons/arrow_down_icon.svg" alt="">
                        </button>
                        <div class="faq__content">
                            <p>Если вы не получили сообщений от курьера, проверьте данные для связи в профиле и раздел уведомлений. Напишите нам — мы уточним статус у службы доставки и ускорим доставку.</p>
                        </div>
                    </div>
                    <div class="faq__item">
                        <button class="faq__header" type="button">
                            <span class="faq__question">Что делать, если я получил книгу с дефектом?</span>
                            <img class="faq__arrow" src="../assets/media/icons/arrow_down_icon.svg" alt="">
                        </button>
                        <div class="faq__content">
                            <p>Сделайте фото дефекта и обратитесь в поддержку в течение 14 дней с момента получения. Мы предложим обмен на новый экземпляр или возврат стоимости. Пожалуйста, сохраните упаковку и чек.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </main>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=YOUR_YANDEX_MAPS_API_KEY"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
