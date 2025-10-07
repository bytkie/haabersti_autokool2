<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
session_start();


// =======================
// 0) Утилиты
// =======================
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

// =======================
// 1) Подключение БД
// =======================
require __DIR__ . '/config/database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


// --- bootstrap: если нет ни одного админа — создаём дефолтного
$adminsCount = (int)$conn->query("SELECT COUNT(*) AS c FROM admins")->fetch_assoc()['c'];
if ($adminsCount === 0) {
    $defaultUser = 'admin';
    $defaultPass = 'haabersti2024';
    $hash = password_hash($defaultPass, PASSWORD_DEFAULT);
    $st = $conn->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
    $st->bind_param('ss', $defaultUser, $hash);
    $st->execute();
    $_SESSION['bootstrap_notice'] = "Создан администратор: <b>admin / haabersti2024</b>...";
}

// =======================
// 2) Логаут
// =======================
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php'); exit;
}

// =======================
// 3) Логин (через таблицу admins)
// =======================
if (empty($_SESSION['admin_logged_in'])) {
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $u = trim($_POST['username'] ?? '');
        $p = (string)($_POST['password'] ?? '');

        // ищем пользователя по username (case-sensitive/insensitive — зависит от collation; можно принудить BINARY, если нужно строго)
        $st = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ?");
        $st->bind_param('s', $u);
        $st->execute();
        $res = $st->get_result();

        if ($row = $res->fetch_assoc()) {
            if (password_verify($p, $row['password_hash'])) {
                // опционально: если алгоритм устарел — пересчитать хеш
                if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($p, PASSWORD_DEFAULT);
                    $st2 = $conn->prepare("UPDATE admins SET password_hash=? WHERE id=?");
                    $st2->bind_param('si', $newHash, $row['id']);
                    $st2->execute();
                }
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username']  = $row['username'];
                header('Location: admin.php'); exit;
            } else {
                $error = 'Неверный логин или пароль';
            }
        } else {
            $error = 'Пользователь не найден';
        }
    }

    // Форма логина
    ?>
    <!doctype html><html lang="ru"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Вход в админ панель - Haabersti Autokool</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
      .login-container{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%)}
      .login-form{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2);width:100%;max-width:420px}
      .login-form h1{text-align:center;margin-bottom:1.2rem;color:#2c3e50}
      .alert{padding:1rem;border-radius:8px;margin-bottom:1rem}
      .alert-danger{background:#f8d7da;color:#721c24}
      .alert-info{background:#d1ecf1;color:#0c5460}
    </style>
    </head><body>
      <div class="login-container">
        <div class="login-form">
          <h1>Админ панель</h1>
          <?php if (!empty($_SESSION['bootstrap_notice'])): ?>
            <div class="alert alert-info"><?= $_SESSION['bootstrap_notice']; unset($_SESSION['bootstrap_notice']); ?></div>
          <?php endif; ?>
          <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
          <form method="post" autocomplete="off">
            <div class="form-group"><label for="username">Логин</label>
              <input type="text" id="username" name="username" required></div>
            <div class="form-group"><label for="password">Пароль</label>
              <input type="password" id="password" name="password" required></div>
            <button type="submit" name="login" class="btn btn-primary btn-full">Войти</button>
          </form>
          
        </div>
      </div>
    </body></html>
    <?php
    exit;
}

// =======================
// 4) Обработка POST (с CSRF) — Бизнес-логика панели
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_check()) { flash('error','Сессия устарела. Обновите страницу.'); header('Location: admin.php'); exit; }

    try {
        switch ($_POST['action']) {
            case 'add_course':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $duration = (int)($_POST['duration'] ?? 0);
                $lessons  = (int)($_POST['lessons'] ?? 0);
                $price    = (float)($_POST['price'] ?? 0);
                $image    = trim($_POST['image'] ?? '');
                $active   = isset($_POST['active']) ? 1 : 0;

                if ($name === '' || $category === '' || $duration<=0 || $lessons<=0 || $price<=0) {
                    flash('error','Заполните обязательные поля корректно.');
                    break;
                }
                $sql = "INSERT INTO courses (name, description, category, duration, lessons, price, image, active, created_at)
                        VALUES (?,?,?,?,?,?,?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                // types: s s s i i d s i
                $stmt->bind_param('sssiidsi', $name, $description, $category, $duration, $lessons, $price, $image, $active);
                $stmt->execute();
                flash('success','Курс успешно добавлен.');
                break;

            case 'update_booking_status':
                $booking_id = (int)($_POST['booking_id'] ?? 0);
                $status = $_POST['status'] ?? 'pending';
                $allowed = ['pending','confirmed','completed','cancelled'];
                if ($booking_id<=0 || !in_array($status,$allowed,true)) { flash('error','Неверные данные.'); break; }
                $stmt = $conn->prepare("UPDATE bookings SET status=? WHERE id=?");
                $stmt->bind_param('si', $status, $booking_id);
                $stmt->execute();
                flash('success','Статус заявки обновлён.');
                break;

            case 'approve_review':
                $review_id = (int)($_POST['review_id'] ?? 0);
                if ($review_id<=0) { flash('error','Неверный отзыв.'); break; }
                $stmt = $conn->prepare("UPDATE reviews SET approved=1 WHERE id=?");
                $stmt->bind_param('i', $review_id);
                $stmt->execute();
                flash('success','Отзыв одобрен.');
                break;

            case 'mark_message':
                $msg_id = (int)($_POST['msg_id'] ?? 0);
                $to     = $_POST['to'] ?? 'read'; // read|replied
                $allowed = ['new','read','replied'];
                if ($msg_id<=0 || !in_array($to,$allowed,true)) { flash('error','Неверные данные.'); break; }
                $stmt = $conn->prepare("UPDATE contact_messages SET status=? WHERE id=?");
                $stmt->bind_param('si', $to, $msg_id);
                $stmt->execute();
                flash('success','Статус сообщения обновлён.');
                break;

            // --- управление администраторами (опционально) ---
            case 'add_admin':
                $u = trim($_POST['new_username'] ?? '');
                $p = (string)($_POST['new_password'] ?? '');
                if ($u==='' || $p==='') { flash('error','Логин и пароль обязательны.'); break; }
                $hash = password_hash($p, PASSWORD_DEFAULT);
                $st = $conn->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
                $st->bind_param('ss', $u, $hash);
                $st->execute();
                flash('success',"Админ «{$u}» добавлен.");
                break;

            case 'change_password':
                $old = (string)($_POST['old_password'] ?? '');
                $new = (string)($_POST['new_password'] ?? '');
                if ($new==='') { flash('error','Новый пароль пуст.'); break; }

                $u = $_SESSION['admin_username'] ?? '';
                $st = $conn->prepare("SELECT id, password_hash FROM admins WHERE username=?");
                $st->bind_param('s',$u); $st->execute();
                $row = $st->get_result()->fetch_assoc();
                if (!$row || !password_verify($old, $row['password_hash'])) { flash('error','Старый пароль неверен.'); break; }

                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $st2 = $conn->prepare("UPDATE admins SET password_hash=? WHERE id=?");
                $st2->bind_param('si',$newHash,$row['id']);
                $st2->execute();
                flash('success','Пароль обновлён.');
                break;
        }
    } catch (Throwable $t) {
        flash('error','Ошибка: '.$t->getMessage());
    }

    $tab = $_GET['tab'] ?? $_POST['tab'] ?? 'dashboard';
    header('Location: admin.php?tab='.$tab); exit;
}

// =======================
// 5) Данные для экранов
// =======================
$tab = $_GET['tab'] ?? 'dashboard';

$stats = [
  'total_courses'    => 0,
  'pending_bookings' => 0,
  'pending_reviews'  => 0,
  'new_messages'     => 0,
];
$res = $conn->query("SELECT 
 (SELECT COUNT(*) FROM courses WHERE active=1) AS total_courses,
 (SELECT COUNT(*) FROM bookings WHERE status='pending') AS pending_bookings,
 (SELECT COUNT(*) FROM reviews WHERE approved=0) AS pending_reviews,
 (SELECT COUNT(*) FROM contact_messages WHERE status='new') AS new_messages
");
if ($res) { $stats = $res->fetch_assoc(); }

function paginate(string $param='page', int $per=10): array {
    $page = max(1, (int)($_GET[$param] ?? 1));
    $offset = ($page-1)*$per;
    return [$page,$per,$offset];
}

function fetch_bookings(mysqli $conn, int $limit, int $offset): array {
    $q = "SELECT SQL_CALC_FOUND_ROWS b.*, c.name AS course_name
          FROM bookings b
          LEFT JOIN courses c ON c.id=b.course_id
          ORDER BY b.created_at DESC
          LIMIT ? OFFSET ?";
    $st = $conn->prepare($q);
    $st->bind_param('ii',$limit,$offset);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $total = (int)($conn->query("SELECT FOUND_ROWS() as t")->fetch_assoc()['t'] ?? 0);
    return [$rows,$total];
}

function fetch_reviews_pending(mysqli $conn): array {
    return $conn->query("SELECT * FROM reviews WHERE approved=0 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
}

function fetch_courses(mysqli $conn): array {
    return $conn->query("SELECT * FROM courses ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
}

function fetch_messages(mysqli $conn, int $limit, int $offset, string $only=''): array {
    $where = '';
    if (in_array($only,['new','read','replied'],true)) {
        $where="WHERE status='".$conn->real_escape_string($only)."'";
    }
    $q="SELECT SQL_CALC_FOUND_ROWS * FROM contact_messages $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $st=$conn->prepare($q); $st->bind_param('ii',$limit,$offset); $st->execute();
    $rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);
    $total = (int)($conn->query("SELECT FOUND_ROWS() as t")->fetch_assoc()['t'] ?? 0);
    return [$rows,$total];
}

[$bPage,$bPer,$bOff] = paginate('bpage',10);
[$bookings,$bookingsTotal] = ($tab==='bookings'||$tab==='dashboard') ? fetch_bookings($conn,$bPer,$bOff) : [[],0];

$reviewsPending = ($tab==='reviews'||$tab==='dashboard') ? fetch_reviews_pending($conn) : [];

$courses = ($tab==='courses') ? fetch_courses($conn) : [];

[$mPage,$mPer,$mOff] = paginate('mpage',10);
$filter = $_GET['status'] ?? '';
[$messages,$messagesTotal] = ($tab==='messages') ? fetch_messages($conn,$mPer,$mOff,$filter) : [[],0];

// =======================
// 6) Вьюха (та же, что была; добавил два блока для управления админами)
// =======================
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Админ панель - Haabersti Autokool</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
  .admin-container{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
  .admin-sidebar{background:#2c3e50;color:#fff;padding:1.5rem}
  .admin-main{padding:1.5rem;background:#f8f9fa}
  .admin-nav{list-style:none;margin:0;padding:0}
  .admin-nav a{display:block;color:#bdc3c7;text-decoration:none;padding:.6rem .8rem;border-radius:8px;margin-bottom:.4rem}
  .admin-nav a:hover,.admin-nav a.active{background:#3498db;color:#fff}
  .stat-card{background:#fff;border-radius:12px;padding:1rem;box-shadow:0 2px 10px rgba(0,0,0,.06);text-align:center}
  .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.2rem}
  .table{width:100%;border-collapse:collapse}
  .table th,.table td{padding:.7rem;border-bottom:1px solid #e9ecef;text-align:left}
  .table th{background:#fff;font-weight:600}
  .admin-section{background:#fff;border-radius:12px;padding:1rem;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:1.2rem}
  .status-pending{background:#fff3cd;color:#856404;padding:.2rem .45rem;border-radius:4px}
  .status-confirmed{background:#d4edda;color:#155724;padding:.2rem .45rem;border-radius:4px}
  .status-completed{background:#d1ecf1;color:#0c5460;padding:.2rem .45rem;border-radius:4px}
  .status-cancelled{background:#f8d7da;color:#721c24;padding:.2rem .45rem;border-radius:4px}
  .flash{padding:.7rem 1rem;border-radius:8px;margin-bottom:.7rem}
  .flash-success{background:#d4edda;color:#155724}
  .flash-error{background:#f8d7da;color:#721c24}
  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
  .muted{color:#6c757d}
  .pagination{display:flex;gap:.4rem}
  .pagination a{padding:.35rem .6rem;border:1px solid #dee2e6;border-radius:6px;text-decoration:none;color:#333;background:#fff}
  .pagination .active{background:#3498db;color:#fff;border-color:#3498db}
</style>
</head>
<body>
<div class="admin-container">
  <aside class="admin-sidebar">
    <h2 style="margin-bottom:.8rem">Админ панель</h2>
    <div class="muted" style="margin-bottom:1rem">Привет, <?= e($_SESSION['admin_username'] ?? 'admin') ?></div>
    <ul class="admin-nav">
      <li><a href="admin.php?tab=dashboard" class="<?= $tab==='dashboard'?'active':'' ?>">Главная</a></li>
      <li><a href="admin.php?tab=bookings" class="<?= $tab==='bookings'?'active':'' ?>">Заявки</a></li>
      <li><a href="admin.php?tab=courses" class="<?= $tab==='courses'?'active':'' ?>">Курсы</a></li>
      <li><a href="admin.php?tab=reviews" class="<?= $tab==='reviews'?'active':'' ?>">Отзывы</a></li>
      <li><a href="admin.php?tab=messages" class="<?= $tab==='messages'?'active':'' ?>">Сообщения</a></li>
      <li><a href="admin.php?tab=admins" class="<?= $tab==='admins'?'active':'' ?>">Администраторы</a></li>
      <li><a href="admin.php?logout=1">Выход</a></li>
    </ul>
  </aside>

  <main class="admin-main">
    <?php if (!empty($_SESSION['flash'])): foreach($_SESSION['flash'] as [$t,$m]): ?>
      <div class="flash flash-<?= e($t) ?>"><?= $m ?></div>
    <?php endforeach; unset($_SESSION['flash']); endif; ?>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-number"><?= (int)$stats['total_courses'] ?></div><div class="stat-label">Активных курсов</div></div>
      <div class="stat-card"><div class="stat-number"><?= (int)$stats['pending_bookings'] ?></div><div class="stat-label">Новых заявок</div></div>
      <div class="stat-card"><div class="stat-number"><?= (int)$stats['pending_reviews'] ?></div><div class="stat-label">Отзывов на модерации</div></div>
      <div class="stat-card"><div class="stat-number"><?= (int)$stats['new_messages'] ?></div><div class="stat-label">Новых сообщений</div></div>
    </div>

    <?php if ($tab==='dashboard' || $tab==='bookings'): ?>
      <section class="admin-section" id="bookings">
        <h3 style="margin-bottom:.6rem">Последние заявки</h3>
        <table class="table">
          <thead><tr>
            <th>Имя</th><th>Email</th><th>Телефон</th><th>Курс</th><th>Статус</th><th>Дата</th><th>Действия</th>
          </tr></thead>
          <tbody>
          <?php foreach ($bookings as $b): ?>
            <tr>
              <td><?= e($b['name']) ?></td>
              <td><?= e($b['email']) ?></td>
              <td><?= e($b['phone']) ?></td>
              <td><?= e($b['course_name'] ?? 'Не указан') ?></td>
              <td><span class="status-<?= e($b['status']) ?>">
                <?php $statuses=['pending'=>'Ожидает','confirmed'=>'Подтверждена','completed'=>'Завершена','cancelled'=>'Отменена']; echo $statuses[$b['status']] ?? e($b['status']); ?>
              </span></td>
              <td><?= e(date('d.m.Y H:i', strtotime($b['created_at']))) ?></td>
              <td>
                <form method="post" style="display:flex;gap:.4rem;align-items:center">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="update_booking_status">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                  <select name="status" onchange="this.form.submit()">
                    <?php foreach (['pending','confirmed','completed','cancelled'] as $s): ?>
                      <option value="<?= $s ?>" <?= $b['status']===$s?'selected':'' ?>>
                        <?= ['pending'=>'Ожидает','confirmed'=>'Подтверждена','completed'=>'Завершена','cancelled'=>'Отменена'][$s] ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>

    <?php if ($tab==='reviews' || $tab==='dashboard'): ?>
      <section class="admin-section" id="reviews">
        <h3 style="margin-bottom:.6rem">Отзывы на модерации</h3>
        <?php if (!$reviewsPending): ?><div class="muted">Нет отзывов в очереди.</div><?php endif; ?>
        <?php foreach ($reviewsPending as $r): ?>
          <div style="border:1px solid #e9ecef;border-radius:8px;padding:1rem;margin-bottom:.7rem">
            <div style="display:flex;justify-content:space-between;gap:1rem">
              <div>
                <h4 style="margin:0 0 .3rem"><?= e($r['author_name']) ?></h4>
                <div class="review-rating" style="margin-bottom:.3rem">
                  <?php for($i=1;$i<=5;$i++): ?>
                    <i class="fas fa-star <?= $i <= (int)$r['rating'] ? 'active':'' ?>" style="color:#f39c12"></i>
                  <?php endfor; ?>
                </div>
                <div style="color:#434955;white-space:pre-line"><?= e($r['content']) ?></div>
                <small class="muted"><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></small>
              </div>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="approve_review">
                <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                <button class="btn btn-primary" type="submit">Одобрить</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($tab==='courses'): ?>
      <section class="admin-section" id="courses">
        <h3 style="margin-bottom:.6rem">Курсы</h3>
        <div class="grid-2">
          <div>
            <table class="table">
              <thead><tr>
                <th>Название</th><th>Категория</th><th>Часы</th><th>Уроки</th><th>Цена</th><th>Активен</th>
              </tr></thead>
              <tbody>
              <?php foreach ($courses as $c): ?>
                <tr>
                  <td><?= e($c['name']) ?></td>
                  <td><?= e($c['category']) ?></td>
                  <td><?= (int)$c['duration'] ?></td>
                  <td><?= (int)$c['lessons'] ?></td>
                  <td><?= number_format((float)$c['price'],2,',',' ') ?> €</td>
                  <td><?= ((int)$c['active']) ? 'Да' : 'Нет' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div>
            <h4 style="margin:.3rem 0 .6rem">Добавить курс</h4>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="add_course">
              <div class="form-group"><label>Название *</label><input name="name" required></div>
              <div class="form-group"><label>Категория *</label><input name="category" required placeholder="Категория B / Дополнительно"></div>
              <div class="form-group"><label>Описание</label><textarea name="description" rows="4"></textarea></div>
              <div class="form-row">
                <div class="form-group"><label>Часы *</label><input type="number" min="1" name="duration" required></div>
                <div class="form-group"><label>Уроки *</label><input type="number" min="1" name="lessons" required></div>
              </div>
              <div class="form-group"><label>Цена, € *</label><input type="number" min="1" step="0.01" name="price" required></div>
              <div class="form-group"><label>Картинка (имя файла)</label><input name="image" placeholder="course-basic-b.jpg"></div>
              <div class="form-group"><label><input type="checkbox" name="active" checked> Активен</label></div>
              <button class="btn btn-primary">Сохранить</button>
            </form>
            <p class="muted" style="margin-top:.5rem">* — обязательные поля</p>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($tab==='messages'): ?>
      <section class="admin-section" id="messages">
        <h3 style="margin-bottom:.6rem">Сообщения</h3>
        <div style="margin-bottom:.6rem">
          Фильтр:
          <?php foreach ([''=>'Все','new'=>'Новые','read'=>'Прочитанные','replied'=>'Отвеченные'] as $k=>$v): ?>
            <a href="admin.php?tab=messages<?= $k!=='' ? '&status='.$k : '' ?>" style="margin-right:.4rem"><?= e($v) ?></a>
          <?php endforeach; ?>
        </div>
        <table class="table">
          <thead><tr><th>Имя</th><th>Email</th><th>Телефон</th><th>Тема</th><th>Сообщение</th><th>Статус</th><th>Дата</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($messages as $m): ?>
            <tr>
              <td><?= e($m['name']) ?></td>
              <td><?= e($m['email']) ?></td>
              <td><?= e($m['phone'] ?? '') ?></td>
              <td><?= e($m['subject'] ?? '') ?></td>
              <td style="max-width:380px;white-space:pre-wrap"><?= e($m['message']) ?></td>
              <td><?= e($m['status']) ?></td>
              <td><?= e(date('d.m.Y H:i', strtotime($m['created_at']))) ?></td>
              <td>
                <form method="post" style="display:flex;gap:.3rem">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="mark_message">
                  <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                  <select name="to">
                    <?php foreach (['new','read','replied'] as $opt): ?>
                      <option value="<?= $opt ?>" <?= $m['status']===$opt?'selected':'' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-primary">OK</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>

    <?php if ($tab==='admins'): ?>
      <section class="admin-section" id="admins">
        <h3 style="margin-bottom:.6rem">Администраторы</h3>
        <div class="grid-2">
          <div>
            <table class="table">
              <thead><tr><th>Логин</th><th>Создан</th></tr></thead>
              <tbody>
              <?php foreach ($conn->query("SELECT username, created_at FROM admins ORDER BY username ASC") as $a): ?>
                <tr><td><?= e($a['username']) ?></td><td><?= e($a['created_at']) ?></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div>
            <h4 style="margin:.3rem 0 .6rem">Добавить администратора</h4>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="add_admin">
              <div class="form-group"><label>Логин *</label><input name="new_username" required></div>
              <div class="form-group"><label>Пароль *</label><input type="text" name="new_password" required></div>
              <button class="btn btn-primary">Добавить</button>
            </form>

            <h4 style="margin:1.2rem 0 .6rem">Сменить свой пароль</h4>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="change_password">
              <div class="form-group"><label>Старый пароль *</label><input type="password" name="old_password" required></div>
              <div class="form-group"><label>Новый пароль *</label><input type="password" name="new_password" required></div>
              <button class="btn btn-primary">Обновить</button>
            </form>
          </div>
        </div>
      </section>
    <?php endif; ?>

  </main>
</div>
</body>
</html>
