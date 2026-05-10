<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

admin_session_start();

if (is_admin_logged_in()) {
    header('Location: ' . admin_url('index.php'));
    exit;
}

$to = isset($_GET['to']) ? (string) $_GET['to'] : admin_url('index.php');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prefer POSTed redirect target, fallback to query-string.
    $to = isset($_POST['to']) ? (string) $_POST['to'] : $to;

    $postedUser = trim((string) ($_POST['username'] ?? ''));
    $postedPass = (string) ($_POST['password'] ?? '');

    if ($postedUser === '' || $postedPass === '') {
        $error = 'Please enter username and password.';
    } elseif (attempt_admin_login($postedUser, $postedPass)) {
        admin_login($postedUser);
        header('Location: ' . $to);
        exit;
    } else {
        if (!admin_credentials_are_configured() && admin_user_count() === 0) {
            $error = 'No admin user is configured yet. Import admin_users.sql into the fineaxa database and insert a user with a PHP password hash, or set ADMIN_USER and ADMIN_PASS_HASH in includes/config.local.php.';
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin Login</title>
  <link rel="stylesheet" href="admin.css" />
</head>
<body>
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="auth-brand">
        <img src="../logo.png" alt="Finexa Solution" style="width: auto; height: 70px; object-fit: contain;" />
       </div>

      <div class="auth-title">Login</div>

      <?php if ($error !== ''): ?>
        <div class="alert error" style="margin:12px 0;"><?= e($error) ?></div>
      <?php endif; ?>

      <form class="auth-form" method="post" action="">
        <input type="hidden" name="to" value="<?= e($to) ?>" />

        <div class="field">
          <label for="username">Email / Username</label>
          <input class="input" id="username" name="username" type="text" autocomplete="username" placeholder="admin@example.com" required />
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="input-group">
            <input class="input" id="password" name="password" type="password" autocomplete="current-password" placeholder="••••••••" required />
            <button class="toggle-pass" type="button" aria-label="Show password" data-target="password">
              <span class="muted">👁</span>
            </button>
          </div>
        </div>

        <div class="auth-row">
          <label class="check">
            <input type="checkbox" name="remember" value="1" />
            <span>Remember me</span>
          </label>
          <a class="muted" href="<?= e(site_url()) ?>">Back to site</a>
        </div>

        <button class="btn auth" type="submit">Login</button>
      </form>
    </div>
  </div>

  <script>
    (function () {
      const btn = document.querySelector('.toggle-pass');
      if (!btn) return;
      btn.addEventListener('click', function () {
        const id = btn.getAttribute('data-target');
        const input = document.getElementById(id);
        if (!input) return;
        const isPass = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPass ? 'text' : 'password');
        btn.setAttribute('aria-label', isPass ? 'Hide password' : 'Show password');
      });
    })();
  </script>
</body>
</html>

