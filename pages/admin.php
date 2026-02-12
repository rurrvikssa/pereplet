<?php
require_once __DIR__ . '/../includes/connect.php';

@session_start();

$CURRENT_ROLE = 0;
if (isset($_SESSION['user']['role'])) {
    $CURRENT_ROLE = (int) $_SESSION['user']['role'];
} elseif (isset($_SESSION['role'])) {
    $CURRENT_ROLE = (int) $_SESSION['role'];
} elseif (!empty($_SESSION['user_id'])) {
    try {
        $stRole = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stRole->execute([(int) $_SESSION['user_id']]);
        if ($rowR = $stRole->fetch()) {
            $CURRENT_ROLE = (int) $rowR['role'];
        }
    } catch (Throwable $e) {
        $CURRENT_ROLE = 0;
    }
}

if ($CURRENT_ROLE !== 2) {
    header('Location: ./account.php');
    exit;
}

$message = null;
$errors = [];
$success = false;
$catMessage = null;
$catErrors = [];

$genres = [];
$categories = [];
$hasGenreTable = false;
$hasCategoryTable = false;
try {
    $genres = $pdo->query('SELECT id, name FROM genres ORDER BY name')->fetchAll();
    $hasGenreTable = true;
} catch (Throwable $e) { 
}
try {
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    $hasCategoryTable = true;
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
        return ['id' => $id, 'name' => $name]; }, array_keys($genreOptions), $genreOptions);
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
        return ['id' => $id, 'name' => $name]; }, array_keys($categoryOptions), $categoryOptions);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_book') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    // accept multiple selections
    $genre_ids = $_POST['genre_id'] ?? $_POST['genre_ids'] ?? [];
    $category_ids = $_POST['category_id'] ?? $_POST['category_ids'] ?? [];
    if (!is_array($genre_ids)) {
        $genre_ids = [$genre_ids];
    }
    if (!is_array($category_ids)) {
        $category_ids = [$category_ids];
    }
    $image_url = null;

    if ($title === '') {
        $errors['title'] = 'Введите название';
    }
    if ($author === '') {
        $errors['author'] = 'Введите автора';
    }
    if ($price === '' || !is_numeric($price) || (float) $price < 0) {
        $errors['price'] = 'Некорректная цена';
    }
    $genre_ids = array_values(array_unique(array_filter(array_map(function ($v) {
        return ctype_digit((string) $v) ? (int) $v : 0; }, $genre_ids), function ($v) {
            return $v > 0; })));
    $category_ids = array_values(array_unique(array_filter(array_map(function ($v) {
        return ctype_digit((string) $v) ? (int) $v : 0; }, $category_ids), function ($v) {
            return $v > 0; })));

    $genId = $genre_ids[0] ?? 0;
    $catId = $category_ids[0] ?? 0;
    if ($genId <= 0) {
        try {
            $tmp = $pdo->query('SELECT id FROM genres ORDER BY id LIMIT 1')->fetchColumn();
            $genId = $tmp ? (int) $tmp : 0;
        } catch (Throwable $e) {
            $genId = 0;
        }
    }
    if ($catId <= 0) {
        try {
            $tmp = $pdo->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn();
            $catId = $tmp ? (int) $tmp : 0;
        } catch (Throwable $e) {
            $catId = 0;
        }
    }
    if ($genId <= 0) {
        $errors['genre_id'] = 'Выберите жанр';
    }
    if ($catId <= 0) {
        $errors['category_id'] = 'Выберите категорию';
    }
    if (!empty($_FILES['image_file']['name'])) {
        $uploadErr = $_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadErr === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image_file']['tmp_name'];
            $size = (int) ($_FILES['image_file']['size'] ?? 0);
            if ($size > 5 * 1024 * 1024) { // 5MB
                $errors['image_file'] = 'Файл слишком большой (макс. 5 МБ)';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $tmp) : null;
                if ($finfo) {
                    finfo_close($finfo);
                }
                $allowed = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp', 'image/gif' => '.gif'];
                if (!isset($allowed[$mime])) {
                    $errors['image_file'] = 'Недопустимый формат изображения';
                } else {
                    $ext = $allowed[$mime];
                    $dir = realpath(__DIR__ . '/../assets/media');
                    if ($dir === false) {
                        $errors['image_file'] = 'Путь загрузки недоступен';
                    } else {
                        $uploadDir = $dir . DIRECTORY_SEPARATOR . 'uploads';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0777, true);
                        }
                        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                            $errors['image_file'] = 'Не удалось создать папку загрузок';
                        } else {
                            $fname = 'book_' . bin2hex(random_bytes(6)) . $ext;
                            $dest = $uploadDir . DIRECTORY_SEPARATOR . $fname;
                            if (move_uploaded_file($tmp, $dest)) {
                                
                                $image_url = '../assets/media/uploads/' . $fname;
                            } else {
                                $errors['image_file'] = 'Не удалось сохранить файл';
                            }
                        }
                    }
                }
            }
        } elseif ($uploadErr !== UPLOAD_ERR_NO_FILE) {
            $errors['image_file'] = 'Ошибка загрузки файла';
        }
    }

    if (!$errors) {
        try {

            if ($hasGenreTable && $hasCategoryTable) {
                $pdo->exec('CREATE TABLE IF NOT EXISTS books_genres (
                    book_id INT NOT NULL,
                    genre_id INT NOT NULL,
                    PRIMARY KEY (book_id, genre_id),
                    CONSTRAINT fk_bg_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_bg_genre FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
                $pdo->exec('CREATE TABLE IF NOT EXISTS books_categories (
                    book_id INT NOT NULL,
                    category_id INT NOT NULL,
                    PRIMARY KEY (book_id, category_id),
                    CONSTRAINT fk_bc_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_bc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            }

            $stmt = $pdo->prepare('INSERT INTO books (title, author, description, price, category_id, genre_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $title,
                $author,
                $description !== '' ? $description : null,
                number_format((float) $price, 2, '.', ''),
                $catId,
                $genId,
                $image_url,
            ]);
            $bookId = (int) $pdo->lastInsertId();

            // fill junctions: include primary ids plus the rest
            if ($bookId > 0 && $hasGenreTable && $hasCategoryTable) {
                // genres
                $allGen = $genre_ids;
                if (!in_array($genId, $allGen, true)) {
                    array_unshift($allGen, $genId);
                }
                $insG = $pdo->prepare('INSERT IGNORE INTO books_genres (book_id, genre_id) VALUES (?, ?)');
                foreach ($allGen as $gid) {
                    $insG->execute([$bookId, (int) $gid]);
                }
                // categories
                $allCat = $category_ids;
                if (!in_array($catId, $allCat, true)) {
                    array_unshift($allCat, $catId);
                }
                $insC = $pdo->prepare('INSERT IGNORE INTO books_categories (book_id, category_id) VALUES (?, ?)');
                foreach ($allCat as $cid) {
                    $insC->execute([$bookId, (int) $cid]);
                }
            }
            $message = 'Товар добавлен';
            $success = true;
        } catch (Throwable $e) {
            $message = 'Ошибка при добавлении товара: ' . $e->getMessage();
        }
    }
}

// add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_category') {
    $category_name = trim($_POST['category_name'] ?? '');
    if ($category_name === '') {
        $catErrors['name'] = 'Введите название категории';
    }
    if (!$catErrors) {
        try {
            // ensure categories table exists
            $pdo->exec('CREATE TABLE IF NOT EXISTS categories (
                id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_category_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $hasCategoryTable = true;
            // ensure books_categories junction exists (if books table present)
            try {
                $pdo->exec('CREATE TABLE IF NOT EXISTS books_categories (
                    book_id INT NOT NULL,
                    category_id INT NOT NULL,
                    PRIMARY KEY (book_id, category_id),
                    KEY idx_bc_category (category_id),
                    KEY idx_bc_book (book_id),
                    CONSTRAINT fk_bc_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_bc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            } catch (Throwable $e) { /* books table may not exist yet */
            }
            // helpful indexes for sorting/filtering
            try {
                $pdo->exec('ALTER TABLE books ADD INDEX idx_books_category (category_id)');
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec('ALTER TABLE books ADD INDEX idx_books_genre (genre_id)');
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec('ALTER TABLE books ADD INDEX idx_books_price (price)');
            } catch (Throwable $e) {
            }
            try {
                $pdo->exec('ALTER TABLE books ADD INDEX idx_books_created (created_at)');
            } catch (Throwable $e) {
            }

            // insert category (ignore duplicate by name)
            $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
            $stmt->execute([$category_name]);
            $catMessage = 'Категория добавлена';
            // refresh categories list for selects
            try {
                $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
            } catch (Throwable $e) {
            }
        } catch (Throwable $e) {
            $catMessage = 'Ошибка при добавлении категории: ' . $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - админ-панель</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="shortcut icon" href="../assets/media/icons/logo.svg" type="image/x-icon">
    <style>
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .4);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000
        }

        .modal {
            width: min(520px, 90vw);
            background: var(--color-white);
            border-radius: 22px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .15);
            padding: 24px
        }

        .modal__title {
            font-family: "Unbounded", sans-serif;
            font-size: 20px;
            margin-bottom: 12px;
            color: var(--color-text)
        }

        .modal__text {
            font-size: 14px;
            line-height: 1.5;
            color: var(--color-text);
            margin-bottom: 20px
        }

        .modal__actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end
        }

        .modal__btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 10px;
            font-family: "Unbounded", sans-serif
        }

        .modal__btn--primary {
            background: var(--color-accent);
            color: var(--color-white)
        }

        .modal__btn--ghost {
            background: var(--color-bg);
            color: var(--color-text)
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div id="successModal" class="modal-overlay" aria-hidden="true" role="dialog">
        <div class="modal">
            <div class="modal__title">товар добавлен</div>
            <div class="modal__text">Книга успешно сохранена в каталоге. Вы можете добавить ещё одну или открыть
                каталог.</div>
            <div class="modal__actions">
                <button type="button" class="modal__btn modal__btn--ghost" data-modal-close>закрыть</button>
                <a href="./catalog.php" class="modal__btn modal__btn--primary">в каталог</a>
            </div>
        </div>
    </div>
    <main>
        <nav class="breadcrumbs">
            <div class="container breadcrumbs__inner">
                <ol class="breadcrumbs__list">
                    <li><a href="./index.php" class="breadcrumbs__link">главная</a></li>
                    <li class="breadcrumbs__sep">/</li>
                    <li><a href="./account.php" class="breadcrumbs__link">профиль</a></li>
                    <li class="breadcrumbs__sep">/</li>
                    <li class="breadcrumbs__current">админ-панель</li>
                </ol>
            </div>
        </nav>
        <section class="admin">
            <div class="container admin__inner">
                <aside class="account__sidebar">
                    <nav class="account-menu">
                        <a href="#" class="account-menu__item is-active">
                            <img src="../assets/media/icons/add_icon.svg" alt="">
                            <span>добавление</span>
                        </a>
                        <a href="./admin-clients.php" class="account-menu__item">
                            <img src="../assets/media/icons/clients_icon.svg" alt="">
                            <span>пользователи</span>
                        </a>
                        <a href="./admin-orders.php" class="account-menu__item">
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
                    <div class="admin-block">
                        <div class="admin-block__title">добавление товара</div>
                        <?php if ($message || $errors) { ?>
                            <div class="admin-hint"
                                style="color: <?= $errors ? '#8F121E' : 'var(--color-accent)' ?>; margin-bottom:16px;">
                                <?= $errors ? htmlspecialchars(reset($errors)) : htmlspecialchars($message) ?>
                            </div>
                        <?php } ?>
                        <form class="admin-grid admin-grid--two" action="" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_book">
                            <label class="admin-field">
                                <span class="admin-field__label">название</span>
                                <input name="title" type="text" class="admin-input" placeholder="введите название"
                                    value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                            </label>
                            <label class="admin-field">
                                <span class="admin-field__label">жанры</span>
                                <?php $postedGenres = $_POST['genre_ids'] ?? $_POST['genre_id'] ?? [];
                                if (!is_array($postedGenres)) {
                                    $postedGenres = [$postedGenres];
                                }
                                $postedGenres = array_map('intval', $postedGenres); ?>
                                <select name="genre_ids[]" class="admin-select" multiple size="1" required>
                                    <?php foreach ($genres as $g) {
                                        $gid = (int) $g['id'];
                                        $sel = in_array($gid, $postedGenres, true) ? 'selected' : ''; ?>
                                        <option value="<?= $gid ?>" <?= $sel ?>><?= htmlspecialchars($g['name']) ?></option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="admin-field">
                                <span class="admin-field__label">автор</span>
                                <input name="author" type="text" class="admin-input" placeholder="введите автора"
                                    value="<?= htmlspecialchars($_POST['author'] ?? '') ?>" required>
                            </label>
                            <label class="admin-field">
                                <span class="admin-field__label">категории</span>
                                <?php $postedCats = $_POST['category_ids'] ?? $_POST['category_id'] ?? [];
                                if (!is_array($postedCats)) {
                                    $postedCats = [$postedCats];
                                }
                                $postedCats = array_map('intval', $postedCats); ?>
                                <select name="category_ids[]" class="admin-select" multiple size="1" required>
                                    <?php foreach ($categories as $c) {
                                        $cid = (int) $c['id'];
                                        $sel = in_array($cid, $postedCats, true) ? 'selected' : ''; ?>
                                        <option value="<?= $cid ?>" <?= $sel ?>><?= htmlspecialchars($c['name']) ?></option>
                                    <?php } ?>
                                </select>
                            </label>

                            <label class="admin-field admin-field--area">
                                <span class="admin-field__label">описание</span>
                                <textarea name="description" class="admin-textarea"
                                    placeholder="опишите товар..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </label>
                            <label class="admin-field">
                                <span class="admin-field__label">цена</span>
                                <input name="price" type="number" step="0.01" class="admin-input"
                                    placeholder="введите стоимость"
                                    value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" min="0" required>
                            </label>
                            <label class="admin-field">
                                <span class="admin-field__label">изображение (файл)</span>
                                <input name="image_file" type="file" class="admin-input" accept="image/*">
                            </label>
                            <div></div>
                            <div></div>
                            <div class="admin-grid__btn">
                                <button class="admin-btn" type="submit">добавить товар</button>
                            </div>
                        </form>
                    </div>

                    <div class="admin-block">
                        <div class="admin-block__title">добавление категории</div>
                        <?php if ($catMessage || $catErrors) { ?>
                            <div class="admin-hint"
                                style="color: <?= $catErrors ? '#8F121E' : 'var(--color-accent)' ?>; margin-bottom:16px;">
                                <?= $catErrors ? htmlspecialchars(reset($catErrors)) : htmlspecialchars($catMessage) ?>
                            </div>
                        <?php } ?>
                        <form class="admin-grid admin-grid--one" action="" method="post">
                            <input type="hidden" name="action" value="add_category">
                            <label class="admin-field">
                                <span class="admin-field__label">название</span>
                                <input name="category_name" type="text" class="admin-input"
                                    placeholder="введите название"
                                    value="<?= htmlspecialchars($_POST['category_name'] ?? '') ?>" required>
                            </label>
                            <div class="admin-grid__btn">
                                <button class="admin-btn" type="submit">добавить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script>
        (function () {
            var modal = document.getElementById('successModal');
            var closeBtn = modal ? modal.querySelector('[data-modal-close]') : null;
            function open() { if (!modal) return; modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false'); }
            function close() { if (!modal) return; modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); }
            if (closeBtn) { closeBtn.addEventListener('click', close); }
            if (modal) { modal.addEventListener('click', function (e) { if (e.target === modal) close(); }); }
            <?php if ($success) { ?>
                open();
                try { var f = document.querySelector('.admin-grid.admin-grid--two'); if (f) f.reset(); } catch (e) { }
            <?php } ?>
        })();
    </script>
</body>

</html>