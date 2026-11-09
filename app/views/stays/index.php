<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<?php $stays = get_defined_vars()['stays'] ?? []; ?>
<?php $destination = (string) (get_defined_vars()['destination'] ?? ''); ?>
<?php $nearby_hotels = is_array(get_defined_vars()['nearby_hotels'] ?? null) ? get_defined_vars()['nearby_hotels'] : []; ?>
<?php $hotel_search_error = (string) (get_defined_vars()['hotel_search_error'] ?? ''); ?>
<?php $stay_type = (string) (get_defined_vars()['stay_type'] ?? 'all'); ?>
<?php $nearby_trips = is_array(get_defined_vars()['nearby_trips'] ?? null) ? get_defined_vars()['nearby_trips'] : []; ?>
<?php $paymongo_test_ready = !empty(get_defined_vars()['paymongo_test_ready']); ?>
<?php $is_logged_in = auth_current_user() !== null; ?>
<h1>Places to Stay</h1>
<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
<p class="sub"><?= $destination !== '' ? 'Places to stay near ' . htmlspecialchars($destination) . ', from eco-conscious stays to standard hotels.' : 'Compare eco-conscious stays and standard hotels for your trip.' ?></p>
<form method="get" action="<?= site_url('stays') ?>" class="card stay-filter">
  <label for="stay-destination">Where are you going?</label>
  <input id="stay-destination" name="destination" maxlength="150" value="<?= htmlspecialchars($destination) ?>" placeholder="Enter a destination, e.g. Baguio">
  <label for="stay-type">Type of stay</label>
  <select id="stay-type" name="type">
    <option value="all" <?= $stay_type === 'all' ? 'selected' : '' ?>>All stays</option>
    <option value="green" <?= $stay_type === 'green' ? 'selected' : '' ?>>Eco-rated stays</option>
    <option value="standard" <?= $stay_type === 'standard' ? 'selected' : '' ?>>Standard hotels &amp; stays</option>
  </select>
  <p><button class="btn" type="submit">Find places to stay</button>
    <?php if ($destination !== '' || $stay_type !== 'all'): ?> <a href="<?= site_url('stays') ?>">Show all stays</a><?php endif; ?>
  </p>
</form>
<?php if ($destination !== ''): ?>
  <section class="nearby-hotels" aria-labelledby="nearby-hotels-title">
    <div class="nearby-hotels-heading">
      <div>
        <h2 id="nearby-hotels-title">Hotels near <?= htmlspecialchars($destination) ?></h2>
        <p class="sub">Nearby places listed in OpenStreetMap. Pay a reservation deposit through PayMongo; hotel availability and the final room price must be confirmed directly with the hotel.</p>
      </div>
      <span class="osm-attribution">Data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a></span>
    </div>
    <?php if ($hotel_search_error !== ''): ?>
      <div class="alert bad">
        <?= htmlspecialchars($hotel_search_error) ?>
        <p><a href="<?= site_url('stays?' . http_build_query(['destination' => $destination, 'type' => $stay_type])) ?>">Retry nearby search</a></p>
      </div>
    <?php elseif (!$nearby_hotels): ?>
      <div class="todo">No hotels were returned for this search. Try a nearby city or a more specific destination.</div>
    <?php else: ?>
      <div class="grid nearby-hotel-grid">
        <?php foreach ($nearby_hotels as $hotel_index => $hotel): ?>
          <article class="card nearby-hotel-card">
            <h3><?= htmlspecialchars($hotel['name']) ?></h3>
            <p class="sub"><?= htmlspecialchars($hotel['address']) ?></p>
            <p><span class="tag"><?= htmlspecialchars($hotel['kind']) ?></span></p>
            <span class="tag">Not eco-rated by EcoTrip</span>
            <?php if ($is_logged_in): ?>
              <?php if (!$nearby_trips): ?>
                <p class="sub">Create an <a href="<?= site_url('plan') ?>">upcoming overnight trip</a> before paying the reservation deposit.</p>
              <?php else: ?>
                <form class="card booking-form nearby-payment-form" method="post" action="<?= site_url('stays/nearby/checkout') ?>">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="reservation_name" value="<?= htmlspecialchars(mb_substr($hotel['name'], 0, 150), ENT_QUOTES) ?>">
                  <input type="hidden" name="reservation_address" value="<?= htmlspecialchars(mb_substr($hotel['address'], 0, 150), ENT_QUOTES) ?>">
                  <input type="hidden" name="destination" value="<?= htmlspecialchars($destination, ENT_QUOTES) ?>">
                  <label for="nearby-trip-<?= (int) $hotel_index ?>">Trip</label>
                  <select id="nearby-trip-<?= (int) $hotel_index ?>" name="trip_id" required>
                    <?php foreach ($nearby_trips as $trip): ?>
                      <option value="<?= (int) $trip['id'] ?>"><?= htmlspecialchars($trip['title']) ?> · <?= htmlspecialchars($trip['destination']) ?> · <?= htmlspecialchars($trip['start_date']) ?> to <?= htmlspecialchars($trip['end_date']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ((float) ($nearby_deposit_amount ?? 0) > 0): ?>
                    <p class="stay-total-note">Reservation deposit: ₱<?= number_format((float) ($nearby_deposit_amount ?? 0), 2) ?>. PayMongo opens securely, then returns you to EcoTrip.</p>
                    <button class="btn" type="submit">Pay deposit with PayMongo</button>
                  <?php else: ?>
                    <p class="stay-total-note">No trusted deposit rate is configured. This sends a reservation request without payment; confirm availability and price with the stay.</p>
                    <button class="btn" type="submit">Send reservation request</button>
                  <?php endif; ?>
                </form>
              <?php endif; ?>
            <?php else: ?>
              <p><a class="btn" href="<?= site_url('login') ?>">Sign in to pay the reservation deposit</a></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
<?php if (!$stays): ?>
  <div class="todo">
    No <?= $stay_type === 'green' ? 'eco-rated ' : ($stay_type === 'standard' ? 'standard ' : '') ?>stays found<?= $destination !== '' ? ' near ' . htmlspecialchars($destination) : '' ?> in EcoTrip's catalog.<?= $hotel_search_error === '' ? ' Try another filter or browse all stays.' : ' Nearby OpenStreetMap results are currently unavailable.' ?>
  </div>
<?php endif; ?>
<div class="grid">
<?php foreach ($stays as $s): ?>
  <div class="card"><h3><a href="<?= site_url('stays/'.$s['id']) ?>"><?= htmlspecialchars($s['name']) ?></a></h3>
    <p class="sub" style="margin:0 0 8px"><?= htmlspecialchars($s['location']) ?></p>
    <p>
      <?php if ((int) $s['eco_rating'] > 0): ?>
        <span class="tag">Eco-rated <?= (int) $s['eco_rating'] ?>/5</span>
      <?php else: ?>
        <span class="tag">Standard stay · not eco-rated</span>
      <?php endif; ?>
      ₱<?= number_format($s['price_per_night'], 2) ?>/night
    </p>
    <?php if (!empty($s['description'])): ?><p><?= htmlspecialchars($s['description']) ?></p><?php endif; ?>
    <a class="btn" href="<?= site_url('stays/'.$s['id']) ?>">View stay &amp; book</a></div>
<?php endforeach; ?>
</div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
