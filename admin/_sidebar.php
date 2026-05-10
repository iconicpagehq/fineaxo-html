<?php
declare(strict_types=1);

/**
 * Sidebar "component"
 *
 * Expected variables (set before include):
 * - $active  : 'dashboard' | 'leads' (optional)
 * - $theme   : 'teal' | 'dark' | 'light' (optional)
 * - $logoH   : int (optional) (max height in px)
 */

$active = isset($active) ? (string) $active : '';
$theme = isset($theme) ? (string) $theme : 'teal';
$logoH = isset($logoH) ? (int) $logoH : 180;

$sidebarClass = 'sidebar';
$sidebarClass .= ' sidebar--' . preg_replace('/[^a-z0-9_-]/i', '', $theme);

function nav_active(string $key, string $active): string
{
    return $key === $active ? 'active' : '';
}
?>

<aside class="<?= e($sidebarClass) ?>">
  <div class="brand">
    <img src="../logo.png" alt="Finexa Solution" style="max-height: <?= e((string) $logoH) ?>px;" />
  </div>

  <nav class="side-nav">
    <a class="<?= e(nav_active('dashboard', $active)) ?>" href="<?= e(admin_url('index.php')) ?>">Dashboard</a>
    <a class="<?= e(nav_active('leads', $active)) ?>" href="<?= e(admin_url('leads.php')) ?>">Leads</a>
    <a href="<?= e(site_url()) ?>" class="muted">Website</a>
  </nav>

  <div class="spacer-grow"></div>
  <a class="btn danger" href="<?= e(admin_url('logout.php')) ?>">Logout</a>
</aside>

