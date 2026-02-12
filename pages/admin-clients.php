<?php
require_once __DIR__ . '/../includes/connect.php';

@session_start();

// только админ (role=2) может работать с пользователями
$CURRENT_ROLE = 0;
if (isset($_SESSION['user']['role'])) {
    $CURRENT_ROLE = (int)$_SESSION['user']['role'];
} elseif (isset($_SESSION['role'])) {
    $CURRENT_ROLE = (int)$_SESSION['role'];
} elseif (!empty($_SESSION['user_id'])) {
    try {
        $stRole = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stRole->execute([(int)$_SESSION['user_id']]);
        if ($rowR = $stRole->fetch()) {
            $CURRENT_ROLE = (int)$rowR['role'];
        }
    } catch (Throwable $e) {
        $CURRENT_ROLE = 0;
    }
}

if ($CURRENT_ROLE !== 2) {
    header('Location: ./account.php');
    exit;
}

// удаление пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId > 0) {
        try {
            // физически удаляем пользователя из БД
            $stDel = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stDel->execute([$userId]);
        } catch (Throwable $e) {
            // игнорируем ошибку, просто не удаляем
        }
    }
    header('Location: ./admin-clients.php');
    exit;
}

// загружаем пользователей для админки
$adminUsers = [];
try {
    $stmt = $pdo->query('SELECT id, username, email, phone, status FROM users ORDER BY id DESC');
    $adminUsers = $stmt->fetchAll();
} catch (Throwable $e) {
    $adminUsers = [];
}
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переплёт - админ-панель</title>
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
                    <li><a href="./account.php" class="breadcrumbs__link">профиль</a></li>
                    <li class="breadcrumbs__sep">/</li>
                    <li><a href="./admin.php" class="breadcrumbs__link">админ-панель</a></li>
                    <li class="breadcrumbs__sep">/</li>
                    <li class="breadcrumbs__current">пользователи</li>
                </ol>
            </div>
        </nav>
        <section class="admin">
            <div class="container admin__inner">
                <aside class="account__sidebar">
                    <nav class="account-menu">
                        <a href="./admin.php" class="account-menu__item">
                            <img src="../assets/media/icons/add_icon.svg" alt="">
                            <span>добавление</span>
                        </a>
                        <a href="./admin-clients.php" class="account-menu__item is-active">
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
                    <h1 class="clients__title">пользователи</h1>
                    <div class="clients-grid">
                        <?php if (!empty($adminUsers)): ?>
                            <?php foreach ($adminUsers as $u): ?>
                                <?php
                                $uName   = trim((string)($u['username'] ?? ''));
                                $uEmail  = trim((string)($u['email'] ?? ''));
                                $uPhone  = trim((string)($u['phone'] ?? ''));
                                $uStatus = trim((string)($u['status'] ?? ''));
                                if ($uName === '') {
                                    $uName = $uEmail !== '' ? $uEmail : ('Пользователь #' . (int)$u['id']);
                                }
                                if ($uStatus === '') {
                                    $uStatus = 'читатель';
                                }
                                ?>
                                <div class="client-card" data-user-id="<?= (int)$u['id'] ?>" data-user-name="<?= htmlspecialchars($uName, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="client-card__name"><?= htmlspecialchars($uName, ENT_QUOTES, 'UTF-8') ?></div>
                                    <dl class="client-card__list">
                                        <div class="client-card__row"><dt>статус:</dt><dd class="is-accent"><?= htmlspecialchars($uStatus, ENT_QUOTES, 'UTF-8') ?></dd></div>
                                        <div class="client-card__row"><dt>номер телефона:</dt><dd><?= htmlspecialchars($uPhone, ENT_QUOTES, 'UTF-8') ?: '-' ?></dd></div>
                                        <div class="client-card__row"><dt>почта:</dt><dd><?= htmlspecialchars($uEmail, ENT_QUOTES, 'UTF-8') ?: '-' ?></dd></div>
                                    </dl>
                                    <div class="client-card__actions">
                                        <button class="client-card__btn" type="button">подробнее</button>
                                        <button class="client-card__remove" type="button" data-user-id="<?= (int)$u['id'] ?>">удалить</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Пользователи не найдены.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- modal: confirm user delete -->
    <div class="modal-overlay" id="userDeleteOverlay" style="display:none;"></div>
    <div class="modal" id="userDeleteModal" style="display:none;">
        <div class="modal__header">
            <h2 class="modal__title">Удалить пользователя?</h2>
        </div>
        <div class="modal__body">
            <p class="modal__text">Вы действительно хотите удалить пользователя <span id="userDeleteName"></span>? Эту операцию нельзя будет отменить.</p>
        </div>
        <form class="modal__footer" method="post">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" id="userDeleteId" value="0">
            <button type="button" class="modal__btn modal__btn--muted" id="userDeleteCancel">отмена</button>
            <button type="submit" class="modal__btn">удалить</button>
        </form>
    </div>

    <script>
    (function(){
        var overlay = document.getElementById('userDeleteOverlay');
        var modal   = document.getElementById('userDeleteModal');
        if (!overlay || !modal) return;

        function openModal(userId, userName) {
            document.getElementById('userDeleteId').value = userId;
            document.getElementById('userDeleteName').textContent = userName || '';
            overlay.style.display = 'block';
            modal.style.display = 'block';
        }

        function closeModal() {
            overlay.style.display = 'none';
            modal.style.display = 'none';
        }

        var buttons = document.querySelectorAll('.client-card__remove');
        buttons.forEach(function(btn){
            btn.addEventListener('click', function(){
                var card = btn.closest('.client-card');
                if (!card) return;
                var uid  = card.getAttribute('data-user-id');
                var name = card.getAttribute('data-user-name') || '';
                openModal(uid, name);
            });
        });

        document.getElementById('userDeleteCancel').addEventListener('click', function(e){
            e.preventDefault();
            closeModal();
        });

        overlay.addEventListener('click', function(){ closeModal(); });
    })();
    </script>
</body>
</html>
