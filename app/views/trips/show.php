<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php
$viewData = get_defined_vars();
$trip = is_array($viewData['trip'] ?? null)
    ? array_replace([
        'id' => 0,
        'title' => '',
        'destination' => '',
        'start_date' => '',
        'end_date' => '',
        'budget' => 0,
        'travelers' => 1,
        'status' => 'draft',
    ], $viewData['trip'])
    : ['id' => 0, 'title' => '', 'destination' => '', 'start_date' => '', 'end_date' => '', 'budget' => 0, 'travelers' => 1, 'status' => 'draft'];
$items = is_array($viewData['items'] ?? null) ? $viewData['items'] : [];
$flash = is_string($viewData['flash'] ?? null) ? $viewData['flash'] : '';
$flash_error = is_string($viewData['flash_error'] ?? null) ? $viewData['flash_error'] : '';
?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<h1><?= htmlspecialchars($trip['title'], ENT_QUOTES, 'UTF-8') ?></h1>
<p class="sub"><?= htmlspecialchars($trip['destination'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($trip['start_date'], ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($trip['end_date'], ENT_QUOTES, 'UTF-8') ?></p>
<p><a class="btn" href="<?= htmlspecialchars(site_url('stays?destination=' . rawurlencode($trip['destination'])), ENT_QUOTES, 'UTF-8') ?>">Find green stays in <?= htmlspecialchars($trip['destination'], ENT_QUOTES, 'UTF-8') ?></a></p>
<?php if (!empty($flash)): ?><div class="alert ok"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="grid">
  <div class="card"><h3>Budget</h3><p class="big">₱<?= number_format($trip['budget'], 2) ?></p></div>
  <div class="card"><h3>Travelers</h3><p class="big"><?= (int)$trip['travelers'] ?></p></div>
  <div class="card"><h3>Status</h3><p class="big"><span class="tag"><?= htmlspecialchars($trip['status'], ENT_QUOTES, 'UTF-8') ?></span></p></div>
</div>
<h2>Itinerary</h2>
<?php if (!$items): ?>
  <div class="todo">No itinerary items yet. <a href="<?= htmlspecialchars(site_url('trips/'.$trip['id'].'/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit this trip</a> to add your plans.</div>
<?php else:
  $items_by_day = [];
  foreach ($items as $item) $items_by_day[(int) $item['day_number']][] = $item;
  $trip_start = new DateTimeImmutable($trip['start_date']);
  $trip_days = (int) $trip_start->diff(new DateTimeImmutable($trip['end_date']))->days + 1;
?>
  <?php for ($day = 1; $day <= $trip_days; $day++):
    $day_items = $items_by_day[$day] ?? [];
    $day_cost = array_sum(array_column($day_items, 'est_cost'));
    $day_emissions = array_sum(array_column($day_items, 'est_co2_kg'));
    $day_date = $trip_start->modify('+' . ($day - 1) . ' days');
  ?>
    <section class="itinerary-day">
      <div class="itinerary-day-heading">
        <h3>Day <?= $day ?></h3>
        <span><?= htmlspecialchars($day_date->format('l, M j'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <?php if (!$day_items): ?>
        <p class="itinerary-empty">No stops planned yet.</p>
      <?php else: ?>
        <?php foreach ($day_items as $item):
          $time = DateTime::createFromFormat('H:i:s', (string) ($item['start_time'] ?? ''));
        ?>
          <article class="itinerary-item">
            <time class="itinerary-time"><?= $time ? htmlspecialchars($time->format('H:i'), ENT_QUOTES, 'UTF-8') : 'Time TBD' ?></time>
            <div class="itinerary-item-details">
              <p class="itinerary-description"><?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
              <span class="itinerary-type itinerary-type-<?= htmlspecialchars($item['item_type'] ?? 'activity', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($item['item_type'] ?? 'activity'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <span class="itinerary-estimates">₱<?= number_format((float) ($item['est_cost'] ?? 0), 0) ?> · <?= number_format((float) ($item['est_co2_kg'] ?? 0), 1) ?> kg CO₂</span>
          </article>
        <?php endforeach; ?>
        <p class="itinerary-day-total">Day estimate: ₱<?= number_format((float) $day_cost, 0) ?> · <?= number_format((float) $day_emissions, 1) ?> kg CO₂</p>
      <?php endif; ?>
    </section>
  <?php endfor; ?>
<?php endif; ?>
<p class="actions">
  <a class="btn" href="<?= htmlspecialchars(site_url('trips/'.$trip['id'].'/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
  <form method="post" action="<?= htmlspecialchars(site_url('trips/'.$trip['id'].'/delete'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Delete this trip and its itinerary? This cannot be undone.');">
    <?php csrf_field(); ?><button class="btn danger-btn">Delete</button></form>
  <a href="<?= htmlspecialchars(site_url('trips'), ENT_QUOTES, 'UTF-8') ?>">← Back to My Trips</a>
</p>
<?php include __DIR__.'/../layouts/footer.php'; ?>
