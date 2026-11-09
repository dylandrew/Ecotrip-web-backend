<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<?php
$view_data = get_defined_vars();
$overview = is_array($view_data['overview'] ?? null) ? $view_data['overview'] : [];
$users = is_array($view_data['users'] ?? null) ? $view_data['users'] : [];
$trips = is_array($view_data['trips'] ?? null) ? $view_data['trips'] : [];
$stays = is_array($view_data['stays'] ?? null) ? $view_data['stays'] : [];
$routes = is_array($view_data['routes'] ?? null) ? $view_data['routes'] : [];
$bookings = is_array($view_data['bookings'] ?? null) ? $view_data['bookings'] : [];
$reminders = is_array($view_data['reminders'] ?? null) ? $view_data['reminders'] : [];
$photos = is_array($view_data['photos'] ?? null) ? $view_data['photos'] : [];
$current_user_id = (int) ($view_data['current_user_id'] ?? 0);
?>
<main class="admin-page">
	<header class="admin-heading">
		<div><p class="eyebrow">ECOTRIP / CONTROL ROOM</p><h1>Admin workspace</h1><p class="sub">Manage accounts, trip content, and platform activity.</p></div>
		<a class="btn" href="<?= site_url('dashboard') ?>">View traveler dashboard</a>
	</header>
	<?php if (!empty($flash)): ?><div class="alert ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
	<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
	<div class="admin-metrics" aria-label="Platform summary">
		<div><span>Accounts</span><strong><?= (int) ($overview['users'] ?? 0) ?></strong></div>
		<div><span>Trips</span><strong><?= (int) ($overview['trips'] ?? 0) ?></strong></div>
		<div><span>Catalog items</span><strong><?= (int) ($overview['stays'] ?? 0) + (int) ($overview['routes'] ?? 0) ?></strong></div>
		<div><span>Pending payments</span><strong><?= (int) ($overview['pending_payments'] ?? 0) ?></strong></div>
		<div><span>Pending reminders</span><strong><?= (int) ($overview['pending_reminders'] ?? 0) ?></strong></div>
	</div>

	<details class="admin-section" open>
		<summary><span>Accounts</span><small><?= count($users) ?> total</small></summary>
		<div class="admin-section-body">
			<form class="admin-create-form" method="post" action="<?= site_url('admin/users') ?>">
				<?php csrf_field(); ?>
				<label>Name<input type="text" name="name" maxlength="100" required></label>
				<label>Email<input type="email" name="email" maxlength="150" required></label>
				<label>Temporary password<input type="password" name="password" minlength="12" maxlength="200" required></label>
				<label>Role<select name="role"><option value="traveler">Traveler</option><option value="admin">Admin</option></select></label>
				<button class="btn" type="submit">Create account</button>
			</form>
			<div class="admin-table-wrap"><table class="admin-table">
				<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Access</th></tr></thead>
				<tbody><?php foreach ($users as $user): ?>
					<tr>
						<td><?= htmlspecialchars($user['name']) ?></td><td><?= htmlspecialchars($user['email']) ?></td>
						<td><span class="tag"><?= htmlspecialchars($user['role']) ?></span></td><td><?= htmlspecialchars(date('M j, Y', strtotime($user['created_at']))) ?></td>
						<td>
							<?php if ((int) $user['id'] !== $current_user_id): ?>
							<form class="admin-inline-form" method="post" action="<?= site_url('admin/users/'.$user['id'].'/role') ?>">
								<?php csrf_field(); ?>
								<select name="role" aria-label="Role for <?= htmlspecialchars($user['name']) ?>">
									<option value="traveler" <?= $user['role'] === 'traveler' ? 'selected' : '' ?>>Traveler</option>
									<option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
								</select><button class="admin-text-button" type="submit">Save</button>
							</form>
							<?php else: ?><span class="admin-self-label">You</span><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?></tbody>
			</table></div>
		</div>
	</details>

	<details class="admin-section" open>
		<summary><span>Trips</span><small><?= count($trips) ?> total</small></summary>
		<div class="admin-section-body admin-table-wrap"><table class="admin-table">
			<thead><tr><th>Trip</th><th>Traveler</th><th>Dates</th><th>Budget</th><th>Status</th><th>Actions</th></tr></thead>
			<tbody><?php foreach ($trips as $trip): ?>
				<tr>
					<td><a href="<?= site_url('trips/'.$trip['id']) ?>"><?= htmlspecialchars($trip['title']) ?></a><small><?= htmlspecialchars($trip['destination']) ?></small></td>
					<td><?= htmlspecialchars($trip['traveler_name']) ?><small><?= htmlspecialchars($trip['traveler_email']) ?></small></td>
					<td><?= htmlspecialchars($trip['start_date']) ?><small>to <?= htmlspecialchars($trip['end_date']) ?></small></td>
					<td>₱<?= number_format((float) $trip['budget'], 0) ?></td>
					<td><form class="admin-inline-form" method="post" action="<?= site_url('admin/trips/'.$trip['id'].'/status') ?>">
						<?php csrf_field(); ?><select name="status" aria-label="Status for <?= htmlspecialchars($trip['title']) ?>">
							<?php foreach (['draft','planned','booked','completed'] as $status): ?><option value="<?= $status ?>" <?= $trip['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?>
						</select><button class="admin-text-button" type="submit">Save</button></form></td>
					<td><form method="post" action="<?= site_url('admin/trips/'.$trip['id'].'/delete') ?>" onsubmit="return confirm('Delete this trip and its dependent records?');"><?php csrf_field(); ?><button class="admin-text-button admin-danger" type="submit">Delete</button></form></td>
				</tr>
			<?php endforeach; ?></tbody>
		</table></div>
	</details>

	<details class="admin-section">
		<summary><span>Places to stay</span><small><?= count($stays) ?> listed</small></summary>
		<div class="admin-section-body">
			<form class="admin-create-form" method="post" action="<?= site_url('admin/stays') ?>">
				<?php csrf_field(); ?><input type="hidden" name="id" value="0">
				<label>Name<input name="name" maxlength="150" required></label><label>Location<input name="location" maxlength="150" required></label>
				<label>Eco rating<select name="eco_rating"><option value="0">Standard stay (not eco-rated)</option><option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option></select></label>
				<label>Price / night<input type="number" name="price_per_night" min="0.01" step="0.01" required></label><label>Certification<input name="certification" maxlength="100" placeholder="Optional"></label>
				<label>Description<input name="description" maxlength="1000"></label><button class="btn" type="submit">Add stay</button>
			</form>
			<div class="admin-table-wrap"><table class="admin-table">
				<thead><tr><th>Stay</th><th>Location</th><th>Type / eco rating</th><th>Price/night</th><th>Save</th><th>Remove</th></tr></thead>
				<tbody><?php foreach ($stays as $stay): ?>
					<tr>
						<td><input form="stay-edit-<?= (int) $stay['id'] ?>" name="name" value="<?= htmlspecialchars($stay['name']) ?>" aria-label="Stay name" required></td>
						<td><input form="stay-edit-<?= (int) $stay['id'] ?>" name="location" value="<?= htmlspecialchars($stay['location']) ?>" aria-label="Stay location" required></td>
						<td><select form="stay-edit-<?= (int) $stay['id'] ?>" name="eco_rating" aria-label="Type or eco rating"><option value="0" <?= (int) $stay['eco_rating'] === 0 ? 'selected' : '' ?>>Standard (not eco-rated)</option><?php for ($rating = 5; $rating >= 1; $rating--): ?><option value="<?= $rating ?>" <?= (int) $stay['eco_rating'] === $rating ? 'selected' : '' ?>>Eco <?= $rating ?>/5</option><?php endfor; ?></select></td>
						<td><input form="stay-edit-<?= (int) $stay['id'] ?>" type="number" name="price_per_night" min="0.01" step="0.01" value="<?= htmlspecialchars((string) $stay['price_per_night']) ?>" aria-label="Price per night" required></td>
						<td><form id="stay-edit-<?= (int) $stay['id'] ?>" method="post" action="<?= site_url('admin/stays') ?>"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int) $stay['id'] ?>"><input type="hidden" name="certification" value="<?= htmlspecialchars($stay['certification'] ?? '') ?>"><input type="hidden" name="description" value="<?= htmlspecialchars($stay['description'] ?? '') ?>"><button class="admin-text-button" type="submit">Save</button></form></td>
						<td><form method="post" action="<?= site_url('admin/stays/'.$stay['id'].'/delete') ?>" onsubmit="return confirm('Remove this stay from the directory?');"><?php csrf_field(); ?><button class="admin-text-button admin-danger" type="submit">Remove</button></form></td>
					</tr>
				<?php endforeach; ?></tbody>
			</table></div>
		</div>
	</details>

	<details class="admin-section">
		<summary><span>Low-carbon routes</span><small><?= count($routes) ?> listed</small></summary>
		<div class="admin-section-body">
			<form class="admin-create-form" method="post" action="<?= site_url('admin/routes') ?>">
				<?php csrf_field(); ?><input type="hidden" name="id" value="0">
				<label>Mode<select name="mode"><?php foreach (['bus','train','bike','walk','ferry','carpool'] as $mode): ?><option value="<?= $mode ?>"><?= ucfirst($mode) ?></option><?php endforeach; ?></select></label>
				<label>Origin<input name="origin" maxlength="150" required></label><label>Destination<input name="destination" maxlength="150" required></label>
				<label>Distance km<input type="number" name="distance_km" min="0" step="0.01" required></label><label>CO₂/person kg<input type="number" name="co2_kg_per_person" min="0" step="0.01" required></label>
				<label>Cost PHP<input type="number" name="cost" min="0" step="0.01" required></label><button class="btn" type="submit">Add route</button>
			</form>
			<div class="admin-table-wrap"><table class="admin-table">
				<thead><tr><th>Mode</th><th>From</th><th>To</th><th>Distance</th><th>CO₂/person</th><th>Cost</th><th>Actions</th></tr></thead>
				<tbody><?php foreach ($routes as $route): ?>
					<tr>
						<td><select form="route-edit-<?= (int) $route['id'] ?>" name="mode" aria-label="Route mode"><?php foreach (['bus','train','bike','walk','ferry','carpool'] as $mode): ?><option value="<?= $mode ?>" <?= $route['mode'] === $mode ? 'selected' : '' ?>><?= ucfirst($mode) ?></option><?php endforeach; ?></select></td>
						<td><input form="route-edit-<?= (int) $route['id'] ?>" name="origin" value="<?= htmlspecialchars($route['origin']) ?>" aria-label="Origin" required></td>
						<td><input form="route-edit-<?= (int) $route['id'] ?>" name="destination" value="<?= htmlspecialchars($route['destination']) ?>" aria-label="Destination" required></td>
						<td><input form="route-edit-<?= (int) $route['id'] ?>" type="number" name="distance_km" min="0" step="0.01" value="<?= htmlspecialchars((string) $route['distance_km']) ?>" aria-label="Distance km" required></td>
						<td><input form="route-edit-<?= (int) $route['id'] ?>" type="number" name="co2_kg_per_person" min="0" step="0.01" value="<?= htmlspecialchars((string) $route['co2_kg_per_person']) ?>" aria-label="CO2 kg per person" required></td>
						<td><input form="route-edit-<?= (int) $route['id'] ?>" type="number" name="cost" min="0" step="0.01" value="<?= htmlspecialchars((string) $route['cost']) ?>" aria-label="Cost PHP" required></td>
						<td class="admin-row-actions"><form id="route-edit-<?= (int) $route['id'] ?>" method="post" action="<?= site_url('admin/routes') ?>"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int) $route['id'] ?>"><button class="admin-text-button" type="submit">Save</button></form><form method="post" action="<?= site_url('admin/routes/'.$route['id'].'/delete') ?>" onsubmit="return confirm('Remove this route?');"><?php csrf_field(); ?><button class="admin-text-button admin-danger" type="submit">Remove</button></form></td>
					</tr>
				<?php endforeach; ?></tbody>
			</table></div>
		</div>
	</details>

	<details class="admin-section">
		<summary><span>Bookings &amp; payments</span><small><?= count($bookings) ?> records</small></summary>
		<div class="admin-section-body admin-table-wrap"><table class="admin-table"><thead><tr><th>Traveler</th><th>Trip</th><th>Stay/offset</th><th>Amount</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
			<?php foreach ($bookings as $booking): ?><tr><td><?= htmlspecialchars($booking['traveler_name']) ?><small><?= htmlspecialchars($booking['traveler_email']) ?></small></td><td><?= htmlspecialchars($booking['trip_title']) ?></td><td><?= htmlspecialchars($booking['stay_name'] ?: 'Carbon offset') ?></td><td>₱<?= number_format((float) $booking['amount'], 2) ?></td><td><span class="tag"><?= str_starts_with((string) ($booking['paymongo_ref'] ?? ''), 'mock-') ? 'Mock paid' : htmlspecialchars($booking['payment_status']) ?></span></td><td><?= htmlspecialchars($booking['created_at']) ?></td><td><?php if ($booking['payment_status'] === 'pending' && str_starts_with((string) ($booking['paymongo_ref'] ?? ''), 'link_')): ?><form method="post" action="<?= site_url('admin/bookings/'.$booking['id'].'/reconcile') ?>"><?php csrf_field(); ?><button class="admin-text-button" type="submit">Reconcile</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
		</tbody></table></div>
		<p class="admin-note">Reconciliation checks PayMongo directly and confirms the amount before updating a pending booking.</p>
	</details>

	<details class="admin-section">
		<summary><span>Journal &amp; reminders</span><small><?= count($photos) ?> photos · <?= count($reminders) ?> reminders</small></summary>
		<div class="admin-section-body admin-record-grid">
			<section><h3>Recent journal photos</h3><?php if (!$photos): ?><p class="admin-note">No journal photos yet.</p><?php else: foreach (array_slice($photos, 0, 8) as $photo): ?>
				<div class="admin-photo-row"><img src="<?= base_url($photo['photo_path']) ?>" alt=""><span><?= htmlspecialchars($photo['traveler_name']) ?><small><?= htmlspecialchars($photo['trip_title']) ?></small></span></div>
			<?php endforeach; endif; ?></section>
			<section><h3>Trip reminders</h3><?php if (!$reminders): ?><p class="admin-note">No reminders scheduled.</p><?php else: ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Traveler</th><th>Trip</th><th>Send at</th><th>Status</th></tr></thead><tbody>
				<?php foreach (array_slice($reminders, 0, 10) as $reminder): ?><tr><td><?= htmlspecialchars($reminder['traveler_name']) ?></td><td><?= htmlspecialchars($reminder['trip_title']) ?></td><td><?= htmlspecialchars($reminder['send_at']) ?></td><td><?= htmlspecialchars($reminder['status']) ?></td></tr><?php endforeach; ?>
			</tbody></table></div><?php endif; ?></section>
		</div>
	</details>
</main>
<?php include __DIR__.'/../layouts/footer.php'; ?>