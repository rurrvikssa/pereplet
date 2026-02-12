<?php
require_once __DIR__ . '/../includes/connect.php';
session_start();

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $old = [
        'email' => $email,
        'username' => $username,
        'phone' => $phone,
    ];

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректная почта';
    }
    if (mb_strlen($password) < 6) {
        $errors['password'] = 'Минимум 6 символов';
    }
    if ($password !== $password2) {
        $errors['password2'] = 'Пароли не совпадают';
    }

    // имя пользователя: минимум 3 символа
    if ($username === '' || mb_strlen($username, 'UTF-8') < 3) {
        $errors['username'] = 'Минимум 3 символа';
    }

    // телефон: только цифры и не пустой
    if ($phone === '' || !preg_match('/^\d+$/', $phone)) {
        $errors['phone'] = 'Телефон должен содержать только цифры';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Почта уже зарегистрирована';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $status = 'читатель';
        try {
            // Preferred: with status and bonus_points initialized to 0
            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, username, phone, status, bonus_points) VALUES (?, ?, ?, ?, ?, 0)');
            $stmt->execute([$email, $hash, $username !== '' ? $username : null, $phone !== '' ? $phone : null, $status]);
        } catch (PDOException $e1) {
            try {
                // Fallback: if bonus_points column doesn't exist but status does
                $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, username, phone, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$email, $hash, $username !== '' ? $username : null, $phone !== '' ? $phone : null, $status]);
            } catch (PDOException $e2) {
                // Final fallback: minimal insert
                $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, username, phone) VALUES (?, ?, ?, ?)');
                $stmt->execute([$email, $hash, $username !== '' ? $username : null, $phone !== '' ? $phone : null]);
            }
        }

        $userId = (int)$pdo->lastInsertId();
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $email;
        $_SESSION['username'] = $username;
        header('Location: ./account.php');
        exit;
    }
}
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - регистрация</title>
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
                    <li class="breadcrumbs__current">регистрация</li>
                </ol>
            </div>
        </nav>

        <section class="register">
            <div class="container">
                <div class="register__inner">
                    <img class="register__bg-left" src="../assets/media/images/reg/reg_layout_img.png" alt="">
                    <img class="register__illus" src="../assets/media/images/reg/reg_img.png" alt="">

                    <div class="register__content">
                        <h1 class="register__title">регистрация</h1>
                        <?php if ($errors) { ?>
                            <div class="register__hint" style="color:#8F121E;">Проверьте поля формы</div>
                        <?php } ?>

                        <form class="register__form" action="" method="post" novalidate>
                            <div class="register__group">
                                <label class="register__label" for="reg_phone">номер телефона</label>
                                <input class="register__input" type="tel" id="reg_phone" name="phone" placeholder="введите номер телефона" value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
                                <?php if (!empty($errors['phone'])) { ?><div class="register__hint" style="color:#8F121E;"><?= htmlspecialchars($errors['phone']) ?></div><?php } ?>
                            </div>

                            <div class="register__group">
                                <label class="register__label" for="reg_email">почта</label>
                                <input class="register__input" type="email" id="reg_email" name="email" placeholder="введите почту" value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                                <?php if (!empty($errors['email'])) { ?><div class="register__hint" style="color:#8F121E;"><?= htmlspecialchars($errors['email']) ?></div><?php } ?>
                            </div>

                            <div class="register__group">
                                <label class="register__label" for="reg_user">имя пользователя</label>
                                <input class="register__input" type="text" id="reg_user" name="username" placeholder="придумайте имя пользователя" value="<?= htmlspecialchars($old['username'] ?? '') ?>">
                                <?php if (!empty($errors['username'])) { ?><div class="register__hint" style="color:#8F121E;"><?= htmlspecialchars($errors['username']) ?></div><?php } ?>
                            </div>

                            <div class="register__group">
                                <label class="register__label" for="reg_pass">пароль</label>
                                <input class="register__input" type="password" id="reg_pass" name="password" placeholder="введите пароль">
                                <?php if (!empty($errors['password'])) { ?><div class="register__hint" style="color:#8F121E;"><?= htmlspecialchars($errors['password']) ?></div><?php } ?>
                            </div>

                            <div class="register__group">
                                <label class="register__label" for="reg_pass2">подтверждение пароля</label>
                                <input class="register__input" type="password" id="reg_pass2" name="password2" placeholder="повторите пароль">
                                <?php if (!empty($errors['password2'])) { ?><div class="register__hint" style="color:#8F121E;"><?= htmlspecialchars($errors['password2']) ?></div><?php } ?>
                            </div>

                            <div class="register__hint">уже есть аккаунт? <a class="register__link" href="./login.php">вход</a></div>

                            <button type="submit" class="register__btn">
                                <img src="../assets/media/icons/sparkles_icon.svg" alt="">
                                <span>зарегистрироваться</span>
                            </button>
                        </form>
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
