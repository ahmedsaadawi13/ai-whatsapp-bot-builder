<?php
// FILE: /app/models/Notification.php

/**
 * Notification Model - log of simulated emails sent by the platform.
 */
class Notification extends Model {

    protected $table = 'notifications';
    protected $hasUpdatedAt = false;
    protected $searchable = ['subject', 'recipient'];

    public function existsByDedupeKey($key) {
        return (bool) $this->query("SELECT 1 FROM {$this->table} WHERE dedupe_key = ? LIMIT 1", [$key])->fetch();
    }
}
