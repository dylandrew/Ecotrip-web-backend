<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<?php
$view_data = get_defined_vars();
$trip = is_array($view_data['trip'] ?? null) ? $view_data['trip'] : [];
$errors = is_array($view_data['errors'] ?? null) ? $view_data['errors'] : [];
$mode = $mode ?? 'create';
$action = $action ?? '';
$v = function ($k) use ($trip) { return htmlspecialchars((string)($trip[$k] ?? '')); };
$err = function ($k) use ($errors) { return isset($errors[$k]) ? '<div class="err">'.htmlspecialchars($errors[$k]).'</div>' : ''; };
$ai_ready = trim((string) config_item('gemini_api_key')) !== '';
?>
<h1><?= $mode === 'edit' ? 'Edit Trip' : 'Plan a Trip' ?></h1>
<p class="sub"><?= $mode === 'edit' ? 'Update the details below.' : 'Set your destination, dates, and budget, then build a lighter itinerary.' ?></p>
<?php if ($errors): ?><div class="alert bad">Please fix the highlighted fields.</div><?php endif; ?>
<form id="trip-form" method="post" action="<?= $action ?>" class="card" novalidate>
  <?php csrf_field(); ?>
  <label>Trip title *</label><input name="title" maxlength="150" value="<?= $v('title') ?>"><?= $err('title') ?>
  <label>Destination *</label><input name="destination" maxlength="150" value="<?= $v('destination') ?>"><?= $err('destination') ?>
  <p class="sub"><a id="destination-stays-link" data-base-url="<?= htmlspecialchars(site_url('stays')) ?>" href="<?= site_url('stays') ?>">Find green stays near this destination</a></p>
  <label>Start date *</label><input type="date" name="start_date" value="<?= $v('start_date') ?>"><?= $err('start_date') ?>
  <label>End date *</label><input type="date" name="end_date" value="<?= $v('end_date') ?>"><?= $err('end_date') ?>
  <label>Budget (PHP) *</label><input name="budget" inputmode="decimal" value="<?= $v('budget') ?>"><?= $err('budget') ?>
  <label>Travelers *</label><input name="travelers" inputmode="numeric" value="<?= $v('travelers') ?>"><?= $err('travelers') ?>
  <?php if ($mode === 'create'): ?>
    <input type="hidden" name="generated_itinerary" value="<?= $v('generated_itinerary') ?>">
    <label for="preferences">Travel preferences</label>
    <textarea id="preferences" name="preferences" rows="3" maxlength="500" placeholder="Slow travel, local food, nature walks..."></textarea>
    <div class="integration-panel">
      <button id="generate-itinerary" class="btn" type="button" <?= $ai_ready ? '' : 'disabled' ?>>Generate eco itinerary</button>
      <span id="itinerary-status" class="integration-status" aria-live="polite"><?= $ai_ready ? 'Gemini itinerary planner ready' : 'Add GEMINI_API_KEY to enable AI generation.' ?></span>
    </div>
    <div id="itinerary-preview" class="itinerary-preview" aria-live="polite"></div>
  <?php endif; ?>
  <label>Status</label>
  <select name="status">
    <?php foreach (['draft','planned','booked','completed'] as $s): ?>
      <option value="<?= $s ?>" <?= ($trip['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
    <?php endforeach; ?>
  </select><?= $err('status') ?>
  <p><button class="btn"><?= $mode === 'edit' ? 'Save changes' : 'Create trip' ?></button>
     <a href="<?= site_url('trips') ?>">Cancel</a></p>
</form>
<script>
(() => {
  const destination = document.querySelector('#trip-form input[name="destination"]');
  const link = document.getElementById('destination-stays-link');
  if (!destination || !link) return;
  const updateLink = () => {
    const location = destination.value.trim();
    link.href = location ? `${link.dataset.baseUrl}?destination=${encodeURIComponent(location)}` : link.dataset.baseUrl;
  };
  destination.addEventListener('input', updateLink);
  updateLink();
})();
</script>
<?php if ($mode === 'create'): ?>
<script>
(() => {
  const form = document.getElementById('trip-form');
  const button = document.getElementById('generate-itinerary');
  const status = document.getElementById('itinerary-status');
  const preview = document.getElementById('itinerary-preview');
  if (!form || !button || button.disabled) return;

  button.addEventListener('click', async () => {
    button.disabled = true;
    status.textContent = 'Building your itinerary...';
    preview.replaceChildren();
    try {
      const response = await fetch('<?= site_url('trips/generate-itinerary') ?>', {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: new FormData(form)
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Itinerary generation failed.');

      form.elements.generated_itinerary.value = JSON.stringify({ items: result.items });
      const itemsByDay = new Map();
      result.items.forEach((item) => {
        if (!itemsByDay.has(item.day)) itemsByDay.set(item.day, []);
        itemsByDay.get(item.day).push(item);
      });
      const money = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 });
      const totalCost = result.items.reduce((sum, item) => sum + Number(item.estimated_cost || 0), 0);
      const totalEmissions = result.items.reduce((sum, item) => sum + Number(item.estimated_co2_kg || 0), 0);
      const summary = document.createElement('div');
      summary.className = 'itinerary-summary';
      summary.textContent = `${result.items.length} planned stops · ₱${money.format(totalCost)} estimated total · ${totalEmissions.toFixed(1)} kg CO₂`;
      preview.append(summary);

      const startDate = new Date(`${form.elements.start_date.value}T00:00:00Z`);
      const dayHeading = new Intl.DateTimeFormat(undefined, { timeZone: 'UTC', weekday: 'long', month: 'short', day: 'numeric' });
      for (let day = 1; day <= Number(result.days || Math.max(...itemsByDay.keys())); day += 1) {
        const dayItems = (itemsByDay.get(day) || []).sort((a, b) => a.time.localeCompare(b.time));
        const date = new Date(startDate);
        date.setUTCDate(date.getUTCDate() + day - 1);
        const dayCard = document.createElement('section');
        dayCard.className = 'itinerary-day';
        const heading = document.createElement('div');
        heading.className = 'itinerary-day-heading';
        const label = document.createElement('h3');
        label.textContent = `Day ${day}`;
        const dateLabel = document.createElement('span');
        dateLabel.textContent = dayHeading.format(date);
        heading.append(label, dateLabel);
        dayCard.append(heading);

        if (!dayItems.length) {
          const empty = document.createElement('p');
          empty.className = 'itinerary-empty';
          empty.textContent = 'No stops planned yet.';
          dayCard.append(empty);
        }
        dayItems.forEach((item) => {
          const row = document.createElement('article');
          row.className = 'itinerary-item';
          const time = document.createElement('time');
          time.className = 'itinerary-time';
          time.textContent = item.time;
          const details = document.createElement('div');
          details.className = 'itinerary-item-details';
          const description = document.createElement('p');
          description.className = 'itinerary-description';
          description.textContent = item.description;
          const type = document.createElement('span');
          type.className = `itinerary-type itinerary-type-${item.type}`;
          type.textContent = item.type;
          details.append(description, type);
          const estimates = document.createElement('span');
          estimates.className = 'itinerary-estimates';
          estimates.textContent = `₱${money.format(Number(item.estimated_cost || 0))} · ${Number(item.estimated_co2_kg || 0).toFixed(1)} kg CO₂`;
          row.append(time, details, estimates);
          dayCard.append(row);
        });
        preview.append(dayCard);
      }
      status.textContent = 'Itinerary ready. Create the trip to save it.';
    } catch (error) {
      status.textContent = error.message;
    } finally {
      button.disabled = false;
    }
  });
})();
</script>
<?php endif; ?>
<?php include __DIR__.'/../layouts/footer.php'; ?>
