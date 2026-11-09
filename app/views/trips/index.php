<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php
$viewData = get_defined_vars();
$trips = is_array($viewData['trips'] ?? null) ? $viewData['trips'] : [];
$q = is_string($viewData['q'] ?? null) ? $viewData['q'] : '';
$flash = is_string($viewData['flash'] ?? null) ? $viewData['flash'] : '';
$flash_error = is_string($viewData['flash_error'] ?? null) ? $viewData['flash_error'] : '';
?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<link rel="stylesheet" href="<?= htmlspecialchars(base_url('assets/ecotrip/ecotrip.css'), ENT_QUOTES, 'UTF-8') ?>">
<h1>My Trips</h1>
<p class="sub">Your saved low-carbon itineraries.</p>
<?php if (!empty($flash)): ?><div class="alert ok"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div id="trips-react-app"
     data-api-root="<?= htmlspecialchars(site_url('api/trips'), ENT_QUOTES, 'UTF-8') ?>"
     data-token-url="<?= htmlspecialchars(site_url('api/session-token'), ENT_QUOTES, 'UTF-8') ?>"
     aria-live="polite"></div>
<div id="trips-legacy-fallback">
<form method="get" action="<?= htmlspecialchars(site_url('trips'), ENT_QUOTES, 'UTF-8') ?>" class="bar">
  <input name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search title or destination">
  <button class="btn">Search</button>
  <a class="btn" href="<?= htmlspecialchars(site_url('trips/create'), ENT_QUOTES, 'UTF-8') ?>">+ New trip</a>
</form>
<?php if (!$trips): ?>
  <div class="todo">No trips found. <a href="<?= htmlspecialchars(site_url('trips/create'), ENT_QUOTES, 'UTF-8') ?>">Plan your first trip</a>.</div>
<?php else: ?>
<div class="card" style="overflow-x:auto"><table>
  <tr><th>Title</th><th>Destination</th><th>Dates</th><th>Budget</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($trips as $t): ?>
  <tr>
    <td><a href="<?= htmlspecialchars(site_url('trips/'.$t['id']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></a></td>
    <td><?= htmlspecialchars($t['destination'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars($t['start_date'], ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($t['end_date'], ENT_QUOTES, 'UTF-8') ?></td>
    <td>₱<?= number_format($t['budget'], 2) ?></td>
    <td><span class="tag"><?= htmlspecialchars($t['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
    <td class="actions">
      <a href="<?= htmlspecialchars(site_url('trips/'.$t['id']), ENT_QUOTES, 'UTF-8') ?>">View</a>
      <a href="<?= htmlspecialchars(site_url('trips/'.$t['id'].'/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
      <form method="post" action="<?= htmlspecialchars(site_url('trips/'.$t['id'].'/delete'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Delete this trip and its itinerary? This cannot be undone.');">
        <?php csrf_field(); ?><button class="link danger">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
</div>
<script type="module" src="<?= htmlspecialchars(base_url('assets/ecotrip/ecotrip.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php include __DIR__.'/../layouts/footer.php'; ?>
