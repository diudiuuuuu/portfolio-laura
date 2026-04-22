<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (isAdmin()) {
    header('Location: /admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: /admin.php');
        exit;
    }
    $error = '用户名或密码错误';
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login</title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
  <div class="admin-wrap" style="max-width:420px;margin-top:8vh;">
    <h1>管理后台登录</h1>
    <?php if ($error !== ''): ?><div class="flash" style="background:#420f0f;border-color:#8b2b2b;color:#ffd5d5;"><?= esc($error) ?></div><?php endif; ?>
    <form method="post">
      <div style="margin-bottom:10px;">
        <label>用户名</label>
        <input type="text" name="username" required>
      </div>
      <div style="margin-bottom:10px;">
        <label>密码</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit">登录</button>
    </form>
    <p style="margin-top:14px;color:#aaa;font-size:12px;">默认账号：admin / admin123456</p>
  </div>
</body>
</html>
