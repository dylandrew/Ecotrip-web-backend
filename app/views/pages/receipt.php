<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<?php
$view_data = get_defined_vars();
$booking = is_array($view_data['booking'] ?? null) ? $view_data['booking'] : [];
$is_mock_payment = str_starts_with((string) ($booking['paymongo_ref'] ?? ''), 'mock-');
?>
<section class="receipt-page" aria-labelledby="receipt-title">
	<div class="receipt-topline">
		<p class="eyebrow"><?= $is_mock_payment ? 'MOCK PAYMENT · NOT REAL' : 'PAYMENT CONFIRMED' ?></p>
		<span class="tag"><?= $is_mock_payment ? 'Mock paid' : 'Paid' ?></span>
	</div>
	<h1 id="receipt-title">Thank you for travelling thoughtfully.</h1>
	<p class="sub"><?= $is_mock_payment ? 'This is a test reservation only. No money was charged, and this is not a valid proof of payment.' : 'Your PayMongo payment is confirmed. This receipt is ready to print or save as a PDF.' ?></p>
	<div class="receipt-sheet">
		<div class="receipt-brand"><span>Eco</span>Trip <small>LOWER-IMPACT JOURNEYS</small></div>
		<div class="receipt-number"><span>RECEIPT</span><strong>ET-<?= str_pad((string) $booking['id'], 6, '0', STR_PAD_LEFT) ?></strong></div>
		<div class="receipt-divider"></div>
		<dl class="receipt-details">
			<div><dt>Trip</dt><dd><?= htmlspecialchars($booking['trip_title']) ?></dd></div>
			<div><dt>Destination</dt><dd><?= htmlspecialchars($booking['destination']) ?></dd></div>
			<div><dt>Purchase</dt><dd><?= htmlspecialchars($booking['stay_name'] ?: 'Carbon offset contribution') ?></dd></div>
			<div><dt>Reference</dt><dd><?= htmlspecialchars($booking['paymongo_ref']) ?></dd></div>
			<div><dt>Receipt issued</dt><dd><?= htmlspecialchars(date('M j, Y', strtotime($booking['created_at']))) ?></dd></div>
		</dl>
		<div class="receipt-total"><span>Total paid</span><strong>₱<?= number_format((float) $booking['amount'], 2) ?> <small>PHP</small></strong></div>
		<p class="receipt-note"><?= $is_mock_payment ? 'Mock payment for local testing only. This reservation has not been paid in real life.' : 'Payment status verified with PayMongo. Thank you for supporting more thoughtful travel.' ?></p>
	</div>
	<div class="receipt-actions">
		<button class="btn" type="button" onclick="window.print()">Download / save receipt as PDF</button>
		<a href="<?= site_url('bookings') ?>">Back to bookings</a>
	</div>
</section>
<?php include __DIR__.'/../layouts/footer.php'; ?>