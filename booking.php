<?php
session_start();
require 'config/database.php';

// включим строгие ошибки mysqli (помогает ловить проблемы на dev)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// CSRF-токен (генерируем один раз на сессию)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ==== ОБРАБОТКА ФОРМЫ ЗАПИСИ (БЕЗОПАСНО) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {

    // 1) Проверяем CSRF
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $error_message = 'Сессия устарела. Обновите страницу и отправьте форму ещё раз.';
    } else {

        // 2) Забираем и нормализуем данные
        $name           = trim($_POST['name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $course_id      = isset($_POST['course_id']) ? (int)$_POST['course_id'] : null; // обязателен
        $instructor_id  = ($_POST['instructor_id'] ?? '') === '' ? null : (int)$_POST['instructor_id']; // опционален
        $preferred_date = trim($_POST['preferred_date'] ?? ''); // опционален
        $message        = trim($_POST['message'] ?? '');

        // 3) Простая валидация
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || !$course_id) {
            $error_message = 'Пожалуйста, заполните обязательные поля корректно.';
        } else {
            // 4) Преобразуем пустые к NULL
            if ($preferred_date === '') { $preferred_date = null; }
            if ($message === '')        { $message = null; }

            // 5) Подготовленный запрос
            $sql = "INSERT INTO bookings
                    (name, email, phone, course_id, instructor_id, preferred_date, message, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

            $stmt = $conn->prepare($sql);

            // Типы: name(s), email(s), phone(s), course_id(i), instructor_id(i), preferred_date(s), message(s)
            // => 'sssiiss'
            $stmt->bind_param(
                'sssiiss',
                $name,
                $email,
                $phone,
                $course_id,
                $instructor_id,
                $preferred_date,
                $message
            );

            try {
                $stmt->execute();
                $success_message = 'Ваша заявка успешно отправлена! Мы свяжемся с вами в ближайшее время.';
            } catch (mysqli_sql_exception $e) {
                // Можно логировать $e->getMessage()
                $error_message = 'Произошла ошибка при отправке заявки. Пожалуйста, попробуйте снова.';
            }
        }
    }
}

// ==== ДАННЫЕ ДЛЯ СЕЛЕКТОВ ====
$courses_result = $conn->query("SELECT * FROM courses WHERE active = 1 ORDER BY name ASC");
$instructors_result = $conn->query("SELECT * FROM instructors WHERE active = 1 ORDER BY name ASC");
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Записаться на обучение - Haabersti Autokool</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Навигация -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="index.php">
                    <img src="assets/images/logo.png" alt="Haabersti Autokool">
                    <span>Haabersti Autokool</span>
                </a>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-link">Главная</a>
                <a href="index.php#courses" class="nav-link">Курсы</a>
                <a href="index.php#about" class="nav-link">О нас</a>
                <a href="index.php#contact" class="nav-link">Контакты</a>
            </div>
        </div>
    </nav>

    <!-- Форма записи -->
    <section class="booking-section">
        <div class="container">
            <div class="booking-header">
                <h1>Записаться на обучение</h1>
                <p>Заполните форму ниже, и мы свяжемся с вами для подтверждения записи</p>
            </div>

            <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>

            <div class="booking-content">
                <div class="booking-form">
                    <form action="booking.php" method="POST">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Имя и фамилия *</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Телефон *</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                            <div class="form-group">
                                <label for="course_id">Выберите курс *</label>
                                <select id="course_id" name="course_id" required>
                                    <option value="">-- Выберите курс --</option>
                                    <?php 
                                    mysqli_data_seek($courses_result, 0);
                                    while($course = mysqli_fetch_assoc($courses_result)): ?>
                                        <option value="<?php echo $course['id']; ?>" 
                                               <?php echo (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                            <?php echo $course['name']; ?> - <?php echo $course['price']; ?>€
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="instructor_id">Предпочтительный инструктор</label>
                                <select id="instructor_id" name="instructor_id">
                                    <option value="">-- Любой инструктор --</option>
                                    <?php while($instructor = mysqli_fetch_assoc($instructors_result)): ?>
                                        <option value="<?php echo $instructor['id']; ?>">
                                            <?php echo $instructor['name']; ?> (<?php echo $instructor['specialization']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="preferred_date">Предпочтительная дата начала</label>
                                <input type="date" id="preferred_date" name="preferred_date" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="message">Дополнительная информация</label>
                            <textarea id="message" name="message" rows="4" 
                                     placeholder="Расскажите о ваших потребностях, удобном времени занятий и другой важной информации..."></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="submit_booking" class="btn btn-primary btn-large">
                                <i class="fas fa-paper-plane"></i>
                                Отправить заявку
                            </button>
                        </div>
                    </form>
                </div>

                <div class="booking-info">
                    <div class="info-card">
                        <h3><i class="fas fa-clock"></i> Время работы</h3>
                        <ul>
                            <li>Понедельник - Пятница: 8:30 - 20:00</li>
                            <li>Суббота: 9:00 - 17:00</li>
                            <li>Воскресенье: выходной</li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-phone"></i> Контакты</h3>
                        <ul>
                            <li>Телефон: +372 5XXX XXXX</li>
                            <li>Email: info@haaberstiautokool.ee</li>
                            <li>Адрес: Haabersti, Таллинн</li>
                        </ul>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-info-circle"></i> Что дальше?</h3>
                        <ol>
                            <li>Мы получим вашу заявку</li>
                            <li>Свяжемся с вами в течение 24 часов</li>
                            <li>Обсудим детали и составим расписание</li>
                            <li>Начнем обучение в удобное время</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Футер -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Haabersti Autokool</h4>
                    <p>Профессиональное обучение вождению в Таллинне</p>
                </div>
                <div class="footer-section">
                    <h4>Контакты</h4>
                    <ul>
                        <li><i class="fas fa-phone"></i> +372 5XXX XXXX</li>
                        <li><i class="fas fa-envelope"></i> info@haaberstiautokool.ee</li>
                        <li><i class="fas fa-map-marker-alt"></i> Haabersti, Таллинн</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Haabersti Autokool. Все права защищены.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>

