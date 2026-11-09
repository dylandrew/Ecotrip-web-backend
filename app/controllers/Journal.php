<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Journal extends Controller {
	public function __construct() {
		parent::__construct();
		auth_require_login();
		$this->call->model('Journal_model');
	}

	private function uid() { return auth_user_id(); }
	private function go($path) { redirect($path); exit; }

	public function index() {
		$this->call->view('pages/journal', [
			'title' => 'Photo Journal',
			'trips' => $this->Journal_model->trips_for_user($this->uid()),
			'entries' => $this->Journal_model->entries_for_user($this->uid()),
			'flash' => $this->session->flashdata('success'),
			'flash_error' => $this->session->flashdata('error'),
		]);
	}

	public function upload() {
		integration_require_csrf();
		$trip_id = (int) ($_POST['trip_id'] ?? 0);
		$file = $_FILES['photo'] ?? null;
		if (!$this->Journal_model->trip_belongs_to_user($trip_id, $this->uid())) {
			$this->session->set_flashdata('error', 'Choose one of your trips first.');
			$this->go('journal');
		}
		if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 8 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
			$this->session->set_flashdata('error', 'Choose a photo no larger than 8 MB and try again.');
			$this->go('journal');
		}

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime = $finfo->file($file['tmp_name']);
		$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
		if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
			$this->session->set_flashdata('error', 'Use a valid JPEG, PNG, or WebP image.');
			$this->go('journal');
		}

		$directory = ROOT_DIR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'journal';
		if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
			$this->session->set_flashdata('error', 'Photo storage is unavailable.');
			$this->go('journal');
		}
		if (!extension_loaded('gd')) {
			$this->session->set_flashdata('error', 'Image processing is unavailable.');
			$this->go('journal');
		}
		$image = match ($mime) {
			'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
			'image/png' => @imagecreatefrompng($file['tmp_name']),
			'image/webp' => @imagecreatefromwebp($file['tmp_name']),
		};
		if (!$image) {
			$this->session->set_flashdata('error', 'The photo could not be processed.');
			$this->go('journal');
		}
		$filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
		$destination = $directory . DIRECTORY_SEPARATOR . $filename;
		$saved = match ($mime) {
			'image/jpeg' => imagejpeg($image, $destination, 85),
			'image/png' => imagepng($image, $destination, 9),
			'image/webp' => imagewebp($image, $destination, 85),
		};
		imagedestroy($image);
		if (!$saved || !is_file($destination)) {
			$this->session->set_flashdata('error', 'The photo could not be saved.');
			$this->go('journal');
		}
		$caption = trim(strip_tags((string) ($_POST['caption'] ?? '')));
		$this->Journal_model->create_entry($trip_id, 'uploads/journal/' . $filename, mb_substr($caption, 0, 255));
		$this->session->set_flashdata('success', 'Photo added to your trip journal.');
		$this->go('journal');
	}

	public function delete($id) {
		integration_require_csrf();
		$entry = $this->Journal_model->delete_entry((int) $id, $this->uid());
		if (!$entry) {
			$this->session->set_flashdata('error', 'That journal photo was not found.');
			$this->go('journal');
		}
		$relative = ltrim(str_replace('\\', '/', (string) $entry['photo_path']), '/');
		if (str_starts_with($relative, 'uploads/journal/') && preg_match('/^[a-zA-Z0-9._/-]+$/', $relative)) {
			$path = ROOT_DIR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
			if (is_file($path)) @unlink($path);
		}
		$this->session->set_flashdata('success', 'Journal photo deleted.');
		$this->go('journal');
	}
}
