<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) || isset($_SESSION['member_id'])) {
    header('Location: index.php');
    exit;
}

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Properly capture and sanitize form inputs
    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = trim($_POST['password'] ?? '');

    if (!empty($usernameInput) && !empty($passwordInput)) {
        $db = getDBConnection();
        if ($db) {
            try {
                // 2. Query team_members with unique parameter names to avoid PDO HY093 duplicate parameter issues
                $stmt = $db->prepare("SELECT * FROM team_members 
                                      WHERE LOWER(username) = LOWER(:uname) 
                                         OR LOWER(email) = LOWER(:email) 
                                      LIMIT 1");
                $stmt->execute([
                    'uname' => $usernameInput,
                    'email' => $usernameInput
                ]);
                $member = $stmt->fetch();

                // 3. Verify Password against BCRYPT Hash
                if ($member && !empty($member['password']) && password_verify($passwordInput, $member['password'])) {
                    // Set session variables
                    $_SESSION['user_id']     = $member['id']; // Legacy compatibility
                    $_SESSION['member_id']   = $member['id'];
                    $_SESSION['full_name']   = $member['full_name'];
                    $_SESSION['system_role'] = strtolower($member['system_role'] ?? 'user');

                    header('Location: index.php');
                    exit;
                } else {
                    $errorMsg = 'Invalid username/email or password.';
                }
            } catch (PDOException $e) {
                $errorMsg = 'Database error occurred. Please try again later.';
            }
        } else {
            $errorMsg = 'Unable to connect to the database.';
        }
    } else {
        $errorMsg = 'Please enter both username/email and password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <title>Login - ProjectPulse Tracker</title>
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body class="theme-dark d-flex flex-column min-vh-100 justify-content-center py-4" style="background-color: #0b1329;">
  <div class="container container-tight py-4">
    
    <div class="text-center mb-4">
      <a href="#" class="navbar-brand navbar-brand-autodark d-inline-flex align-items-center gap-2 text-decoration-none">
        <span class="avatar bg-primary text-white rounded shadow">
          <i class="ti ti-activity fs-1"></i>
        </span>
        <span class="fs-1 fw-bold text-white">ProjectPulse <small class="fs-4 text-indigo-lt fw-normal ms-1">Tracker</small></span>
      </a>
    </div>

    <div class="card card-md border-0 shadow-lg" style="background-color: #0f172a;">
      <div class="card-body">
        <h2 class="h2 text-center mb-4 text-white">Sign in to your account</h2>

        <?php if (!empty($errorMsg)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
              <i class="ti ti-alert-circle fs-3 me-2"></i>
              <div><?= htmlspecialchars($errorMsg) ?></div>
            </div>
          </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="off">
          <div class="mb-3">
            <label class="form-label text-secondary required">Username or Email Address</label>
            <div class="input-icon">
              <span class="input-icon-addon">
                <i class="ti ti-user"></i>
              </span>
              <input type="text" name="username" class="form-control" placeholder="Enter username or email" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label text-secondary required">Password</label>
            <div class="input-icon">
              <span class="input-icon-addon">
                <i class="ti ti-lock"></i>
              </span>
              <input type="password" name="password" class="form-control" placeholder="Enter password" required>
            </div>
          </div>

          <div class="form-footer mt-4">
            <button type="submit" class="btn btn-primary w-100 shadow-sm py-2">
              <i class="ti ti-login me-1"></i> Sign In
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="text-center text-muted small mt-3">
      ProjectPulse Executive Tracker &copy; <?= date('Y') ?>
    </div>

  </div>
</body>
</html>