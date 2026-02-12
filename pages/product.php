<?php
require_once __DIR__ . '/../includes/connect.php';

// Load book by id вместе с характеристиками из books_specs (только из этой таблицы)
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$book = null;
if ($id > 0) {
    try {
        $stmt = $pdo->prepare('SELECT 
                b.id,
                b.title,
                b.author,
                b.price,
                b.image_url,
                b.description,
                s.series,
                s.publish_year,
                s.publisher,
                s.pages,
                s.cover_type,
                s.size
            FROM books b
            LEFT JOIN books_specs s ON s.book_id = b.id
            WHERE b.id = ?');
        $stmt->execute([$id]);
        $book = $stmt->fetch();
    } catch (Throwable $e) {
        $book = null;
    }
}

$title = $book && !empty($book['title']) ? (string) $book['title'] : 'Товар';
$author = $book && !empty($book['author']) ? (string) $book['author'] : '';
$priceVal = $book && isset($book['price']) && $book['price'] !== '' ? number_format((float) $book['price'], 0, '.', ' ') : '';
$img = ($book && !empty($book['image_url'])) ? (string) $book['image_url'] : '../assets/media/images/card_img.png';
$desc = $book && !empty($book['description']) ? (string) $book['description'] : 'Описание появится позже.';

// Характеристики берём из присоединённой books_specs; если null/пусто, показываем «—»
$series = ($book && !empty($book['series'])) ? (string) $book['series'] : '—';
$publishYear = ($book && !empty($book['publish_year'])) ? (string) $book['publish_year'] : '—';
$publisher = ($book && !empty($book['publisher'])) ? (string) $book['publisher'] : '—';
$pages = ($book && !empty($book['pages'])) ? (string) $book['pages'] : '—';
$coverType = ($book && !empty($book['cover_type'])) ? (string) $book['cover_type'] : '—';
$size = ($book && !empty($book['size'])) ? (string) $book['size'] : '—';

// genres for product (via junction table with fallback)
$genreNames = [];
if ($book) {
    try {
        // try junction
        $stmt = $pdo->prepare('SELECT g.name FROM books_genres bg JOIN genres g ON g.id = bg.genre_id WHERE bg.book_id = ? ORDER BY g.name');
        $stmt->execute([$book['id']]);
        $genreNames = array_map(function ($r) {
            return (string) $r['name']; }, $stmt->fetchAll());
    } catch (Throwable $e) {
        $genreNames = [];
    }
    if (!$genreNames) {
        // fallback to single genre_id on books
        try {
            $stmt = $pdo->prepare('SELECT g.name FROM genres g JOIN books b ON b.genre_id = g.id WHERE b.id = ?');
            $stmt->execute([$book['id']]);
            $row = $stmt->fetch();
            if ($row && !empty($row['name'])) {
                $genreNames = [(string) $row['name']];
            }
        } catch (Throwable $e) {
        }
    }
}
$genreList = $genreNames ? implode(', ', $genreNames) : '—';

// Demo reviews (9 unique)
$reviews = [
    ['name' => 'Анна Петрова', 'date' => '05.06.2025', 'text' => 'Захватывающий сюжет и хорошо прописанные персонажи. Читала взахлёб!'],
    ['name' => 'Иван Смирнов', 'date' => '08.06.2025', 'text' => 'Отличная книга для вечернего чтения. Лёгкая, но при этом глубокая.'],
    ['name' => 'Мария Орлова', 'date' => '11.06.2025', 'text' => 'Давно не встречала такой атмосферной истории. Рекомендую всем друзьям.'],
    ['name' => 'Дмитрий Соколов', 'date' => '14.06.2025', 'text' => 'Местами тягуче, но финал полностью окупает ожидание. Стоит своих денег.'],
    ['name' => 'Елена Новикова', 'date' => '17.06.2025', 'text' => 'Сильные эмоции, запомнилась надолго. Автор умеет держать интригу.'],
    ['name' => 'Павел Иванов', 'date' => '20.06.2025', 'text' => 'Читал в дороге — время пролетело незаметно. Отличный язык и стиль.'],
    ['name' => 'Ольга Кузнецова', 'date' => '23.06.2025', 'text' => 'Понравилась динамика и развитие героя. Есть над чем подумать.'],
    ['name' => 'Сергей Морозов', 'date' => '26.06.2025', 'text' => 'Перечитаю ещё раз. Особенно крутые главы в середине книги.'],
    ['name' => 'Наталья Алексеева', 'date' => '29.06.2025', 'text' => 'Книга как уютный плед: тёплая и вовлекающая. Прекрасный подарок себе.'],
];
?><!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - товар</title>
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
                    <li><a href="./catalog.php" class="breadcrumbs__link">каталог</a></li>
                    <li class="breadcrumbs__sep">/</li>
                    <li class="breadcrumbs__current"><?= htmlspecialchars($title) ?></li>
                </ol>
            </div>
        </nav>

        <section class="product-hero">
            <div class="container">
                <div class="product-hero__inner">
                    <div class="product-hero__left">
                        <div class="product-hero__bookmark">
                            <img src="../assets/media/icons/bookmark_icon.svg" alt="">
                        </div>
                        <img class="product-hero__image" src="<?= htmlspecialchars($img) ?>"
                            alt="<?= htmlspecialchars($title) ?>">
                    </div>
                    <div class="product-hero__right">
                        <h1 class="product-hero__title"><?= htmlspecialchars($title) ?></h1>

                        <div class="product-hero__rating">
                            <div class="product-hero__stars">
                                <img src="../assets/media/icons/star_fill_icon.svg" alt="">
                                <img src="../assets/media/icons/star_fill_icon.svg" alt="">
                                <img src="../assets/media/icons/star_fill_icon.svg" alt="">
                                <img src="../assets/media/icons/star_fill_icon.svg" alt="">
                                <img src="../assets/media/icons/star_empty_icon.svg" alt="">
                            </div>
                            <div class="product-hero__rating-value">4,9</div>
                            <div class="product-hero__rating-count">45 000 оценок</div>
                            <div class="product-hero__reviews">15 897 отзывов</div>
                        </div>

                        <div class="product-hero__buy">
                            <?php if ($priceVal !== '') { ?>
                                <div class="catalog__card-price" style="margin:0 0 12px 0; font-size:24px;"><?= $priceVal ?>
                                    ₽</div>
                            <?php } ?>
                            <form action="./cart.php" method="post" style="display:inline-block;">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="book_id" value="<?= (int) $id ?>">
                                <button class="product-hero__btn" type="submit">в корзину</button>
                            </form>
                            <div class="product-hero__stock">
                                <img src="../assets/media/icons/check_icon.svg" alt="">
                                <span>есть в наличии</span>
                            </div>
                        </div>

                        <div class="product-hero__delivery">
                            <div class="product-hero__row">
                                <div class="product-hero__row-left">
                                    <img src="../assets/media/icons/location_icon.svg" alt="">
                                    <div class="product-hero__row-texts">
                                        <div class="product-hero__row-title">В пункты выдачи, бесплатно</div>
                                        <div class="product-hero__row-sub">с 16 октября</div>
                                    </div>
                                </div>
                                <div class="product-hero__row-right product-hero__row-right--muted">пункты выдачи</div>
                            </div>
                            <div class="product-hero__row">
                                <div class="product-hero__row-left">
                                    <img src="../assets/media/icons/box_icon.svg" alt="">
                                    <div class="product-hero__row-texts">
                                        <div class="product-hero__row-title">Доставка курьером, 150 ₽</div>
                                        <div class="product-hero__row-sub">с 18 октября</div>
                                    </div>
                                </div>
                                <div class="product-hero__row-right">заказ от 1 899 ₽</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="product-about">
            <div class="container">
                <div class="product-about__inner">
                    <img class="product-about__bg" src="../assets/media/images/card/card_layout_img.png" alt="о товаре">

                    <div class="product-about__left">
                        <h2 class="product-about__title">о товаре</h2>
                        <div class="product-about__genre-row">
                            <div class="product-about__genre-label">жанр</div>
                            <div class="product-about__genre-value"><?= htmlspecialchars($genreList) ?></div>
                        </div>
                        <p class="product-about__desc"><?= htmlspecialchars($desc) ?></p>
                    </div>

                    <div class="product-about__right">
                        <h2 class="product-about__title product-about__title--right">характеристики</h2>
                        <dl class="product-about__props">
                            <div class="product-about__prop">
                                <dt>автор</dt>
                                <dd><?= htmlspecialchars($author ?: '—') ?></dd>
                            </div>
                            <div class="product-about__prop">
                                <dt>серия</dt>
                                <dd><?= htmlspecialchars($series) ?></dd>
                            </div>
                            <div class="product-about__prop">
                                <dt>год издания</dt>
                                <dd><?= htmlspecialchars($publishYear) ?></dd>
                            </div>
                            <div class="product-about__prop">
                                <dt>издательство</dt>
                                <dd><?= htmlspecialchars($publisher) ?></dd>
                            </div>
                            <div class="product-about__prop">
                                <dt>количество страниц</dt>
                                <dd><?= htmlspecialchars($pages) ?></dd>
                            </div>
                            <div class="product-about__prop">
                                <dt>тип издания</dt>
                                <dd><?= htmlspecialchars($coverType) ?></dd>
                            </div>
                            <div class="product-about__prop">
                                <dt>размер</dt>
                                <dd><?= htmlspecialchars($size) ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </section>

        <section class="reviews">
            <div class="container reviews__inner">
                <div class="reviews__header">
                    <h2 class="reviews__title">отзывы (<?= count($reviews) ?>)</h2>
                    <div class="reviews__actions">
                        <div class="catalog__filter reviews__sort">
                            <button type="button" class="catalog__dropdown">
                                <span>сначала новые</span>
                                <img src="../assets/media/icons/arrow_down_icon.svg" alt="">
                            </button>
                            <div class="catalog__menu">
                                <button class="catalog__menu-item" type="button">сначала новые</button>
                                <button class="catalog__menu-item" type="button">сначала старые</button>
                                <button class="catalog__menu-item" type="button">по рейтингу</button>
                            </div>
                        </div>
                        <button type="button" class="reviews__write-btn">
                            <img src="../assets/media/icons/pencil_icon.svg" alt="">
                            <span>написать отзыв</span>
                        </button>
                    </div>
                </div>

                <div class="reviews__viewport" id="reviewsViewport">
                    <div class="reviews__track" id="reviewsTrack">
                        <?php foreach ($reviews as $r) { ?>
                            <article class="review-card">
                                <h3 class="review-card__name"><?= htmlspecialchars($r['name']) ?></h3>
                                <div class="review-card__date"><?= htmlspecialchars($r['date']) ?></div>
                                <div class="review-card__stars">
                                    <img src="../assets/media/icons/star_fill_icon.svg" alt="">
                                    <img src="../assets/media/icons/star_fill_icon.svg" alt="">
                                    <img src="../assets/media/icons/star_fill_icon.svg" alt="">
                                    <img src="../assets/media/icons/star_fill_icon.svg" alt="">
                                    <img src="../assets/media/icons/star_fill_icon.svg" alt="">
                                </div>
                                <p class="review-card__text"><?= htmlspecialchars($r['text']) ?></p>
                            </article>
                        <?php } ?>
                    </div>
                </div>

                <div class="reviews__controls">
                    <button id="reviewsPrev" class="selections__btn" type="button">
                        <img src="../assets/media/icons/hand_point_icon.svg" alt="prev">
                    </button>
                    <button id="reviewsNext" class="selections__btn" type="button">
                        <img src="../assets/media/icons/hand_point_right.svg" alt="next">
                    </button>
                </div>
            </div>
        </section>

        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </main>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=YOUR_YANDEX_MAPS_API_KEY"></script>
    <script src="../assets/js/script.js"></script>
</body>

</html>