<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<section class="account-page">
	<p class="eyebrow">Account recovery</p>
	<h1>Enter your reset code</h1>
	<p class="sub">The code expires after 10 minutes and can only be used once.</p>
	<?php if (!empty($flash)): ?><div class="alert ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
	<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
	<form class="card account-form" method="post" action="<?= site_url('reset-password') ?>">
		<?php csrf_field(); ?>
		<label for="reset-email">Email</label><input id="reset-email" type="email" name="email" autocomplete="email" required>
		<label for="reset-otp">Six-digit code</label><input id="reset-otp" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
		<label for="new-password">New password</label><input id="new-password" type="password" name="password" minlength="12" maxlength="200" autocomplete="new-password" required>
		<label for="confirm-password">Confirm new password</label><input id="confirm-password" type="password" name="confirm_password" minlength="12" maxlength="200" autocomplete="new-password" required>
		<button class="btn" type="submit">Reset password</button>
	</form>
	<p class="account-alternate"><a href="<?= site_url('forgot-password') ?>">Request a new code</a> · <a href="<?= site_url('login') ?>">Back to sign in</a></p>
</section>
<?php include __DIR__.'/../layouts/footer.php'; ?>
