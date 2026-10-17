<?php
// FILE: /app/core/Crypto.php

/**
 * Symmetric encryption for secrets stored in the database
 * (e.g. WhatsApp provider credentials). Uses AES-256-CBC + HMAC-SHA256
 * keyed from APP_KEY in .env.
 */
class Crypto {

    const PREFIX = 'enc:v1:';

    private static function key() {
        $key = getenv('APP_KEY');
        if (!$key) {
            // Development fallback; README tells you to set APP_KEY in production.
            $key = 'ai-whatsapp-bot-builder-insecure-dev-key';
        }
        return hash('sha256', $key, true);
    }

    public static function encrypt($plain) {
        if ($plain === null || $plain === '') {
            return $plain;
        }
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv);
        $mac = hash_hmac('sha256', $iv . $cipher, self::key(), true);
        return self::PREFIX . base64_encode($iv . $mac . $cipher);
    }

    /**
     * Decrypt a value. Values without the prefix (e.g. seed data) are
     * returned unchanged so legacy plaintext keeps working.
     */
    public static function decrypt($value) {
        if (!is_string($value) || strpos($value, self::PREFIX) !== 0) {
            return $value;
        }
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 48) {
            return null;
        }
        $iv = substr($raw, 0, 16);
        $mac = substr($raw, 16, 32);
        $cipher = substr($raw, 48);
        if (!hash_equals(hash_hmac('sha256', $iv . $cipher, self::key(), true), $mac)) {
            return null;
        }
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? null : $plain;
    }
}
