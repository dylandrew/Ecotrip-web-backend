<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<p class="eyebrow">Travel with intention</p>
<h1>Bookings &amp; carbon offsets</h1>
<p class="sub">Make a secure contribution toward offsetting your trip's emissions.</p>
<?php if (!empty($flash)): ?><div class="alert ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
<div class="integration-panel">
	<?php if (!empty($paymongo_ready)): ?>
		<strong>PayMongo checkout is ready.</strong> Payment status updates after a verified webhook.
	<?php else: ?>
		<strong>PayMongo needs a secret key.</strong> Add PAYMONGO_SECRET_KEY to the local environment to enable checkout.
	<?php endif; ?>
</div>
<form method="post" action="<?= site_url('bookings/checkout') ?>" class="card booking-form">
	<?php csrf_field(); ?>
	<label for="trip-id">Trip</label>
	<select id="trip-id" name="trip_id" required>
		<?php foreach (($trips ?? []) as $trip): ?>
			<option value="<?= (int) $trip['id'] ?>"><?= htmlspecialchars($trip['title']) ?> · <?= htmlspecialchars($trip['destination']) ?></option>
		<?php endforeach; ?>
	</select>
	<label for="offset-amount">Offset contribution (PHP)</label>
	<input id="offset-amount" type="number" name="offset_amount" min="1" max="1000000" step="0.01" value="100" required>
	<button class="btn" type="submit" <?= empty($paymongo_ready) || empty($trips) ? 'disabled' : '' ?>>Continue to PayMongo</button>
</form>
<h2>Payment history</h2>
<?php if (empty($bookings)): ?>
	<div class="todo">Your offset contributions will appear here.</div>
<?php else: ?>
	<div class="card" style="overflow-x:auto"><table>
	    <thead><tr><th>Trip</th><th>Stay / offset</th><th>Amount</th><th>Status</th><th>Date</th><th>Receipt</th></tr></thead>
		<tbody><?php foreach ($bookings as $booking): ?>
	      <tr>
	        <td><?= htmlspecialchars($booking['trip_title']) ?></td>
	        <td><?= htmlspecialchars($booking['stay_name'] ?: 'Carbon offset') ?></td>
	        <td>₱<?= number_format((float) $booking['amount'], 2) ?></td>
	        <td><span class="tag"><?= str_starts_with((string) ($booking['paymongo_ref'] ?? ''), 'mock-') ? 'Mock paid' : htmlspecialchars($booking['payment_status']) ?></span></td>
	        <td><?= htmlspecialchars($booking['created_at']) ?></td>
	        <td>
	          <?php if ($booking['payment_status'] === 'paid'): ?>
	            <a href="<?= site_url('bookings/'.$booking['id'].'/receipt') ?>">View receipt</a>
	          <?php elseif ($booking['payment_status'] === 'pending' && !empty($paymongo_ready)): ?>
	            <form method="post" action="<?= site_url('bookings/'.$booking['id'].'/verify') ?>">
	              <?php csrf_field(); ?><button class="link" type="submit">Check payment</button>
	            </form>
	          <?php else: ?>
	            <span class="sub">Unavailable</span>
	          <?php endif; ?>
	        </td>
	      </tr>
		<?php endforeach; ?></tbody>
	</table></div>
<?php endif; ?>
<?php include __DIR__.'/../layouts/footer.php'; ?>
