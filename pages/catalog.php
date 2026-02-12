<?php
require_once __DIR__ . '/../includes/connect.php';
@session_start();

// Simple JSON responder
function respond_json($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
// Determine current user role with DB fallback; admin=2, user=1
$CURRENT_ROLE = 0;
if (isset($_SESSION['user']['role'])) {
    $CURRENT_ROLE = (int) $_SESSION['user']['role'];
} elseif (isset($_SESSION['role'])) {
    $CURRENT_ROLE = (int) $_SESSION['role'];
} elseif (!empty($_SESSION['user_id'])) {
    try {
        $st = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $st->execute([(int) $_SESSION['user_id']]);
        $r = $st->fetch();
        if ($r && isset($r['role'])) {
            $CURRENT_ROLE = (int) $r['role'];
        }
    } catch (Throwable $e) { /* ignore */
    }
}

// allow preview via query for testing UI
if (isset($_GET['admin_preview']) && (string) $_GET['admin_preview'] === '1') {
    $CURRENT_ROLE = 2;
}

// Admin actions: delete/update via POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($CURRENT_ROLE !== 2) {
        respond_json(['ok' => false, 'error' => 'forbidden'], 403);
    }

    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
    try {
        if ($action === 'delete') {
            $bookId = isset($_POST['book_id']) ? (int) $_POST['book_id'] : 0;
            if ($bookId <= 0) {
                respond_json(['ok' => false, 'error' => 'invalid id'], 400);
            }
            // delete from junctions if exist
            try {
                $pdo->prepare('DELETE FROM books_genres WHERE book_id=?')->execute([$bookId]);
            } catch (Throwable $e) {
            }
            try {
                $pdo->prepare('DELETE FROM books_categories WHERE book_id=?')->execute([$bookId]);
            } catch (Throwable $e) {
            }
            // delete specs if separate table exists
            try {
                $pdo->prepare('DELETE FROM books_specs WHERE book_id=?')->execute([$bookId]);
            } catch (Throwable $e) {
            }
            // delete book
            $pdo->prepare('DELETE FROM books WHERE id=?')->execute([$bookId]);
            respond_json(['ok' => true]);
        } elseif ($action === 'delete_category') {
            $catId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
            if ($catId <= 0) {
                respond_json(['ok' => false, 'error' => 'invalid id'], 400);
            }
            try {
                $pdo->prepare('DELETE FROM books_categories WHERE category_id=?')->execute([$catId]);
            } catch (Throwable $e) {
            }
            $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$catId]);
            respond_json(['ok' => true]);
        } elseif ($action === 'update') {
            $bookId = isset($_POST['book_id']) ? (int) $_POST['book_id'] : 0;
            if ($bookId <= 0) {
                respond_json(['ok' => false, 'error' => 'invalid id'], 400);
            }
            $title = trim((string) ($_POST['title'] ?? ''));
            $author = trim((string) ($_POST['author'] ?? ''));
            $price = (string) ($_POST['price'] ?? '');
            $priceVal = is_numeric($price) ? (float) $price : null;
            $image_url = trim((string) ($_POST['image_url'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));

            // try extended update if columns exist; fallback to basic
            $series = trim((string) ($_POST['series'] ?? ''));
            $year = trim((string) ($_POST['publish_year'] ?? ''));
            $publisher = trim((string) ($_POST['publisher'] ?? ''));
            $pages = trim((string) ($_POST['pages'] ?? ''));
            $cover_type = trim((string) ($_POST['cover_type'] ?? ''));
            $size = trim((string) ($_POST['size'] ?? ''));

            $didExtended = false;
            try {
                // попытка сохранить характеристики прямо в books, если есть такие поля
                $stmt = $pdo->prepare('UPDATE books SET title=?, author=?, price=?, image_url=?, description=?, series=?, publish_year=?, publisher=?, pages=?, cover_type=?, size=? WHERE id=?');
                $stmt->execute([$title, $author, $priceVal, $image_url, $description, $series, $year, $publisher, $pages, $cover_type, $size, $bookId]);
                $didExtended = true;
            } catch (Throwable $e) {
                // если колонок нет — обновляем только базовые поля
                try {
                    $stmt = $pdo->prepare('UPDATE books SET title=?, author=?, price=?, image_url=?, description=? WHERE id=?');
                    $stmt->execute([$title, $author, $priceVal, $image_url, $description, $bookId]);
                } catch (Throwable $e2) {
                }
            }

            // независимо от наличия колонок в books, постараемся сохранить характеристики в отдельной таблице books_specs
            try {
                $pdo->exec('CREATE TABLE IF NOT EXISTS books_specs (
                        book_id INT NOT NULL PRIMARY KEY,
                        series VARCHAR(255) NULL,
                        publish_year VARCHAR(50) NULL,
                        publisher VARCHAR(255) NULL,
                        pages VARCHAR(50) NULL,
                        cover_type VARCHAR(255) NULL,
                        size VARCHAR(100) NULL,
                        CONSTRAINT fk_specs_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

                // простая upsert-логика
                $specStmt = $pdo->prepare('INSERT INTO books_specs (book_id, series, publish_year, publisher, pages, cover_type, size)
                                           VALUES (?, ?, ?, ?, ?, ?, ?)
                                           ON DUPLICATE KEY UPDATE
                                               series=VALUES(series),
                                               publish_year=VALUES(publish_year),
                                               publisher=VALUES(publisher),
                                               pages=VALUES(pages),
                                               cover_type=VALUES(cover_type),
                                               size=VALUES(size)');
                $specStmt->execute([$bookId, $series ?: null, $year ?: null, $publisher ?: null, $pages ?: null, $cover_type ?: null, $size ?: null]);
            } catch (Throwable $eSpecs) {
                // тихо игнорируем, если таблица/констреинты не создаются
            }

            // update genres/categories if provided
            $genre_ids = $_POST['genre_ids'] ?? [];
            if (!is_array($genre_ids)) {
                $genre_ids = [$genre_ids];
            }
            $genre_ids = array_values(array_unique(array_filter(array_map(function ($v) {
                return ctype_digit((string) $v) ? (int) $v : 0;
            }, $genre_ids), function ($v) {
                return $v > 0;
            })));
            $category_ids = $_POST['category_ids'] ?? [];
            if (!is_array($category_ids)) {
                $category_ids = [$category_ids];
            }
            $category_ids = array_values(array_unique(array_filter(array_map(function ($v) {
                return ctype_digit((string) $v) ? (int) $v : 0;
            }, $category_ids), function ($v) {
                return $v > 0;
            })));

            // genres
            try {
                $pdo->prepare('DELETE FROM books_genres WHERE book_id=?')->execute([$bookId]);
                if ($genre_ids) {
                    $ins = $pdo->prepare('INSERT INTO books_genres (book_id, genre_id) VALUES (?, ?)');
                    foreach ($genre_ids as $gid) {
                        $ins->execute([$bookId, (int) $gid]);
                    }
                }
            } catch (Throwable $e) {
            }
            // categories
            try {
                $pdo->prepare('DELETE FROM books_categories WHERE book_id=?')->execute([$bookId]);
                if ($category_ids) {
                    $ins = $pdo->prepare('INSERT INTO books_categories (book_id, category_id) VALUES (?, ?)');
                    foreach ($category_ids as $cid) {
                        $ins->execute([$bookId, (int) $cid]);
                    }
                }
            } catch (Throwable $e) {
            }

            respond_json(['ok' => true]);
        }
    } catch (Throwable $e) {
        respond_json(['ok' => false, 'error' => 'server'], 500);
    }
    respond_json(['ok' => false, 'error' => 'unknown action'], 400);
}

// load genres/categories for filters with fallback lists
$genres = [];
$categories = [];
try {
    $genres = $pdo->query('SELECT id, name FROM genres ORDER BY name')->fetchAll();
} catch (Throwable $e) {
}
try {
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
} catch (Throwable $e) {
}
if (!$genres) {
    $genreOptions = [
        1 => 'фэнтези',
        2 => 'фантастика',
        3 => 'роман',
        4 => 'детектив',
        5 => 'триллер',
        6 => 'мистика',
        7 => 'приключения',
        8 => 'история',
        9 => 'биографии',
        10 => 'психология',
        11 => 'саморазвитие',
        12 => 'бизнес',
        13 => 'наука',
        14 => 'поэзия',
        15 => 'драма',
        16 => 'нон-фикшн',
    ];
    $genres = array_map(function ($id, $name) {
        return ['id' => $id, 'name' => $name];
    }, array_keys($genreOptions), $genreOptions);
}
if (!$categories) {
    $categoryOptions = [
        1 => 'со скидкой',
        2 => 'новинки',
        3 => 'бестселлеры',
        4 => 'хобби и досуг',
        5 => 'бизнес и карьера',
        6 => 'учебная литература',
        7 => 'детям и родителям',
        8 => 'подросткам и молодёжи',
    ];
    $categories = array_map(function ($id, $name) {
        return ['id' => $id, 'name' => $name];
    }, array_keys($categoryOptions), $categoryOptions);
}

$selectedCats = $_GET['category_ids'] ?? [];
if (!is_array($selectedCats)) {
    $selectedCats = [$selectedCats];
}
$selectedCats = array_values(array_unique(array_filter(array_map(function ($v) {
    return ctype_digit((string) $v) ? (int) $v : 0;
}, $selectedCats), function ($v) {
    return $v > 0;
})));
$selectedGensRaw = $_GET['genre_ids'] ?? ($_GET['genre_id'] ?? []);
if (!is_array($selectedGensRaw)) {
    $selectedGensRaw = [$selectedGensRaw];
}
$selectedGens = array_values(array_unique(array_filter(array_map(function ($v) {
    return ctype_digit((string) $v) ? (int) $v : 0;
}, $selectedGensRaw), function ($v) {
    return $v > 0;
})));

// sorting
$sort = isset($_GET['sort']) ? (string) $_GET['sort'] : '';
$sort = in_array($sort, ['default', 'popularity', 'price'], true) ? $sort : 'default';
$sortLabel = [
    'default' => 'по умолчанию',
    'popularity' => 'по популярности',
    'price' => 'по цене',
][$sort];

// search query
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
?><!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - каталог</title>
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
                    <li class="breadcrumbs__current">каталог</li>
                </ol>
            </div>
        </nav>
        <section class="catalog-search">
            <div class="container">
                <form class="catalog__search catalog__search--page" action="" method="get">
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                        placeholder="найти книгу по душе...">
                    <button type="submit" class="catalog__search-btn">
                        <img src="../assets/media/icons/lupa_icon.svg" alt="">
                        <span>найти</span>
                    </button>
                    <?php
                    // helpers to build sort URLs while preserving current filters
                    function build_sort_url($value)
                    {
                        $qs = $_GET;
                        $qs['sort'] = $value;
                        return '?' . http_build_query($qs);
                    }
                    ?>
                    <div class="catalog__filter catalog__filter--sort">
                        <button type="button" class="catalog__sort-btn catalog__dropdown">
                            <span><?= htmlspecialchars($sortLabel) ?></span>
                            <img src="../assets/media/icons/arrow_down_icon.svg" alt="">
                        </button>
                        <div class="catalog__menu" role="menu">
                            <button class="catalog__menu-item" role="menuitem" type="button"
                                onclick="location.href='<?= build_sort_url('default') ?>'">по умолчанию</button>
                            <button class="catalog__menu-item" role="menuitem" type="button"
                                onclick="location.href='<?= build_sort_url('popularity') ?>'">по популярности</button>
                            <button class="catalog__menu-item" role="menuitem" type="button"
                                onclick="location.href='<?= build_sort_url('price') ?>'">по цене</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="catalog-results">
            <div class="container catalog-results__inner">
                <aside class="catalog-filters">
                    <form action="" method="get">
                        <div class="catalog-filters__group">
                            <div class="catalog-filters__title">категории</div>
                            <ul class="catalog-filters__list">
                                <?php foreach ($categories as $c) {
                                    $cid = (int) $c['id'];
                                    $checked = in_array($cid, $selectedCats, true) ? 'checked' : '';
                                    $cname = (string) $c['name']; ?>
                                    <li>
                                        <label>
                                            <input type="checkbox" name="category_ids[]" value="<?= $cid ?>" <?= $checked ?>>
                                            <span><?= htmlspecialchars($cname) ?></span>
                                        </label>
                                        <?php if ($CURRENT_ROLE === 2) { ?>
                                            <button type="button" class="catalog-filters__category-remove js-delete-category" data-id="<?= $cid ?>" data-name="<?= htmlspecialchars($cname, ENT_QUOTES) ?>">×</button>
                                        <?php } ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>

                        <div class="catalog-filters__group">
                            <div class="catalog-filters__title">жанры</div>
                            <?php
                            $selectedGenreId = $selectedGens[0] ?? 0;
                            $selectedGenreName = 'выберите из списка';
                            foreach ($genres as $g) {
                                if ((int) $g['id'] === $selectedGenreId) {
                                    $selectedGenreName = (string) $g['name'];
                                    break;
                                }
                            }
                            function build_genre_url($gid)
                            {
                                $qs = $_GET;
                                // normalize keys
                                unset($qs['genre_ids']);
                                if ($gid) {
                                    $qs['genre_id'] = (int) $gid;
                                } else {
                                    unset($qs['genre_id']);
                                }
                                return '?' . http_build_query($qs);
                            }
                            ?>
                            <div class="catalog-filters__select catalog__filter">
                                <button type="button" class="catalog-filters__select-btn catalog__dropdown">
                                    <span><?= htmlspecialchars($selectedGenreName) ?></span>
                                    <img src="../assets/media/icons/arrow_down_icon.svg" alt="">
                                </button>
                                <div class="catalog-filters__menu catalog__menu" role="menu">
                                    <button class="catalog__menu-item" role="menuitem" type="button"
                                        onclick="location.href='<?= build_genre_url(0) ?>'">все жанры</button>
                                    <?php foreach ($genres as $g) {
                                        $gid = (int) $g['id']; ?>
                                        <button class="catalog__menu-item" role="menuitem" type="button"
                                            onclick="location.href='<?= build_genre_url($gid) ?>'"><?= htmlspecialchars($g['name']) ?></button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <div class="catalog-filters__group">
                            <div class="catalog-filters__title">возраст</div>
                            <ul class="catalog-filters__list">
                                <li><label><input type="checkbox"> <span>0–3 года</span></label></li>
                                <li><label><input type="checkbox"> <span>4–6 лет</span></label></li>
                                <li><label><input type="checkbox"> <span>7–10 лет</span></label></li>
                                <li><label><input type="checkbox"> <span>11–13 лет</span></label></li>
                                <li><label><input type="checkbox"> <span>14–16 лет</span></label></li>
                                <li><label><input type="checkbox"> <span>16+</span></label></li>
                                <li><label><input type="checkbox"> <span>18+</span></label></li>
                            </ul>
                        </div>

                        <div class="catalog-filters__group">
                            <button type="submit" class="admin-btn" style="width:100%">показать</button>
                        </div>
                    </form>
                </aside>

                <div class="catalog-results__grid">
                    <?php
                    $books = [];
                    try {
                        // Build dynamic query with optional joins
                        $sql = 'SELECT b.id, b.title, b.author, b.price, b.image_url, COALESCE(b.description, "") AS description FROM books b';
                        $conds = [];
                        $params = [];
                        $useJCat = false;
                        $useJGen = false;
                        if ($selectedCats) {
                            try {
                                $pdo->query('SELECT 1 FROM books_categories LIMIT 1');
                                $useJCat = true;
                            } catch (Throwable $e) {
                                $useJCat = false;
                            }
                            if ($useJCat) {
                                $sql .= ' JOIN books_categories bc ON bc.book_id = b.id';
                                $place = implode(',', array_fill(0, count($selectedCats), '?'));
                                $conds[] = 'bc.category_id IN (' . $place . ')';
                                $params = array_merge($params, $selectedCats);
                            } else {
                                $place = implode(',', array_fill(0, count($selectedCats), '?'));
                                $conds[] = 'b.category_id IN (' . $place . ')';
                                $params = array_merge($params, $selectedCats);
                            }
                        }
                        if ($selectedGens) {
                            try {
                                $pdo->query('SELECT 1 FROM books_genres LIMIT 1');
                                $useJGen = true;
                            } catch (Throwable $e) {
                                $useJGen = false;
                            }
                            if ($useJGen) {
                                $sql .= ' JOIN books_genres bg ON bg.book_id = b.id';
                                $place = implode(',', array_fill(0, count($selectedGens), '?'));
                                $conds[] = 'bg.genre_id IN (' . $place . ')';
                                $params = array_merge($params, $selectedGens);
                            } else {
                                $place = implode(',', array_fill(0, count($selectedGens), '?'));
                                $conds[] = 'b.genre_id IN (' . $place . ')';
                                $params = array_merge($params, $selectedGens);
                            }
                        }
                        if ($q !== '') {
                            $conds[] = '(b.title LIKE ? OR b.author LIKE ?)';
                            $like = '%' . $q . '%';
                            $params[] = $like;
                            $params[] = $like;
                        }
                        if ($conds) {
                            $sql .= ' WHERE ' . implode(' AND ', $conds);
                        }
                        // apply sorting
                        if ($sort === 'price') {
                            $sqlOrder = $sql . ' ORDER BY b.price ASC, b.id DESC';
                            try {
                                $stmt = $pdo->prepare($sqlOrder);
                                $stmt->execute($params);
                                $books = $stmt->fetchAll();
                            } catch (Throwable $eOrder) {
                                $stmt = $pdo->prepare($sql . ' ORDER BY b.id DESC');
                                $stmt->execute($params);
                                $books = $stmt->fetchAll();
                            }
                        } elseif ($sort === 'popularity') {
                            // try popularity column if exists, else fallback
                            try {
                                $sqlOrder = $sql . ' ORDER BY b.popularity DESC, b.id DESC';
                                $stmt = $pdo->prepare($sqlOrder);
                                $stmt->execute($params);
                                $books = $stmt->fetchAll();
                            } catch (Throwable $eOrder) {
                                $stmt = $pdo->prepare($sql . ' ORDER BY b.id DESC');
                                $stmt->execute($params);
                                $books = $stmt->fetchAll();
                            }
                        } else {
                            // default: newest
                            try {
                                $sqlOrder = $sql . ' ORDER BY b.created_at DESC, b.id DESC';
                                $stmt = $pdo->prepare($sqlOrder);
                                $stmt->execute($params);
                                $books = $stmt->fetchAll();
                            } catch (Throwable $eOrder) {
                                $stmt = $pdo->prepare($sql . ' ORDER BY b.id DESC');
                                $stmt->execute($params);
                                $books = $stmt->fetchAll();
                            }
                        }
                    } catch (Throwable $e2) {
                        $books = [];
                    }
                    if ($books) {
                        foreach ($books as $b) {
                            $img = !empty($b['image_url']) ? $b['image_url'] : '../assets/media/images/card_img.png';
                            $price = is_numeric($b['price']) ? number_format((float) $b['price'], 0, '.', ' ') : htmlspecialchars((string) $b['price']);
                            // role-based access: 2 = admin, 1 = user (default)
                            $isAdmin = ($CURRENT_ROLE === 2);
                            $bmIcon = $isAdmin ? '../assets/media/icons/trash_icon.svg' : '../assets/media/icons/bookmark_icon.svg';
                            $btnText = $isAdmin ? 'редактировать' : 'в корзину';

                            // Preload junction data for admin to prefill modal
                            $gIds = [];
                            $cIds = [];
                            // Preload specs for admin (series, year, etc.)
                            $series = '';
                            $publishYear = '';
                            $publisher = '';
                            $pages = '';
                            $coverType = '';
                            $size = '';
                            if ($isAdmin) {
                                try {
                                    $gIds = array_map(function ($r) {
                                        return (int) $r['genre_id'];
                                    }, $pdo->query('SELECT genre_id FROM books_genres WHERE book_id=' . (int) $b['id'])->fetchAll());
                                } catch (Throwable $e) {
                                }
                                if (!$gIds) {
                                    try {
                                        $g = $pdo->query('SELECT genre_id FROM books WHERE id=' . (int) $b['id'])->fetch();
                                        if ($g && $g['genre_id'])
                                            $gIds = [(int) $g['genre_id']];
                                    } catch (Throwable $e) {
                                    }
                                }
                                try {
                                    $cIds = array_map(function ($r) {
                                        return (int) $r['category_id'];
                                    }, $pdo->query('SELECT category_id FROM books_categories WHERE book_id=' . (int) $b['id'])->fetchAll());
                                } catch (Throwable $e) {
                                }
                                if (!$cIds) {
                                    try {
                                        $c = $pdo->query('SELECT category_id FROM books WHERE id=' . (int) $b['id'])->fetch();
                                        if ($c && $c['category_id'])
                                            $cIds = [(int) $c['category_id']];
                                    } catch (Throwable $e) {
                                    }
                                }

                                // сначала пробуем взять характеристики из books_specs
                                try {
                                    $stSpecs = $pdo->prepare('SELECT series, publish_year, publisher, pages, cover_type, size FROM books_specs WHERE book_id = ?');
                                    $stSpecs->execute([(int) $b['id']]);
                                    if ($rowS = $stSpecs->fetch()) {
                                        $series = (string) ($rowS['series'] ?? '');
                                        $publishYear = (string) ($rowS['publish_year'] ?? '');
                                        $publisher = (string) ($rowS['publisher'] ?? '');
                                        $pages = (string) ($rowS['pages'] ?? '');
                                        $coverType = (string) ($rowS['cover_type'] ?? '');
                                        $size = (string) ($rowS['size'] ?? '');
                                    }
                                } catch (Throwable $eSpecs) {
                                }

                                // если в specs пусто, аккуратно пробуем взять из books,
                                // чтобы старые данные тоже подставлялись в модалку
                                if ($series === '' && $publishYear === '' && $publisher === '' && $pages === '' && $coverType === '' && $size === '') {
                                    try {
                                        $stBookSpecs = $pdo->prepare('SELECT series, publish_year, publisher, pages, cover_type, size FROM books WHERE id = ?');
                                        $stBookSpecs->execute([(int) $b['id']]);
                                        if ($rowB = $stBookSpecs->fetch()) {
                                            $series = (string) ($rowB['series'] ?? '');
                                            $publishYear = (string) ($rowB['publish_year'] ?? '');
                                            $publisher = (string) ($rowB['publisher'] ?? '');
                                            $pages = (string) ($rowB['pages'] ?? '');
                                            $coverType = (string) ($rowB['cover_type'] ?? '');
                                            $size = (string) ($rowB['size'] ?? '');
                                        }
                                    } catch (Throwable $eBookSpecs) {
                                    }
                                }
                            }
                            ?>
                            <div class="catalog__card">
                                <div class="catalog__card-image-wrapper">
                                    <a href="./product.php?id=<?= (int) $b['id'] ?>">
                                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($b['title']) ?>"
                                            class="catalog__card-image">
                                    </a>
                                    <button class="catalog__card-bookmark<?= $isAdmin ? ' js-delete-book' : '' ?>"
                                        data-id="<?= (int) $b['id'] ?>"><img src="<?= $bmIcon ?>" alt=""></button>
                                </div>
                                <div class="catalog__card-content">
                                    <div class="catalog__card-price"><?= $price ?> ₽</div>
                                    <h3 class="catalog__card-title"><a href="./product.php?id=<?= (int) $b['id'] ?>"
                                            style="color:inherit;"><?= htmlspecialchars($b['title']) ?></a></h3>
                                    <p class="catalog__card-author"><?= htmlspecialchars($b['author']) ?></p>
                                    <?php if ($isAdmin) { ?>
                                        <button class="catalog__card-btn js-edit-book" data-id="<?= (int) $b['id'] ?>"
                                            data-title="<?= htmlspecialchars($b['title'], ENT_QUOTES) ?>"
                                            data-author="<?= htmlspecialchars($b['author'], ENT_QUOTES) ?>"
                                            data-price="<?= htmlspecialchars((string) $b['price'], ENT_QUOTES) ?>"
                                            data-image="<?= htmlspecialchars($img, ENT_QUOTES) ?>"
                                            data-description="<?= htmlspecialchars((string) $b['description'], ENT_QUOTES) ?>"
                                            data-genres="<?= htmlspecialchars(implode(',', $gIds), ENT_QUOTES) ?>"
                                            data-categories="<?= htmlspecialchars(implode(',', $cIds), ENT_QUOTES) ?>"
                                            data-series="<?= htmlspecialchars($series, ENT_QUOTES) ?>"
                                            data-publish_year="<?= htmlspecialchars($publishYear, ENT_QUOTES) ?>"
                                            data-publisher="<?= htmlspecialchars($publisher, ENT_QUOTES) ?>"
                                            data-pages="<?= htmlspecialchars($pages, ENT_QUOTES) ?>"
                                            data-cover_type="<?= htmlspecialchars($coverType, ENT_QUOTES) ?>"
                                            data-size="<?= htmlspecialchars($size, ENT_QUOTES) ?>">редактировать</button>
                                    <?php } else { ?>
                                        <form action="./cart.php" method="post">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="book_id" value="<?= (int) $b['id'] ?>">
                                            <button type="submit" class="catalog__card-btn">в корзину</button>
                                        </form>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php }
                    } else { ?>
                        <p>Нет товаров для отображения</p>
                    <?php } ?>
                </div>
            </div>
        </section>

        <div class="catalog__pagination">
            <button class="catalog__pagination-btn">
                <img src="../assets/media/icons/arrow_left_icon.svg" alt="">
            </button>
            <button class="catalog__pagination-btn catalog__pagination-btn--active">1</button>
            <button class="catalog__pagination-btn">2</button>
            <button class="catalog__pagination-btn">3</button>
            <button class="catalog__pagination-btn catalog__pagination-btn--dots">...</button>
            <button class="catalog__pagination-btn">50</button>
            <button class="catalog__pagination-btn">
                <img src="../assets/media/icons/arrow_right_icon.svg" alt="">
            </button>
        </div>

        <?php include __DIR__ . '/../includes/footer.php'; ?>

        <?php // Admin Modals ?>
        <?php if ($CURRENT_ROLE === 2) { ?>
            <div id="modalOverlay" class="modal-overlay" style="display:none"></div>
            <div id="deleteModal" class="modal" style="display:none">
                <div class="modal__header">
                    <h3 class="modal__title">Удалить товар?</h3>
                </div>
                <div class="modal__body">
                    <p class="modal__text">Действие необратимо. Вы уверены, что хотите удалить этот товар?</p>
                </div>
                <div class="modal__footer">
                    <button type="button" class="modal__btn modal__btn--muted" id="delCancel">отмена</button>
                    <button type="button" class="modal__btn" id="delConfirm">удалить</button>
                </div>
            </div>

            <div id="deleteCategoryModal" class="modal" style="display:none">
                <div class="modal__header">
                    <h3 class="modal__title">Удалить категорию?</h3>
                </div>
                <div class="modal__body">
                    <p class="modal__text" id="delCatText">Действие необратимо. Вы уверены, что хотите удалить эту категорию?</p>
                </div>
                <div class="modal__footer">
                    <button type="button" class="modal__btn modal__btn--muted" id="delCatCancel">отмена</button>
                    <button type="button" class="modal__btn" id="delCatConfirm">удалить</button>
                </div>
            </div>

            <div id="editModal" class="modal modal--edit" style="display:none">
                <div class="modal__header">
                    <h3 class="modal__title">Редактирование товара</h3>
                </div>
                <form id="editForm" class="modal__form">
                    <input type="hidden" name="book_id" id="editId">
                    <div class="modal__scroll">
                        <div class="modal__grid modal__grid--two">
                            <label class="modal__field">
                                <span class="modal__label">название</span>
                                <input type="text" name="title" id="editTitle" required>
                            </label>
                            <label class="modal__field">
                                <span class="modal__label">жанры</span>
                                <select name="genre_ids[]" id="editGenres" class="admin-select modal__select" multiple
                                    size="1">
                                    <?php foreach ($genres as $g) {
                                        $gid = (int) $g['id']; ?>
                                        <option value="<?= $gid ?>"><?= htmlspecialchars($g['name']) ?></option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="modal__field">
                                <span class="modal__label">автор</span>
                                <input type="text" name="author" id="editAuthor">
                            </label>
                            <label class="modal__field">
                                <span class="modal__label">категории</span>
                                <select name="category_ids[]" id="editCategories" class="admin-select modal__select"
                                    multiple size="1">
                                    <?php foreach ($categories as $c) {
                                        $cid = (int) $c['id']; ?>
                                        <option value="<?= $cid ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="modal__field modal__field--full">
                                <span class="modal__label">описание</span>
                                <textarea name="description" id="editDesc" rows="4"></textarea>
                            </label>
                            <label class="modal__field">
                                <span class="modal__label">цена</span>
                                <input type="number" step="1" min="0" name="price" id="editPrice">
                            </label>
                            <label class="modal__field">
                                <span class="modal__label">обложка (URL)</span>
                                <input type="text" name="image_url" id="editImage">
                            </label>

                            <fieldset class="modal__field modal__field--full">
                                <span class="modal__label">характеристики</span>
                                <div class="modal__grid modal__grid--two">
                                    <label class="modal__field"><span class="modal__label">серия</span><input type="text"
                                            name="series" placeholder=""></label>
                                    <label class="modal__field"><span class="modal__label">год издания</span><input
                                            type="text" name="publish_year" placeholder=""></label>
                                    <label class="modal__field"><span class="modal__label">издательство</span><input
                                            type="text" name="publisher" placeholder=""></label>
                                    <label class="modal__field"><span class="modal__label">количество страниц</span><input
                                            type="text" name="pages" placeholder=""></label>
                                    <label class="modal__field"><span class="modal__label">тип издания</span><input
                                            type="text" name="cover_type" placeholder=""></label>
                                    <label class="modal__field"><span class="modal__label">размер</span><input type="text"
                                            name="size" placeholder=""></label>
                                </div>
                            </fieldset>
                        </div>
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__btn modal__btn--muted" id="editCancel">отмена</button>
                        <button type="submit" class="modal__btn">сохранить</button>
                    </div>
                </form>
            </div>
        <?php } ?>
    </main>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=YOUR_YANDEX_MAPS_API_KEY"></script>
    <script src="../assets/js/script.js"></script>
    <?php if ($CURRENT_ROLE === 2) { ?>
        <script>
            (function () {
                const qs = s => document.querySelector(s);
                const qsa = s => Array.from(document.querySelectorAll(s));
                const overlay = qs('#modalOverlay');
                const delModal = qs('#deleteModal');
                const delCatModal = qs('#deleteCategoryModal');
                const editModal = qs('#editModal');
                let currentDeleteId = null;
                let currentDeleteCategoryId = null;

                function openModal(m) { if (!m) return; overlay.style.display = 'block'; m.style.display = 'block'; }
                function closeModal() {
                    overlay.style.display = 'none';
                    [delModal, delCatModal, editModal].forEach(m => { if (m) m.style.display = 'none'; });
                }
                if (overlay) overlay.addEventListener('click', closeModal);

                // Delete flow
                qsa('.js-delete-book').forEach(btn => {
                    btn.addEventListener('click', e => {
                        e.preventDefault();
                        currentDeleteId = btn.dataset.id;
                        openModal(delModal);
                    });
                });
                const delCancel = qs('#delCancel');
                if (delCancel) delCancel.addEventListener('click', closeModal);
                const delConfirm = qs('#delConfirm');
                if (delConfirm) delConfirm.addEventListener('click', async () => {
                    if (!currentDeleteId) return;
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('book_id', currentDeleteId);
                    const res = await fetch('', { method: 'POST', body: formData });
                    const j = await res.json().catch(() => ({ ok: false }));
                    if (j && j.ok) { location.reload(); }
                    else { closeModal(); alert('Не удалось удалить товар'); }
                });

                // Category delete flow
                qsa('.js-delete-category').forEach(btn => {
                    btn.addEventListener('click', e => {
                        e.preventDefault();
                        currentDeleteCategoryId = btn.dataset.id;
                        const name = btn.dataset.name || '';
                        const txtEl = qs('#delCatText');
                        if (txtEl) {
                            txtEl.textContent = name
                                ? `Действие необратимо. Вы уверены, что хотите удалить категорию "${name}"?`
                                : 'Действие необратимо. Вы уверены, что хотите удалить эту категорию?';
                        }
                        openModal(delCatModal);
                    });
                });
                const delCatCancel = qs('#delCatCancel');
                if (delCatCancel) delCatCancel.addEventListener('click', closeModal);
                const delCatConfirm = qs('#delCatConfirm');
                if (delCatConfirm) delCatConfirm.addEventListener('click', async () => {
                    if (!currentDeleteCategoryId) return;
                    const formData = new FormData();
                    formData.append('action', 'delete_category');
                    formData.append('category_id', currentDeleteCategoryId);
                    const res = await fetch('', { method: 'POST', body: formData });
                    const j = await res.json().catch(() => ({ ok: false }));
                    if (j && j.ok) { location.reload(); }
                    else { closeModal(); alert('Не удалось удалить категорию'); }
                });

                // Edit flow
                qsa('.js-edit-book').forEach(btn => {
                    btn.addEventListener('click', e => {
                        e.preventDefault();
                        const id = btn.dataset.id;
                        qs('#editId').value = id;
                        qs('#editTitle').value = btn.dataset.title || '';
                        qs('#editAuthor').value = btn.dataset.author || '';
                        qs('#editPrice').value = btn.dataset.price || '';
                        qs('#editImage').value = btn.dataset.image || '';
                        qs('#editDesc').value = btn.dataset.description || '';
                        // характеристики
                        const form = qs('#editForm');
                        if (form) {
                            const s = form.querySelector('input[name="series"]');
                            const y = form.querySelector('input[name="publish_year"]');
                            const p = form.querySelector('input[name="publisher"]');
                            const pg = form.querySelector('input[name="pages"]');
                            const ct = form.querySelector('input[name="cover_type"]');
                            const sz = form.querySelector('input[name="size"]');
                            if (s) s.value = btn.dataset.series || '';
                            if (y) y.value = btn.dataset.publish_year || '';
                            if (p) p.value = btn.dataset.publisher || '';
                            if (pg) pg.value = btn.dataset.pages || '';
                            if (ct) ct.value = btn.dataset.cover_type || '';
                            if (sz) sz.value = btn.dataset.size || '';
                        }
                        // preselect categories in dropdown
                        const cSet = new Set((btn.dataset.categories || '').split(',').filter(Boolean));
                        const catSel = qs('#editCategories');
                        if (catSel) {
                            Array.from(catSel.options).forEach(opt => { opt.selected = cSet.has(opt.value); });
                        }
                        // preselect genres in dropdown
                        const gSet = new Set((btn.dataset.genres || '').split(',').filter(Boolean));
                        const genSel = qs('#editGenres');
                        if (genSel) {
                            Array.from(genSel.options).forEach(opt => { opt.selected = gSet.has(opt.value); });
                        }
                        openModal(editModal);
                    });
                });
                const editCancel = qs('#editCancel');
                if (editCancel) editCancel.addEventListener('click', closeModal);
                const editForm = qs('#editForm');
                if (editForm) editForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const data = new FormData(editForm);
                    data.append('action', 'update');
                    const res = await fetch('', { method: 'POST', body: data });
                    const j = await res.json().catch(() => ({ ok: false }));
                    if (j && j.ok) { location.reload(); }
                    else { closeModal(); alert('Не удалось сохранить изменения'); }
                });
            })();
        </script>
    <?php } ?>
</body>

</html>