<?php
require_once __DIR__ . '/../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['login'] ?? '') === ADMIN_LOGIN && ($_POST['password'] ?? '') === ADMIN_PASSWORD) {
        $_SESSION['scw_admin'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Неверный логин или пароль';
}

if (!empty($_SESSION['scw_admin'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вход — SmartCall Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .login-card { background: #1e293b; border-radius: 20px; padding: 40px 36px; width: 360px; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
  .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; }
  .logo-dot { width: 36px; height: 36px; background: #25c16f; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; font-size: 18px; }
  .logo-text { font-size: 20px; font-weight: 700; color: #fff; }
  .form-label { color: #94a3b8; font-size: 13px; margin-bottom: 5px; }
  .form-control { background: #0f172a; border: 1px solid #334155; color: #fff; border-radius: 10px; height: 44px; }
  .form-control:focus { background: #0f172a; border-color: #25c16f; color: #fff; box-shadow: 0 0 0 3px rgba(37,193,111,.2); }
  .btn-login { background: #25c16f; border: none; border-radius: 10px; height: 46px; font-weight: 600; font-size: 15px; width: 100%; transition: filter .2s; }
  .btn-login:hover { filter: brightness(1.1); }
</style>
</head>
<body>
<div class="login-card">
  <div class="logo">
    <div class="logo-dot">S</div>
    <div class="logo-text">SmartCall</div>
  </div>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 px-3 mb-3" style="background:#7f1d1d;border:none;border-radius:10px;color:#fca5a5;font-size:13px;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <div class="mb-3">
      <div class="form-label">Логин</div>
      <input type="text" name="login" class="form-control" placeholder="admin" autofocus>
    </div>
    <div class="mb-4">
      <div class="form-label">Пароль</div>
      <input type="password" name="password" class="form-control" placeholder="••••••••">
    </div>
    <button class="btn btn-login text-white">Войти</button>
  </form>
</div>
</body>
</html>
