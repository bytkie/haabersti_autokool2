<?php
// Конфигурация подключения к базе данных
$db_host = 'localhost';         // Адрес сервера MySQL
$db_username = 'root';          // Имя пользователя MySQL
$db_password = '';              // Пароль пользователя MySQL
$db_name = 'haabersti_autokool'; // Имя базы данных

// Создаем подключение
$conn = mysqli_connect($db_host, $db_username, $db_password, $db_name);

// Проверяем подключение
if (!$conn) {
    die("Ошибка подключения: " . mysqli_connect_error());
}

// Устанавливаем кодировку
mysqli_set_charset($conn, "utf8");
?>