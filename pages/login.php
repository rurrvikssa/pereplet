<?php
require_once __DIR__ . '/../includes/connect.php';
session_start();

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $old['phone'] = $phone;

    if ($phone === '') {
        $errors['phone'] = 'Введите номер телефона';
    }
    if ($password === '') {
        $errors['password'] = 'Введите пароль';
    }

    if (!$errors) {
        $digits = preg_replace('/\D+/', '', $phone);
        $stmt = $pdo->prepare("SELECT id, email, username, phone, password_hash FROM users WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-',''),'(',')'),')','') = ? OR phone = ? LIMIT 1");
        $stmt->execute([$digits, $phone]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors['phone'] = 'Неверный телефон или пароль';
        } else {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['username'] = $user['username'] ?? '';
            header('Location: ./account.php');
            exit;
        }
    }
}
?><!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - вход</title>
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
                    <li class="breadcrumbs__current">вход</li>
                </ol>
            </div>
        </nav>

        <section class="login">
            <div class="container">
                <div class="login__inner">
                    <img class="login__bg-left" src="../assets/media/images/log/log_layout_img.png" alt="">
                    <img class="login__illus" src="../assets/media/images/log/log_img.png" alt="">

                    <div class="login__content">
                        <h1 class="login__title">вход</h1>

                        <form class="login__form" action="" method="post" novalidate>
                            <div class="login__group">
                                <label class="login__label" for="phone">номер телефона</label>
                                <input class="login__input" type="tel" id="phone" name="phone"
                                    placeholder="введите номер телефона" value="<?php echo $old['phone'] ?? ''; ?>">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="login__error"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="login__group">
                                <label class="login__label" for="password">пароль</label>
                                <input class="login__input" type="password" id="password" name="password"
                                    placeholder="введите пароль">
                                <?php if (isset($errors['password'])): ?>
                                    <div class="login__error"><?php echo $errors['password']; ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="login__hint">еще нет аккаунта? <a class="login__link"
                                    href="./register.php">регистрация</a></div>

                            <button type="submit" class="login__btn">
                                <img src="../assets/media/icons/sparkles_icon.svg" alt="">
                                <span>войти</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </main>
    <script src="../assets/js/script.js"></script>
</body>

</html>