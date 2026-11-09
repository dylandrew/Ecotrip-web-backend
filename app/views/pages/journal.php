<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php include __DIR__.'/../layouts/header.php'; ?>
<p class="eyebrow">Keep the moments</p>
<h1>Trip photo journal</h1>
<p class="sub">Add a photo from your camera or library and keep it with the trip.</p>
<?php if (!empty($flash)): ?><div class="alert ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if (!empty($flash_error)): ?><div class="alert bad"><?= htmlspecialchars($flash_error) ?></div><?php endif; ?>
<?php if (!empty($trips)): ?>
<form method="post" action="<?= site_url('journal/upload') ?>" class="card journal-upload" enctype="multipart/form-data">
	<?php csrf_field(); ?>
	<label for="journal-trip">Trip</label>
	<select id="journal-trip" name="trip_id" required>
		<?php foreach ($trips as $trip): ?>
			<option value="<?= (int) $trip['id'] ?>"><?= htmlspecialchars($trip['title']) ?> · <?= htmlspecialchars($trip['destination']) ?></option>
		<?php endforeach; ?>
	</select>
	<label for="journal-photo">Photo</label>
	<div class="camera-controls">
		<button id="open-camera" class="btn" type="button">Open camera</button>
		<span id="camera-status" class="integration-status" aria-live="polite">Or choose an image from your device.</span>
	</div>
	<div id="camera-panel" class="camera-panel" hidden>
		<video id="camera-preview" autoplay muted playsinline aria-label="Live camera preview"></video>
		<div class="camera-actions">
			<button id="capture-photo" class="btn" type="button">Capture photo</button>
			<button id="close-camera" class="camera-close" type="button">Close camera</button>
		</div>
	</div>
	<input id="journal-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" required>
	<label for="journal-caption">Caption</label>
	<input id="journal-caption" type="text" name="caption" maxlength="255" placeholder="A moment worth remembering">
	<button class="btn" type="submit">Add photo</button>
</form>
<script>
(() => {
	const openButton = document.getElementById('open-camera');
	const closeButton = document.getElementById('close-camera');
	const captureButton = document.getElementById('capture-photo');
	const panel = document.getElementById('camera-panel');
	const video = document.getElementById('camera-preview');
	const input = document.getElementById('journal-photo');
	const status = document.getElementById('camera-status');
	let stream = null;

	function stopCamera() {
		if (stream) stream.getTracks().forEach((track) => track.stop());
		stream = null;
		video.srcObject = null;
		panel.hidden = true;
		openButton.hidden = false;
	}

	openButton.addEventListener('click', async () => {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			status.textContent = 'Camera access is unavailable here. Choose an image from your device instead.';
			return;
		}
		openButton.disabled = true;
		status.textContent = 'Requesting camera access...';
		try {
			const permission = await navigator.permissions?.query({ name: 'camera' }).catch(() => null);
			if (permission?.state === 'denied') {
				status.textContent = 'Camera access is blocked. In this site’s browser settings, set Camera to Allow and reload, or choose an image from your device.';
				return;
			}
			stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: 'environment' } } });
			video.srcObject = stream;
			panel.hidden = false;
			openButton.hidden = true;
			status.textContent = 'Camera is on. Capture a photo when ready.';
			await video.play();
		} catch (error) {
			status.textContent = error.name === 'NotAllowedError'
				? 'Camera access is blocked. In this site’s browser settings, set Camera to Allow and reload, or choose an image from your device.'
				: 'Could not open the camera. Choose an image from your device instead.';
		} finally {
			openButton.disabled = false;
		}
	});

	captureButton.addEventListener('click', () => {
		if (!video.videoWidth || !video.videoHeight) {
			status.textContent = 'Wait for the camera preview, then try again.';
			return;
		}
		const canvas = document.createElement('canvas');
		canvas.width = video.videoWidth;
		canvas.height = video.videoHeight;
		canvas.getContext('2d').drawImage(video, 0, 0);
		canvas.toBlob((blob) => {
			if (!blob) {
				status.textContent = 'The photo could not be captured. Please try again.';
				return;
			}
			const file = new File([blob], `ecotrip-${Date.now()}.jpg`, { type: 'image/jpeg' });
			const transfer = new DataTransfer();
			transfer.items.add(file);
			input.files = transfer.files;
			status.textContent = 'Photo captured. Add a caption or save it to your journal.';
			stopCamera();
		}, 'image/jpeg', 0.9);
	});

	closeButton.addEventListener('click', stopCamera);
	window.addEventListener('pagehide', stopCamera);
})();
</script>
<?php else: ?>
<div class="todo">Create a trip before adding journal photos.</div>
<?php endif; ?>
<div class="journal-grid">
	<?php foreach (($entries ?? []) as $entry): ?>
		<article class="journal-entry">
			<img src="<?= base_url($entry['photo_path']) ?>" alt="<?= htmlspecialchars($entry['caption'] ?: 'Photo from ' . $entry['trip_title']) ?>" loading="lazy">
			<div class="journal-entry-copy"><span class="feature-index"><?= htmlspecialchars($entry['trip_title']) ?></span>
				<?php if (!empty($entry['caption'])): ?><p><?= htmlspecialchars($entry['caption']) ?></p><?php endif; ?>
				<time datetime="<?= htmlspecialchars($entry['taken_at']) ?>"><?= htmlspecialchars(date('M j, Y', strtotime($entry['taken_at']))) ?></time>
				<form method="post" action="<?= site_url('journal/' . (int) $entry['id'] . '/delete') ?>">
					<?php csrf_field(); ?>
					<button class="camera-close" type="submit" onclick="return confirm('Delete this journal photo?');">Delete photo</button>
				</form>
			</div>
		</article>
	<?php endforeach; ?>
</div>
<?php if (empty($entries)): ?><div class="todo">Your trip photos will appear here.</div><?php endif; ?>
<?php include __DIR__.'/../layouts/footer.php'; ?>
