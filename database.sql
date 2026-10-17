-- FILE: /database.sql
-- AI WhatsApp Bot Builder - Database Schema + Demo Seed Data
-- MySQL 5.7+ / MariaDB 10.3+ (InnoDB, utf8mb4). All timestamps are UTC.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `ai_whatsapp_bot` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ai_whatsapp_bot`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `notifications`, `login_attempts`, `usage_tracking`, `api_keys`, `webhook_logs`, `webhooks`,
  `broadcast_messages`, `broadcasts`, `templates`, `conversation_variables`, `messages`, `conversations`,
  `contact_tags`, `tags`, `contacts`, `knowledge_base`, `flow_steps`, `flows`, `bots`, `channels`,
  `payments`, `invoices`, `tenant_subscriptions`, `users`, `tenants`, `plans`;
SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================================
-- PLANS
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
-- TENANTS (business / agency accounts)
-- ===================================================================
CREATE TABLE `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `timezone` varchar(50) NOT NULL DEFAULT 'UTC',
  `status` enum('active','suspended','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- USERS (tenant_id is NULL only for platform_admin)
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
  KEY `idx_tenant_role` (`tenant_id`,`role`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_ip_time` (`email`,`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- SUBSCRIPTIONS & BILLING
-- ===================================================================
CREATE TABLE `tenant_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','cancelled','expired','suspended') NOT NULL DEFAULT 'active',
  `started_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_plan_id` (`plan_id`),
  CONSTRAINT `fk_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `due_date` date NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_invoice_number` (`invoice_number`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoices_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `tenant_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('credit_card','bank_transfer','paypal','other') NOT NULL DEFAULT 'credit_card',
  `transaction_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `notes` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_invoice_id` (`invoice_id`),
  CONSTRAINT `fk_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- CHANNELS (WhatsApp numbers). provider_config is encrypted by the app.
-- ===================================================================
CREATE TABLE `channels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `provider_type` enum('cloud_api','twilio','sandbox','other') NOT NULL DEFAULT 'sandbox',
  `provider_config` text,
  `webhook_url` varchar(255) DEFAULT NULL,
  `webhook_verify_token` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_phone_number` (`phone_number`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  CONSTRAINT `fk_channels_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- BOTS
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
  `fallback_message` varchar(500) DEFAULT NULL,
  `status` enum('active','paused','draft') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_channel_status` (`channel_id`,`status`),
  CONSTRAINT `fk_bots_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bots_channel` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- FLOWS & FLOW STEPS
-- ===================================================================
CREATE TABLE `flows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `bot_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `trigger_type` enum('keyword','message_contains','default','menu','button') NOT NULL DEFAULT 'keyword',
  `trigger_value` varchar(255) DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT '0',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_bot_status_priority` (`bot_id`,`status`,`priority`),
  CONSTRAINT `fk_flows_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flows_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY `idx_flow_order` (`flow_id`,`step_order`),
  CONSTRAINT `fk_flow_steps_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_flow_steps_flow` FOREIGN KEY (`flow_id`) REFERENCES `flows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- KNOWLEDGE BASE (bot_id NULL = shared by all bots of the tenant)
-- ===================================================================
CREATE TABLE `knowledge_base` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `bot_id` int(11) DEFAULT NULL,
  `topic` varchar(255) NOT NULL,
  `question` text NOT NULL,
  `answer` mediumtext NOT NULL,
  `keywords` text,
  `category` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` int(11) NOT NULL DEFAULT '0',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_bot_id` (`bot_id`),
  FULLTEXT KEY `ft_kb_search` (`question`,`answer`,`keywords`),
  CONSTRAINT `fk_knowledge_base_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_knowledge_base_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- CONTACTS & TAGS
-- ===================================================================
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `custom_fields` text,
  `notes` text,
  `status` enum('active','blocked','unsubscribed') NOT NULL DEFAULT 'active',
  `last_contact_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_phone` (`tenant_id`,`phone_number`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  CONSTRAINT `fk_contacts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#6b7280',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_tag` (`tenant_id`,`name`),
  CONSTRAINT `fk_tags_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contact_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_contact_tag` (`contact_id`,`tag_id`),
  KEY `idx_tag_id` (`tag_id`),
  CONSTRAINT `fk_contact_tags_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contact_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- CONVERSATIONS, MESSAGES, VARIABLES
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
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status_last` (`tenant_id`,`status`,`last_message_at`),
  KEY `idx_contact_channel` (`contact_id`,`channel_id`,`status`),
  KEY `idx_bot_id` (`bot_id`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  CONSTRAINT `fk_conversations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversations_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversations_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_conversations_channel` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversations_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `direction` enum('inbound','outbound') NOT NULL,
  `type` enum('text','media','template','interactive') NOT NULL DEFAULT 'text',
  `content` text,
  `media_url` varchar(255) DEFAULT NULL,
  `metadata` text,
  `triggered_by` enum('flow','ai','agent','api','broadcast') DEFAULT NULL,
  `flow_id` int(11) DEFAULT NULL,
  `sent_by_user_id` int(11) DEFAULT NULL,
  `status` enum('sent','delivered','read','failed') NOT NULL DEFAULT 'sent',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_conversation_created` (`conversation_id`,`created_at`),
  KEY `idx_conversation_direction_status` (`conversation_id`,`direction`,`status`),
  KEY `idx_flow_id` (`flow_id`),
  CONSTRAINT `fk_messages_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_flow` FOREIGN KEY (`flow_id`) REFERENCES `flows` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_user` FOREIGN KEY (`sent_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversation_variables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `variable_key` varchar(100) NOT NULL,
  `variable_value` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conversation_variable` (`conversation_id`,`variable_key`),
  CONSTRAINT `fk_conversation_variables` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- TEMPLATES & BROADCASTS
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
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  CONSTRAINT `fk_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `broadcasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `bot_id` int(11) DEFAULT NULL,
  `channel_id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `target_filter` text,
  `template_variables` text,
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
  KEY `idx_status_scheduled` (`status`,`scheduled_at`),
  KEY `idx_channel_id` (`channel_id`),
  CONSTRAINT `fk_broadcasts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_broadcasts_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_broadcasts_channel` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_broadcasts_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `broadcast_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `broadcast_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `message_id` int(11) DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` text,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_broadcast_contact` (`broadcast_id`,`contact_id`),
  KEY `idx_broadcast_status` (`broadcast_id`,`status`),
  KEY `idx_contact_id` (`contact_id`),
  CONSTRAINT `fk_broadcast_messages_broadcast` FOREIGN KEY (`broadcast_id`) REFERENCES `broadcasts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_broadcast_messages_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- OUTGOING WEBHOOKS
-- ===================================================================
CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `bot_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `events` text NOT NULL,
  `secret` varchar(255) NOT NULL,
  `retry_attempts` int(11) NOT NULL DEFAULT '3',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_bot_id` (`bot_id`),
  CONSTRAINT `fk_webhooks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_webhooks_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `webhook_id` int(11) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payload` text,
  `response_code` int(11) DEFAULT NULL,
  `response_body` text,
  `status` enum('success','failed') NOT NULL,
  `attempt` int(11) NOT NULL DEFAULT '1',
  `retried` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_created` (`webhook_id`,`created_at`),
  KEY `idx_retry` (`status`,`retried`),
  CONSTRAINT `fk_webhook_logs` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- API KEYS (only a SHA-256 hash of the key is stored)
-- ===================================================================
CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `key_hash` char(64) NOT NULL,
  `key_prefix` varchar(20) NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key_hash` (`key_hash`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_api_keys_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- USAGE TRACKING (one row per tenant per month)
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
  CONSTRAINT `fk_usage_tracking_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- NOTIFICATIONS (simulated emails: welcome, quota warnings, daily summary)
-- ===================================================================
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` mediumtext NOT NULL,
  `dedupe_key` varchar(150) DEFAULT NULL,
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dedupe` (`dedupe_key`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`),
  CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ###################################################################
-- DEMO SEED DATA
-- All demo users use the password: password
-- Demo API keys (send as X-API-KEY):
--   TechCorp:          awb_demo_techcorp_key_000000000000000000000001
--   Marketing Masters: awb_demo_marketing_key_00000000000000000000002
-- ###################################################################

INSERT INTO `plans` (`id`, `name`, `description`, `price`, `billing_period`, `max_channels`, `max_bots`, `max_contacts`, `max_messages_per_month`, `max_storage_mb`, `features`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Starter', 'Perfect for small businesses getting started', 29.00, 'monthly', 1, 2, 500, 5000, 500, 'Flow builder, AI fallback, Email support', 'active', NOW(), NOW()),
(2, 'Professional', 'For growing businesses with several numbers', 99.00, 'monthly', 3, 10, 5000, 50000, 2000, 'Everything in Starter, Broadcasts, Webhooks, Priority support', 'active', NOW(), NOW()),
(3, 'Enterprise', 'For agencies and large organizations', 299.00, 'monthly', 10, 50, 50000, 500000, 10000, 'Everything in Professional, Unlimited agents, Dedicated manager', 'active', NOW(), NOW());

INSERT INTO `tenants` (`id`, `name`, `slug`, `email`, `phone`, `address`, `website`, `logo`, `industry`, `timezone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'TechCorp Solutions', 'techcorp', 'contact@techcorp.com', '+15550100100', '100 Market St, San Francisco, CA', 'https://techcorp.example.com', NULL, 'Technology', 'America/New_York', 'active', DATE_SUB(NOW(), INTERVAL 90 DAY), NOW()),
(2, 'Marketing Masters Agency', 'marketing-masters', 'hello@marketingmasters.com', '+15550200200', '42 Sunset Blvd, Los Angeles, CA', 'https://marketingmasters.example.com', NULL, 'Marketing Agency', 'America/Los_Angeles', 'active', DATE_SUB(NOW(), INTERVAL 60 DAY), NOW());

-- password = "password"
INSERT INTO `users` (`id`, `tenant_id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Platform Admin', 'admin@platform.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'platform_admin', 'active', NOW(), NOW()),
(2, 1, 'John Smith', 'john@techcorp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant_admin', 'active', NOW(), NOW()),
(3, 1, 'Sarah Johnson', 'sarah@techcorp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', NOW(), NOW()),
(4, 1, 'Mike Developer', 'mike@techcorp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'developer', 'active', NOW(), NOW()),
(5, 2, 'Emma Wilson', 'emma@marketingmasters.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant_admin', 'active', NOW(), NOW()),
(6, 2, 'David Brown', 'david@marketingmasters.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent', 'active', NOW(), NOW()),
(7, 2, 'Olivia Chen', 'olivia@marketingmasters.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'developer', 'active', NOW(), NOW());

INSERT INTO `tenant_subscriptions` (`id`, `tenant_id`, `plan_id`, `status`, `started_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'active', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY), NOW(), NOW()),
(2, 2, 3, 'active', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 25 DAY), NOW(), NOW());

INSERT INTO `invoices` (`id`, `tenant_id`, `subscription_id`, `invoice_number`, `description`, `amount`, `tax`, `total`, `status`, `period_start`, `period_end`, `due_date`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'INV-DEMO-0001', 'Professional plan - previous month', 99.00, 9.90, 108.90, 'paid', DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 33 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY)),
(2, 2, 2, 'INV-DEMO-0002', 'Enterprise plan - current month', 299.00, 29.90, 328.90, 'paid', DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 25 DAY), CURDATE(), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(3, 1, 1, 'INV-DEMO-0003', 'Professional plan - current month', 99.00, 9.90, 108.90, 'pending', DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY));

INSERT INTO `payments` (`id`, `tenant_id`, `invoice_id`, `amount`, `payment_method`, `transaction_id`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 108.90, 'credit_card', 'txn_demo_0001', 'completed', 'Dummy gateway', DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY)),
(2, 2, 2, 328.90, 'bank_transfer', 'txn_demo_0002', 'completed', 'Dummy gateway', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY));

INSERT INTO `channels` (`id`, `tenant_id`, `name`, `phone_number`, `provider_type`, `provider_config`, `webhook_url`, `webhook_verify_token`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'TechCorp Support Line', '+15551234567', 'cloud_api', '{"phone_number_id":"1000001","access_token":"demo-token-1"}', '/api/v1/webhook', 'verify_token_123', 'active', NOW(), NOW()),
(2, 1, 'TechCorp Sales Line', '+15551234568', 'sandbox', '{}', '/api/v1/webhook', 'verify_token_124', 'active', NOW(), NOW()),
(3, 2, 'Marketing Masters Main', '+15559876543', 'twilio', '{"account_sid":"ACdemo","auth_token":"demo"}', '/api/v1/webhook', 'verify_token_125', 'active', NOW(), NOW()),
(4, 2, 'Client Sandbox Number', '+15559876544', 'sandbox', '{}', '/api/v1/webhook', 'verify_token_126', 'active', NOW(), NOW());

INSERT INTO `bots` (`id`, `tenant_id`, `channel_id`, `name`, `description`, `default_language`, `ai_enabled`, `ai_tone`, `ai_max_length`, `fallback_message`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Support Bot', 'Welcome menu and customer support with AI fallback', 'en', 1, 'professional', 500, 'Thanks for your message. A support agent will reply shortly.', 'active', NOW(), NOW()),
(2, 1, 2, 'Sales Bot', 'Lead capture and qualification', 'en', 1, 'friendly', 300, NULL, 'active', NOW(), NOW()),
(3, 1, 1, 'FAQ Assistant (draft)', 'Pure knowledge-base bot being prepared', 'en', 1, 'concise', 200, NULL, 'draft', NOW(), NOW()),
(4, 2, 3, 'FAQ Bot', 'Answers agency FAQ from the knowledge base', 'en', 1, 'friendly', 400, NULL, 'active', NOW(), NOW()),
(5, 2, 4, 'Lead Capture Bot', 'Collects leads for a client campaign', 'en', 0, 'concise', 200, 'Type START to begin.', 'active', NOW(), NOW());

INSERT INTO `flows` (`id`, `tenant_id`, `bot_id`, `name`, `description`, `trigger_type`, `trigger_value`, `priority`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Welcome Menu', 'Greets the user and shows the main menu', 'keyword', 'hi,hello,start,menu', 10, 'active', NOW(), NOW()),
(2, 1, 1, 'Menu: Support', 'Collects a support request', 'menu', '1,support,help', 9, 'active', NOW(), NOW()),
(3, 1, 1, 'Menu: Sales', 'Routes to sales', 'menu', '2,sales', 9, 'active', NOW(), NOW()),
(4, 1, 1, 'Menu: Business Hours', 'Answers with AI from the knowledge base', 'menu', '3,hours', 9, 'active', NOW(), NOW()),
(5, 1, 2, 'Lead Capture', 'Asks name and email, then confirms', 'message_contains', 'pricing', 8, 'active', NOW(), NOW()),
(6, 1, 2, 'Budget Qualification', 'Branches on the stated budget', 'keyword', 'budget', 7, 'active', NOW(), NOW()),
(7, 1, 2, 'Demo Button', 'Simulated button click', 'button', 'book_demo', 7, 'active', NOW(), NOW()),
(8, 2, 4, 'Welcome', 'Agency greeting with quick replies', 'keyword', 'hi,hello', 10, 'active', NOW(), NOW()),
(9, 2, 4, 'Services Button', 'Answers about services via AI', 'button', 'services', 9, 'active', NOW(), NOW()),
(10, 2, 5, 'Client Lead Form', 'Collects name and business type', 'keyword', 'start', 10, 'active', NOW(), NOW()),
(11, 2, 5, 'Default Reply', 'Fallback when nothing matches (AI disabled)', 'default', NULL, 0, 'active', NOW(), NOW());

INSERT INTO `flow_steps` (`id`, `tenant_id`, `flow_id`, `step_order`, `action_type`, `action_config`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'ask_question', '{"question":"Welcome to TechCorp, {{contact_name}}! How can we help?\\n1. Support\\n2. Sales\\n3. Business hours","options":["Support","Sales","Business hours"]}', NOW(), NOW()),
(2, 1, 2, 1, 'send_text', '{"message":"Sorry to hear you have an issue. Let us open a ticket."}', NOW(), NOW()),
(3, 1, 2, 2, 'ask_question', '{"question":"What is your email address?","variable":"email"}', NOW(), NOW()),
(4, 1, 2, 3, 'ask_question', '{"question":"Please describe the problem in one message.","variable":"issue"}', NOW(), NOW()),
(5, 1, 2, 4, 'set_variable', '{"key":"ticket_status","value":"open"}', NOW(), NOW()),
(6, 1, 2, 5, 'send_text', '{"message":"Thanks! Ticket created for {{email}}. An agent will contact you soon."}', NOW(), NOW()),
(7, 1, 3, 1, 'send_text', '{"message":"Our sales team is online 9am-6pm EST. Type PRICING to get a quote or BUDGET to find the right plan."}', NOW(), NOW()),
(8, 1, 4, 1, 'call_ai', '{"prompt":"What are your business hours?"}', NOW(), NOW()),
(9, 1, 5, 1, 'send_text', '{"message":"Great, let me prepare a quote for you."}', NOW(), NOW()),
(10, 1, 5, 2, 'ask_question', '{"question":"What is your name?","variable":"name"}', NOW(), NOW()),
(11, 1, 5, 3, 'ask_question', '{"question":"Thanks {{name}}! What is your work email?","variable":"email"}', NOW(), NOW()),
(12, 1, 5, 4, 'set_variable', '{"key":"lead_status","value":"qualified"}', NOW(), NOW()),
(13, 1, 5, 5, 'send_media', '{"media_url":"https://techcorp.example.com/pricing.pdf","caption":"Here is our pricing sheet, {{name}}. We will follow up at {{email}}."}', NOW(), NOW()),
(14, 1, 6, 1, 'ask_question', '{"question":"What is your monthly budget in USD?","variable":"budget"}', NOW(), NOW()),
(15, 1, 6, 2, 'branch_condition', '{"variable":"budget","operator":">=","value":"1000","then_message":"With {{budget}} USD/month our Enterprise plan is the best fit.","else_message":"With {{budget}} USD/month we recommend the Starter or Professional plan."}', NOW(), NOW()),
(16, 1, 7, 1, 'send_text', '{"message":"Book your demo here: https://techcorp.example.com/demo"}', NOW(), NOW()),
(17, 2, 8, 1, 'ask_question', '{"question":"Hi! Welcome to Marketing Masters. What are you interested in?","options":["services","pricing","talk to a human"]}', NOW(), NOW()),
(18, 2, 9, 1, 'call_ai', '{"prompt":"What services do you offer?"}', NOW(), NOW()),
(19, 2, 10, 1, 'ask_question', '{"question":"Welcome! What is your name?","variable":"name"}', NOW(), NOW()),
(20, 2, 10, 2, 'ask_question', '{"question":"Nice to meet you {{name}}. What type of business do you run?","variable":"business_type"}', NOW(), NOW()),
(21, 2, 10, 3, 'send_text', '{"message":"Thanks {{name}}! A consultant for {{business_type}} businesses will reach out today."}', NOW(), NOW()),
(22, 2, 11, 1, 'send_text', '{"message":"Sorry, I did not understand. Type START to begin."}', NOW(), NOW());

INSERT INTO `knowledge_base` (`id`, `tenant_id`, `bot_id`, `topic`, `question`, `answer`, `keywords`, `category`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Business Hours', 'What are your business hours?', 'We are open Monday through Friday, 9 AM to 6 PM EST. Our support bot is available 24/7.', 'hours,timing,schedule,open,opening', 'General', 'active', NOW(), NOW()),
(2, 1, NULL, 'Pricing', 'How much does your service cost?', 'We offer three plans: Starter at $29/month, Professional at $99/month and Enterprise at $299/month.', 'price,pricing,cost,payment,plans', 'Sales', 'active', NOW(), NOW()),
(3, 1, NULL, 'Setup Time', 'How long does setup take?', 'Most customers finish setup within 30 minutes and our team can help you get started right away.', 'setup,installation,onboarding', 'Onboarding', 'active', NOW(), NOW()),
(4, 1, 1, 'Password Reset', 'How do I reset my password?', 'Open the login page, click "Forgot password" and follow the link we email you. Links expire after 1 hour.', 'password,reset,login,forgot', 'Support', 'active', NOW(), NOW()),
(5, 1, NULL, 'Refund Policy', 'What is your refund policy?', 'You can request a full refund within 14 days of purchase by contacting support.', 'refund,money back,cancel', 'Billing', 'active', NOW(), NOW()),
(6, 2, 4, 'Services', 'What services do you offer?', 'We offer social media management, SEO, content marketing and paid advertising campaigns.', 'services,offering,what do you do', 'Services', 'active', NOW(), NOW()),
(7, 2, NULL, 'Agency Pricing', 'How much do your marketing packages cost?', 'Packages start at $1,500/month for social media management. SEO audits start at $800.', 'price,pricing,cost,package', 'Sales', 'active', NOW(), NOW()),
(8, 2, NULL, 'Reporting', 'How often do you send reports?', 'Every client receives a weekly performance report and a monthly strategy call.', 'report,reports,reporting,weekly', 'Services', 'active', NOW(), NOW());

INSERT INTO `contacts` (`id`, `tenant_id`, `phone_number`, `name`, `email`, `custom_fields`, `notes`, `status`, `last_contact_at`, `created_at`, `updated_at`) VALUES
(1, 1, '+15557001001', 'Alice Cooper', 'alice@example.com', '{"company":"ABC Corp","order_id":"A-1001"}', 'VIP since 2024', 'active', NOW(), DATE_SUB(NOW(), INTERVAL 20 DAY), NOW()),
(2, 1, '+15557001002', 'Bob Smith', 'bob@example.com', '{"company":"XYZ Inc","order_id":"A-1002"}', NULL, 'active', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY), NOW()),
(3, 1, '+15557001003', 'Charlie Davis', 'charlie@example.com', NULL, NULL, 'active', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 10 DAY), NOW()),
(4, 1, '+15557001004', 'Fatima Khan', 'fatima@example.com', '{"company":"Khan Retail"}', NULL, 'active', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 5 DAY), NOW()),
(5, 2, '+15558002001', 'Diana Prince', 'diana@example.com', '{"industry":"Retail"}', NULL, 'active', NOW(), DATE_SUB(NOW(), INTERVAL 12 DAY), NOW()),
(6, 2, '+15558002002', 'Ethan Hunt', 'ethan@example.com', '{"industry":"Finance"}', NULL, 'active', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY), NOW()),
(7, 2, '+15558002003', 'George Miller', NULL, '{"industry":"Restaurants"}', NULL, 'unsubscribed', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), NOW());

INSERT INTO `tags` (`id`, `tenant_id`, `name`, `color`, `created_at`, `updated_at`) VALUES
(1, 1, 'VIP Customer', '#dc2626', NOW(), NOW()),
(2, 1, 'Lead', '#16a34a', NOW(), NOW()),
(3, 1, 'Support Issue', '#ea580c', NOW(), NOW()),
(4, 2, 'High Priority', '#dc2626', NOW(), NOW()),
(5, 2, 'Newsletter', '#2563eb', NOW(), NOW());

INSERT INTO `contact_tags` (`contact_id`, `tag_id`, `created_at`) VALUES
(1, 1, NOW()), (1, 2, NOW()), (2, 2, NOW()), (3, 3, NOW()), (4, 2, NOW()),
(5, 4, NOW()), (5, 5, NOW()), (6, 5, NOW()), (7, 5, NOW());

INSERT INTO `conversations` (`id`, `tenant_id`, `contact_id`, `bot_id`, `channel_id`, `status`, `assigned_to`, `last_message_at`, `closed_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 'open', 3, DATE_SUB(NOW(), INTERVAL 5 MINUTE), NULL, DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW()),
(2, 1, 2, 2, 2, 'closed', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), NOW()),
(3, 1, 3, 1, 1, 'pending', 3, DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR), NOW()),
(4, 1, 4, 2, 2, 'open', NULL, DATE_SUB(NOW(), INTERVAL 3 HOUR), NULL, DATE_SUB(NOW(), INTERVAL 4 HOUR), NOW()),
(5, 2, 5, 4, 3, 'open', 6, DATE_SUB(NOW(), INTERVAL 29 MINUTE), NULL, DATE_SUB(NOW(), INTERVAL 30 MINUTE), NOW()),
(6, 2, 6, 4, 3, 'closed', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), NOW()),
(7, 2, 7, 5, 4, 'open', NULL, DATE_SUB(NOW(), INTERVAL 4 DAY), NULL, DATE_SUB(NOW(), INTERVAL 4 DAY), NOW());

INSERT INTO `messages` (`tenant_id`, `conversation_id`, `direction`, `type`, `content`, `media_url`, `metadata`, `triggered_by`, `flow_id`, `sent_by_user_id`, `status`, `created_at`) VALUES
(1, 1, 'inbound', 'text', 'Hello', NULL, NULL, NULL, NULL, NULL, 'read', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(1, 1, 'outbound', 'interactive', 'Welcome to TechCorp, Alice Cooper! How can we help?\n1. Support\n2. Sales\n3. Business hours', NULL, '{"quick_replies":["Support","Sales","Business hours"]}', 'flow', 1, NULL, 'read', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(1, 1, 'inbound', 'text', '1', NULL, NULL, NULL, NULL, NULL, 'read', DATE_SUB(NOW(), INTERVAL 14 MINUTE)),
(1, 1, 'outbound', 'text', 'Sorry to hear you have an issue. Let us open a ticket.', NULL, NULL, 'flow', 2, NULL, 'read', DATE_SUB(NOW(), INTERVAL 14 MINUTE)),
(1, 1, 'outbound', 'text', 'What is your email address?', NULL, NULL, 'flow', 2, NULL, 'read', DATE_SUB(NOW(), INTERVAL 14 MINUTE)),
(1, 1, 'inbound', 'text', 'alice@example.com', NULL, NULL, NULL, NULL, NULL, 'read', DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(1, 1, 'outbound', 'text', 'Please describe the problem in one message.', NULL, NULL, 'flow', 2, NULL, 'read', DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(1, 1, 'inbound', 'text', 'My invoice PDF does not download', NULL, NULL, NULL, NULL, NULL, 'read', DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(1, 1, 'outbound', 'text', 'Thanks! Ticket created for alice@example.com. An agent will contact you soon.', NULL, NULL, 'flow', 2, NULL, 'read', DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(1, 1, 'outbound', 'text', 'Hi Alice, Sarah here. I have re-sent the invoice to your email.', NULL, NULL, 'agent', NULL, 3, 'delivered', DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
(1, 3, 'inbound', 'text', 'How do I reset my password?', NULL, NULL, NULL, NULL, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 3, 'outbound', 'text', 'Open the login page, click "Forgot password" and follow the link we email you. Links expire after 1 hour.', NULL, NULL, 'ai', NULL, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, 3, 'inbound', 'text', 'The link never arrives', NULL, NULL, NULL, NULL, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1, 4, 'inbound', 'text', 'I want pricing for 5 numbers', NULL, NULL, NULL, NULL, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1, 4, 'outbound', 'text', 'Great, let me prepare a quote for you.', NULL, NULL, 'flow', 5, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1, 4, 'outbound', 'text', 'What is your name?', NULL, NULL, 'flow', 5, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1, 4, 'inbound', 'text', 'Fatima', NULL, NULL, NULL, NULL, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(1, 4, 'outbound', 'text', 'Thanks Fatima! What is your work email?', NULL, NULL, 'flow', 5, NULL, 'delivered', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(2, 5, 'inbound', 'text', 'What services do you offer?', NULL, NULL, NULL, NULL, NULL, 'read', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(2, 5, 'outbound', 'text', 'We offer social media management, SEO, content marketing and paid advertising campaigns.', NULL, NULL, 'ai', NULL, NULL, 'read', DATE_SUB(NOW(), INTERVAL 29 MINUTE)),
(2, 7, 'inbound', 'text', 'start', NULL, NULL, NULL, NULL, NULL, 'read', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 7, 'outbound', 'text', 'Welcome! What is your name?', NULL, NULL, 'flow', 10, NULL, 'read', DATE_SUB(NOW(), INTERVAL 4 DAY));

-- History for dashboard charts: 14 days of conversations 2 (tenant 1) and 6 (tenant 2)
INSERT INTO `messages` (`tenant_id`, `conversation_id`, `direction`, `type`, `content`, `triggered_by`, `flow_id`, `sent_by_user_id`, `status`, `created_at`)
SELECT 1, 2, 'inbound', 'text', CONCAT('Question number ', d.n), NULL, NULL, NULL, 'read', DATE_SUB(DATE_SUB(NOW(), INTERVAL d.n DAY), INTERVAL (d.n * 7) MINUTE)
FROM (SELECT 2 n UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
      UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13) d;

INSERT INTO `messages` (`tenant_id`, `conversation_id`, `direction`, `type`, `content`, `triggered_by`, `flow_id`, `sent_by_user_id`, `status`, `created_at`)
SELECT 1, 2, 'outbound', 'text', CONCAT('Answer number ', d.n),
       CASE MOD(d.n, 3) WHEN 0 THEN 'ai' WHEN 1 THEN 'flow' ELSE 'agent' END,
       CASE MOD(d.n, 3) WHEN 1 THEN 5 ELSE NULL END,
       CASE MOD(d.n, 3) WHEN 2 THEN 2 ELSE NULL END,
       'read',
       DATE_ADD(DATE_SUB(DATE_SUB(NOW(), INTERVAL d.n DAY), INTERVAL (d.n * 7) MINUTE), INTERVAL CASE MOD(d.n, 3) WHEN 2 THEN 240 ELSE 3 END SECOND)
FROM (SELECT 2 n UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
      UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13) d;

INSERT INTO `messages` (`tenant_id`, `conversation_id`, `direction`, `type`, `content`, `triggered_by`, `flow_id`, `sent_by_user_id`, `status`, `created_at`)
SELECT 2, 6, d.dir, 'text', CONCAT(IF(d.dir = 'inbound', 'Client question ', 'Bot answer '), d.n),
       IF(d.dir = 'inbound', NULL, IF(MOD(d.n, 2) = 0, 'ai', 'flow')),
       IF(d.dir = 'outbound' AND MOD(d.n, 2) = 1, 8, NULL), NULL, 'read',
       DATE_ADD(DATE_SUB(NOW(), INTERVAL d.n DAY), INTERVAL IF(d.dir = 'inbound', 0, 2) SECOND)
FROM (SELECT 1 n, 'inbound' dir UNION ALL SELECT 1, 'outbound' UNION ALL SELECT 3, 'inbound' UNION ALL SELECT 3, 'outbound'
      UNION ALL SELECT 5, 'inbound' UNION ALL SELECT 5, 'outbound' UNION ALL SELECT 6, 'inbound' UNION ALL SELECT 6, 'outbound'
      UNION ALL SELECT 9, 'inbound' UNION ALL SELECT 9, 'outbound' UNION ALL SELECT 12, 'inbound' UNION ALL SELECT 12, 'outbound') d;

INSERT INTO `conversation_variables` (`conversation_id`, `variable_key`, `variable_value`, `created_at`, `updated_at`) VALUES
(1, 'email', 'alice@example.com', NOW(), NOW()),
(1, 'issue', 'My invoice PDF does not download', NOW(), NOW()),
(1, 'ticket_status', 'open', NOW(), NOW()),
(4, 'name', 'Fatima', NOW(), NOW()),
(4, '_awaiting_input', 'email', NOW(), NOW()),
(4, '_resume_flow', '5', NOW(), NOW()),
(4, '_resume_step', '11', NOW(), NOW()),
(7, '_awaiting_input', 'name', NOW(), NOW()),
(7, '_resume_flow', '10', NOW(), NOW()),
(7, '_resume_step', '19', NOW(), NOW());

INSERT INTO `templates` (`id`, `tenant_id`, `name`, `category`, `language`, `header`, `body`, `footer`, `variables`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Welcome Message', 'notification', 'en', 'Welcome!', 'Hi {{name}}, welcome to TechCorp! Reply MENU any time to see options.', 'Reply STOP to unsubscribe', '["name"]', 'active', NOW(), NOW()),
(2, 1, 'Order Confirmation', 'notification', 'en', 'Order Confirmed', 'Hi {{name}}, your order {{order_id}} has been confirmed.', 'Thank you for your business!', '["name","order_id"]', 'active', NOW(), NOW()),
(3, 1, 'Support Follow-up', 'support', 'en', NULL, 'Hi {{name}}, did our team resolve your issue? Reply YES or NO.', NULL, '["name"]', 'active', NOW(), NOW()),
(4, 2, 'Monthly Newsletter', 'marketing', 'en', 'Monthly Newsletter', 'Hi {{name}}, our {{month}} marketing tips for {{industry}} brands are out!', 'Reply STOP to unsubscribe', '["name","month","industry"]', 'active', NOW(), NOW()),
(5, 2, 'Promo Offer', 'marketing', 'en', NULL, 'Hi {{name}}, get 20% off an SEO audit this week with code {{code}}.', NULL, '["name","code"]', 'active', NOW(), NOW());

INSERT INTO `broadcasts` (`id`, `tenant_id`, `bot_id`, `channel_id`, `template_id`, `name`, `target_filter`, `template_variables`, `message_content`, `status`, `scheduled_at`, `started_at`, `completed_at`, `total_recipients`, `sent_count`, `failed_count`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 2, 1, 'Welcome Campaign', '{"type":"tag","tag_id":2}', '{}', NULL, 'completed', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), 3, 3, 0, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 1, 1, 1, 3, 'Support Follow-up (scheduled)', '{"type":"tag","tag_id":3}', '{}', NULL, 'scheduled', DATE_ADD(NOW(), INTERVAL 2 DAY), NULL, NULL, 0, 0, 0, NOW(), NOW()),
(3, 2, 4, 3, 4, 'Monthly Newsletter', '{"type":"tag","tag_id":5}', '{"month":"October","industry":"{{custom.industry}}"}', NULL, 'completed', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), 3, 2, 1, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 2, 4, 3, 5, 'SEO Promo (draft)', '{"type":"all"}', '{"code":"SEO20"}', NULL, 'draft', NULL, NULL, NULL, 0, 0, 0, NOW(), NOW());

INSERT INTO `broadcast_messages` (`broadcast_id`, `contact_id`, `status`, `error_message`, `sent_at`, `created_at`) VALUES
(1, 1, 'sent', NULL, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 2, 'sent', NULL, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 4, 'sent', NULL, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 5, 'sent', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 6, 'sent', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 7, 'failed', 'Contact has unsubscribed', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Demo webhooks are inactive so the demo never calls external URLs.
INSERT INTO `webhooks` (`id`, `tenant_id`, `bot_id`, `name`, `url`, `events`, `secret`, `retry_attempts`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Slack Notifications', 'https://hooks.example.com/services/techcorp', '["message.received","conversation.opened"]', 'whsec_demo_techcorp_123', 3, 'inactive', NOW(), NOW()),
(2, 2, NULL, 'CRM Integration', 'https://crm.example.com/webhook', '["contact.created","conversation.closed"]', 'whsec_demo_marketing_456', 5, 'inactive', NOW(), NOW());

INSERT INTO `webhook_logs` (`webhook_id`, `event_type`, `payload`, `response_code`, `response_body`, `status`, `attempt`, `retried`, `created_at`) VALUES
(1, 'message.received', '{"event":"message.received","data":{"conversation_id":1,"content":"Hello"}}', 200, '{"ok":true}', 'success', 1, 0, DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(1, 'conversation.opened', '{"event":"conversation.opened","data":{"conversation_id":1}}', 503, 'Service Unavailable', 'failed', 1, 1, DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(1, 'conversation.opened', '{"event":"conversation.opened","data":{"conversation_id":1}}', 200, '{"ok":true}', 'success', 2, 0, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(2, 'contact.created', '{"event":"contact.created","data":{"contact_id":7}}', 201, '{"id":"crm_77"}', 'success', 1, 0, DATE_SUB(NOW(), INTERVAL 8 DAY));

INSERT INTO `api_keys` (`tenant_id`, `name`, `key_hash`, `key_prefix`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Demo Production Key', SHA2('awb_demo_techcorp_key_000000000000000000000001', 256), 'awb_demo_tec', 'active', NOW(), NOW()),
(2, 'Demo Agency Key', SHA2('awb_demo_marketing_key_00000000000000000000002', 256), 'awb_demo_mar', 'active', NOW(), NOW());

INSERT INTO `usage_tracking` (`tenant_id`, `period_start`, `period_end`, `total_channels`, `total_bots`, `total_contacts`, `total_messages`, `total_storage_mb`, `created_at`, `updated_at`) VALUES
(1, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m-01'), LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 2 MONTH)), 2, 2, 2, 820, 1.20, NOW(), NOW()),
(1, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01'), LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 2, 3, 3, 1340, 2.10, NOW(), NOW()),
(2, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01'), LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 2, 2, 3, 610, 0.80, NOW(), NOW());

INSERT INTO `notifications` (`tenant_id`, `user_id`, `type`, `recipient`, `subject`, `body`, `dedupe_key`, `status`, `created_at`) VALUES
(1, 2, 'welcome', 'john@techcorp.com', 'Welcome to AI WhatsApp Bot Builder', '<p>Hi John, your TechCorp Solutions workspace is ready.</p>', NULL, 'sent', DATE_SUB(NOW(), INTERVAL 90 DAY)),
(2, 5, 'welcome', 'emma@marketingmasters.com', 'Welcome to AI WhatsApp Bot Builder', '<p>Hi Emma, your Marketing Masters Agency workspace is ready.</p>', NULL, 'sent', DATE_SUB(NOW(), INTERVAL 60 DAY));
