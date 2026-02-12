<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
<header class="header">
    <div class="container header__inner">
        <a class="header__catalog" href="./catalog.php">
            <img src="../assets/media/icons/menu_icon.svg" alt="" class="icon">
            <span>каталог</span>
        </a>

        <a href="./index.php" class="header__logo">
            <img src="../assets/media/icons/logo_text.svg" alt="переплёт">
        </a>

        <form class="header__search" action="#">
            <input type="text" placeholder="найти книгу по душе...">
            <button type="submit" class="header__search-btn">
                <img src="../assets/media/icons/lupa_icon.svg" alt="">
            </button>
        </form>

        <nav class="header__nav">
            <a href="#" class="header__nav-item">
                <img src="../assets/media/icons/bookmark_icon.svg" alt="">
                <span>избранное</span>
            </a>
            <a href="./cart.php" class="header__nav-item">
                <img src="../assets/media/icons/cart_icon.svg" alt="">
                <span>корзина</span>
            </a>
            <a href="./account.php" class="header__nav-item">
                <img src="../assets/media/icons/account_icon.svg" alt="">
                <span>профиль</span>
            </a>
        </nav>

        <?php if (!empty($_SESSION['user_id'])) { ?>
            <a href="./logout.php" class="header__logout">выйти</a>
        <?php } else { ?>
            <a href="./login.php" class="header__login">войти</a>
        <?php } ?>

        <button class="header__burger" type="button" aria-label="Открыть меню">
            <span></span>
        </button>
    </div>
</header>
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu__inner">
        <div class="mobile-menu__top">
            <a href="./index.php" class="mobile-menu__logo">
                <img src="../assets/media/icons/logo_text.svg" alt="переплёт">
            </a>
            <button type="button" class="mobile-menu__close" aria-label="Закрыть меню">×</button>
        </div>
        <nav class="mobile-menu__nav">
            <a href="#" class="mobile-menu__item">
                <span class="mobile-menu__icon-wrap">
                    <img src="../assets/media/icons/bookmark_icon.svg" alt="">
                </span>
                <span class="mobile-menu__label">избранное</span>
            </a>
            <a href="./cart.php" class="mobile-menu__item">
                <span class="mobile-menu__icon-wrap">
                    <img src="../assets/media/icons/cart_icon.svg" alt="">
                </span>
                <span class="mobile-menu__label">корзина</span>
            </a>
            <a href="./account.php" class="mobile-menu__item">
                <span class="mobile-menu__icon-wrap">
                    <img src="../assets/media/icons/account_icon.svg" alt="">
                </span>
                <span class="mobile-menu__label">профиль</span>
            </a>
            <a href="./catalog.php" class="mobile-menu__item">
                <span class="mobile-menu__icon-wrap">
                    <img src="../assets/media/icons/menu_icon.svg" alt="">
                </span>
                <span class="mobile-menu__label">каталог</span>
            </a>
        </nav>
    </div>
</div>
<a href="javascript:history.back()" class="back-btn">
    <img src="../assets/media/icons/arrow_left_icon.svg" alt="">
    <span>назад</span>
</a>