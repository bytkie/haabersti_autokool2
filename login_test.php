<?php
ini_set('display_errors', '1'); error_reporting();
session_start();
require __DIR__.'/config/database.php';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $u = trim($_POST['u'] ?? '');
  $p = (string)($_POST['p'] ?? '');
  $st = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username=?");
  $st->bind_param('s',$u);
  $st->execute();
  if ($row = $st->get_result()->fetch_assoc()) {
    if (password_verify($p, $row['password_hash'])) {
      echo "OK: вошли как ".$row['username']; exit;
    }
  }
  echo "FAIL: неверно";
}
?>
<form method="post">
  <input name="u" placeholder="username">
  <input name="p" placeholder="password" type="password">
  <button>Go</button>
</form>
