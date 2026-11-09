<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<?php
$view_data = get_defined_vars();
$stats = is_array($view_data['stats'] ?? null) ? $view_data['stats'] : ['trips' => 0, 'budget' => 0];
$upcoming = is_array($view_data['upcoming'] ?? null) ? $view_data['upcoming'] : [];
$destination_images = [
	'baguio' => 'https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1100&q=85',
	'siargao' => 'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=1100&q=85',
	'sagada' => 'https://images.unsplash.com/photo-1470770841072-f978cf4d019e?auto=format&fit=crop&w=1100&q=85',
];
?>
<div class="dashboard-page">
	<section class="dashboard-hero" aria-labelledby="dashboard-title">
		<div class="dashboard-hero-content">
			<p class="dashboard-eyebrow">ECO TRAVEL, AT YOUR PACE</p>
			<h1 id="dashboard-title">Go a little farther.<br>Leave a little lighter.</h1>
			<p>Thoughtful journeys start with the right route, a good place to stay, and room to explore.</p>
			<div class="dashboard-actions">
				<a class="dashboard-primary" href="<?= site_url('plan') ?>">Plan a trip <span aria-hidden="true">↗</span></a>
				<a class="dashboard-secondary" href="<?= site_url('transport') ?>">Explore low-carbon routes</a>
			</div>
		</div>
		<p class="dashboard-image-caption">A lighter way to see more</p>
	</section>

	<section class="dashboard-stats" aria-label="Your travel overview">
		<div class="dashboard-stat"><span>Trips planned</span><strong><?= (int) $stats['trips'] ?></strong></div>
		<div class="dashboard-stat"><span>Trip budgets</span><strong>₱<?= number_format((float) $stats['budget'], 0) ?></strong></div>
	</section>

	<section class="dashboard-journeys" aria-labelledby="journeys-title">
		<div class="dashboard-section-heading">
			<div>
				<p class="dashboard-eyebrow">THE NEXT CHAPTER</p>
				<h2 id="journeys-title">Journeys on the horizon</h2>
			</div>
			<a href="<?= site_url('trips') ?>">All trips <span aria-hidden="true">↗</span></a>
		</div>
		<?php if (!$upcoming): ?>
			<div class="dashboard-empty">
				<p>Your next journey is waiting to be imagined.</p>
				<a href="<?= site_url('plan') ?>">Plan your first trip <span aria-hidden="true">↗</span></a>
			</div>
		<?php else: ?>
			<div class="journey-grid">
				<?php foreach ($upcoming as $trip): ?>
					<?php $image = $destination_images[strtolower($trip['destination'])] ?? 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1100&q=85'; ?>
					<article class="journey-card">
						<a class="journey-card-image" href="<?= site_url('trips/'.$trip['id']) ?>">
							<img src="<?= htmlspecialchars($image) ?>" alt="Landscape in <?= htmlspecialchars($trip['destination']) ?>" loading="lazy">
							<span class="journey-status"><?= htmlspecialchars(ucfirst($trip['status'])) ?></span>
						</a>
						<div class="journey-card-body">
							<p class="journey-destination"><?= htmlspecialchars($trip['destination']) ?> <span aria-hidden="true">/</span> <?= date('M j', strtotime($trip['start_date'])) ?> – <?= date('M j, Y', strtotime($trip['end_date'])) ?></p>
							<h3><a href="<?= site_url('trips/'.$trip['id']) ?>"><?= htmlspecialchars($trip['title']) ?></a></h3>
							<div class="journey-card-meta"><span>₱<?= number_format((float) $trip['budget'], 0) ?> budget</span></div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<section class="dashboard-discover" aria-label="Explore EcoTrip">
		<a href="<?= site_url('stays') ?>"><span>01 / STAY WITH CARE</span><strong>Find a greener place to land</strong><b aria-hidden="true">↗</b></a>
		<a href="<?= site_url('assistant') ?>"><span>02 / PLAN WITH PURPOSE</span><strong>Ask your EcoTrip assistant</strong><b aria-hidden="true">↗</b></a>
		<a href="<?= site_url('journal') ?>"><span>03 / KEEP THE MOMENTS</span><strong>Open your trip journal</strong><b aria-hidden="true">↗</b></a>
	</section>
</div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
