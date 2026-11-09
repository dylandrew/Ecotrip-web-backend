<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<section class="account-page">
	<p class="eyebrow">Account security</p>
	<h1>Change password</h1>
	<p class="sub">Signed in as <?= htmlspecialchars($user['email'] ?? '') ?>.</p>
	<?php if (!empty($flash)): ?><div class="alert ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
	<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
	<?php if (!empty($google_connected)): ?><p class="account-alternate">Google sign-in is connected.</p><?php elseif (!empty($google_sign_in_enabled)): ?><p class="account-alternate"><a href="<?= site_url('auth/google') ?>">Connect Google sign-in</a></p><?php endif; ?>
	<h2>Home address</h2>
	<form class="card account-form" method="post" action="<?= site_url('account/address') ?>">
		<?php csrf_field(); ?>
		<label for="account-address">Address</label><input id="account-address" type="text" name="address" maxlength="255" autocomplete="street-address" value="<?= htmlspecialchars($address ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Street, Barangay, City, Province" required>
		<button class="btn" type="submit">Save address</button>
	</form>
	<h2>Password</h2>
	<form class="card account-form" method="post" action="<?= site_url('account/password') ?>">
		<?php csrf_field(); ?>
		<?php if (!empty($has_password)): ?><label for="current-password">Current password</label><input id="current-password" type="password" name="current_password" autocomplete="current-password" required><?php endif; ?>
		<label for="new-password">New password</label><input id="new-password" type="password" name="new_password" minlength="12" maxlength="200" autocomplete="new-password" required>
		<label for="confirm-password">Confirm new password</label><input id="confirm-password" type="password" name="confirm_password" minlength="12" maxlength="200" autocomplete="new-password" required>
		<button class="btn" type="submit">Update password</button>
	</form>
		<h2>Data and account</h2>
		<p><a class="btn secondary" href="<?= site_url('account/export') ?>">Download my trips and bookings as JSON</a></p>
		<form class="card account-form" method="post" action="<?= site_url('account/delete') ?>" onsubmit="return confirm('Delete your EcoTrip account permanently?');">
			<?php csrf_field(); ?>
			<?php if (!empty($has_password)): ?><label for="delete-current-password">Current password</label><input id="delete-current-password" type="password" name="current_password" autocomplete="current-password" required><?php endif; ?>
			<button class="btn" type="submit">Delete my account</button>
		</form>
</section>
<?php include __DIR__.'/../layouts/footer.php'; ?>