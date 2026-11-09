<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php $nav_user = $_SESSION['ecotrip_user'] ?? null; ?>
<?php $page_assets = is_array($page_assets ?? null) ? $page_assets : []; ?>
<?php
// Cache-bust static assets by file modification time so design updates show immediately.
$asset_version = static function (string $relative): string {
  $file = dirname(__DIR__, 3) . '/public/' . $relative;
  return is_file($file) ? (string) filemtime($file) : '1';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f3ede2">
<title><?= htmlspecialchars($title ?? 'EcoTrip') ?> | EcoTrip</title>
<script>
  /* Motion is progressive: content only starts hidden while the animation script is alive. */
  document.documentElement.classList.add('js');
  setTimeout(function () { if (!window.__ecoMotionReady) document.documentElement.classList.remove('js'); }, 3500);
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400;1,6..72,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('assets/style.css') ?>?v=<?= $asset_version('assets/style.css') ?>">
<?php if (($title ?? '') === 'Low-Carbon Routes'): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<?php endif; ?>
<?php foreach (($page_assets['stylesheets'] ?? []) as $stylesheet): ?>
  <?php if (is_array($stylesheet) && is_string($stylesheet['href'] ?? null)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($stylesheet['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?php if (is_string($stylesheet['integrity'] ?? null)): ?> integrity="<?= htmlspecialchars($stylesheet['integrity'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?php endif; ?><?php if (is_string($stylesheet['crossorigin'] ?? null)): ?> crossorigin="<?= htmlspecialchars($stylesheet['crossorigin'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?php endif; ?>>
  <?php endif; ?>
<?php endforeach; ?>
<script src="<?= base_url('assets/motion.js') ?>?v=<?= $asset_version('assets/motion.js') ?>" defer></script>
</head>
<body class="<?= htmlspecialchars((string) ($page_assets['body_class'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<header class="site-header" id="site-header">
  <nav class="site-nav" aria-label="Main navigation">
    <a class="brand" href="<?= site_url('/') ?>" aria-label="EcoTrip home">Eco<span>Trip</span></a>
    <div class="nav-actions">
      <a class="nav-cta" href="<?= site_url('plan') ?>" data-roll>Plan a trip</a>
      <button id="menu-toggle" class="menu-toggle" type="button" aria-controls="site-menu" aria-expanded="false" aria-label="Open navigation">
        <span class="menu-label" aria-hidden="true"><span>Menu</span><span>Close</span></span>
        <span class="menu-icon" aria-hidden="true"><i></i><i></i></span>
      </button>
    </div>
  </nav>
  <div id="site-menu" class="site-menu" aria-hidden="true">
    <div>
      <p class="menu-kicker">Navigate</p>
      <div class="nav-links">
      <?php
        $menu_links = [
          ['plan', 'Plan a Trip'],
          ['assistant', 'Trip Assistant'],
          ['trips', 'My Trips'],
          ['stays', 'Green Stays'],
          ['transport', 'Routes'],
          ['bookings', 'Bookings'],
          ['journal', 'Journal'],
          ['reminders', 'Reminders'],
        ];
        if (is_array($nav_user)) { $menu_links[] = ['notifications', 'Notifications']; }
        $menu_links[] = ['dashboard', 'Dashboard'];
        if (is_array($nav_user)) {
          $menu_links[] = ['account/password', 'Account & password'];
          if (($nav_user['role'] ?? '') === 'admin') { $menu_links[] = ['admin', 'Admin']; }
        }
        foreach ($menu_links as $k => [$path, $label]):
      ?>
        <div class="menu-item" style="--k: <?= (int) $k ?>"><a href="<?= site_url($path) ?>" data-roll><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></div>
      <?php endforeach; ?>
      </div>
    </div>
    <div class="menu-foot">
      <?php if (is_array($nav_user)): ?>
        <form class="menu-logout" method="post" action="<?= site_url('logout') ?>">
          <?php csrf_field(); ?><button class="nav-login" type="submit">Sign out · <?= htmlspecialchars($nav_user['name'] ?? 'Account') ?></button>
        </form>
      <?php else: ?>
        <a class="nav-login" href="<?= site_url('login') ?>">Log in</a>
        <a class="nav-register" href="<?= site_url('register') ?>">Create account</a>
      <?php endif; ?>
      <small>Sustainable travel, planned with care.</small>
    </div>
  </div>
</header>
<main id="main">
