<?php
// FILE: /app/services/WebhookDispatcher.php

/**
 * Webhook Dispatcher
 *
 * Sends signed JSON events to tenant webhooks and logs every attempt.
 * Failed deliveries are retried by cron/retry_webhooks.php until the
 * webhook's retry_attempts limit is reached.
 *
 * Signature header: X-Signature: sha256=HMAC_SHA256(body, webhook secret)
 *
 * In web requests, events are queued and delivered after the response has
 * been sent (fastcgi_finish_request on PHP-FPM), so a slow endpoint never
 * delays the API caller. CLI scripts (cron, tests) deliver immediately.
 */
class WebhookDispatcher {

    const TIMEOUT_SECONDS = 5;

    /** Tests can replace the HTTP transport: function ($url, $body, $headers) => result array. */
    public static $fakeTransport = null;

    /** Deliveries waiting for the end of the request. */
    private static $queue = [];

    /**
     * Fire an event to every matching active webhook of the tenant.
     */
    public static function dispatch($tenantId, $event, $data, $botId = null) {
        try {
            $webhooks = (new Webhook())->getForEvent($event, $tenantId, $botId);
            foreach ($webhooks as $webhook) {
                $payload = [
                    'event' => $event,
                    'tenant_id' => (int) $tenantId,
                    'webhook_id' => (int) $webhook['id'],
                    'timestamp' => gmdate('c'),
                    'data' => $data
                ];
                self::enqueue($webhook, $event, json_encode($payload));
            }
        } catch (Exception $e) {
            // Webhook problems must never break message processing.
            error_log('Webhook dispatch error: ' . $e->getMessage());
        }
    }

    private static function enqueue($webhook, $event, $body) {
        if (PHP_SAPI === 'cli') {
            self::deliver($webhook, $event, $body, 1);
            return;
        }
        if (empty(self::$queue)) {
            register_shutdown_function([__CLASS__, 'flush']);
        }
        self::$queue[] = [$webhook, $event, $body];
    }

    /** Shutdown handler: finish the HTTP response, then deliver queued events. */
    public static function flush() {
        if (empty(self::$queue)) {
            return;
        }
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $queue = self::$queue;
        self::$queue = [];
        foreach ($queue as $item) {
            try {
                self::deliver($item[0], $item[1], $item[2], 1);
            } catch (Exception $e) {
                error_log('Webhook delivery error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Deliver one payload and log the attempt.
     */
    public static function deliver($webhook, $event, $body, $attempt) {
        $result = self::post($webhook['url'], $body, $webhook['secret']);

        (new WebhookLog())->create([
            'webhook_id' => $webhook['id'],
            'event_type' => $event,
            'payload' => $body,
            'response_code' => $result['code'],
            'response_body' => mb_substr((string) $result['body'], 0, 2000, 'UTF-8'),
            'status' => $result['ok'] ? 'success' : 'failed',
            'attempt' => $attempt
        ]);

        return $result;
    }

    /**
     * Retry failed deliveries (called from cron).
     *
     * @return int number of retries performed
     */
    public static function retryFailed($limit = 50) {
        $logModel = new WebhookLog();
        $count = 0;
        foreach ($logModel->getRetryable($limit) as $log) {
            $logModel->markRetried($log['id']);
            $webhook = ['id' => $log['webhook_id'], 'url' => $log['url'], 'secret' => $log['secret']];
            self::deliver($webhook, $log['event_type'], $log['payload'], (int) $log['attempt'] + 1);
            $count++;
        }
        return $count;
    }

    /**
     * POST JSON to a URL. Used for webhooks and for the flow "call_webhook" action.
     *
     * @return array ['ok' => bool, 'code' => int|null, 'body' => string]
     */
    public static function post($url, $body, $secret = null) {
        $target = self::resolveSafe($url);
        if ($target === null) {
            return ['ok' => false, 'code' => null, 'body' => 'Blocked: URL must be public http(s)'];
        }

        $headers = [
            'Content-Type: application/json',
            'User-Agent: AI-WhatsApp-Bot-Builder/1.0'
        ];
        if ($secret) {
            $headers[] = 'X-Signature: sha256=' . hash_hmac('sha256', $body, $secret);
        }

        if (is_callable(self::$fakeTransport)) {
            return call_user_func(self::$fakeTransport, $url, $body, $headers);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS
            ]);
            if ($target['ip'] !== null) {
                // Connect to the IP we vetted, so DNS cannot be re-pointed (rebinding).
                curl_setopt($ch, CURLOPT_RESOLVE, [$target['host'] . ':' . $target['port'] . ':' . $target['ip']]);
            }
            $response = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                return ['ok' => false, 'code' => null, 'body' => 'Connection error: ' . $error];
            }
            return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'body' => (string) $response];
        }

        if ($target['ip'] !== null && !filter_var($target['host'], FILTER_VALIDATE_IP)) {
            // Without curl we cannot pin the IP safely; refuse rather than risk rebinding.
            return ['ok' => false, 'code' => null, 'body' => 'The curl extension is required for webhooks to hostnames'];
        }
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => self::TIMEOUT_SECONDS,
            'ignore_errors' => true,
            'follow_location' => 0
        ]]);
        $response = @file_get_contents($url, false, $context);
        $code = null;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        return ['ok' => $code !== null && $code >= 200 && $code < 300, 'code' => $code, 'body' => (string) $response];
    }

    /**
     * SSRF protection: only http(s) URLs whose host resolves exclusively to
     * public IPs. Set WEBHOOK_ALLOW_PRIVATE=true in .env to allow local
     * targets during development.
     */
    public static function isSafeUrl($url) {
        return self::resolveSafe($url) !== null;
    }

    /**
     * @return array|null ['host' => string, 'port' => int, 'ip' => ?string] or null if unsafe
     */
    private static function resolveSafe($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
        $port = parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80);

        if (getenv('WEBHOOK_ALLOW_PRIVATE') === 'true') {
            return ['host' => $host, 'port' => (int) $port, 'ip' => null];
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (empty($ips)) {
            return null;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }
        return ['host' => $host, 'port' => (int) $port, 'ip' => $ips[0]];
    }
}
