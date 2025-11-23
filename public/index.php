<?php
// FILE: /public/index.php

/**
 * Application Entry Point
 *
 * This file bootstraps the application and handles all incoming requests.
 */

// Enable error reporting in development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set default timezone
date_default_timezone_set('UTC');

// Load environment variables from .env if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Load core classes
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/View.php';
require_once __DIR__ . '/../app/core/Router.php';
require_once __DIR__ . '/../app/core/Request.php';
require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/CSRF.php';
require_once __DIR__ . '/../app/core/Validator.php';
require_once __DIR__ . '/../app/core/FileUpload.php';

// Initialize router
$router = new Router();

// ===================================================================
// PUBLIC ROUTES (No authentication required)
// ===================================================================

$router->get('/', 'HomeController@index');
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->get('/logout', 'AuthController@logout');

// ===================================================================
// API ROUTES (Public API with API key authentication)
// ===================================================================

$router->post('/api/v1/send-message', 'ApiController@sendMessage');
$router->post('/api/v1/webhook', 'ApiController@webhook');

// ===================================================================
// AUTHENTICATED ROUTES
// ===================================================================

// Dashboard
$router->get('/dashboard', 'DashboardController@index');

// Tenants (Platform Admin only)
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

// Channels (WhatsApp Numbers)
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

// Flows
$router->get('/bots/{botId}/flows', 'FlowController@index');
$router->get('/bots/{botId}/flows/create', 'FlowController@create');
$router->post('/bots/{botId}/flows/store', 'FlowController@store');
$router->get('/flows/{id}/edit', 'FlowController@edit');
$router->post('/flows/{id}/update', 'FlowController@update');
$router->post('/flows/{id}/delete', 'FlowController@delete');

// Flow Steps
$router->get('/flows/{flowId}/steps', 'FlowStepController@index');
$router->get('/flows/{flowId}/steps/create', 'FlowStepController@create');
$router->post('/flows/{flowId}/steps/store', 'FlowStepController@store');
$router->get('/steps/{id}/edit', 'FlowStepController@edit');
$router->post('/steps/{id}/update', 'FlowStepController@update');
$router->post('/steps/{id}/delete', 'FlowStepController@delete');

// Knowledge Base
$router->get('/knowledge-base', 'KnowledgeBaseController@index');
$router->get('/knowledge-base/create', 'KnowledgeBaseController@create');
$router->post('/knowledge-base/store', 'KnowledgeBaseController@store');
$router->get('/knowledge-base/{id}/edit', 'KnowledgeBaseController@edit');
$router->post('/knowledge-base/{id}/update', 'KnowledgeBaseController@update');
$router->post('/knowledge-base/{id}/delete', 'KnowledgeBaseController@delete');

// Contacts
$router->get('/contacts', 'ContactController@index');
$router->get('/contacts/create', 'ContactController@create');
$router->post('/contacts/store', 'ContactController@store');
$router->get('/contacts/{id}', 'ContactController@show');
$router->get('/contacts/{id}/edit', 'ContactController@edit');
$router->post('/contacts/{id}/update', 'ContactController@update');
$router->post('/contacts/{id}/delete', 'ContactController@delete');
$router->post('/contacts/import', 'ContactController@import');

// Conversations & Inbox
$router->get('/inbox', 'InboxController@index');
$router->get('/conversations/{id}', 'ConversationController@show');
$router->post('/conversations/{id}/send-message', 'ConversationController@sendMessage');
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
$router->post('/broadcasts/{id}/delete', 'BroadcastController@delete');

// Webhooks
$router->get('/webhooks', 'WebhookController@index');
$router->get('/webhooks/create', 'WebhookController@create');
$router->post('/webhooks/store', 'WebhookController@store');
$router->get('/webhooks/{id}/edit', 'WebhookController@edit');
$router->post('/webhooks/{id}/update', 'WebhookController@update');
$router->post('/webhooks/{id}/delete', 'WebhookController@delete');
$router->get('/webhooks/{id}/logs', 'WebhookController@logs');

// Analytics
$router->get('/analytics', 'AnalyticsController@index');
$router->get('/analytics/messages', 'AnalyticsController@messages');
$router->get('/analytics/conversations', 'AnalyticsController@conversations');

// Subscription & Billing
$router->get('/subscription', 'SubscriptionController@index');
$router->get('/subscription/plans', 'SubscriptionController@plans');
$router->post('/subscription/change-plan', 'SubscriptionController@changePlan');
$router->get('/billing', 'BillingController@index');
$router->get('/billing/invoices/{id}', 'BillingController@showInvoice');
$router->post('/billing/pay-invoice', 'BillingController@payInvoice');

// Settings
$router->get('/settings', 'SettingsController@index');
$router->post('/settings/update', 'SettingsController@update');
$router->get('/settings/api-keys', 'SettingsController@apiKeys');
$router->post('/settings/generate-api-key', 'SettingsController@generateApiKey');
$router->post('/settings/revoke-api-key', 'SettingsController@revokeApiKey');

// Dispatch request
try {
    $router->dispatch();
} catch (Exception $e) {
    // Handle exceptions
    http_response_code(500);
    if (getenv('APP_DEBUG') === 'true') {
        echo '<h1>Error</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>500 - Internal Server Error</h1>';
    }
}
