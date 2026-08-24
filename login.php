<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <title>Login - ProjectPulse</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body class="d-flex flex-column theme-dark">
  <div class="page page-center">
    <div class="container container-tight py-4">
      <div class="text-center mb-4">
        <h1 class="navbar-brand navbar-brand-autodark"><i class="ti ti-activity text-primary me-2"></i>ProjectPulse</h1>
      </div>
      
      <form class="card card-md" action="login.php" method="POST" autocomplete="off">
        <div class="card-body">
          <h2 class="h2 text-center mb-4">Sign in to your account</h2>
          
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2" role="alert"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="e.g. niro" required autofocus>
          </div>
          <div class="mb-2">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
          </div>
          <div class="form-footer">
            <button type="submit" class="btn btn-primary w-100">Sign in</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</body>
</html>