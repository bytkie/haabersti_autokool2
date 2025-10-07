<?php
session_start();
include 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $message = mysqli_real_escape_string($conn, trim($_POST['message']));
    
    // Простая валидация
    if (empty($name) || empty($email) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Заполните все обязательные поля']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Некорректный email адрес']);
        exit;
    }
    
    // Вставляем сообщение в базу данных
    $insert_query = "INSERT INTO contact_messages (name, email, phone, message, created_at) 
                     VALUES ('$name', '$email', '$phone', '$message', NOW())";
    
    if (mysqli_query($conn, $insert_query)) {
        // Отправляем email администратору (опционально)
        $admin_email = 'info@haaberstiautokool.ee';
        $subject = 'Новое сообщение с сайта Haabersti Autokool';
        $email_message = "Имя: $name\\nEmail: $email\\nТелефон: $phone\\n\\nСообщение:\\n$message";
        
        // Можно добавить отправку email здесь
        // mail($admin_email, $subject, $email_message);
        
        echo json_encode(['success' => true, 'message' => 'Сообщение успешно отправлено']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при отправке сообщения']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
}
?>