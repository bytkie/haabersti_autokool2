
<?php
session_start();
include 'config/database.php';
//
// Проверяем подключение к базе данных
if (!$conn) {
    die("Ошибка подключения: " . mysqli_connect_error());
}

// Получаем информацию о курсах
$courses_query = "SELECT * FROM courses WHERE active = 1 ORDER BY price ASC";
$courses_result = mysqli_query($conn, $courses_query);

// Получаем отзывы
$reviews_query = "SELECT * FROM reviews WHERE approved = 1 ORDER BY created_at DESC LIMIT 3";
$reviews_result = mysqli_query($conn, $reviews_query);

$instructors_query = "SELECT * FROM instructors WHERE active = 1 ORDER BY name ASC";
$instructors_result = mysqli_query($conn, $instructors_query);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haabersti Autokool - Автошкола в Таллинне</title>
    <meta name="description" content="Haabersti Autokool - лучшая автошкола в районе Хааберсти, Таллинн. Обучение вождению категории B, опытные инструкторы, доступные цены.">
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
            <div class="nav-menu" id="nav-menu">
                <a href="#home" class="nav-link">Главная</a>
                <a href="#courses" class="nav-link">Курсы</a>
                <a href="#about" class="nav-link">О нас</a>
                <a href="#instructors" class="nav-link">Инструкторы</a>
                <a href="#reviews" class="nav-link">Отзывы</a>
                <a href="#contact" class="nav-link">Контакты</a>
                <a href="booking.php" class="btn btn-primary">Записаться</a>
            </div>
            <div class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Главная секция -->
    <section id="home" class="hero">
        <div class="hero-content">
            <div class="container">
                <h1>Добро пожаловать в Haabersti Autokool</h1>
                <p class="hero-subtitle">Профессиональное обучение вождению в Таллинне с опытными инструкторами</p>
                <div class="hero-features">
                    <div class="feature">
                        <i class="fas fa-car"></i>
                        <span>Современные автомобили</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-certificate"></i>
                        <span>Лицензированные инструкторы</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-clock"></i>
                        <span>Гибкое расписание</span>
                    </div>
                </div>
                <div class="hero-buttons">
                    <a href="booking.php" class="btn btn-primary btn-large">Записаться на обучение</a>
                    <a href="#courses" class="btn btn-secondary btn-large">Посмотреть курсы</a>
                </div>
            </div>
        </div>
        <div class="hero-image">
            <img src="assets/images/hero-car.png" alt="Обучение вождению">
        </div>
    </section>

    <!-- Курсы -->
    <section id="courses" class="courses">
        <div class="container">
            <h2 class="section-title">Наши курсы</h2>
            <p class="section-subtitle">Выберите подходящий курс обучения вождению</p>
            
            <div class="courses-grid">
                <?php while($course = mysqli_fetch_assoc($courses_result)): ?>
                <div class="course-card">
                    <div class="course-image">
                        <img src="assets/images/<?php echo $course['image']; ?>" alt="<?php echo $course['name']; ?>">
                        <div class="course-category"><?php echo $course['category']; ?></div>
                    </div>
                    <div class="course-content">
                        <h3><?php echo $course['name']; ?></h3>
                        <p><?php echo $course['description']; ?></p>
                        <div class="course-details">
                            <div class="detail">
                                <i class="fas fa-clock"></i>
                                <span><?php echo $course['duration']; ?> часов</span>
                            </div>
                            <div class="detail">
                                <i class="fas fa-car"></i>
                                <span><?php echo $course['lessons']; ?> уроков</span>
                            </div>
                        </div>
                        <div class="course-footer">
                            <div class="price">
                                <span class="price-amount"><?php echo $course['price']; ?>€</span>
                                <span class="price-period">полный курс</span>
                            </div>
                            <a href="booking.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">Записаться</a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- О нас -->
    <section id="about" class="about">
        <div class="container">
            <div class="about-content">
                <div class="about-text">
                    <h2>О Haabersti Autokool</h2>
                    <p>Наша автошкола работает в районе Хааберсти уже более 0 лет, предоставляя качественное обучение вождению для жителей Таллинна и окрестностей.</p>
                    
                    <div class="stats">
                        <div class="stat">
                            <div class="stat-number">0+</div>
                            <div class="stat-label">Выпускников</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">0%</div>
                            <div class="stat-label">Сдают с первого раза</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">0+</div>
                            <div class="stat-label">Лет опыта</div>
                        </div>
                    </div>

                    <div class="advantages">
                        <div class="advantage">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <h4>Опытные инструкторы</h4>
                                <p>Все наши инструкторы имеют многолетний опыт и необходимые лицензии</p>
                            </div>
                        </div>
                        <div class="advantage">
                            <i class="fas fa-car"></i>
                            <div>
                                <h4>Современные автомобили</h4>
                                <p>Обучение проходит на новых безопасных автомобилях с двойными педалями</p>
                            </div>
                        </div>
                        <div class="advantage">
                            <i class="fas fa-euro-sign"></i>
                            <div>
                                <h4>Доступные цены</h4>
                                <p>Конкурентные цены и гибкие планы оплаты для всех категорий студентов</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="about-image">
                    <img src="assets/images/about-us.jpg" alt="О нас">
                </div>
            </div>
        </div>
    </section>


    <!-- Инструктора -->
    <section id="instructors" class="instructors">
        <div class="container">
            <h2 class="section-title">Инструкторы</h2>
            <div class="instructors-grid">
                <?php while($instructor = mysqli_fetch_assoc($instructors_result)): ?>
                    <div class="instructor-card">
                        <div class="instructor-photo">
                            <img src="assets/images/<?php echo htmlspecialchars($instructor['photo']); ?>" alt="<?php echo htmlspecialchars($instructor['name']); ?>">
                        </div>
                        <div class="instructor-info">
                            <h3><?php echo htmlspecialchars($instructor['name']); ?></h3>
                            <p><strong>Специализация:</strong> <?php echo htmlspecialchars($instructor['specialization']); ?></p>
                            <p><strong>Опыт:</strong> <?php echo (int)$instructor['experience']; ?> лет</p>
                            <p><strong>Телефон:</strong> <?php echo htmlspecialchars($instructor['phone']); ?></p>
                            <p><strong>Место занятий:</strong> <?php echo htmlspecialchars($instructor['location']); ?></p>
                            <p><strong>Время работы:<br></strong> <?php echo nl2br(htmlspecialchars($instructor['working_hours'])); ?></p>
                            <p><strong>Автомобиль:<br></strong> <?php echo nl2br(htmlspecialchars($instructor['car_info'])); ?></p>
                            <p><strong>Описание: <br></strong><?php echo nl2br(htmlspecialchars($instructor['description'])); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Отзывы -->
    <section id="reviews" class="reviews">
        <div class="container">
            <h2 class="section-title">Отзывы наших учеников</h2>
            <div class="reviews-grid">
                <?php while($review = mysqli_fetch_assoc($reviews_result)): ?>
                <div class="review-card">
                    <div class="review-rating">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'active' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p>"<?php echo $review['content']; ?>"</p>
                    <div class="review-author">
                        <div class="author-info">
                            <h4><?php echo $review['author_name']; ?></h4>
                            <span><?php echo date('d.m.Y', strtotime($review['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Контакты -->
    <section id="contact" class="contact">
        <div class="container">
            <h2 class="section-title">Контакты</h2>
            <div class="contact-content">
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h4>Адрес</h4>
                            <p>Haabersti, Таллинн, Эстония<br>Рядом с Kadaka Selver</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <h4>Телефон</h4>
                            <p>+372 5XXX XXXX</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <h4>Email</h4>
                            <p>info@haaberstiautokool.ee</p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h4>Время работы</h4>
                            <p>Пн-Пт: 8:30 - 20:00<br>Сб: 9:00 - 17:00</p>
                        </div>
                    </div>
                </div>
                <div class="contact-form">
                    <form action="process_contact.php" method="POST">
                        <div class="form-group">
                            <input type="text" name="name" placeholder="Ваше имя" required>
                        </div>
                        <div class="form-group">
                            <input type="email" name="email" placeholder="Email" required>
                        </div>
                        <div class="form-group">
                            <input type="tel" name="phone" placeholder="Телефон" required>
                        </div>
                        <div class="form-group">
                            <textarea name="message" placeholder="Сообщение" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full">Отправить сообщение</button>
                    </form>
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
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>Быстрые ссылки</h4>
                    <ul>
                        <li><a href="#courses">Курсы</a></li>
                        <li><a href="#about">О нас</a></li>
                        <li><a href="#instructors">Инструкторы</a></li>
                        <li><a href="booking.php">Записаться</a></li>
                    </ul>
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
                <p>&copy; 2025 Haabersti Autokool. Все права защищены.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>

