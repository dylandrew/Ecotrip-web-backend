</main>
<?php $footer_user = $_SESSION['ecotrip_user'] ?? null; ?>
<footer class="site-footer">
  <div class="footer-cols">
    <div data-reveal>
      <h4><b>1</b>Explore</h4>
      <ul>
        <li><a href="<?= site_url('plan') ?>" data-roll>Plan a Trip</a></li>
        <li><a href="<?= site_url('stays') ?>" data-roll>Green Stays</a></li>
        <li><a href="<?= site_url('transport') ?>" data-roll>Routes</a></li>
        <li><a href="<?= site_url('assistant') ?>" data-roll>Trip Assistant</a></li>
      </ul>
    </div>
    <div data-reveal style="--d: 80ms">
      <h4><b>2</b>Your journey</h4>
      <ul>
        <li><a href="<?= site_url('trips') ?>" data-roll>My Trips</a></li>
        <li><a href="<?= site_url('bookings') ?>" data-roll>Bookings</a></li>
        <li><a href="<?= site_url('journal') ?>" data-roll>Journal</a></li>
        <li><a href="<?= site_url('reminders') ?>" data-roll>Reminders</a></li>
      </ul>
    </div>
    <div data-reveal style="--d: 160ms">
      <h4><b>3</b>Account</h4>
      <ul>
        <li><a href="<?= site_url('dashboard') ?>" data-roll>Dashboard</a></li>
        <?php if (is_array($footer_user)): ?>
          <li><a href="<?= site_url('account/password') ?>" data-roll>Account &amp; password</a></li>
        <?php else: ?>
          <li><a href="<?= site_url('login') ?>" data-roll>Log in</a></li>
          <li><a href="<?= site_url('register') ?>" data-roll>Create account</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="footer-mark" aria-hidden="true" data-footer-mark><span data-split>Eco<em>Trip</em></span></div>
  <div class="footer-base">
    <span>&copy; <?= date('Y') ?> EcoTrip &middot; Sustainable Travel Planner</span>
    <span>Travel light. Stay well. Keep close.</span>
  </div>
</footer>
</body>
</html>
