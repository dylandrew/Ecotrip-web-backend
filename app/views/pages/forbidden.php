<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<h1>Request blocked</h1>
<p class="sub">The security token was missing or expired. Please go back, reload the page, and try again.</p>
<a class="btn" href="<?= site_url('trips') ?>">Back to My Trips</a>
<?php include __DIR__.'/../layouts/footer.php'; ?>
