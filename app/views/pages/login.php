<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<section class="account-page">
	<p class="eyebrow">Welcome back</p>
	<h1>Sign in to EcoTrip</h1>
	<p class="sub">Your next thoughtful journey starts here.</p>
	<?php if (!empty($flash)): ?><div class="alert ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
	<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
	<?php if (!empty($google_sign_in_enabled)): ?>
		<a class="btn secondary" href="<?= site_url('auth/google') ?>">Continue with Google</a>
	<?php else: ?>
		<p class="account-alternate">Google sign-in is unavailable until Google OAuth is configured.</p>
	<?php endif; ?>
	<form class="card account-form" method="post" action="<?= site_url('login') ?>">
		<?php csrf_field(); ?>
		<label for="login-email">Email</label><input id="login-email" type="email" name="email" autocomplete="email" required>
		<label for="login-password">Password</label><input id="login-password" type="password" name="password" autocomplete="current-password" required>
		<button class="btn" type="submit">Log in</button>
	</form>
	<p class="account-alternate"><a href="<?= site_url('forgot-password') ?>">Forgot your password?</a></p>
	<p class="account-alternate">New to EcoTrip? <a href="<?= site_url('register') ?>">Create an account</a></p>
</section>
<?php include __DIR__.'/../layouts/footer.php'; ?>
