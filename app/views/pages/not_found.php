<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<h1>Not found</h1>
<p class="sub">That record does not exist (or is not yours).</p>
<a class="btn" href="<?= site_url('trips') ?>">Back to My Trips</a>
<?php include __DIR__.'/../layouts/footer.php'; ?>
