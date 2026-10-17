<?php
// FILE: /tests/HttpTest.php

/**
 * End-to-end HTTP tests against PHP's built-in server (started on a free
 * port with the test database): public API, webhook receiver, CSRF, roles,
 * cross-tenant URL access, and that every main page renders without PHP notices.
 */
class HttpTest extends TestCase {

    const TECHCORP_KEY = 'awb_demo_techcorp_key_000000000000000000000001';
    const AGENCY_KEY = 'awb_demo_marketing_key_00000000000000000000002';

    private $process;
    private $base;
    private $cookieFiles = [];

    public function setUpClass() {
        $port = 18000 + random_int(0, 999);
        $this->base = 'http://127.0.0.1:' . $port;
        $env = array_merge(getenv(), ['DB_NAME' => getenv('DB_NAME'), 'APP_DEBUG' => 'true', 'APP_URL' => $this->base]);
        $this->process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', APP_ROOT . '/public'],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes, APP_ROOT, $env
        );
        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', $port)) {
                return;
            }
            usleep(100000);
        }
        throw new RuntimeException('Test server did not start');
    }

    public function tearDownClass() {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
        }
        foreach ($this->cookieFiles as $file) {
            @unlink($file);
        }
    }

    /** @return array [status, body, headers] */
    private function request($method, $path, $options = []) {
        $ch = curl_init($this->base . $path);
        $headers = isset($options['headers']) ? $options['headers'] : [];
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 20]);
        if (isset($options['json'])) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json']));
        } elseif (isset($options['form'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options['form']));
        } elseif (isset($options['raw'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['raw']);
        }
        if (isset($options['cookies'])) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $options['cookies']);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $options['cookies']);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return [$status, substr($response, $headerSize), substr($response, 0, $headerSize)];
    }

    private function api($method, $path, $json = null, $key = self::TECHCORP_KEY) {
        $options = ['headers' => $key ? ['X-API-KEY: ' . $key] : []];
        if ($json !== null) {
            $options['json'] = $json;
        }
        list($status, $body) = $this->request($method, $path, $options);
        return [$status, json_decode($body, true)];
    }

    private function login($email) {
        $jar = tempnam(sys_get_temp_dir(), 'cj');
        $this->cookieFiles[] = $jar;
        list(, $html) = $this->request('GET', '/login', ['cookies' => $jar]);
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m);
        list($status, , $headers) = $this->request('POST', '/login', ['cookies' => $jar, 'form' => ['csrf_token' => $m[1], 'email' => $email, 'password' => 'password']]);
        $this->assertSame(302, $status);
        $this->assertContains('Location: /dashboard', $headers, 'login as ' . $email);
        return $jar;
    }

    private function csrfFrom($jar, $path) {
        list(, $html) = $this->request('GET', $path, ['cookies' => $jar]);
        preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $html, $m);
        return $m[1];
    }

    // ---------- Public API ----------

    public function testSendMessageRequiresApiKey() {
        list($status, $body) = $this->api('POST', '/api/v1/send-message', ['bot_id' => 1], null);
        $this->assertSame(401, $status);
        list($status) = $this->api('POST', '/api/v1/send-message', ['bot_id' => 1], 'awb_wrong_key_0000000000000000000');
        $this->assertSame(401, $status);
    }

    public function testSendMessageValidatesInput() {
        list($status, $body) = $this->api('POST', '/api/v1/send-message', ['bot_id' => 'x', 'phone_number' => '12345', 'message' => '']);
        $this->assertSame(422, $status);
        $this->assertSame(['bot_id', 'phone_number', 'message'], array_keys($body['details']));

        list($status) = $this->request('POST', '/api/v1/send-message', ['headers' => ['X-API-KEY: ' . self::TECHCORP_KEY, 'Content-Type: text/plain'], 'raw' => 'hi']);
        $this->assertSame(415, $status);
        list($status) = $this->request('POST', '/api/v1/send-message', ['headers' => ['X-API-KEY: ' . self::TECHCORP_KEY, 'Content-Type: application/json'], 'raw' => '{bad json']);
        $this->assertSame(400, $status);
    }

    public function testSendMessageToOtherTenantsBotIs404() {
        list($status, $body) = $this->api('POST', '/api/v1/send-message', ['bot_id' => 4, 'phone_number' => '+15550401010', 'message' => 'hi']);
        $this->assertSame(404, $status);
        $this->assertSame('Bot not found', $body['error']);
    }

    public function testSendMessageRunsTheBotAndReturnsReplies() {
        list($status, $body) = $this->api('POST', '/api/v1/send-message', [
            'bot_id' => 1, 'phone_number' => '+1 555 040 2020', 'message' => 'hello', 'contact_name' => 'Api User', 'metadata' => ['source' => 'website']
        ]);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertTrue($body['success']);
        $this->assertTrue($body['conversation_created']);
        $this->assertSame('interactive', $body['messages'][0]['type']);
        $this->assertContains('Welcome to TechCorp, Api User', $body['messages'][0]['content']);
        $this->assertSame(['Support', 'Sales', 'Business hours'], $body['messages'][0]['quick_replies']);
        $this->assertSame('+15550402020', $this->scalar('SELECT phone_number FROM contacts WHERE id = ?', [$body['contact_id']]), 'phone normalised');

        list($status, $conversation) = $this->api('GET', '/api/v1/conversations/' . $body['conversation_id']);
        $this->assertSame(200, $status);
        $this->assertSame(2, count($conversation['messages']));

        list($status) = $this->api('GET', '/api/v1/conversations/' . $body['conversation_id'], null, self::AGENCY_KEY);
        $this->assertSame(404, $status, 'other tenant cannot read the conversation');
    }

    public function testOutboundMessageViaApi() {
        list($status, $body) = $this->api('POST', '/api/v1/send-message', ['bot_id' => 2, 'phone_number' => '+15550403030', 'message' => 'Your order shipped', 'direction' => 'outbound']);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame('api', $body['messages'][0]['triggered_by']);
    }

    public function testListBots() {
        list($status, $body) = $this->api('GET', '/api/v1/bots', null, self::AGENCY_KEY);
        $this->assertSame(200, $status);
        $ids = array_column($body['data'], 'id');
        sort($ids);
        $this->assertSame([4, 5], $ids);
    }

    public function testApiQuotaReturns429() {
        $used = (int) $this->scalar('SELECT COUNT(*) FROM messages WHERE tenant_id = 1 AND created_at >= ?', [date('Y-m-01 00:00:00')]);
        $this->setTinyPlan(1, ['max_messages_per_month' => $used]);
        list($status, $body) = $this->api('POST', '/api/v1/send-message', ['bot_id' => 1, 'phone_number' => '+15550404040', 'message' => 'hello']);
        $this->assertSame(429, $status);
        $this->assertContains('Plan limit reached', $body['error']);
        $this->exec("UPDATE tenant_subscriptions SET status = 'cancelled' WHERE tenant_id = 1 AND plan_id > 3");
        $this->exec("UPDATE tenant_subscriptions SET status = 'active' WHERE id = 1");
    }

    // ---------- Webhook receiver ----------

    public function testWebhookRequiresMatchingToken() {
        $payload = ['to_phone' => '+15551234567', 'from_phone' => '+15550405050', 'message' => 'hi'];
        list($status) = $this->request('POST', '/api/v1/webhook', ['json' => $payload]);
        $this->assertSame(401, $status);
        list($status) = $this->request('POST', '/api/v1/webhook', ['json' => $payload, 'headers' => ['X-Webhook-Token: verify_token_125']]);
        $this->assertSame(401, $status, 'token of another channel must not work');
    }

    public function testWebhookRoutesToTheChannelsBot() {
        list($status, $body) = $this->request('POST', '/api/v1/webhook', [
            'json' => ['to_phone' => '+15559876543', 'from_phone' => '+15550406060', 'message' => 'What services do you offer?', 'message_id' => 'wamid.1'],
            'headers' => ['X-Webhook-Token: verify_token_125']
        ]);
        $body = json_decode($body, true);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame(4, $body['bot_id']);
        $this->assertContains('social media management', $body['messages'][0]['content']);
        $this->assertSame('2', (string) $this->scalar('SELECT tenant_id FROM conversations WHERE id = ?', [$body['conversation_id']]));
    }

    public function testWebhookVerificationChallenge() {
        list($status, $body) = $this->request('GET', '/api/v1/webhook?hub.mode=subscribe&hub.verify_token=verify_token_123&hub.challenge=abc123');
        $this->assertSame(200, $status);
        $this->assertSame('abc123', $body);
        list($status) = $this->request('GET', '/api/v1/webhook?hub.mode=subscribe&hub.verify_token=nope&hub.challenge=abc');
        $this->assertSame(403, $status);
    }

    // ---------- Web app ----------

    public function testLoginRequiresCsrfToken() {
        $jar = tempnam(sys_get_temp_dir(), 'cj');
        $this->cookieFiles[] = $jar;
        list($status, , $headers) = $this->request('POST', '/login', ['cookies' => $jar, 'form' => ['email' => 'john@techcorp.com', 'password' => 'password']]);
        $this->assertSame(302, $status);
        list($status) = $this->request('GET', '/dashboard', ['cookies' => $jar]);
        $this->assertSame(302, $status, 'not logged in without CSRF token');
    }

    public function testPostWithoutCsrfIsRejected() {
        $jar = $this->login('john@techcorp.com');
        $this->request('POST', '/bots/1/delete', ['cookies' => $jar, 'form' => []]);
        $this->assertSame('1', (string) $this->scalar('SELECT COUNT(*) FROM bots WHERE id = 1'));
    }

    public function testCrossTenantUrlsReturn404() {
        $jar = $this->login('john@techcorp.com');
        foreach (['/bots/4', '/contacts/5', '/conversations/5', '/broadcasts/3', '/billing/invoices/2', '/webhooks/2/logs', '/flows/8/steps'] as $path) {
            list($status) = $this->request('GET', $path, ['cookies' => $jar]);
            $this->assertSame(404, $status, $path);
        }
        $token = $this->csrfFrom($jar, '/dashboard');
        list($status) = $this->request('POST', '/contacts/5/delete', ['cookies' => $jar, 'form' => ['csrf_token' => $token]]);
        $this->assertSame(404, $status);
        $this->assertSame('1', (string) $this->scalar('SELECT COUNT(*) FROM contacts WHERE id = 5'));
    }

    public function testRolePermissions() {
        $agent = $this->login('sarah@techcorp.com');
        foreach (['/bots', '/users', '/billing', '/webhooks', '/settings/api-keys'] as $path) {
            list($status) = $this->request('GET', $path, ['cookies' => $agent]);
            $this->assertSame(403, $status, 'agent on ' . $path);
        }
        list($status) = $this->request('GET', '/inbox', ['cookies' => $agent]);
        $this->assertSame(200, $status);

        $developer = $this->login('mike@techcorp.com');
        list($status) = $this->request('GET', '/webhooks', ['cookies' => $developer]);
        $this->assertSame(200, $status);
        list($status) = $this->request('GET', '/billing', ['cookies' => $developer]);
        $this->assertSame(403, $status);

        $admin = $this->login('john@techcorp.com');
        list($status) = $this->request('GET', '/tenants', ['cookies' => $admin]);
        $this->assertSame(403, $status, 'tenant admin cannot manage tenants');
    }

    public function testTenantAdminCannotCreatePlatformAdmin() {
        $jar = $this->login('john@techcorp.com');
        $token = $this->csrfFrom($jar, '/users/create');
        $this->request('POST', '/users/store', ['cookies' => $jar, 'form' => [
            'csrf_token' => $token, 'name' => 'Evil', 'email' => 'evil@example.com', 'password' => 'password123', 'role' => 'platform_admin'
        ]]);
        $this->assertSame('0', (string) $this->scalar("SELECT COUNT(*) FROM users WHERE email = 'evil@example.com'"));
    }

    public function testAgentReplyIsLoggedAndTakesOverConversation() {
        $jar = $this->login('sarah@techcorp.com');
        $token = $this->csrfFrom($jar, '/conversations/4');
        list($status) = $this->request('POST', '/conversations/4/send-message', ['cookies' => $jar, 'form' => ['csrf_token' => $token, 'message' => 'Hi Fatima, I will send the quote today.']]);
        $this->assertSame(302, $status);
        $this->assertSame('agent', $this->scalar('SELECT triggered_by FROM messages WHERE conversation_id = 4 ORDER BY id DESC LIMIT 1'));
        $this->assertSame('3', (string) $this->scalar('SELECT assigned_to FROM conversations WHERE id = 4'));
    }

    public function testRegistrationCreatesTenantAdminSubscriptionInvoiceAndWelcomeEmail() {
        $jar = tempnam(sys_get_temp_dir(), 'cj');
        $this->cookieFiles[] = $jar;
        list(, $html) = $this->request('GET', '/register', ['cookies' => $jar]);
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $html, $m);
        list($status, , $headers) = $this->request('POST', '/register', ['cookies' => $jar, 'form' => [
            'csrf_token' => $m[1], 'tenant_name' => 'Cafe Nile', 'name' => 'Nour', 'email' => 'nour@cafenile.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'plan_id' => 1
        ]]);
        $this->assertContains('Location: /dashboard', $headers);
        $tenantId = $this->scalar("SELECT tenant_id FROM users WHERE email = 'nour@cafenile.com' AND role = 'tenant_admin'");
        $this->assertNotNull($tenantId);
        $this->assertSame('1', (string) $this->scalar("SELECT plan_id FROM tenant_subscriptions WHERE tenant_id = ? AND status = 'active'", [$tenantId]));
        $this->assertSame('31.90', $this->scalar('SELECT total FROM invoices WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame('welcome', $this->scalar('SELECT type FROM notifications WHERE tenant_id = ?', [$tenantId]));
    }

    /** Submit a form found on $page (fresh CSRF token) and return [status, headers]. */
    private function submit($jar, $page, $action, $fields, $files = []) {
        $fields['csrf_token'] = $this->csrfFrom($jar, $page);
        $ch = curl_init($this->base . $action);
        foreach ($files as $name => $file) {
            $fields[$name] = new CURLFile($file[0], $file[1], $file[2]);
        }
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => empty($files) ? http_build_query($fields) : $fields,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar,
            CURLOPT_HTTPHEADER => ['Referer: ' . $this->base . $page]]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headers = substr($response, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
        curl_close($ch);
        $this->assertTrue($status === 302 || $status === 200, "{$action} returned {$status}");
        $this->assertFalse(strpos($response, 'Fatal error') !== false || strpos($response, 'Warning</b>') !== false, "PHP error on {$action}");
        return [$status, $headers];
    }

    public function testBuilderFormsCreateRecords() {
        $jar = $this->login('john@techcorp.com');
        $count = function ($table) { return (int) $this->scalar("SELECT COUNT(*) FROM {$table} WHERE tenant_id = 1"); };

        $this->submit($jar, '/channels/create', '/channels/store', ['name' => 'Promo Line', 'phone_number' => '+1 555 777 8888', 'provider_type' => 'sandbox', 'provider_config' => '{"token":"abc"}', 'status' => 'active']);
        $this->assertSame(3, $count('channels'));
        $channel = $this->db()->query("SELECT * FROM channels WHERE phone_number = '+15557778888'")->fetch();
        $this->assertContains('enc:v1:', $channel['provider_config'], 'credentials encrypted');

        $this->submit($jar, '/bots/create', '/bots/store', ['name' => 'Promo Bot', 'channel_id' => $channel['id'], 'default_language' => 'en', 'ai_enabled' => '1', 'ai_tone' => 'friendly', 'ai_max_length' => 300, 'status' => 'active']);
        $botId = (int) $this->scalar("SELECT id FROM bots WHERE name = 'Promo Bot'");
        $this->assertTrue($botId > 0);

        $this->submit($jar, "/bots/{$botId}/flows/create", "/bots/{$botId}/flows/store", ['name' => 'Promo', 'trigger_type' => 'keyword', 'trigger_value' => 'promo, deal', 'priority' => 5, 'status' => 'active']);
        $flowId = (int) $this->scalar('SELECT id FROM flows WHERE bot_id = ?', [$botId]);
        $this->submit($jar, "/flows/{$flowId}/steps/create", "/flows/{$flowId}/steps/store", ['action_type' => 'ask_question', 'question' => 'Which city?', 'variable' => 'city', 'options' => 'Cairo, Dubai', 'step_order' => 1]);
        $this->submit($jar, "/flows/{$flowId}/steps/create", "/flows/{$flowId}/steps/store", ['action_type' => 'send_text', 'message' => 'Deals for {{city}} coming up!', 'step_order' => 2]);
        $this->assertSame(['{"question":"Which city?","variable":"city","options":["Cairo","Dubai"]}', '{"message":"Deals for {{city}} coming up!"}'],
            $this->db()->query("SELECT action_config FROM flow_steps WHERE flow_id = {$flowId} ORDER BY step_order")->fetchAll(PDO::FETCH_COLUMN));

        // Simulator (AJAX JSON)
        $token = $this->csrfFrom($jar, "/bots/{$botId}");
        foreach (['promo' => 'Which city?', 'Dubai' => 'Deals for Dubai coming up!'] as $say => $expect) {
            list($status, $body) = $this->request('POST', "/bots/{$botId}/simulate", ['cookies' => $jar, 'form' => ['csrf_token' => $token, 'message' => $say],
                'headers' => ['X-Requested-With: XMLHttpRequest']]);
            $json = json_decode($body, true);
            $this->assertSame(200, $status, $body);
            $this->assertSame($expect, $json['replies'][0]['content']);
        }

        $this->submit($jar, '/knowledge-base/create', '/knowledge-base/store', ['topic' => 'Parking', 'question' => 'Is there parking?', 'answer' => 'Yes, free parking for visitors.', 'keywords' => 'parking, car', 'status' => 'active']);
        $doc = tempnam(sys_get_temp_dir(), 'kb');
        file_put_contents($doc, "Delivery takes two business days across the country.");
        $this->submit($jar, '/knowledge-base', '/knowledge-base/upload', ['topic' => 'Delivery times'], ['document' => [$doc, 'text/plain', 'delivery.txt']]);
        $this->assertContains('two business days', $this->scalar("SELECT answer FROM knowledge_base WHERE topic = 'Delivery times'"));

        $this->submit($jar, '/templates/create', '/templates/store', ['name' => 'Promo', 'category' => 'marketing', 'language' => 'en', 'body' => 'Hi {{name}}, {{deal}} today!', 'status' => 'active']);
        $this->assertSame('["name","deal"]', $this->scalar("SELECT variables FROM templates WHERE name = 'Promo' AND tenant_id = 1"));
        $templateId = (int) $this->scalar("SELECT id FROM templates WHERE name = 'Promo' AND tenant_id = 1");

        $this->submit($jar, '/contacts/create', '/contacts/store', ['phone_number' => '+15550990000', 'name' => 'Promo Fan', 'status' => 'active', 'tags' => 'Promo List', 'custom_fields' => "city=Cairo"]);
        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, "phone_number,name,email,tags
+15550990001,Csv One,one@example.com,Promo List|VIP Customer
not-a-phone,Bad,,
");
        $this->submit($jar, '/contacts', '/contacts/import', [], ['csv_file' => [$csv, 'text/csv', 'contacts.csv']]);
        $this->assertSame('Csv One', $this->scalar("SELECT name FROM contacts WHERE phone_number = '+15550990001'"));
        $tagId = (int) $this->scalar("SELECT id FROM tags WHERE name = 'Promo List' AND tenant_id = 1");

        $this->submit($jar, '/broadcasts/create', '/broadcasts/store', ['name' => 'Promo blast', 'channel_id' => $channel['id'], 'bot_id' => $botId, 'template_id' => $templateId,
            'target_type' => 'tag', 'tag_id' => $tagId, 'template_variables' => 'deal=2 for 1', 'schedule_mode' => 'now']);
        $broadcast = $this->db()->query("SELECT * FROM broadcasts WHERE name = 'Promo blast'")->fetch();
        $this->assertSame('completed', $broadcast['status']);
        $this->assertSame('2', (string) $broadcast['sent_count']);
        $this->assertSame('Hi Csv One, 2 for 1 today!', $this->scalar("SELECT content FROM messages WHERE triggered_by = 'broadcast' AND tenant_id = 1 ORDER BY id DESC LIMIT 1"));

        $this->submit($jar, '/webhooks/create', '/webhooks/store', ['name' => 'Internal', 'url' => 'http://127.0.0.1:9/x', 'events' => ['message.sent'], 'retry_attempts' => 3, 'status' => 'active']);
        $this->assertSame('0', (string) $this->scalar("SELECT COUNT(*) FROM webhooks WHERE name = 'Internal'"), 'private URL rejected (SSRF)');
    }

    public function testBillingAndSettingsForms() {
        $jar = $this->login('john@techcorp.com');
        $this->submit($jar, '/subscription/plans', '/subscription/change-plan', ['plan_id' => 1]);
        $this->assertSame('2', (string) $this->scalar("SELECT plan_id FROM tenant_subscriptions WHERE tenant_id = 1 AND status = 'active'"), 'downgrade blocked: tenant has 2 channels, Starter allows 1');

        list(, $headers) = $this->submit($jar, '/subscription/plans', '/subscription/change-plan', ['plan_id' => 3]);
        $this->assertContains('Location: /billing/invoices/', $headers);
        $invoiceId = (int) $this->scalar("SELECT MAX(id) FROM invoices WHERE tenant_id = 1");
        $this->submit($jar, "/billing/invoices/{$invoiceId}", '/billing/pay-invoice', ['invoice_id' => $invoiceId, 'payment_method' => 'credit_card', 'card_number' => '4242 4242 4242 4242']);
        $this->assertSame('paid', $this->scalar('SELECT status FROM invoices WHERE id = ?', [$invoiceId]));

        $this->submit($jar, '/settings', '/settings/update', ['name' => 'TechCorp Ltd', 'email' => 'hi@techcorp.com', 'timezone' => 'Africa/Cairo', 'website' => 'https://techcorp.example.com']);
        $this->assertSame('Africa/Cairo', $this->scalar('SELECT timezone FROM tenants WHERE id = 1'));

        $png = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
        $this->submit($jar, '/settings', '/settings/logo', [], ['logo' => [$png, 'image/png', 'logo.png']]);
        $logo = $this->scalar('SELECT logo FROM tenants WHERE id = 1');
        $this->assertContains('tenant_1/logos/', $logo);
        list($status) = $this->request('GET', '/files?path=' . rawurlencode($logo), ['cookies' => $jar]);
        $this->assertSame(200, $status);
        $other = $this->login('emma@marketingmasters.com');
        list($status) = $this->request('GET', '/files?path=' . rawurlencode($logo), ['cookies' => $other]);
        $this->assertSame(404, $status, 'other tenant cannot download the logo');
        list($status) = $this->request('GET', '/files?path=' . rawurlencode('../../.env'), ['cookies' => $jar]);
        $this->assertSame(404, $status);
        @unlink(FileUpload::resolve($logo));

        $dev = $this->login('mike@techcorp.com');
        $this->submit($dev, '/settings/api-keys', '/settings/generate-api-key', ['name' => 'CI key']);
        list(, $html) = $this->request('GET', '/settings/api-keys', ['cookies' => $dev]);
        $this->assertTrue(preg_match('/awb_[a-f0-9]{48}/', $html, $m) === 1, 'new raw key shown after creation');
        list(, $html) = $this->request('GET', '/settings/api-keys', ['cookies' => $dev]);
        $this->assertFalse(strpos($html, $m[0]) !== false, 'raw key is shown only once');
        list($status) = $this->api('GET', '/api/v1/bots', null, $m[0]);
        $this->assertSame(200, $status, 'generated key works');
    }

    public function testMainPagesRenderWithoutPhpErrors() {
        $pages = [
            'john@techcorp.com' => ['/dashboard', '/inbox', '/inbox?status=', '/conversations/1', '/contacts', '/contacts?search=a&tag_id=2', '/contacts/1', '/contacts/1/edit', '/contacts/create',
                '/analytics', '/analytics/messages', '/analytics/conversations', '/analytics/channels', '/analytics/broadcasts',
                '/channels', '/channels/create', '/channels/1/edit', '/bots', '/bots/create', '/bots/1', '/bots/1/edit', '/bots/1/flows', '/bots/1/flows/create', '/flows/1/edit',
                '/flows/5/steps', '/flows/5/steps/create', '/flows/5/steps/create?type=branch_condition', '/steps/15/edit', '/steps/13/edit',
                '/knowledge-base', '/knowledge-base/create', '/knowledge-base/1/edit', '/templates', '/templates/create', '/templates/1/edit',
                '/broadcasts', '/broadcasts/create', '/broadcasts/1', '/broadcasts/2', '/webhooks', '/webhooks/create', '/webhooks/1/edit', '/webhooks/1/logs',
                '/settings/api-keys', '/users', '/users/create', '/users/3/edit', '/subscription', '/subscription/plans', '/billing', '/billing/invoices/3',
                '/notifications', '/settings', '/does-not-exist'],
            'admin@platform.com' => ['/dashboard', '/tenants', '/tenants/create', '/tenants/1', '/tenants/1/edit', '/notifications'],
        ];
        foreach ($pages as $email => $paths) {
            $jar = $this->login($email);
            foreach ($paths as $path) {
                list($status, $body) = $this->request('GET', $path, ['cookies' => $jar]);
                $expected = $path === '/does-not-exist' ? 404 : 200;
                $this->assertSame($expected, $status, $email . ' ' . $path);
                foreach (['Warning</b>', 'Notice</b>', 'Deprecated</b>', 'Fatal error', 'View not found', 'Uncaught'] as $needle) {
                    $this->assertFalse(strpos($body, $needle) !== false, "{$needle} on {$path}");
                }
            }
        }
        foreach (['/', '/login', '/register'] as $path) {
            list($status, $body) = $this->request('GET', $path);
            $this->assertSame(200, $status, $path);
            $this->assertFalse(strpos($body, 'Warning</b>') !== false, "Warning on {$path}");
        }
    }
}
