<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<?php $options = $options ?? []; ?>
<?php $trips = $trips ?? []; ?>
<?php $selected_trip = $selected_trip ?? null; ?>
<?php $planned_travel = $planned_travel ?? []; ?>
<?php $googleModes = ['bus' => 'transit', 'train' => 'transit', 'ferry' => 'transit', 'bike' => 'bicycling', 'walk' => 'walking', 'carpool' => 'driving']; ?>
<div class="routes-heading">
	<div>
		<p class="eyebrow">Move a little lighter</p>
		<h1>Low-carbon routes</h1>
		<p class="sub"><?= $selected_trip ? 'Travel plans and route options for ' . htmlspecialchars($selected_trip['destination']) . '.' : 'Choose one of your trips to see its planned travel and routes for that destination.' ?></p>
	</div>
</div>
<form class="route-trip-filter" method="get" action="<?= site_url('transport') ?>">
	<label for="route-trip">Show routes for a trip</label>
	<select id="route-trip" name="trip_id" <?= !$trips ? 'disabled' : '' ?>>
		<option value="">All route options</option>
		<?php foreach ($trips as $trip): ?>
			<option value="<?= (int) $trip['id'] ?>" <?= $selected_trip && (int) $selected_trip['id'] === (int) $trip['id'] ? 'selected' : '' ?>>
				<?= htmlspecialchars($trip['title']) ?> · <?= htmlspecialchars($trip['destination']) ?>
			</option>
		<?php endforeach; ?>
	</select>
	<button class="btn" type="submit" <?= !$trips ? 'disabled' : '' ?>>Show trip routes</button>
	<?php if ($selected_trip): ?><a href="<?= site_url('transport') ?>">Show all routes</a><?php endif; ?>
</form>
<?php if (!$trips && empty($_SESSION['ecotrip_user'])): ?>
	<p class="route-help"><a href="<?= site_url('login') ?>">Sign in</a> to match routes to your own trips.</p>
<?php elseif (!$trips): ?>
	<p class="route-help">Create a trip first to filter routes by destination.</p>
<?php endif; ?>
<?php if ($selected_trip): ?>
<section class="planned-travel" aria-labelledby="planned-travel-title">
	<div class="planned-travel-heading">
		<div>
			<h2 id="planned-travel-title">Planned travel</h2>
			<p class="sub">Transport stops saved in <?= htmlspecialchars($selected_trip['title']) ?>’s itinerary. These are plans, not confirmed transport bookings.</p>
		</div>
		<a href="<?= site_url('trips/' . (int) $selected_trip['id']) ?>">View trip itinerary</a>
	</div>
	<?php if ($planned_travel): ?>
		<div class="planned-travel-list">
			<?php foreach ($planned_travel as $item): ?>
				<article class="planned-travel-item">
					<span class="planned-travel-time">Day <?= (int) $item['day_number'] ?> · <?= htmlspecialchars(substr((string) ($item['start_time'] ?? ''), 0, 5) ?: 'Time TBD') ?></span>
					<span><?= htmlspecialchars($item['description'] ?? '') ?></span>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else: ?>
		<p class="route-help">No transport stops are saved in this trip yet. Add travel to its itinerary to see it here.</p>
	<?php endif; ?>
</section>
<?php endif; ?>
<h2 class="route-options-title"><?= $selected_trip ? 'Routes near ' . htmlspecialchars($selected_trip['destination']) : 'Route options' ?></h2>
<?php if ($selected_trip && !$options): ?>
	<p class="route-help">There are no catalog routes near <?= htmlspecialchars($selected_trip['destination']) ?> yet. Your planned travel is shown above.</p>
<?php endif; ?>
<section class="route-explorer" aria-label="Map and route options">
	<div class="route-map-wrap">
		<div id="route-map" class="route-map" role="region" aria-label="Map of low-carbon routes"></div>
		<p class="map-note">Durations are estimates. Google Maps may show different live travel times.</p>
	</div>
	<aside class="route-list" aria-labelledby="route-list-title">
		<h2 id="route-list-title">Matching routes</h2>
		<div class="route-options">
			<?php foreach ($options as $index => $o): ?>
			<div class="route-option" data-route-index="<?= (int) $index ?>">
				<button class="route-select" type="button" data-route-index="<?= (int) $index ?>">
					<span class="route-mode"><?= htmlspecialchars(ucfirst($o['mode'])) ?></span>
					<span class="route-name"><?= htmlspecialchars($o['origin']) ?> &rarr; <?= htmlspecialchars($o['destination']) ?></span>
					<span class="route-meta"><?= is_numeric($o['distance_km']) ? number_format((float) $o['distance_km'], 1).' km' : 'Distance unavailable' ?> <span aria-hidden="true">&middot;</span> Est. <?= htmlspecialchars($o['estimated_duration'] ?? 'unavailable') ?> <span aria-hidden="true">&middot;</span> <?= number_format((float) $o['co2_kg_per_person'], 1) ?> kg CO&#8322;</span>
				</button>
				<a class="route-google" href="https://www.google.com/maps/dir/?api=1&amp;origin=<?= rawurlencode($o['origin']) ?>&amp;destination=<?= rawurlencode($o['destination']) ?>&amp;travelmode=<?= $googleModes[$o['mode']] ?? 'transit' ?>" target="_blank" rel="noopener noreferrer">Open in Google Maps</a>
			</div>
			<?php endforeach; ?>
			<?php if (!$options): ?><p class="route-help">No matching route options.</p><?php endif; ?>
		</div>
	</aside>
</section>
<?php if ($options): ?><div class="card" style="overflow-x:auto"><table>
<tr><th>Mode</th><th>From</th><th>To</th><th>Distance</th><th>CO₂ / person</th><th>Cost</th></tr>
<?php foreach ($options as $o): ?>
<tr><td><span class="tag"><?= htmlspecialchars($o['mode']) ?></span></td><td><?= htmlspecialchars($o['origin']) ?></td><td><?= htmlspecialchars($o['destination']) ?></td>
<td><?= number_format($o['distance_km'], 1) ?> km</td><td><?= number_format($o['co2_kg_per_person'], 1) ?> kg</td><td>₱<?= number_format($o['cost'], 2) ?></td></tr>
<?php endforeach; ?>
</table></div><?php endif; ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(() => {
	const routes = <?= json_encode(array_values($options), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?>;
	const selectedDestination = <?= json_encode($selected_trip['destination'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
	const userAddress = <?= json_encode($user_address ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
	const fallbackLocations = {
		'manila': [14.5995, 120.9842], 'baguio': [16.4023, 120.5960],
		'sagada': [17.0850, 120.9010], 'cebu city': [10.3157, 123.8854],
		'siargao': [9.8500, 126.0500], 'burnham park': [16.4122, 120.5946],
		'calamba': [14.2117, 121.1653], 'siargao town': [9.8500, 126.0500],
		'tuason point': [9.8000, 126.1600]
	};
	if (typeof L === 'undefined') {
		document.getElementById('route-map').innerHTML = '<p class="route-help" style="padding:20px">The map could not load. Please refresh the page or check your internet connection.</p>';
		return;
	}
	const colors = ['#bd5f43', '#347654', '#367b93', '#bd8a36', '#6f6a9a', '#638248', '#9b5367'];
	const map = L.map('route-map', { scrollWheelZoom: false });
	const layer = L.featureGroup().addTo(map);
	const plotted = [];
	const routeLayers = [];
	const buttons = [...document.querySelectorAll('.route-select')];
	const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
		'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
	})[character]);

	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
	}).addTo(map);

	async function geocode(place) {
		const key = String(place).trim().toLowerCase();
		if (fallbackLocations[key]) return fallbackLocations[key];
		const params = new URLSearchParams({ q: place + ', Philippines', format: 'jsonv2', limit: '1', countrycodes: 'ph' });
		const response = await fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
			headers: { 'Accept': 'application/json' }
		});
		if (!response.ok) throw new Error('Location lookup failed');
		const results = await response.json();
		return results.length ? [Number(results[0].lat), Number(results[0].lon)] : null;
	}

	async function roadRoute(origin, destination, mode) {
		const profile = mode === 'bike' ? 'bike' : (mode === 'walk' ? 'foot' : 'driving');
		const coordinates = `${origin[1]},${origin[0]};${destination[1]},${destination[0]}`;
		const response = await fetch(`https://router.project-osrm.org/route/v1/${profile}/${coordinates}?overview=full&geometries=geojson&steps=false`);
		if (!response.ok) return null;
		const data = await response.json();
		return data.routes?.[0]?.geometry?.coordinates?.map(([lon, lat]) => [lat, lon]) || null;
	}

	routes.forEach((route, index) => {
		const color = colors[index % colors.length];
		const googleModes = { bus: 'transit', train: 'transit', ferry: 'transit', bike: 'bicycling', walk: 'walking', carpool: 'driving' };
		const googleUrl = `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(route.origin)}&destination=${encodeURIComponent(route.destination)}&travelmode=${googleModes[route.mode] || 'transit'}`;
		const popup = `<strong>${escapeHtml(route.origin)} &rarr; ${escapeHtml(route.destination)}</strong><br>${escapeHtml(route.mode)} &middot; Est. ${escapeHtml(route.estimated_duration || 'unavailable')}<br>${Number(route.co2_kg_per_person || 0).toFixed(1)} kg CO&#8322; per person<br><a href="${googleUrl}" target="_blank" rel="noopener noreferrer">Open in Google Maps</a>`;

		Promise.all([geocode(route.origin), geocode(route.destination)]).then(async ([origin, destination]) => {
			if (!origin || !destination) return;
			const roadCoordinates = await roadRoute(origin, destination, route.mode);
			const line = L.polyline(roadCoordinates || [origin, destination], { color, weight: 5, opacity: 0.85 }).addTo(layer);
			line.bindPopup(popup);
			L.circleMarker(origin, { radius: 7, color, fillColor: '#fff', fillOpacity: 1, weight: 2 }).bindTooltip(escapeHtml(route.origin)).addTo(layer);
			L.circleMarker(destination, { radius: 8, color, fillColor: color, fillOpacity: 1, weight: 2 }).bindTooltip(escapeHtml(route.destination)).addTo(layer);
			plotted.push(origin, destination);
			routeLayers[index] = { line, color };
			line.on('click', () => selectRoute(index));
			if (plotted.length) map.fitBounds(L.latLngBounds(plotted).pad(0.12));
		}).catch(() => {});
	});

	if (selectedDestination) {
		geocode(selectedDestination).then(async (destination) => {
			if (userAddress && destination) {
				const origin = await geocode(userAddress);
				if (origin) {
					const homeRoute = await roadRoute(origin, destination, 'carpool');
					L.polyline(homeRoute || [origin, destination], { color: '#25301f', weight: 5, opacity: 0.9, dashArray: '8 10' }).bindPopup(`<strong>Home to trip destination</strong><br>${escapeHtml(userAddress)} &rarr; ${escapeHtml(selectedDestination)}<br><a href="https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(userAddress)}&destination=${encodeURIComponent(selectedDestination)}&travelmode=driving" target="_blank" rel="noopener noreferrer">Open in Google Maps</a>`).addTo(layer);
					L.circleMarker(origin, { radius: 9, color: '#25301f', fillColor: '#f3ede2', fillOpacity: 1, weight: 3 }).bindTooltip('Your home address').addTo(layer);
					map.fitBounds(L.latLngBounds([origin, destination]).pad(0.15));
				}
			}
			if (!destination) return;
			L.circleMarker(destination, { radius: 10, color: '#347654', fillColor: '#bd5f43', fillOpacity: 0.9, weight: 3 })
				.bindTooltip(`Planned destination: ${selectedDestination}`, { permanent: false })
				.addTo(layer);
			if (!userAddress) map.setView(destination, 11);
		}).catch(() => {});
	}

	function selectRoute(index) {
		routeLayers.forEach((item) => {
			if (item) item.line.setStyle({ weight: 4, opacity: 0.55 });
		});
		const selected = routeLayers[index];
		if (!selected) return;
		selected.line.setStyle({ weight: 6, opacity: 1 });
		buttons.forEach((button) => button.closest('.route-option').classList.toggle('is-selected', Number(button.dataset.routeIndex) === index));
		map.fitBounds(selected.line.getBounds(), { padding: [36, 36], maxZoom: 9 });
		selected.line.openPopup();
	}

	buttons.forEach((button) => button.addEventListener('click', () => selectRoute(Number(button.dataset.routeIndex))));
	requestAnimationFrame(() => map.invalidateSize(false));
	window.addEventListener('resize', () => map.invalidateSize(false));
	if (plotted.length) map.fitBounds(L.latLngBounds(plotted).pad(0.12));
	else map.setView([12.8, 122.0], 5);
})();
</script>
<?php include __DIR__.'/../layouts/footer.php'; ?>
