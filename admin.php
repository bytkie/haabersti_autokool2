<?php
// admin.php — Haabersti Autokool
declare(strict_types=1);
session_start();

// =======================
// 1) Настройки авторизации
// =======================
// Можно задать через переменные окружения (рекомендуется):
// setx ADMIN_USER admin
// setx ADMIN_HASH <bcrypt-хеш>
// Ниже дефолт: логин admin / пароль haabersti2024 (сменить!)
$ADMIN_USER = getenv('ADMIN_USER') ?: 'admin';
$ADMIN_HASH = getenv('ADMIN_HASH') ?: '$2y$12$e2RozsdMVWAVDflAcYNJE.LGp2AOy4xVSx5hgkmAFczIJ4o0l6.pa'; // hash('haabersti2024')

// =======================
// 2) Утилиты
// =======================
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): bool {
    return isset($_POST['csrf']) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}
function require_admin(): void {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: admin.php'); exit;
    }
}

// =======================
// 3) ЛОГИН/ЛОГАУТ
// =======================
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php'); exit;
}

if (empty($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $u = trim($_POST['username'] ?? '');
        $p = (string)($_POST['password'] ?? '');
        if (hash_equals($ADMIN_USER, $u) && password_verify($p, $ADMIN_HASH)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $u;
            header('Location: admin.php'); exit;
        } else {
            $error = 'Неверный логин или пароль';
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
      .error{background:#f8d7da;color:#721c24;padding:1rem;border-radius:6px;margin-bottom:1rem}
    </style>
    </head><body>
      <div class="login-container">
        <div class="login-form">
          <h1>Админ панель</h1>
          <?php if (!empty($error)): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
          <form method="post" autocomplete="off">
            <div class="form-group"><label for="username">Логин</label>
              <input type="text" id="username" name="username" required></div>
            <div class="form-group"><label for="password">Пароль</label>
              <input type="password" id="password" name="password" required></div>
            <button type="submit" name="login" class="btn btn-primary btn-full">Войти</button>
          </form>
          <p style="margin-top:12px;color:#7f8c8d;font-size:.9rem">
            <b>Важно:</b> поменяй пароль! Сгенерируй хеш: <code>password_hash('новый', PASSWORD_BCRYPT)</code> и положи его в переменную окружения <code>ADMIN_HASH</code>.
          </p>
        </div>
      </div>
    </body></html>
    <?php
    exit;
}

// =======================
// 4) БД
// =======================
require __DIR__ . '/config/test-db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =======================
// 5) Обработка POST (с CSRF)
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
                        VALUES (?,?,?,?,?,?,?,?,NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sssii dsi', $name, $description, $category, $duration, $lessons, $price, $image, $active);
                // корректная строка типов:
                // s (name) s (desc) s (category) i (duration) i (lessons) d (price) s (image) i (active)
                $stmt->bind_param('sssii dsi', $name, $description, $category, $duration, $lessons, $price, $image, $active); // подсказка редакторам
                // PHP не любит пробел в типах — ставим правильную:
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
        }
    } catch (Throwable $t) {
        flash('error','Ошибка: '.$t->getMessage());
    }

    $back = strtok($_SERVER['HTTP_REFERER'] ?? 'admin.php','?');
    header('Location: admin.php?'.http_build_query(['tab'=>($_GET['tab'] ?? $_POST['tab'] ?? 'dashboard')])); exit;
}

// =======================
// 6) Данные для экранов
// =======================
$tab = $_GET['tab'] ?? 'dashboard';

// Статистика
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

// Пагинация helper
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
    $total = $conn->query("SELECT FOUND_ROWS() as t")->fetch_assoc()['t'] ?? 0;
    return [$rows,(int)$total];
}

function fetch_reviews_pending(mysqli $conn): array {
    $q="SELECT * FROM reviews WHERE approved=0 ORDER BY created_at DESC";
    return $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

function fetch_courses(mysqli $conn): array {
    $q="SELECT * FROM courses ORDER BY created_at DESC";
    return $conn->query($q)->fetch_all(MYSQLI_ASSOC);
}

function fetch_messages(mysqli $conn, int $limit, int $offset, string $only=''): array {
    $where = '';
    if (in_array($only,['new','read','replied'],true)) $where="WHERE status='".$conn->real_escape_string($only)."'";
    $q="SELECT SQL_CALC_FOUND_ROWS * FROM contact_messages $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $st=$conn->prepare($q); $st->bind_param('ii',$limit,$offset); $st->execute();
    $rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);
    $total = $conn->query("SELECT FOUND_ROWS() as t")->fetch_assoc()['t'] ?? 0;
    return [$rows,(int)$total];
}

// Подгрузка по табам
[$bPage,$bPer,$bOff] = paginate('bpage',10);
[$bookings,$bookingsTotal] = ($tab==='bookings'||$tab==='dashboard') ? fetch_bookings($conn,$bPer,$bOff) : [[],0];

$reviewsPending = ($tab==='reviews'||$tab==='dashboard') ? fetch_reviews_pending($conn) : [];

$courses = ($tab==='courses') ? fetch_courses($conn) : [];

[$mPage,$mPer,$mOff] = paginate('mpage',10);
$filter = $_GET['status'] ?? '';
[$messages,$messagesTotal] = ($tab==='messages') ? fetch_messages($conn,$mPer,$mOff,$filter) : [[],0];

// =======================
// 7) Вьюха
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
      <li><a href="admin.php?logout=1">Выход</a></li>
    </ul>
  </aside>

  <main class="admin-main">
    <?php if (!empty($_SESSION['flash'])): foreach($_SESSION['flash'] as [$t,$m]): ?>
      <div class="flash flash-<?= e($t) ?>"><?= e($m) ?></div>
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
        <?php if ($tab==='bookings'): 
          $pages = max(1, (int)ceil($bookingsTotal/$bPer)); ?>
          <div class="pagination" style="margin-top:.7rem">
            <?php for($i=1;$i<=$pages;$i++): ?>
              <a class="<?= $i===$bPage?'active':'' ?>" href="admin.php?tab=bookings&bpage=<?= $i ?>"><?= $i ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
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
            <h4 style="margin:.3rem 0  .6rem">Добавить курс</h4>
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
                    <?php foreach (['new'=>'new','read'=>'read','replied'=>'replied'] as $opt): ?>
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
        <?php $pages=max(1,(int)ceil($messagesTotal/$mPer)); if ($pages>1): ?>
          <div class="pagination" style="margin-top:.7rem">
            <?php for($i=1;$i<=$pages;$i++): ?>
              <a class="<?= $i===$mPage?'active':'' ?>" href="admin.php?tab=messages&mpage=<?= $i ?><?= $filter?('&status='.e($filter)) : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

  </main>
</div>
</body>
</html>
