<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Home extends Controller {
	public function index() {
		$this->call->view('pages/home', ['title' => 'Plan Low-Carbon Trips']);
	}
	public function about() {
		$this->call->view('pages/about', ['title' => 'About EcoTrip']);
	}
}
