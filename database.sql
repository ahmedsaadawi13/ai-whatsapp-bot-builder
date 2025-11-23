-- FILE: /database.sql
-- AI WhatsApp Bot Builder - Database Schema
-- Compatible with MySQL 5.7+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ===================================================================
-- DATABASE CREATION
-- ===================================================================

CREATE DATABASE IF NOT EXISTS `ai_whatsapp_bot` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ai_whatsapp_bot`;

-- ===================================================================
-- PLANS TABLE (Subscription Plans)
-- ===================================================================

CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `billing_period` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `max_channels` int(11) NOT NULL DEFAULT '1',
  `max_bots` int(11) NOT NULL DEFAULT '1',
  `max_contacts` int(11) NOT NULL DEFAULT '100',
  `max_messages_per_month` int(11) NOT NULL DEFAULT '1000',
  `max_storage_mb` int(11) NOT NULL DEFAULT '100',
  `features` text,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TENANTS TABLE
-- ===================================================================

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50),
  `logo` varchar(255),
  `industry` varchar(100),
  `timezone` varchar(50) NOT NULL DEFAULT 'UTC',
  `status` enum('active','suspended','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- USERS TABLE
-- ===================================================================

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('platform_admin','tenant_admin','agent','developer') NOT NULL DEFAULT 'agent',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TENANT SUBSCRIPTIONS TABLE
-- ===================================================================

CREATE TABLE `tenant_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','cancelled','expired','suspended') NOT NULL DEFAULT 'active',
  `started_at` datetime NOT NULL,
  `expires_at` datetime,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- INVOICES TABLE
-- ===================================================================

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` date NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_invoice_number` (`invoice_number`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- PAYMENTS TABLE
-- ===================================================================

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('credit_card','bank_transfer','paypal','other') NOT NULL DEFAULT 'credit_card',
  `transaction_id` varchar(255),
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `notes` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- CHANNELS TABLE (WhatsApp Numbers/Channels)
-- ===================================================================

CREATE TABLE `channels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `provider_type` enum('cloud_api','twilio','sandbox','other') NOT NULL DEFAULT 'cloud_api',
  `provider_config` text,
  `webhook_url` varchar(255),
  `webhook_verify_token` varchar(255),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_phone_number` (`phone_number`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_channels_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- BOTS TABLE
-- ===================================================================

CREATE TABLE `bots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `channel_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `default_language` varchar(10) NOT NULL DEFAULT 'en',
  `ai_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `ai_tone` enum('friendly','formal','concise','professional') NOT NULL DEFAULT 'friendly',
  `ai_max_length` int(11) NOT NULL DEFAULT '500',
  `status` enum('active','paused','draft') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_bots_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bots_channel` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- FLOWS TABLE
-- ===================================================================

CREATE TABLE `flows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `trigger_type` enum('keyword','message_contains','default','menu','button') NOT NULL DEFAULT 'keyword',
  `trigger_value` varchar(255),
  `priority` int(11) NOT NULL DEFAULT '0',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_bot_id` (`bot_id`),
  KEY `idx_trigger_type` (`trigger_type`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_flows_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flows_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- FLOW STEPS TABLE
-- ===================================================================

CREATE TABLE `flow_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `flow_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL DEFAULT '0',
  `action_type` enum('send_text','send_media','ask_question','call_ai','call_webhook','set_variable','branch_condition') NOT NULL DEFAULT 'send_text',
  `action_config` text,
  `next_step_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_flow_id` (`flow_id`),
  KEY `idx_step_order` (`step_order`),
  CONSTRAINT `fk_flow_steps_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flow_steps_flow` FOREIGN KEY (`flow_id`) REFERENCES `flows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- KNOWLEDGE BASE TABLE
-- ===================================================================

CREATE TABLE `knowledge_base` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `bot_id` int(11) DEFAULT NULL,
  `topic` varchar(255) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `keywords` text,
  `category` varchar(100),
  `file_path` varchar(255),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_bot_id` (`bot_id`),
  KEY `idx_status` (`status`),
  FULLTEXT KEY `idx_question_answer` (`question`,`answer`),
  CONSTRAINT `fk_knowledge_base_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_knowledge_base_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- CONTACTS TABLE
-- ===================================================================

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `name` varchar(255),
  `email` varchar(255),
  `custom_fields` text,
  `notes` text,
  `status` enum('active','blocked','unsubscribed') NOT NULL DEFAULT 'active',
  `last_contact_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_phone` (`tenant_id`,`phone_number`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_phone_number` (`phone_number`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_contacts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TAGS TABLE
-- ===================================================================

CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#000000',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_tag` (`tenant_id`,`name`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_tags_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- CONTACT TAGS TABLE (Many-to-Many)
-- ===================================================================

CREATE TABLE `contact_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_contact_tag` (`contact_id`,`tag_id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_tag_id` (`tag_id`),
  CONSTRAINT `fk_contact_tags_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contact_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- CONVERSATIONS TABLE
-- ===================================================================

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `bot_id` int(11) DEFAULT NULL,
  `channel_id` int(11) NOT NULL,
  `status` enum('open','pending','closed') NOT NULL DEFAULT 'open',
  `assigned_to` int(11) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_bot_id` (`bot_id`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_status` (`status`),
  KEY `idx_assigned_to` (`assigned_to`),
  CONSTRAINT `fk_conversations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversations_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversations_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conversations_channel` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversations_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- MESSAGES TABLE
-- ===================================================================

CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `direction` enum('inbound','outbound') NOT NULL,
  `type` enum('text','media','template','interactive') NOT NULL DEFAULT 'text',
  `content` text,
  `media_url` varchar(255),
  `metadata` text,
  `triggered_by` enum('flow','ai','agent','api') DEFAULT NULL,
  `flow_id` int(11) DEFAULT NULL,
  `sent_by_user_id` int(11) DEFAULT NULL,
  `status` enum('sent','delivered','read','failed') NOT NULL DEFAULT 'sent',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_direction` (`direction`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_messages_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_flow` FOREIGN KEY (`flow_id`) REFERENCES `flows` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_user` FOREIGN KEY (`sent_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- CONVERSATION VARIABLES TABLE
-- ===================================================================

CREATE TABLE `conversation_variables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `variable_key` varchar(100) NOT NULL,
  `variable_value` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conversation_variable` (`conversation_id`,`variable_key`),
  KEY `idx_conversation_id` (`conversation_id`),
  CONSTRAINT `fk_conversation_variables` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TEMPLATES TABLE (Message Templates)
-- ===================================================================

CREATE TABLE `templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` enum('notification','support','marketing','other') NOT NULL DEFAULT 'notification',
  `language` varchar(10) NOT NULL DEFAULT 'en',
  `header` text,
  `body` text NOT NULL,
  `footer` text,
  `variables` text,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- BROADCASTS TABLE
-- ===================================================================

CREATE TABLE `broadcasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `bot_id` int(11) DEFAULT NULL,
  `channel_id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `target_filter` text,
  `message_content` text,
  `status` enum('draft','scheduled','running','completed','cancelled') NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `total_recipients` int(11) NOT NULL DEFAULT '0',
  `sent_count` int(11) NOT NULL DEFAULT '0',
  `failed_count` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_bot_id` (`bot_id`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_broadcasts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_broadcasts_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_broadcasts_channel` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_broadcasts_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- BROADCAST MESSAGES TABLE
-- ===================================================================

CREATE TABLE `broadcast_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `broadcast_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` text,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_broadcast_id` (`broadcast_id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_broadcast_messages_broadcast` FOREIGN KEY (`broadcast_id`) REFERENCES `broadcasts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_broadcast_messages_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- WEBHOOKS TABLE
-- ===================================================================

CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `bot_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `events` text NOT NULL,
  `secret` varchar(255),
  `retry_attempts` int(11) NOT NULL DEFAULT '3',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_bot_id` (`bot_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_webhooks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_webhooks_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- WEBHOOK LOGS TABLE
-- ===================================================================

CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `webhook_id` int(11) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payload` text,
  `response_code` int(11),
  `response_body` text,
  `status` enum('success','failed') NOT NULL,
  `attempt` int(11) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_id` (`webhook_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_webhook_logs` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- API KEYS TABLE
-- ===================================================================

CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `key_prefix` varchar(20) NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key_hash` (`key_hash`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_key_prefix` (`key_prefix`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_api_keys_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- USAGE TRACKING TABLE
-- ===================================================================

CREATE TABLE `usage_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_channels` int(11) NOT NULL DEFAULT '0',
  `total_bots` int(11) NOT NULL DEFAULT '0',
  `total_contacts` int(11) NOT NULL DEFAULT '0',
  `total_messages` int(11) NOT NULL DEFAULT '0',
  `total_storage_mb` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_period` (`tenant_id`,`period_start`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_period` (`period_start`,`period_end`),
  CONSTRAINT `fk_usage_tracking_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- SEED DATA
-- ===================================================================

-- Insert Default Plans
INSERT INTO `plans` (`id`, `name`, `description`, `price`, `billing_period`, `max_channels`, `max_bots`, `max_contacts`, `max_messages_per_month`, `max_storage_mb`, `features`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Starter', 'Perfect for small businesses getting started', 29.00, 'monthly', 1, 2, 500, 5000, 500, 'Basic analytics, Email support', 'active', NOW(), NOW()),
(2, 'Professional', 'For growing businesses', 99.00, 'monthly', 3, 10, 5000, 50000, 2000, 'Advanced analytics, Priority support, Custom branding', 'active', NOW(), NOW()),
(3, 'Enterprise', 'For large organizations', 299.00, 'monthly', 10, 50, 50000, 500000, 10000, 'Full analytics, 24/7 support, White label, Custom integrations', 'active', NOW(), NOW());

-- Insert Platform Admin User
INSERT INTO `users` (`id`, `tenant_id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Platform Admin', 'admin@platform.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'platform_admin', 'active', NOW(), NOW());
-- Password: password

-- Insert Demo Tenants
INSERT INTO `tenants` (`id`, `name`, `slug`, `email`, `phone`, `logo`, `industry`, `timezone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'TechCorp Solutions', 'techcorp', 'contact@techcorp.com', '+1-555-0100', NULL, 'Technology', 'America/New_York', 'active', NOW(), NOW()),
(2, 'Marketing Masters Agency', 'marketing-masters', 'hello@marketingmasters.com', '+1-555-0200', NULL, 'Marketing', 'America/Los_Angeles', 'active', NOW(), NOW());

-- Insert Tenant Subscriptions
INSERT INTO `tenant_subscriptions` (`tenant_id`, `plan_id`, `status`, `started_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), NOW(), NOW()),
(2, 3, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), NOW(), NOW());

-- Insert Tenant Users
INSERT INTO `users` (`tenant_id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'John Smith', 'john@techcorp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant_admin', 'active', NOW(), NOW()),
(1, 'Sarah Johnson', 'sarah@techcorp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', NOW(), NOW()),
(1, 'Mike Developer', 'mike@techcorp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'developer', 'active', NOW(), NOW()),
(2, 'Emma Wilson', 'emma@marketingmasters.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant_admin', 'active', NOW(), NOW()),
(2, 'David Brown', 'david@marketingmasters.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', NOW(), NOW());

-- Insert Channels
INSERT INTO `channels` (`tenant_id`, `name`, `phone_number`, `provider_type`, `provider_config`, `webhook_url`, `webhook_verify_token`, `status`, `created_at`, `updated_at`) VALUES
(1, 'TechCorp Support Line', '+15551234567', 'cloud_api', '{"app_id":"123456","app_secret":"secret123"}', '/api/v1/webhook', 'verify_token_123', 'active', NOW(), NOW()),
(1, 'TechCorp Sales Line', '+15551234568', 'cloud_api', '{"app_id":"123457","app_secret":"secret124"}', '/api/v1/webhook', 'verify_token_124', 'active', NOW(), NOW()),
(2, 'Marketing Masters Main', '+15559876543', 'twilio', '{"account_sid":"AC123","auth_token":"token123"}', '/api/v1/webhook', 'verify_token_125', 'active', NOW(), NOW());

-- Insert Bots
INSERT INTO `bots` (`tenant_id`, `channel_id`, `name`, `description`, `default_language`, `ai_enabled`, `ai_tone`, `ai_max_length`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Support Bot', 'Handles customer support inquiries', 'en', 1, 'professional', 500, 'active', NOW(), NOW()),
(1, 2, 'Sales Bot', 'Lead qualification and sales assistance', 'en', 1, 'friendly', 300, 'active', NOW(), NOW()),
(2, 3, 'FAQ Bot', 'Answers frequently asked questions', 'en', 1, 'friendly', 400, 'active', NOW(), NOW());

-- Insert Flows
INSERT INTO `flows` (`tenant_id`, `bot_id`, `name`, `description`, `trigger_type`, `trigger_value`, `priority`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Welcome Flow', 'Greets new users and shows main menu', 'keyword', 'start,hello,hi,menu', 10, 'active', NOW(), NOW()),
(1, 1, 'Support Request', 'Handles support ticket creation', 'keyword', 'support,help,issue', 5, 'active', NOW(), NOW()),
(1, 2, 'Lead Capture', 'Captures lead information', 'keyword', 'pricing,demo,info', 8, 'active', NOW(), NOW()),
(2, 3, 'FAQ Handler', 'Handles common questions', 'default', NULL, 1, 'active', NOW(), NOW());

-- Insert Flow Steps
INSERT INTO `flow_steps` (`tenant_id`, `flow_id`, `step_order`, `action_type`, `action_config`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'send_text', '{"message":"Welcome to TechCorp! How can I help you today?\\n\\n1. Support\\n2. Sales\\n3. General Info"}', NOW(), NOW()),
(1, 2, 1, 'send_text', '{"message":"I can help you with that. Please describe your issue."}', NOW(), NOW()),
(1, 2, 2, 'ask_question', '{"question":"What is your email address?","variable":"email"}', NOW(), NOW()),
(1, 3, 1, 'send_text', '{"message":"Great! I can help you with pricing information."}', NOW(), NOW()),
(1, 3, 2, 'call_ai', '{"prompt":"Provide information about our pricing plans"}', NOW(), NOW());

-- Insert Knowledge Base
INSERT INTO `knowledge_base` (`tenant_id`, `bot_id`, `topic`, `question`, `answer`, `keywords`, `category`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Business Hours', 'What are your business hours?', 'We are open Monday through Friday, 9 AM to 6 PM EST. Our support team is available 24/7 via chat.', 'hours,timing,schedule,open', 'General', 'active', NOW(), NOW()),
(1, 1, 'Pricing', 'How much does your service cost?', 'We offer three plans: Starter at $29/month, Professional at $99/month, and Enterprise at $299/month. Each plan includes different features and limits.', 'price,cost,payment,plans', 'Sales', 'active', NOW(), NOW()),
(1, 1, 'Setup Time', 'How long does setup take?', 'Most customers complete setup within 30 minutes. Our team can help you get started right away!', 'setup,installation,onboarding,start', 'Onboarding', 'active', NOW(), NOW()),
(2, 3, 'Services', 'What services do you offer?', 'We offer comprehensive digital marketing services including social media management, SEO, content marketing, and paid advertising.', 'services,offering,what do you do', 'Services', 'active', NOW(), NOW());

-- Insert Contacts
INSERT INTO `contacts` (`tenant_id`, `phone_number`, `name`, `email`, `custom_fields`, `status`, `last_contact_at`, `created_at`, `updated_at`) VALUES
(1, '+15557001001', 'Alice Cooper', 'alice@example.com', '{"company":"ABC Corp","role":"Manager"}', 'active', NOW(), NOW(), NOW()),
(1, '+15557001002', 'Bob Smith', 'bob@example.com', '{"company":"XYZ Inc","role":"Director"}', 'active', NOW(), NOW(), NOW()),
(1, '+15557001003', 'Charlie Davis', 'charlie@example.com', NULL, 'active', NOW(), NOW(), NOW()),
(2, '+15558002001', 'Diana Prince', 'diana@example.com', '{"industry":"Retail"}', 'active', NOW(), NOW(), NOW()),
(2, '+15558002002', 'Ethan Hunt', 'ethan@example.com', '{"industry":"Finance"}', 'active', NOW(), NOW(), NOW());

-- Insert Tags
INSERT INTO `tags` (`tenant_id`, `name`, `color`, `created_at`, `updated_at`) VALUES
(1, 'VIP Customer', '#FF0000', NOW(), NOW()),
(1, 'Lead', '#00FF00', NOW(), NOW()),
(1, 'Support Issue', '#FFA500', NOW(), NOW()),
(2, 'High Priority', '#FF0000', NOW(), NOW()),
(2, 'Newsletter', '#0000FF', NOW(), NOW());

-- Insert Contact Tags
INSERT INTO `contact_tags` (`contact_id`, `tag_id`, `created_at`) VALUES
(1, 1, NOW()),
(2, 2, NOW()),
(3, 3, NOW());

-- Insert Conversations
INSERT INTO `conversations` (`tenant_id`, `contact_id`, `bot_id`, `channel_id`, `status`, `assigned_to`, `last_message_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'open', 3, NOW(), NOW(), NOW()),
(1, 2, 2, 2, 'closed', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), NOW()),
(1, 3, 1, 1, 'pending', 3, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR), NOW()),
(2, 4, 3, 3, 'open', 6, NOW(), NOW(), NOW()),
(2, 5, 3, 3, 'closed', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), NOW());

-- Insert Messages
INSERT INTO `messages` (`tenant_id`, `conversation_id`, `direction`, `type`, `content`, `triggered_by`, `flow_id`, `status`, `created_at`) VALUES
(1, 1, 'inbound', 'text', 'Hello', NULL, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(1, 1, 'outbound', 'text', 'Welcome to TechCorp! How can I help you today?\n\n1. Support\n2. Sales\n3. General Info', 'flow', 1, 'delivered', DATE_SUB(NOW(), INTERVAL 9 MINUTE)),
(1, 1, 'inbound', 'text', '1', NULL, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 8 MINUTE)),
(1, 1, 'outbound', 'text', 'I can help you with that. Please describe your issue.', 'flow', 2, 'delivered', DATE_SUB(NOW(), INTERVAL 7 MINUTE)),
(1, 2, 'inbound', 'text', 'I need pricing info', NULL, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 2, 'outbound', 'text', 'We offer three plans: Starter at $29/month, Professional at $99/month, and Enterprise at $299/month.', 'ai', NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 4, 'inbound', 'text', 'What services do you offer?', NULL, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(2, 4, 'outbound', 'text', 'We offer comprehensive digital marketing services including social media management, SEO, content marketing, and paid advertising.', 'ai', NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 29 MINUTE));

-- Insert Conversation Variables
INSERT INTO `conversation_variables` (`conversation_id`, `variable_key`, `variable_value`, `created_at`, `updated_at`) VALUES
(1, 'user_name', 'Alice', NOW(), NOW()),
(1, 'issue_type', 'technical', NOW(), NOW()),
(2, 'interest', 'pricing', NOW(), NOW());

-- Insert Templates
INSERT INTO `templates` (`tenant_id`, `name`, `category`, `language`, `header`, `body`, `footer`, `variables`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Welcome Message', 'notification', 'en', 'Welcome!', 'Hi {{name}}, welcome to TechCorp! We are excited to have you on board.', 'Reply STOP to unsubscribe', '["name"]', 'active', NOW(), NOW()),
(1, 'Order Confirmation', 'notification', 'en', 'Order Confirmed', 'Your order {{order_id}} has been confirmed. Total: ${{amount}}', 'Thank you for your business!', '["order_id","amount"]', 'active', NOW(), NOW()),
(2, 'Newsletter Template', 'marketing', 'en', 'Monthly Newsletter', 'Hi {{name}}, check out our latest marketing tips and trends!', 'Unsubscribe anytime', '["name"]', 'active', NOW(), NOW());

-- Insert Broadcasts
INSERT INTO `broadcasts` (`tenant_id`, `bot_id`, `channel_id`, `template_id`, `name`, `target_filter`, `message_content`, `status`, `scheduled_at`, `started_at`, `completed_at`, `total_recipients`, `sent_count`, `failed_count`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'Welcome Campaign', '{"tag":"Lead"}', NULL, 'completed', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), 2, 2, 0, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 3, 3, 3, 'Monthly Newsletter', '{"status":"active"}', NULL, 'completed', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), 2, 2, 0, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Insert Broadcast Messages
INSERT INTO `broadcast_messages` (`broadcast_id`, `contact_id`, `status`, `sent_at`, `created_at`) VALUES
(1, 1, 'sent', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 2, 'sent', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 4, 'sent', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 5, 'sent', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Insert Webhooks
INSERT INTO `webhooks` (`tenant_id`, `bot_id`, `name`, `url`, `events`, `secret`, `retry_attempts`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Slack Notifications', 'https://hooks.slack.com/services/example', '["message.received","conversation.opened"]', 'webhook_secret_123', 3, 'active', NOW(), NOW()),
(2, 3, 'CRM Integration', 'https://crm.example.com/webhook', '["contact.created","conversation.closed"]', 'webhook_secret_456', 5, 'active', NOW(), NOW());

-- Insert Webhook Logs
INSERT INTO `webhook_logs` (`webhook_id`, `event_type`, `payload`, `response_code`, `response_body`, `status`, `attempt`, `created_at`) VALUES
(1, 'message.received', '{"message":"Hello","from":"+15557001001"}', 200, '{"ok":true}', 'success', 1, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1, 'conversation.opened', '{"conversation_id":1}', 200, '{"ok":true}', 'success', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- Insert API Keys
INSERT INTO `api_keys` (`tenant_id`, `name`, `key_hash`, `key_prefix`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Production API Key', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJK', 'tc_live_', 'active', NOW(), NOW()),
(2, 'Development API Key', '$2y$10$1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJK', 'mm_test_', 'active', NOW(), NOW());

-- Insert Invoices
INSERT INTO `invoices` (`tenant_id`, `invoice_number`, `amount`, `tax`, `total`, `status`, `due_date`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 'INV-2024-0001', 99.00, 9.90, 108.90, 'paid', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 'INV-2024-0002', 299.00, 29.90, 328.90, 'paid', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'INV-2024-0003', 99.00, 9.90, 108.90, 'pending', DATE_ADD(NOW(), INTERVAL 5 DAY), NULL, NOW(), NOW());

-- Insert Payments
INSERT INTO `payments` (`tenant_id`, `invoice_id`, `amount`, `payment_method`, `transaction_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 108.90, 'credit_card', 'txn_1234567890', 'completed', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 2, 328.90, 'credit_card', 'txn_0987654321', 'completed', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Insert Usage Tracking
INSERT INTO `usage_tracking` (`tenant_id`, `period_start`, `period_end`, `total_channels`, `total_bots`, `total_contacts`, `total_messages`, `total_storage_mb`, `created_at`, `updated_at`) VALUES
(1, DATE_FORMAT(NOW(), '%Y-%m-01'), LAST_DAY(NOW()), 2, 2, 3, 145, 45.50, NOW(), NOW()),
(2, DATE_FORMAT(NOW(), '%Y-%m-01'), LAST_DAY(NOW()), 1, 1, 2, 89, 23.75, NOW(), NOW());
