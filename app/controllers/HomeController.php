<?php
// FILE: /app/controllers/HomeController.php

/**
 * Home Controller
 *
 * Handles home page and public landing pages.
 */
class HomeController extends Controller {

    /**
     * Show home page
     */
    public function index() {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }

        $this->view('home/index', [
            'title' => 'AI WhatsApp Bot Builder'
        ], null);
    }
}
