<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_admin_auth();

$pdo = db();

// KPI queries
$total = (int) $pdo->query("SELECT COUNT(*) AS c FROM contact_submissions")->fetch()['c'];
$today = (int) $pdo->query("SELECT COUNT(*) AS c FROM contact_submissions WHERE DATE(created_at) = CURDATE()")->fetch()['c'];
$last7 = (int) $pdo->query("SELECT COUNT(*) AS c FROM contact_submissions WHERE created_at >= (NOW() - INTERVAL 7 DAY)")->fetch()['c'];

$recentStmt = $pdo->query("
    SELECT id, name, email, phone, service, message, ip_address, user_agent, created_at
    FROM contact_submissions
    ORDER BY id DESC
    LIMIT 6
");
$recent = $recentStmt->fetchAll();

$user = isset($_SESSION['admin_username']) ? (string) $_SESSION['admin_username'] : 'admin';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="admin.css" />
</head>
<body>
  <div class="admin-shell">
    <?php $active = 'dashboard'; $theme = 'teal'; $logoH = 220; require __DIR__ . '/_sidebar.php'; ?>

    <div class="overlay" data-sidebar-close></div>

    <main class="content">
    <div class="mobile-topbar">
      <button class="menu-btn" type="button" data-sidebar-toggle aria-label="Open menu">☰</button>
      <div class="pill">Dashboard</div>
    </div>
    <div class="grid">
      <div class="card">
        <h2>Overview</h2>
        <div class="kpis">
          <div class="kpi">
            <div class="label">Total leads</div>
            <div class="value"><?= e((string) $total) ?></div>
          </div>
          <div class="kpi">
            <div class="label">Today</div>
            <div class="value"><?= e((string) $today) ?></div>
          </div>
          <div class="kpi">
            <div class="label">Last 7 days</div>
            <div class="value"><?= e((string) $last7) ?></div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="row" style="justify-content: space-between;">
          <h2 style="margin:0;">Recent leads</h2>
          <a class="btn primary" href="<?= e(admin_url('leads.php')) ?>">View all</a>
        </div>
        <div class="spacer"></div>

        <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Service</th>
              <th>Message</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$recent): ?>
              <tr><td colspan="5" class="muted">No leads yet.</td></tr>
            <?php else: ?>
              <?php foreach ($recent as $row): ?>
                <tr>
                  <td><?= e((string) $row['name']) ?></td>
                  <td><a href="mailto:<?= e((string) $row['email']) ?>"><?= e((string) $row['email']) ?></a></td>
                  <td><?= e((string) $row['phone']) ?></td>
                  <td><?= e((string) $row['service']) ?></td>
                  <td title="<?= e((string) $row['message']) ?>"><?= e(trim_ellipsis((string) $row['message'], 140)) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
    </main>
  </div>

  <script>
    (function () {
      const sidebar = document.querySelector('.sidebar');
      const overlay = document.querySelector('.overlay');
      const toggle = document.querySelector('[data-sidebar-toggle]');
      if (!sidebar || !overlay || !toggle) return;

      function open() {
        sidebar.classList.add('open');
        document.body.classList.add('sidebar-open');
      }
      function close() {
        sidebar.classList.remove('open');
        document.body.classList.remove('sidebar-open');
      }

      toggle.addEventListener('click', open);
      overlay.addEventListener('click', close);
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
      });
    })();
  </script>
</body>
</html>

