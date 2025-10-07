<?php
$conn = mysqli_connect('127.0.0.1', 'root', '', 'haabersti_autokool');
if (!$conn) {
    die("Ошибка: " . mysqli_connect_error());
}
echo "✅ Подключение успешно!";
mysqli_close($conn);
