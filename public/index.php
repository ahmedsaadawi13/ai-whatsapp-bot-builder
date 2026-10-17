<?php
// FILE: /public/index.php

/**
 * AI WhatsApp Bot Builder - Front Controller
 *
 * Every HTTP request enters here, is routed to a controller method,
 * and exceptions are converted into friendly error pages / JSON.
 */

require_once __DIR__ . '/../app/bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', app_debug() ? '1' : '0');
ini_set('log_errors', '1');

// Basic security headers for every response
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header_remove('X-Powered-By');

$router = new Router();

// ===================================================================
// PUBLIC ROUTES
// ===================================================================
$router->get('/', 'HomeController@index');
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->post('/logout', 'AuthController@logout');
$router->get('/logout', 'AuthController@logout');

// ===================================================================
// PUBLIC API (X-API-KEY / webhook token authentication)
// ===================================================================
$router->post('/api/v1/send-message', 'ApiController@sendMessage');
$router->get('/api/v1/bots', 'ApiController@listBots');
$router->get('/api/v1/conversations/{id}', 'ApiController@getConversation');
$router->get('/api/v1/webhook', 'ApiController@verifyWebhook');
$router->post('/api/v1/webhook', 'ApiController@webhook');

// ===================================================================
// AUTHENTICATED ROUTES
// ===================================================================
$router->get('/dashboard', 'DashboardController@index');
$router->get('/notifications', 'NotificationController@index');
$router->get('/files', 'FileController@serve');

// Tenants (platform admin)
$router->get('/tenants', 'TenantController@index');
$router->get('/tenants/create', 'TenantController@create');
$router->post('/tenants/store', 'TenantController@store');
$router->get('/tenants/{id}', 'TenantController@show');
$router->get('/tenants/{id}/edit', 'TenantController@edit');
$router->post('/tenants/{id}/update', 'TenantController@update');
$router->post('/tenants/{id}/delete', 'TenantController@delete');

// Users
$router->get('/users', 'UserController@index');
$router->get('/users/create', 'UserController@create');
$router->post('/users/store', 'UserController@store');
$router->get('/users/{id}/edit', 'UserController@edit');
$router->post('/users/{id}/update', 'UserController@update');
$router->post('/users/{id}/delete', 'UserController@delete');

// Channels
$router->get('/channels', 'ChannelController@index');
$router->get('/channels/create', 'ChannelController@create');
$router->post('/channels/store', 'ChannelController@store');
$router->get('/channels/{id}/edit', 'ChannelController@edit');
$router->post('/channels/{id}/update', 'ChannelController@update');
$router->post('/channels/{id}/delete', 'ChannelController@delete');

// Bots
$router->get('/bots', 'BotController@index');
$router->get('/bots/create', 'BotController@create');
$router->post('/bots/store', 'BotController@store');
$router->get('/bots/{id}', 'BotController@show');
$router->get('/bots/{id}/edit', 'BotController@edit');
$router->post('/bots/{id}/update', 'BotController@update');
$router->post('/bots/{id}/delete', 'BotController@delete');
$router->post('/bots/{id}/toggle-status', 'BotController@toggleStatus');
$router->post('/bots/{id}/simulate', 'BotController@simulate');

// Flows
$router->get('/bots/{botId}/flows', 'FlowController@index');
$router->get('/bots/{botId}/flows/create', 'FlowController@create');
$router->post('/bots/{botId}/flows/store', 'FlowController@store');
$router->get('/flows/{id}/edit', 'FlowController@edit');
$router->post('/flows/{id}/update', 'FlowController@update');
$router->post('/flows/{id}/delete', 'FlowController@delete');

// Flow steps
$router->get('/flows/{flowId}/steps', 'FlowStepController@index');
$router->get('/flows/{flowId}/steps/create', 'FlowStepController@create');
$router->post('/flows/{flowId}/steps/store', 'FlowStepController@store');
$router->get('/steps/{id}/edit', 'FlowStepController@edit');
$router->post('/steps/{id}/update', 'FlowStepController@update');
$router->post('/steps/{id}/delete', 'FlowStepController@delete');
$router->post('/steps/{id}/move-up', 'FlowStepController@moveUp');
$router->post('/steps/{id}/move-down', 'FlowStepController@moveDown');

// Knowledge base
$router->get('/knowledge-base', 'KnowledgeBaseController@index');
$router->get('/knowledge-base/create', 'KnowledgeBaseController@create');
$router->post('/knowledge-base/store', 'KnowledgeBaseController@store');
$router->post('/knowledge-base/upload', 'KnowledgeBaseController@upload');
$router->get('/knowledge-base/{id}/edit', 'KnowledgeBaseController@edit');
$router->post('/knowledge-base/{id}/update', 'KnowledgeBaseController@update');
$router->post('/knowledge-base/{id}/delete', 'KnowledgeBaseController@delete');

// Contacts
$router->get('/contacts', 'ContactController@index');
$router->get('/contacts/create', 'ContactController@create');
$router->post('/contacts/store', 'ContactController@store');
$router->post('/contacts/import', 'ContactController@import');
$router->get('/contacts/{id}', 'ContactController@show');
$router->get('/contacts/{id}/edit', 'ContactController@edit');
$router->post('/contacts/{id}/update', 'ContactController@update');
$router->post('/contacts/{id}/delete', 'ContactController@delete');

// Inbox & conversations
$router->get('/inbox', 'InboxController@index');
$router->get('/conversations/{id}', 'ConversationController@show');
$router->post('/conversations/{id}/send-message', 'ConversationController@sendMessage');
$router->post('/conversations/{id}/rewrite', 'ConversationController@rewrite');
$router->post('/conversations/{id}/assign', 'ConversationController@assign');
$router->post('/conversations/{id}/status', 'ConversationController@setStatus');
$router->post('/conversations/{id}/close', 'ConversationController@close');
$router->post('/conversations/{id}/reopen', 'ConversationController@reopen');

// Templates
$router->get('/templates', 'TemplateController@index');
$router->get('/templates/create', 'TemplateController@create');
$router->post('/templates/store', 'TemplateController@store');
$router->get('/templates/{id}/edit', 'TemplateController@edit');
$router->post('/templates/{id}/update', 'TemplateController@update');
$router->post('/templates/{id}/delete', 'TemplateController@delete');

// Broadcasts
$router->get('/broadcasts', 'BroadcastController@index');
$router->get('/broadcasts/create', 'BroadcastController@create');
$router->post('/broadcasts/store', 'BroadcastController@store');
$router->get('/broadcasts/{id}', 'BroadcastController@show');
$router->post('/broadcasts/{id}/send', 'BroadcastController@send');
$router->post('/broadcasts/{id}/cancel', 'BroadcastController@cancel');
$router->post('/broadcasts/{id}/delete', 'BroadcastController@delete');

// Webhooks
$router->get('/webhooks', 'WebhookController@index');
$router->get('/webhooks/create', 'WebhookController@create');
$router->post('/webhooks/store', 'WebhookController@store');
$router->get('/webhooks/{id}/edit', 'WebhookController@edit');
$router->post('/webhooks/{id}/update', 'WebhookController@update');
$router->post('/webhooks/{id}/delete', 'WebhookController@delete');
$router->post('/webhooks/{id}/test', 'WebhookController@test');
$router->get('/webhooks/{id}/logs', 'WebhookController@logs');

// Analytics & reports
$router->get('/analytics', 'AnalyticsController@index');
$router->get('/analytics/messages', 'AnalyticsController@messages');
$router->get('/analytics/conversations', 'AnalyticsController@conversations');
$router->get('/analytics/channels', 'AnalyticsController@channels');
$router->get('/analytics/broadcasts', 'AnalyticsController@broadcasts');

// Subscription & billing
$router->get('/subscription', 'SubscriptionController@index');
$router->get('/subscription/plans', 'SubscriptionController@plans');
$router->post('/subscription/change-plan', 'SubscriptionController@changePlan');
$router->get('/billing', 'BillingController@index');
$router->get('/billing/invoices/{id}', 'BillingController@showInvoice');
$router->post('/billing/pay-invoice', 'BillingController@payInvoice');

// Settings & API keys
$router->get('/settings', 'SettingsController@index');
$router->post('/settings/update', 'SettingsController@update');
$router->post('/settings/logo', 'SettingsController@uploadLogo');
$router->get('/settings/api-keys', 'SettingsController@apiKeys');
$router->post('/settings/generate-api-key', 'SettingsController@generateApiKey');
$router->post('/settings/revoke-api-key', 'SettingsController@revokeApiKey');

try {
    $router->dispatch();
} catch (Throwable $e) {
    error_log('[AI WhatsApp Bot Builder] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }

    $isApi = strpos(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/api/') === 0;
    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal server error'] + (app_debug() ? ['detail' => $e->getMessage()] : []));
    } elseif (app_debug()) {
        echo '<h1>Error</h1><p>' . e($e->getMessage()) . '</p><pre>' . e($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>500 - Internal Server Error</h1><p>Something went wrong. Please try again later.</p>';
    }
}
