<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<section class="account-page">
	<p class="eyebrow">Account recovery</p>
	<h1>Reset your password</h1>
	<p class="sub">We will email a six-digit code if an account exists for that address.</p>
	<?php if (!empty($flash)): ?><div class="alert ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
	<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
	<form class="card account-form" method="post" action="<?= site_url('forgot-password') ?>">
		<?php csrf_field(); ?>
		<label for="reset-email">Email</label><input id="reset-email" type="email" name="email" autocomplete="email" required>
		<button class="btn" type="submit">Send reset code</button>
	</form>
	<p class="account-alternate"><a href="<?= site_url('reset-password') ?>">I already have a code</a> · <a href="<?= site_url('login') ?>">Back to sign in</a></p>
</section>
<?php include __DIR__.'/../layouts/footer.php'; ?>
