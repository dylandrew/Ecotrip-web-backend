<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<?php
$view_data = get_defined_vars();
$stay = is_array($view_data['stay'] ?? null) ? $view_data['stay'] : [];
$trips = is_array($view_data['trips'] ?? null) ? $view_data['trips'] : [];
$paymongo_ready = $view_data['paymongo_ready'] ?? false;
?>
<h1><?= htmlspecialchars($stay['name']) ?></h1><p class="sub"><?= htmlspecialchars($stay['location']) ?></p>
<?php if (!empty($flash)): ?><div class="alert ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
<div class="card"><p>
<?php if ((int) $stay['eco_rating'] > 0): ?>
	<span class="tag">Eco-rated <?= (int)$stay['eco_rating'] ?>/5</span>
	<?php if (!empty($stay['certification'])): ?> <span class="tag"><?= htmlspecialchars($stay['certification']) ?></span><?php endif; ?>
<?php else: ?>
	<span class="tag">Standard stay · not eco-rated</span>
<?php endif; ?></p>
<p>₱<?= number_format($stay['price_per_night'], 2) ?> per night</p><p><?= htmlspecialchars($stay['description']) ?></p></div>
<section class="stay-booking" aria-labelledby="stay-booking-title">
	<h2 id="stay-booking-title">Book this stay</h2>
	<?php if (empty($paymongo_ready)): ?>
		<div class="integration-panel"><?= (config_item('environment') ?: 'development') === 'production' ? 'Production checkout requires a PAYMONGO_SECRET_KEY that starts with sk_live_.' : 'PayMongo test checkout requires a PAYMONGO_SECRET_KEY that starts with sk_test_.'; ?></div>
	<?php elseif (empty($trips)): ?>
		<div class="todo">Create an upcoming trip with overnight dates before booking a stay. <a href="<?= site_url('plan') ?>">Plan a trip</a></div>
	<?php else: ?>
		<form class="card booking-form" method="post" action="<?= site_url('stays/'.$stay['id'].'/checkout') ?>">
			<?php csrf_field(); ?>
			<label for="stay-trip">Add this stay to a trip</label>
			<select id="stay-trip" name="trip_id" required>
				<?php foreach ($trips as $trip): ?>
					<option value="<?= (int) $trip['id'] ?>"><?= htmlspecialchars($trip['title']) ?> · <?= htmlspecialchars($trip['start_date']) ?> to <?= htmlspecialchars($trip['end_date']) ?></option>
				<?php endforeach; ?>
			</select>
			<p class="stay-total-note">Total is calculated from the selected trip's overnight dates and this stay's nightly rate.</p>
			<button class="btn" type="submit">Continue to PayMongo</button>
		</form>
	<?php endif; ?>
</section>
<a href="<?= site_url('stays') ?>">← All stays</a>
<?php include __DIR__.'/../layouts/footer.php'; ?>
