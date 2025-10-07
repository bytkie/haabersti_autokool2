<?php
ini_set('display_errors', '1'); error_reporting(E_ALL);
require __DIR__.'/config/database.php'; // тот самый файл, где $conn = mysqli_connect(...)

if (!$conn) { die('Нет подключения: '.mysqli_connect_error()); }
echo "OK: подключено к MySQL\n";

$r = mysqli_query($conn, "SELECT 1 AS ok");
var_dump(mysqli_fetch_assoc($r));
