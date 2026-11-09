<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<p class="eyebrow">A gentle nudge, right on time</p>
<h1>Trip reminders</h1>
<p class="sub">Schedule an email reminder. The email automatically includes the complete itinerary for the selected trip.</p>
<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
<?php if (!empty($trips)): ?>
<form method="post" action="<?= site_url('reminders/store') ?>" class="card reminder-form">
	<?php csrf_field(); ?>
	<label for="reminder-trip">Trip</label>
	<select id="reminder-trip" name="trip_id" required>
		<?php foreach ($trips as $trip): ?>
			<option value="<?= (int) $trip['id'] ?>"><?= htmlspecialchars($trip['title']) ?> · <?= htmlspecialchars($trip['destination']) ?></option>
		<?php endforeach; ?>
	</select>
	<label for="reminder-time">Send at</label>
	<input id="reminder-time" type="datetime-local" name="send_at" required>
	<label for="reminder-message">Message</label>
	<textarea id="reminder-message" name="message" rows="3" maxlength="255" required>Your EcoTrip starts soon. Travel gently and enjoy the journey!</textarea>
	<button class="btn" type="submit">Schedule reminder</button>
</form>
<?php else: ?>
<div class="todo">Create a trip before scheduling a reminder.</div>
<?php endif; ?>
<h2>Scheduled messages</h2>
<?php if (empty($reminders)): ?>
	<div class="todo">Your scheduled reminders will appear here.</div>
<?php else: ?>
	<div class="card" style="overflow-x:auto"><table>
		<thead><tr><th>Trip</th><th>Message</th><th>Send at</th><th>Status</th></tr></thead>
		<tbody><?php foreach ($reminders as $reminder): ?>
			<tr><td><?= htmlspecialchars($reminder['trip_title']) ?></td><td><?= htmlspecialchars($reminder['message']) ?></td><td><?= htmlspecialchars($reminder['send_at']) ?></td><td><span class="tag"><?= htmlspecialchars($reminder['status']) ?></span></td></tr>
		<?php endforeach; ?></tbody>
	</table></div>
<?php endif; ?>
<?php include __DIR__.'/../layouts/footer.php'; ?>
