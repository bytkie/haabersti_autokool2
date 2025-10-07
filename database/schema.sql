-- Создание базы данных
CREATE DATABASE IF NOT EXISTS haabersti_autokool CHARACTER SET utf8 COLLATE utf8_general_ci;
USE haabersti_autokool;

-- Таблица курсов
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100) NOT NULL,
    duration INT NOT NULL COMMENT 'Продолжительность в часах',
    lessons INT NOT NULL COMMENT 'Количество уроков',
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255) DEFAULT 'default-course.jpg',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Таблица инструкторов
CREATE TABLE instructors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    specialization VARCHAR(255) NOT NULL,
    experience INT NOT NULL COMMENT 'Опыт в годах',
    phone VARCHAR(50),
    email VARCHAR(255),
    photo VARCHAR(255) DEFAULT 'default-instructor.jpg',
    description TEXT,
    rating DECIMAL(3,2) DEFAULT 5.00,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Таблица заявок на обучение
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    course_id INT,
    instructor_id INT NULL,
    preferred_date DATE NULL,
    message TEXT,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE SET NULL
);

-- Таблица отзывов
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    content TEXT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    course_id INT NULL,
    instructor_id INT NULL,
    approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE SET NULL
);

-- Таблица контактных сообщений
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    subject VARCHAR(255),
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Вставка тестовых данных для курсов
INSERT INTO courses (name, description, category, duration, lessons, price, image) VALUES
('Базовый курс категории B', 'Полный курс обучения вождению для получения прав категории B. Включает теорию и практику.', 'Категория B', 40, 30, 450.00, 'course-basic-b.jpg'),
('Интенсивный курс категории B', 'Ускоренный курс для тех, кто хочет быстро получить права. Обучение за 3–4 недели.', 'Категория B', 35, 25, 520.00, 'course-intensive-b.jpg'),
('Курс для начинающих', 'Специальный курс для тех, кто впервые садится за руль. Максимум внимания и терпения.', 'Категория B', 45, 35, 480.00, 'course-beginner.jpg'),
('Курс восстановления навыков', 'Для тех, кто давно не водил и хочет восстановить навыки вождения.', 'Дополнительно', 20, 15, 280.00, 'course-refresh.jpg'),
('Зимнее вождение', 'Специальный курс по вождению в зимних условиях и экстремальных ситуациях.', 'Дополнительно', 10, 8, 150.00, 'course-winter.jpg');

-- Вставка данных об инструкторах
INSERT INTO instructors (name, specialization, experience, phone, email, description) VALUES
('Андрей Петров', 'Категория B, зимнее вождение', 12, '+372 5XXX-XXXX', 'andrey@haaberstiautokool.ee', 'Опытный инструктор с терпением и индивидуальным подходом к каждому ученику.'),
('Мария Иванова', 'Категория B, женский инструктор', 8, '+372 5XXX-XXXY', 'maria@haaberstiautokool.ee', 'Специализируется на обучении женщин и начинающих водителей.'),
('Дмитрий Козлов', 'Категория B, интенсивные курсы', 15, '+372 5XXX-XXXZ', 'dmitry@haaberstiautokool.ee', 'Эксперт по быстрому обучению и подготовке к экзаменам.'),
('Елена Сидорова', 'Категория B, восстановление навыков', 10, '+372 5XXX-XXXA', 'elena@haaberstiautokool.ee', 'Помогает восстановить уверенность за рулем после длительного перерыва.');

-- Вставка тестовых отзывов
INSERT INTO reviews (author_name, content, rating, approved, created_at) VALUES
('Анна К.', 'Отличная автошкола! Инструктор Андрей очень терпеливый и профессиональный. Сдала экзамен с первого раза!', 5, TRUE, '2024-09-15 10:30:00'),
('Максим П.', 'Проходил интенсивный курс у Дмитрия. Все очень четко и по делу. Рекомендую!', 5, TRUE, '2024-09-20 14:15:00'),
('Ольга М.', 'Мария - замечательный инструктор! Очень спокойная и объясняет все понятно. Спасибо за терпение!', 5, TRUE, '2024-09-25 16:45:00'),
('Владимир С.', 'Восстанавливал навыки после 5-летнего перерыва. Елена помогла быстро вернуть уверенность за рулем.', 4, TRUE, '2024-10-01 11:20:00'),
('Татьяна Л.', 'Очень довольна обучением. Современные автомобили, удобное расписание. Всем рекомендую!', 5, TRUE, '2024-10-03 13:10:00');

-- Создание индексов для оптимизации
CREATE INDEX idx_courses_active ON courses(active);
CREATE INDEX idx_instructors_active ON instructors(active);
CREATE INDEX idx_bookings_status ON bookings(status);
CREATE INDEX idx_bookings_created ON bookings(created_at);
CREATE INDEX idx_reviews_approved ON reviews(approved);
CREATE INDEX idx_contact_status ON contact_messages(status);
