<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<section class="account-page">
	<p class="eyebrow">Your next chapter</p>
	<h1>Create an account</h1>
	<p class="sub">Save your trips, itineraries, and travel memories.</p>
	<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
	<?php if (!empty($google_sign_in_enabled)): ?>
		<a class="btn secondary" href="<?= site_url('auth/google') ?>">Sign up with Google</a>
	<?php else: ?>
		<p class="account-alternate">Google sign-up is unavailable until Google OAuth is configured.</p>
	<?php endif; ?>
	<form class="card account-form" method="post" action="<?= site_url('register') ?>">
		<?php csrf_field(); ?>
		<label for="register-name">Name</label><input id="register-name" type="text" name="name" maxlength="100" autocomplete="name" required>
		<label for="register-email">Email</label><input id="register-email" type="email" name="email" maxlength="150" autocomplete="email" required>
		<label for="register-address">Home address</label><input id="register-address" type="text" name="address" maxlength="255" autocomplete="street-address" placeholder="Street, Barangay, City, Province" required>
		<label for="register-password">Password</label><input id="register-password" type="password" name="password" minlength="12" maxlength="200" autocomplete="new-password" required>
		<button class="btn" type="submit">Create account</button>
	</form>
	<p class="account-alternate">Already have an account? <a href="<?= site_url('login') ?>">Log in</a></p>
</section>
<?php include __DIR__.'/../layouts/footer.php'; ?>
