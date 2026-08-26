-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 26, 2026 at 06:56 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `garmentflow`
--

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

DROP TABLE IF EXISTS `alerts`;
CREATE TABLE IF NOT EXISTS `alerts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `alert_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `related_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_id` bigint UNSIGNED DEFAULT NULL,
  `role_slug` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permission_slug` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occurred_at` timestamp NOT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `alerts_alert_key_unique` (`alert_key`),
  KEY `alerts_rule_code_index` (`rule_code`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alerts`
--

INSERT INTO `alerts` (`id`, `alert_key`, `rule_code`, `severity`, `title`, `description`, `related_type`, `related_id`, `role_slug`, `permission_slug`, `occurred_at`, `resolved_at`, `created_at`, `updated_at`) VALUES
(1, 'ALERT-STOCK_SHORTAGE', 'STOCK_SHORTAGE', 'warning', 'Critical Raw Material Alert', 'Low stock warning for 100% Combed Cotton Pique in WH-RAW-01.', NULL, NULL, 'administrator', NULL, '2026-08-26 11:58:13', NULL, '2026-08-26 11:58:13', '2026-08-26 11:58:13'),
(2, 'ALERT-ORDER_CONFIRMED', 'ORDER_CONFIRMED', 'info', 'Buyer Order Confirmed', 'H&M Autumn Polo Launch order (1,200 pcs) successfully locked for production.', NULL, NULL, 'administrator', NULL, '2026-08-26 11:58:13', NULL, '2026-08-26 11:58:13', '2026-08-26 11:58:13'),
(3, 'ALERT-PAYMENT_RECEIVED', 'PAYMENT_RECEIVED', 'success', 'Full Invoice Payment Received', 'Received $22,200.00 from H&M Hennes & Mauritz AB for Invoice #INV-2026-001.', NULL, NULL, 'administrator', NULL, '2026-08-26 11:58:13', NULL, '2026-08-26 11:58:13', '2026-08-26 11:58:13'),
(4, 'ALERT-DELIVERY_DISPATCHED', 'DELIVERY_DISPATCHED', 'info', 'Export Shipment Dispatched', 'Maersk Line Ocean vessel departed for Hamburg with Delivery #DEL-2026-001.', NULL, NULL, 'administrator', NULL, '2026-08-26 11:58:13', NULL, '2026-08-26 11:58:13', '2026-08-26 11:58:13');

-- --------------------------------------------------------

--
-- Table structure for table `alert_reads`
--

DROP TABLE IF EXISTS `alert_reads`;
CREATE TABLE IF NOT EXISTS `alert_reads` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `alert_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `read_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `alert_reads_alert_id_user_id_unique` (`alert_id`,`user_id`),
  KEY `alert_reads_user_id_foreign` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` bigint UNSIGNED NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_module_index` (`module`),
  KEY `audit_logs_record_type_index` (`record_type`),
  KEY `audit_logs_record_id_index` (`record_id`),
  KEY `audit_logs_action_index` (`action`)
) ENGINE=MyISAM AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `module`, `record_type`, `record_id`, `action`, `old_values`, `new_values`, `created_at`, `updated_at`) VALUES
(1, 1, 'boms', 'App\\Models\\BomHeader', 1, 'updated', '{\"id\": 1, \"code\": \"BOM-TEE-001\", \"name\": \"Classic Cotton Crewneck Tee Engineering BOM\", \"status\": \"active\", \"created_at\": \"2026-08-25T17:41:55.000000Z\", \"deleted_at\": null, \"product_id\": 1, \"updated_at\": \"2026-08-25T17:41:55.000000Z\", \"description\": \"Active production BOM for Classic Cotton Tee\"}', '{\"id\": 1, \"code\": \"BOM-TEE-002\", \"name\": \"Classic Cotton Crewneck Tee Engineering BOM\", \"status\": \"active\", \"created_at\": \"2026-08-25T17:41:55.000000Z\", \"deleted_at\": null, \"product_id\": 1, \"updated_at\": \"2026-08-26T17:28:45.000000Z\", \"description\": \"Active production BOM for Classic Cotton Tee\"}', '2026-08-26 11:28:45', '2026-08-26 11:28:45'),
(2, 1, 'boms', 'App\\Models\\BomHeader', 1, 'updated', '{\"id\": 1, \"code\": \"BOM-TEE-002\", \"name\": \"Classic Cotton Crewneck Tee Engineering BOM\", \"status\": \"active\", \"created_at\": \"2026-08-25T17:41:55.000000Z\", \"deleted_at\": null, \"product_id\": 1, \"updated_at\": \"2026-08-26T17:28:45.000000Z\", \"description\": \"Active production BOM for Classic Cotton Tee\"}', '{\"id\": 1, \"code\": \"BOM-TEE-001\", \"name\": \"Classic Cotton Crewneck Tee Engineering BOM\", \"status\": \"active\", \"created_at\": \"2026-08-25T17:41:55.000000Z\", \"deleted_at\": null, \"product_id\": 1, \"updated_at\": \"2026-08-26T17:29:09.000000Z\", \"description\": \"Active production BOM for Classic Cotton Tee\"}', '2026-08-26 11:29:09', '2026-08-26 11:29:09'),
(3, 1, 'boms', 'App\\Models\\BomHeader', 1, 'updated', '{\"id\": 1, \"code\": \"BOM-TEE-001\", \"name\": \"Classic Cotton Crewneck Tee Engineering BOM\", \"status\": \"active\", \"created_at\": \"2026-08-25T17:41:55.000000Z\", \"deleted_at\": null, \"product_id\": 1, \"updated_at\": \"2026-08-26T17:29:09.000000Z\", \"description\": \"Active production BOM for Classic Cotton Tee\"}', '{\"id\": 1, \"code\": \"BOM-TEE-002\", \"name\": \"Classic Cotton Crewneck Tee Engineering BOM\", \"status\": \"active\", \"created_at\": \"2026-08-25T17:41:55.000000Z\", \"deleted_at\": null, \"product_id\": 1, \"updated_at\": \"2026-08-26T18:00:39.000000Z\", \"description\": \"Active production BOM for Classic Cotton Tee\"}', '2026-08-26 12:00:39', '2026-08-26 12:00:39'),
(4, 1, 'boms', 'App\\Models\\BomHeader', 1, 'updated', '{\"id\": 1, \"code\": \"BOM-TEE-002\", \"name\": \"Classic Cotton Crewneck Tee Engineering BOM\", \"status\": \"active\", \"created_at\": \"2026-08-25T17:41:55.000000Z\", \"deleted_at\": null, \"product_id\": 1, \"updated_at\": \"2026-08-26T18:00:39.000000Z\", \"description\": \"Active production BOM for Classic Cotton Tee\"}', '{\"id\": 1, \"code\": \"BOM-TEE-001\", \"name\": \"Classic Cotton Crewneck Tee Engineering BOM\", \"status\": \"active\", \"created_at\": \"2026-08-25T17:41:55.000000Z\", \"deleted_at\": null, \"product_id\": 1, \"updated_at\": \"2026-08-26T18:03:36.000000Z\", \"description\": \"Active production BOM for Classic Cotton Tee\"}', '2026-08-26 12:03:36', '2026-08-26 12:03:36'),
(5, 1, 'buyer-order-items', 'App\\Models\\BuyerOrderItem', 4, 'created', NULL, '{\"id\": 4, \"remarks\": null, \"quantity\": \"200.0000\", \"created_at\": \"2026-08-26T18:17:00.000000Z\", \"item_total\": \"40000.0000\", \"product_id\": 2, \"unit_price\": \"200.0000\", \"updated_at\": \"2026-08-26T18:17:00.000000Z\", \"buyer_order_id\": 4, \"product_variant_id\": 2}', '2026-08-26 12:17:00', '2026-08-26 12:17:00'),
(6, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'created', NULL, '{\"id\": 4, \"status\": \"draft\", \"remarks\": \"test\", \"buyer_id\": 2, \"created_at\": \"2026-08-26T18:17:00.000000Z\", \"created_by\": 1, \"order_date\": \"2026-08-26\", \"updated_at\": \"2026-08-26T18:17:00.000000Z\", \"order_number\": \"BO-20260826-0001\", \"total_amount\": \"40000.0000\", \"delivery_date\": \"2026-10-09\", \"total_quantity\": \"200.0000\"}', '2026-08-26 12:17:00', '2026-08-26 12:17:00'),
(7, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'status_changed', '{\"status\": \"draft\"}', '{\"status\": \"submitted\", \"remarks\": \"Order submitted for approval.\"}', '2026-08-26 12:17:07', '2026-08-26 12:17:07'),
(8, 1, 'order-approvals', 'App\\Models\\OrderApproval', 1, 'requested', NULL, '{\"id\": 1, \"status\": \"pending\", \"remarks\": null, \"created_at\": \"2026-08-26T18:17:07.000000Z\", \"updated_at\": \"2026-08-26T18:17:07.000000Z\", \"requested_at\": \"2026-08-26T18:17:07.000000Z\", \"requested_by\": 1, \"buyer_order_id\": 4}', '2026-08-26 12:17:07', '2026-08-26 12:17:07'),
(9, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'status_changed', '{\"status\": \"submitted\"}', '{\"status\": \"pending_approval\", \"remarks\": \"Order is awaiting approval.\"}', '2026-08-26 12:17:07', '2026-08-26 12:17:07'),
(10, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'submitted', NULL, '{\"status\": \"pending_approval\"}', '2026-08-26 12:17:07', '2026-08-26 12:17:07'),
(11, 1, 'order-approvals', 'App\\Models\\OrderApproval', 1, 'approved', '{\"id\": 1, \"status\": \"pending\", \"remarks\": null, \"created_at\": \"2026-08-26T18:17:07.000000Z\", \"updated_at\": \"2026-08-26T18:17:07.000000Z\", \"reviewed_at\": null, \"reviewed_by\": null, \"requested_at\": \"2026-08-26T18:17:07.000000Z\", \"requested_by\": 1, \"buyer_order_id\": 4}', '{\"id\": 1, \"status\": \"approved\", \"remarks\": null, \"created_at\": \"2026-08-26T18:17:07.000000Z\", \"updated_at\": \"2026-08-26T18:17:14.000000Z\", \"reviewed_at\": \"2026-08-26T18:17:14.000000Z\", \"reviewed_by\": 1, \"requested_at\": \"2026-08-26T18:17:07.000000Z\", \"requested_by\": 1, \"buyer_order_id\": 4}', '2026-08-26 12:17:14', '2026-08-26 12:17:14'),
(12, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'status_changed', '{\"status\": \"pending_approval\"}', '{\"status\": \"submitted\", \"remarks\": \"Order approved and ready for confirmation.\"}', '2026-08-26 12:17:14', '2026-08-26 12:17:14'),
(13, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'approved', NULL, '{\"status\": \"submitted\"}', '2026-08-26 12:17:14', '2026-08-26 12:17:14'),
(14, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'status_changed', '{\"status\": \"submitted\"}', '{\"status\": \"confirmed\", \"remarks\": \"Order confirmed for future planning.\"}', '2026-08-26 12:17:17', '2026-08-26 12:17:17'),
(15, 1, 'order-planning-inputs', 'App\\Models\\OrderPlanningInput', 1, 'created', NULL, '{\"id\": 1, \"notes\": null, \"status\": \"ready\", \"created_at\": \"2026-08-26T18:17:17.000000Z\", \"updated_at\": \"2026-08-26T18:17:17.000000Z\", \"prepared_at\": \"2026-08-26T18:17:17.000000Z\", \"prepared_by\": 1, \"buyer_order_id\": 4, \"total_quantity\": \"200.0000\"}', '2026-08-26 12:17:17', '2026-08-26 12:17:17'),
(16, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'confirmed', NULL, '{\"status\": \"confirmed\"}', '2026-08-26 12:17:17', '2026-08-26 12:17:17'),
(17, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'status_changed', '{\"status\": \"confirmed\"}', '{\"status\": \"planning\", \"remarks\": null}', '2026-08-26 12:17:20', '2026-08-26 12:17:20'),
(18, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'status_changed', '{\"status\": \"planning\"}', '{\"status\": \"in_production\", \"remarks\": null}', '2026-08-26 12:17:23', '2026-08-26 12:17:23'),
(19, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'status_changed', '{\"status\": \"in_production\"}', '{\"status\": \"ready\", \"remarks\": null}', '2026-08-26 12:17:26', '2026-08-26 12:17:26'),
(20, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'status_changed', '{\"status\": \"ready\"}', '{\"status\": \"shipped\", \"remarks\": null}', '2026-08-26 12:17:44', '2026-08-26 12:17:44'),
(21, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'status_changed', '{\"status\": \"shipped\"}', '{\"status\": \"delivered\", \"remarks\": null}', '2026-08-26 12:17:47', '2026-08-26 12:17:47'),
(22, 1, 'buyer-orders', 'App\\Models\\BuyerOrder', 4, 'status_changed', '{\"status\": \"delivered\"}', '{\"status\": \"completed\", \"remarks\": null}', '2026-08-26 12:17:50', '2026-08-26 12:17:50'),
(23, 1, 'sales-order-items', 'App\\Models\\SalesOrderItem', 2, 'created', NULL, '{\"id\": 2, \"remarks\": null, \"unit_id\": 2, \"created_at\": \"2026-08-26T18:25:11.000000Z\", \"line_total\": \"12300.0000\", \"product_id\": 2, \"tax_amount\": \"1.0000\", \"unit_price\": \"100.0000\", \"updated_at\": \"2026-08-26T18:25:11.000000Z\", \"line_number\": 1, \"sales_order_id\": 2, \"discount_amount\": \"2.0000\", \"ordered_quantity\": \"123.0000\", \"confirmed_quantity\": \"0.0000\", \"delivered_quantity\": \"0.0000\", \"product_variant_id\": 2, \"remaining_quantity\": \"123.0000\"}', '2026-08-26 12:25:11', '2026-08-26 12:25:11'),
(24, 1, 'sales-orders', 'App\\Models\\SalesOrder', 2, 'created', NULL, '{\"id\": 2, \"status\": \"draft\", \"remarks\": \"sgs\", \"buyer_id\": null, \"subtotal\": \"12300.0000\", \"created_at\": \"2026-08-26T18:25:11.000000Z\", \"created_by\": 1, \"order_date\": \"2026-08-26\", \"tax_amount\": \"13.0000\", \"updated_at\": \"2026-08-26T18:25:11.000000Z\", \"customer_id\": 2, \"total_amount\": \"12301.0000\", \"warehouse_id\": 1, \"discount_amount\": \"12.0000\", \"delivery_address\": \"test\", \"order_tax_amount\": \"12.0000\", \"ordered_quantity\": \"123.0000\", \"confirmed_quantity\": \"0.0000\", \"delivered_quantity\": \"0.0000\", \"remaining_quantity\": \"123.0000\", \"sales_order_number\": \"SO-20260826-0001\", \"contact_information\": \"098765432\", \"order_discount_amount\": \"10.0000\", \"required_delivery_date\": \"2026-09-05\"}', '2026-08-26 12:25:11', '2026-08-26 12:25:11'),
(25, 1, 'sales-order-items', 'App\\Models\\SalesOrderItem', 3, 'created', NULL, '{\"id\": 3, \"remarks\": null, \"unit_id\": 2, \"created_at\": \"2026-08-26T18:25:38.000000Z\", \"line_total\": \"12300.0000\", \"product_id\": 2, \"tax_amount\": \"1.0000\", \"unit_price\": \"100.0000\", \"updated_at\": \"2026-08-26T18:25:38.000000Z\", \"line_number\": 1, \"sales_order_id\": 2, \"discount_amount\": \"2.0000\", \"ordered_quantity\": \"123.0000\", \"confirmed_quantity\": \"0.0000\", \"delivered_quantity\": \"0.0000\", \"product_variant_id\": 2, \"remaining_quantity\": \"123.0000\"}', '2026-08-26 12:25:38', '2026-08-26 12:25:38'),
(26, 1, 'sales-orders', 'App\\Models\\SalesOrder', 2, 'updated', '{\"id\": 2, \"status\": \"draft\", \"remarks\": \"sgs\", \"buyer_id\": null, \"subtotal\": \"12300.0000\", \"created_at\": \"2026-08-26T18:25:11.000000Z\", \"created_by\": 1, \"deleted_at\": null, \"order_date\": \"2026-08-26\", \"tax_amount\": \"13.0000\", \"updated_at\": \"2026-08-26T18:25:11.000000Z\", \"customer_id\": 2, \"confirmed_at\": null, \"total_amount\": \"12301.0000\", \"warehouse_id\": 1, \"discount_amount\": \"12.0000\", \"delivery_address\": \"test\", \"order_tax_amount\": \"12.0000\", \"ordered_quantity\": \"123.0000\", \"confirmed_quantity\": \"0.0000\", \"delivered_quantity\": \"0.0000\", \"remaining_quantity\": \"123.0000\", \"sales_order_number\": \"SO-20260826-0001\", \"contact_information\": \"098765432\", \"order_discount_amount\": \"10.0000\", \"required_delivery_date\": \"2026-09-05\"}', '{\"id\": 2, \"status\": \"draft\", \"remarks\": \"sgs\", \"buyer_id\": null, \"subtotal\": \"12300.0000\", \"created_at\": \"2026-08-26T18:25:11.000000Z\", \"created_by\": 1, \"deleted_at\": null, \"order_date\": \"2026-08-26\", \"tax_amount\": \"13.0000\", \"updated_at\": \"2026-08-26T18:25:11.000000Z\", \"customer_id\": 2, \"confirmed_at\": null, \"total_amount\": \"12301.0000\", \"warehouse_id\": 1, \"discount_amount\": \"12.0000\", \"delivery_address\": \"test\", \"order_tax_amount\": \"12.0000\", \"ordered_quantity\": \"123.0000\", \"confirmed_quantity\": \"0.0000\", \"delivered_quantity\": \"0.0000\", \"remaining_quantity\": \"123.0000\", \"sales_order_number\": \"SO-20260826-0001\", \"contact_information\": \"098765432\", \"order_discount_amount\": \"10.0000\", \"required_delivery_date\": \"2026-09-05\"}', '2026-08-26 12:25:38', '2026-08-26 12:25:38'),
(27, 1, 'sales-orders', 'App\\Models\\SalesOrder', 2, 'status_changed', '{\"status\": \"draft\"}', '{\"status\": \"submitted\", \"remarks\": \"Sales Order submitted for availability review.\"}', '2026-08-26 12:30:58', '2026-08-26 12:30:58'),
(28, 1, 'sales-orders', 'App\\Models\\SalesOrder', 2, 'status_changed', '{\"status\": \"submitted\"}', '{\"status\": \"confirmed\", \"remarks\": \"Sales Order confirmed after finished-goods availability check.\"}', '2026-08-26 12:31:13', '2026-08-26 12:31:13'),
(29, 1, 'sales-orders', 'App\\Models\\SalesOrder', 2, 'status_changed', '{\"status\": \"confirmed\"}', '{\"status\": \"ready_for_delivery\", \"remarks\": null}', '2026-08-26 12:32:03', '2026-08-26 12:32:03'),
(30, 1, 'sales-orders', 'App\\Models\\SalesOrder', 2, 'status_changed', '{\"status\": \"ready_for_delivery\"}', '{\"status\": \"delivered\", \"remarks\": null}', '2026-08-26 12:32:39', '2026-08-26 12:32:39'),
(31, 1, 'sales-orders', 'App\\Models\\SalesOrder', 2, 'status_changed', '{\"status\": \"delivered\"}', '{\"status\": \"completed\", \"remarks\": null}', '2026-08-26 12:33:13', '2026-08-26 12:33:13'),
(32, 1, 'procurement-requisition-items', 'App\\Models\\PurchaseRequisitionItem', 2, 'created', NULL, '{\"id\": 2, \"remarks\": null, \"unit_id\": 1, \"quantity\": \"500.0000\", \"created_at\": \"2026-08-26T18:36:17.000000Z\", \"updated_at\": \"2026-08-26T18:36:17.000000Z\", \"line_number\": 1, \"material_id\": 2, \"converted_quantity\": \"0.0000\", \"material_requirement_id\": null, \"purchase_requisition_id\": 2}', '2026-08-26 12:36:17', '2026-08-26 12:36:17'),
(33, 1, 'procurement-requisitions', 'App\\Models\\PurchaseRequisition', 2, 'created', NULL, '{\"id\": 2, \"source\": null, \"status\": \"draft\", \"remarks\": null, \"priority\": \"normal\", \"created_at\": \"2026-08-26T18:36:17.000000Z\", \"updated_at\": \"2026-08-26T18:36:17.000000Z\", \"request_date\": \"2026-08-26\", \"requested_by\": 1, \"department_id\": null, \"required_date\": \"2026-08-30\", \"requisition_number\": \"PR-20260826-0001\"}', '2026-08-26 12:36:17', '2026-08-26 12:36:17');

-- --------------------------------------------------------

--
-- Table structure for table `bom_headers`
--

DROP TABLE IF EXISTS `bom_headers`;
CREATE TABLE IF NOT EXISTS `bom_headers` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bom_headers_product_id_unique` (`product_id`),
  UNIQUE KEY `bom_headers_code_unique` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bom_headers`
--

INSERT INTO `bom_headers` (`id`, `product_id`, `code`, `name`, `status`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'BOM-TEE-001', 'Classic Cotton Crewneck Tee Engineering BOM', 'active', 'Active production BOM for Classic Cotton Tee', '2026-08-25 11:41:55', '2026-08-26 12:03:36', NULL),
(2, 2, 'BOM-POLO-PREM-001', 'Premium Polo Standard Technical BOM', 'active', 'Certified production specification for Premium Polo Shirt.', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bom_items`
--

DROP TABLE IF EXISTS `bom_items`;
CREATE TABLE IF NOT EXISTS `bom_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `bom_version_id` bigint UNSIGNED NOT NULL,
  `material_id` bigint UNSIGNED NOT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `wastage_percentage` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `line_number` int UNSIGNED NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bom_items_bom_version_id_material_id_unique` (`bom_version_id`,`material_id`),
  KEY `bom_items_material_id_foreign` (`material_id`),
  KEY `bom_items_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bom_items`
--

INSERT INTO `bom_items` (`id`, `bom_version_id`, `material_id`, `unit_id`, `quantity`, `wastage_percentage`, `line_number`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1.5000, 5.0000, 1, 'Primary knit body fabric', '2026-08-25 11:41:55', '2026-08-25 11:41:55'),
(2, 2, 2, 1, 0.2800, 4.0000, 1, 'Pique knit body fabric', '2026-08-26 11:52:50', '2026-08-26 11:52:50'),
(3, 2, 4, 2, 3.0000, 2.0000, 2, 'Placket buttons', '2026-08-26 11:52:50', '2026-08-26 11:52:50'),
(4, 2, 3, 4, 0.0500, 1.0000, 3, 'Assembly stitching thread', '2026-08-26 11:52:50', '2026-08-26 11:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `bom_versions`
--

DROP TABLE IF EXISTS `bom_versions`;
CREATE TABLE IF NOT EXISTS `bom_versions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `bom_header_id` bigint UNSIGNED NOT NULL,
  `version_number` int UNSIGNED NOT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bom_versions_bom_header_id_version_number_unique` (`bom_header_id`,`version_number`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bom_versions`
--

INSERT INTO `bom_versions` (`id`, `bom_header_id`, `version_number`, `effective_from`, `effective_to`, `status`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, '2026-01-01', NULL, 'active', 'Baseline production specification v1.0', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 2, 1, '2026-01-01', NULL, 'active', 'Factory floor v1.0 standard.', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `buyers`
--

DROP TABLE IF EXISTS `buyers`;
CREATE TABLE IF NOT EXISTS `buyers` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `buyers_code_unique` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buyers`
--

INSERT INTO `buyers` (`id`, `code`, `name`, `company`, `contact_name`, `email`, `phone`, `country`, `address`, `status`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'BUY-001', 'Global Apparel Buyer', 'Global Brands Inc', 'John Doe', 'buyer@globalbrands.example', '+1-555-0100', 'United States', '100 Fashion Avenue, New York, NY 10001', 'active', 'Primary international buyer account.', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 'BUY-HM-001', 'H&M Hennes & Mauritz AB', 'H&M Group International', 'Lars Lindqvist', 'sourcing@hm-apparel.example', '+46-8-796-5500', 'Sweden', 'Mäster Samuelsgatan 46A, 106 38 Stockholm', 'active', 'Major European fast-fashion retailer.', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(3, 'BUY-ZARA-002', 'Zara / Inditex Group', 'Industria de Diseño Textil, S.A.', 'Elena Gomez', 'procurement@inditex.example', '+34-981-185-400', 'Spain', 'Avenida de la Diputación, Arteixo, A Coruña', 'active', 'Continuous agile replenishment buyer.', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(4, 'BUY-TGT-003', 'Target Global Sourcing', 'Target Brands Inc', 'Marcus Vance', 'sourcing@target-retail.example', '+1-612-304-6073', 'United States', '1000 Nicollet Mall, Minneapolis, MN 55403', 'active', 'Large volume department store orders.', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(5, 'BUY-UNI-004', 'Uniqlo / Fast Retailing', 'Fast Retailing Co., Ltd.', 'Kenji Takahashi', 'apparel-orders@uniqlo.example', '+81-3-6865-0050', 'Japan', 'Midtown Tower, Akasaka, Minato-ku, Tokyo', 'active', 'Strict quality inspection and high repeat volumes.', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `buyer_orders`
--

DROP TABLE IF EXISTS `buyer_orders`;
CREATE TABLE IF NOT EXISTS `buyer_orders` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_id` bigint UNSIGNED NOT NULL,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_date` date NOT NULL,
  `delivery_date` date NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `total_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `total_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `buyer_orders_order_number_unique` (`order_number`),
  KEY `buyer_orders_buyer_id_foreign` (`buyer_id`),
  KEY `buyer_orders_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buyer_orders`
--

INSERT INTO `buyer_orders` (`id`, `buyer_id`, `order_number`, `order_date`, `delivery_date`, `status`, `total_quantity`, `total_amount`, `remarks`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 2, 'BO-2026-HM-001', '2026-08-01', '2026-09-15', 'confirmed', 1200.0000, 22200.0000, 'Autumn Season Polo Launch for Europe.', 1, '2026-08-26 11:56:16', '2026-08-26 11:56:16', NULL),
(2, 3, 'BO-2026-ZARA-002', '2026-08-05', '2026-09-20', 'approved', 800.0000, 11200.0000, 'Fast-track replenishment order.', 1, '2026-08-26 11:56:16', '2026-08-26 11:56:16', NULL),
(3, 4, 'BO-2026-TGT-003', '2026-08-10', '2026-10-01', 'draft', 2500.0000, 43000.0000, 'Bulk seasonal order review.', 1, '2026-08-26 11:56:16', '2026-08-26 11:56:16', NULL),
(4, 2, 'BO-20260826-0001', '2026-08-26', '2026-10-09', 'completed', 200.0000, 40000.0000, 'test', 1, '2026-08-26 12:17:00', '2026-08-26 12:17:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `buyer_order_items`
--

DROP TABLE IF EXISTS `buyer_order_items`;
CREATE TABLE IF NOT EXISTS `buyer_order_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_order_id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `unit_price` decimal(15,4) NOT NULL,
  `item_total` decimal(15,4) NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `buyer_order_items_buyer_order_id_foreign` (`buyer_order_id`),
  KEY `buyer_order_items_product_id_foreign` (`product_id`),
  KEY `buyer_order_items_product_variant_id_foreign` (`product_variant_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buyer_order_items`
--

INSERT INTO `buyer_order_items` (`id`, `buyer_order_id`, `product_id`, `product_variant_id`, `quantity`, `unit_price`, `item_total`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 2, 1200.0000, 18.5000, 22200.0000, 'European export spec', '2026-08-26 11:56:16', '2026-08-26 11:56:16'),
(2, 2, 1, 1, 800.0000, 14.0000, 11200.0000, NULL, '2026-08-26 11:56:16', '2026-08-26 11:56:16'),
(3, 3, 2, 2, 2500.0000, 17.2000, 43000.0000, NULL, '2026-08-26 11:56:16', '2026-08-26 11:56:16'),
(4, 4, 2, 2, 200.0000, 200.0000, 40000.0000, NULL, '2026-08-26 12:17:00', '2026-08-26 12:17:00');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('garmentflow-cache-c4711da846908e427eae018f33d1e911:timer', 'i:1787769070;', 1787769070),
('garmentflow-cache-c4711da846908e427eae018f33d1e911', 'i:1;', 1787769070),
('garmentflow-cache-093d7f2e4fe27b523df53459d8b03056:timer', 'i:1787763559;', 1787763559),
('garmentflow-cache-093d7f2e4fe27b523df53459d8b03056', 'i:1;', 1787763559),
('garmentflow-cache-554fe98f15f28b4ba457fa22ffd9207f:timer', 'i:1787767625;', 1787767625),
('garmentflow-cache-554fe98f15f28b4ba457fa22ffd9207f', 'i:1;', 1787767625),
('garmentflow-cache-503121a84c2354fee1be1aa5130338be:timer', 'i:1787767639;', 1787767639),
('garmentflow-cache-503121a84c2354fee1be1aa5130338be', 'i:1;', 1787767639),
('garmentflow-cache-9a2abe100a84bd21beac4cb500a0e28d:timer', 'i:1787766717;', 1787766717),
('garmentflow-cache-9a2abe100a84bd21beac4cb500a0e28d', 'i:1;', 1787766717),
('garmentflow-cache-471b960901b717be614e96950d7f0f4e:timer', 'i:1787766720;', 1787766720),
('garmentflow-cache-471b960901b717be614e96950d7f0f4e', 'i:1;', 1787766720);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_code_unique` (`code`),
  KEY `categories_parent_id_foreign` (`parent_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `code`, `name`, `description`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, NULL, 'APPAREL', 'Apparel & Garments', 'Finished ready-to-wear clothing', 'active', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, NULL, 'APP-MEN', 'Men\'s Apparel', NULL, 'active', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `colors`
--

DROP TABLE IF EXISTS `colors`;
CREATE TABLE IF NOT EXISTS `colors` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hex_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `colors_code_unique` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `colors`
--

INSERT INTO `colors` (`id`, `code`, `name`, `hex_code`, `status`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'NAVY', 'Navy Blue', '#000080', 'active', 'Dark Navy Solid', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 'BLK', 'Pitch Black', '#000000', 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
CREATE TABLE IF NOT EXISTS `customers` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_code_unique` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `code`, `name`, `contact_name`, `email`, `phone`, `country`, `address`, `status`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'CUS-001', 'Retail Partner Customer', 'Jane Smith', 'customer@retailpartner.example', '+1-555-0200', 'United States', '200 Retail Parkway, Chicago, IL 60601', 'active', 'Major retail chain partner.', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 'CUST-HM-EU', 'H&M European Logistics Hub', NULL, 'logistics@hm.example', NULL, NULL, NULL, 'active', NULL, '2026-08-26 11:57:48', '2026-08-26 11:57:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

DROP TABLE IF EXISTS `deliveries`;
CREATE TABLE IF NOT EXISTS `deliveries` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sales_order_id` bigint UNSIGNED NOT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `delivery_date` date DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `ordered_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `dispatched_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `delivered_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remaining_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `carrier_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_address` text COLLATE utf8mb4_unicode_ci,
  `contact_information` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `deliveries_delivery_number_unique` (`delivery_number`),
  KEY `deliveries_sales_order_id_foreign` (`sales_order_id`),
  KEY `deliveries_warehouse_id_foreign` (`warehouse_id`),
  KEY `deliveries_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deliveries`
--

INSERT INTO `deliveries` (`id`, `delivery_number`, `sales_order_id`, `warehouse_id`, `status`, `delivery_date`, `expected_delivery_date`, `dispatched_at`, `delivered_at`, `ordered_quantity`, `dispatched_quantity`, `delivered_quantity`, `remaining_quantity`, `carrier_name`, `tracking_number`, `delivery_address`, `contact_information`, `remarks`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'DEL-2026-001', 1, 3, 'delivered', '2026-08-22', '2026-08-27', '2026-08-22 04:00:00', '2026-08-26 08:00:00', 1200.0000, 1200.0000, 1200.0000, 0.0000, 'Maersk Line Intermodal', 'MSK-OCN-9481023', NULL, NULL, 'Container #MSKU-8821940 sealed and released for export.', 1, '2026-08-26 11:57:48', '2026-08-26 11:57:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_items`
--

DROP TABLE IF EXISTS `delivery_items`;
CREATE TABLE IF NOT EXISTS `delivery_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_id` bigint UNSIGNED NOT NULL,
  `sales_order_item_id` bigint UNSIGNED NOT NULL,
  `line_number` int UNSIGNED NOT NULL DEFAULT '1',
  `product_id` bigint UNSIGNED NOT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `delivery_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `dispatched_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `delivered_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remaining_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `inventory_transaction_id` bigint UNSIGNED DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_items_delivery_id_foreign` (`delivery_id`),
  KEY `delivery_items_sales_order_item_id_foreign` (`sales_order_item_id`),
  KEY `delivery_items_product_id_foreign` (`product_id`),
  KEY `delivery_items_product_variant_id_foreign` (`product_variant_id`),
  KEY `delivery_items_unit_id_foreign` (`unit_id`),
  KEY `delivery_items_inventory_transaction_id_foreign` (`inventory_transaction_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_items`
--

INSERT INTO `delivery_items` (`id`, `delivery_id`, `sales_order_item_id`, `line_number`, `product_id`, `product_variant_id`, `unit_id`, `delivery_quantity`, `dispatched_quantity`, `delivered_quantity`, `remaining_quantity`, `inventory_transaction_id`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 2, 2, 2, 1200.0000, 1200.0000, 1200.0000, 0.0000, NULL, 'Full fulfillment.', '2026-08-26 11:57:48', '2026-08-26 11:57:48');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_tracking_histories`
--

DROP TABLE IF EXISTS `delivery_tracking_histories`;
CREATE TABLE IF NOT EXISTS `delivery_tracking_histories` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_id` bigint UNSIGNED NOT NULL,
  `previous_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changed_by` bigint UNSIGNED NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_tracking_histories_delivery_id_foreign` (`delivery_id`),
  KEY `delivery_tracking_histories_changed_by_foreign` (`changed_by`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_tracking_histories`
--

INSERT INTO `delivery_tracking_histories` (`id`, `delivery_id`, `previous_status`, `new_status`, `carrier_name`, `tracking_number`, `location`, `changed_by`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 'ready_for_dispatch', 'dispatched', 'Maersk Line Intermodal', 'MSK-OCN-9481023', 'Chittagong Port Terminal', 1, 'Loaded aboard vessel MSC Gülsün.', '2026-08-26 11:58:00', '2026-08-26 11:58:00'),
(2, 1, 'dispatched', 'delivered', 'Maersk Line Intermodal', 'MSK-OCN-9481023', 'Port of Hamburg Terminal Burchardkai', 1, 'Customs cleared and delivered to H&M Logistics Hub.', '2026-08-26 11:58:00', '2026-08-26 11:58:00');

-- --------------------------------------------------------

--
-- Table structure for table `demand_forecasts`
--

DROP TABLE IF EXISTS `demand_forecasts`;
CREATE TABLE IF NOT EXISTS `demand_forecasts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` bigint UNSIGNED NOT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `period_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `forecast_quantity` decimal(15,4) NOT NULL,
  `method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `forecast_date` date DEFAULT NULL,
  `confidence_score` decimal(8,4) DEFAULT NULL,
  `accuracy_score` decimal(8,4) DEFAULT NULL,
  `lookback_periods` int UNSIGNED DEFAULT NULL,
  `calculation_snapshot` json DEFAULT NULL,
  `created_by` bigint UNSIGNED NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `demand_forecasts_product_id_foreign` (`product_id`),
  KEY `demand_forecasts_product_variant_id_foreign` (`product_variant_id`),
  KEY `demand_forecasts_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `demand_forecasts`
--

INSERT INTO `demand_forecasts` (`id`, `product_id`, `product_variant_id`, `period_type`, `period_start`, `period_end`, `forecast_quantity`, `method`, `status`, `forecast_date`, `confidence_score`, `accuracy_score`, `lookback_periods`, `calculation_snapshot`, `created_by`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 2, 2, 'monthly', '2026-08-01', '2026-08-31', 1500.0000, 'historical_average', 'active', '2026-08-01', 94.5000, NULL, 3, NULL, 1, 'Generated from 3-month rolling sales average.', '2026-08-26 11:56:16', '2026-08-26 11:56:16', NULL),
(2, 1, 1, 'monthly', '2026-08-01', '2026-08-31', 1000.0000, 'manual', 'active', '2026-08-01', 88.0000, NULL, 3, NULL, 1, 'Manual executive baseline estimate.', '2026-08-26 11:56:16', '2026-08-26 11:56:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_code_unique` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `code`, `name`, `created_at`, `updated_at`) VALUES
(1, 'EXEC', 'Executive Management', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(2, 'SCM', 'Supply Chain Management', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(3, 'PROD', 'Production', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(4, 'PROC', 'Procurement', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(5, 'WH', 'Warehouse & Inventory', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(6, 'FIN', 'Finance & Accounts', '2026-08-25 11:41:54', '2026-08-25 11:41:54');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finished_goods`
--

DROP TABLE IF EXISTS `finished_goods`;
CREATE TABLE IF NOT EXISTS `finished_goods` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `finished_goods_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `production_order_id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `warehouse_location_id` bigint UNSIGNED DEFAULT NULL,
  `inventory_transaction_id` bigint UNSIGNED DEFAULT NULL,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finished_date` date NOT NULL,
  `recorded_by` bigint UNSIGNED NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `finished_goods_finished_goods_number_unique` (`finished_goods_number`),
  UNIQUE KEY `finished_goods_idempotency_key_unique` (`idempotency_key`),
  KEY `finished_goods_production_order_id_foreign` (`production_order_id`),
  KEY `finished_goods_product_id_foreign` (`product_id`),
  KEY `finished_goods_product_variant_id_foreign` (`product_variant_id`),
  KEY `finished_goods_unit_id_foreign` (`unit_id`),
  KEY `finished_goods_warehouse_id_foreign` (`warehouse_id`),
  KEY `finished_goods_warehouse_location_id_foreign` (`warehouse_location_id`),
  KEY `finished_goods_inventory_transaction_id_foreign` (`inventory_transaction_id`),
  KEY `finished_goods_recorded_by_foreign` (`recorded_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `finished_goods`
--

INSERT INTO `finished_goods` (`id`, `finished_goods_number`, `production_order_id`, `product_id`, `product_variant_id`, `unit_id`, `quantity`, `warehouse_id`, `warehouse_location_id`, `inventory_transaction_id`, `idempotency_key`, `finished_date`, `recorded_by`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'FG-2026-001', 1, 2, 2, 2, 1200.0000, 3, 4, NULL, NULL, '2026-08-18', 1, 'Transferred directly to Export Finished Goods Warehouse.', '2026-08-26 11:57:48', '2026-08-26 11:57:48');

-- --------------------------------------------------------

--
-- Table structure for table `goods_receipts`
--

DROP TABLE IF EXISTS `goods_receipts`;
CREATE TABLE IF NOT EXISTS `goods_receipts` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_order_id` bigint UNSIGNED NOT NULL,
  `supplier_id` bigint UNSIGNED NOT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `warehouse_location_id` bigint UNSIGNED DEFAULT NULL,
  `receipt_date` date NOT NULL,
  `received_by` bigint UNSIGNED NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `goods_receipts_receipt_number_unique` (`receipt_number`),
  KEY `goods_receipts_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `goods_receipts_supplier_id_foreign` (`supplier_id`),
  KEY `goods_receipts_warehouse_id_foreign` (`warehouse_id`),
  KEY `goods_receipts_warehouse_location_id_foreign` (`warehouse_location_id`),
  KEY `goods_receipts_received_by_foreign` (`received_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `goods_receipts`
--

INSERT INTO `goods_receipts` (`id`, `receipt_number`, `purchase_order_id`, `supplier_id`, `warehouse_id`, `warehouse_location_id`, `receipt_date`, `received_by`, `status`, `remarks`, `posted_at`, `created_at`, `updated_at`) VALUES
(1, 'GRN-2026-001', 1, 2, 2, 2, '2026-08-08', 1, 'posted', '100% QC Passed. Passed shade & shrinkage test.', '2026-08-08 08:30:00', '2026-08-26 11:57:05', '2026-08-26 11:57:05');

-- --------------------------------------------------------

--
-- Table structure for table `goods_receipt_items`
--

DROP TABLE IF EXISTS `goods_receipt_items`;
CREATE TABLE IF NOT EXISTS `goods_receipt_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `goods_receipt_id` bigint UNSIGNED NOT NULL,
  `purchase_order_item_id` bigint UNSIGNED NOT NULL,
  `material_id` bigint UNSIGNED NOT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `ordered_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `received_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `accepted_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `rejected_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `line_number` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goods_receipt_items_goods_receipt_id_foreign` (`goods_receipt_id`),
  KEY `goods_receipt_items_purchase_order_item_id_foreign` (`purchase_order_item_id`),
  KEY `goods_receipt_items_material_id_foreign` (`material_id`),
  KEY `goods_receipt_items_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `goods_receipt_items`
--

INSERT INTO `goods_receipt_items` (`id`, `goods_receipt_id`, `purchase_order_item_id`, `material_id`, `unit_id`, `ordered_quantity`, `received_quantity`, `accepted_quantity`, `rejected_quantity`, `remarks`, `line_number`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 2, 1, 1000.0000, 1000.0000, 1000.0000, 0.0000, 'Batch #COT-26-889', 1, '2026-08-26 11:57:05', '2026-08-26 11:57:05');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_balances`
--

DROP TABLE IF EXISTS `inventory_balances`;
CREATE TABLE IF NOT EXISTS `inventory_balances` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `stock_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `warehouse_location_id` bigint UNSIGNED DEFAULT NULL,
  `material_id` bigint UNSIGNED DEFAULT NULL,
  `product_id` bigint UNSIGNED DEFAULT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `quantity_on_hand` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `quantity_reserved` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `item_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'material',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_balances_stock_key_unique` (`stock_key`),
  KEY `inventory_balances_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventory_balances_warehouse_location_id_foreign` (`warehouse_location_id`),
  KEY `inventory_balances_material_id_foreign` (`material_id`),
  KEY `inventory_balances_product_id_foreign` (`product_id`),
  KEY `inventory_balances_product_variant_id_foreign` (`product_variant_id`),
  KEY `inventory_balances_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_balances`
--

INSERT INTO `inventory_balances` (`id`, `stock_key`, `warehouse_id`, `warehouse_location_id`, `material_id`, `product_id`, `product_variant_id`, `unit_id`, `quantity_on_hand`, `quantity_reserved`, `item_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'WH-2-LOC-2-MAT-2-U-1', 2, 2, 2, NULL, NULL, 1, 2500.0000, 0.0000, 'material', 'active', '2026-08-26 11:54:48', '2026-08-26 11:54:48'),
(2, 'WH-2-LOC-2-MAT-3-U-4', 2, 2, 3, NULL, NULL, 4, 400.0000, 0.0000, 'material', 'active', '2026-08-26 11:55:04', '2026-08-26 11:55:04'),
(3, 'WH-2-LOC-2-MAT-4-U-2', 2, 2, 4, NULL, NULL, 2, 15000.0000, 0.0000, 'material', 'active', '2026-08-26 11:55:04', '2026-08-26 11:55:04'),
(4, 'WH-3-LOC-4-product_variant-2-U-2', 3, 4, NULL, NULL, 2, 2, 1200.0000, 0.0000, 'product_variant', 'active', '2026-08-26 11:57:48', '2026-08-26 11:57:48');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

DROP TABLE IF EXISTS `inventory_transactions`;
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inventory_balance_id` bigint UNSIGNED NOT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `warehouse_location_id` bigint UNSIGNED DEFAULT NULL,
  `material_id` bigint UNSIGNED DEFAULT NULL,
  `product_id` bigint UNSIGNED DEFAULT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `transaction_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint UNSIGNED DEFAULT NULL,
  `performed_by` bigint UNSIGNED NOT NULL,
  `transaction_date` timestamp NOT NULL,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_transactions_transaction_number_unique` (`transaction_number`),
  UNIQUE KEY `inventory_transactions_idempotency_key_unique` (`idempotency_key`),
  KEY `inventory_transactions_inventory_balance_id_foreign` (`inventory_balance_id`),
  KEY `inventory_transactions_warehouse_id_foreign` (`warehouse_id`),
  KEY `inventory_transactions_warehouse_location_id_foreign` (`warehouse_location_id`),
  KEY `inventory_transactions_material_id_foreign` (`material_id`),
  KEY `inventory_transactions_product_id_foreign` (`product_id`),
  KEY `inventory_transactions_product_variant_id_foreign` (`product_variant_id`),
  KEY `inventory_transactions_unit_id_foreign` (`unit_id`),
  KEY `inventory_transactions_performed_by_foreign` (`performed_by`),
  KEY `inventory_transactions_transaction_type_index` (`transaction_type`),
  KEY `inventory_transactions_reference_type_index` (`reference_type`),
  KEY `inventory_transactions_reference_id_index` (`reference_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `transaction_number`, `inventory_balance_id`, `warehouse_id`, `warehouse_location_id`, `material_id`, `product_id`, `product_variant_id`, `unit_id`, `quantity`, `transaction_type`, `reference_type`, `reference_id`, `performed_by`, `transaction_date`, `idempotency_key`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'TX-INIT-FAB-PIQUE-100', 1, 2, 2, 2, NULL, NULL, 1, 2500.0000, 'STOCK_IN', NULL, NULL, 1, '2026-08-01 02:00:00', NULL, 'Opening audited warehouse balance.', '2026-08-26 11:55:04', '2026-08-26 11:55:04'),
(2, 'TX-INIT-TRM-THREAD-120', 2, 2, 2, 3, NULL, NULL, 4, 400.0000, 'STOCK_IN', NULL, NULL, 1, '2026-08-01 02:00:00', NULL, 'Opening audited warehouse balance.', '2026-08-26 11:55:04', '2026-08-26 11:55:04'),
(3, 'TX-INIT-TRM-BTN-4HOLE', 3, 2, 2, 4, NULL, NULL, 2, 15000.0000, 'STOCK_IN', NULL, NULL, 1, '2026-08-01 02:00:00', NULL, 'Opening audited warehouse balance.', '2026-08-26 11:55:04', '2026-08-26 11:55:04'),
(4, 'TX-FG-INIT-POLO-PREM', 4, 3, 4, NULL, NULL, 2, 2, 1200.0000, 'STOCK_IN', NULL, NULL, 1, '2026-08-18 11:00:00', NULL, 'Finished goods posted from Production Order #PROD-ORD-2026-001.', '2026-08-26 11:57:48', '2026-08-26 11:57:48');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sales_order_id` bigint UNSIGNED NOT NULL,
  `buyer_id` bigint UNSIGNED DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `subtotal` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `discount_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `tax_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `total_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `paid_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `due_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `issued_at` timestamp NULL DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_sales_order_id_foreign` (`sales_order_id`),
  KEY `invoices_buyer_id_foreign` (`buyer_id`),
  KEY `invoices_customer_id_foreign` (`customer_id`),
  KEY `invoices_warehouse_id_foreign` (`warehouse_id`),
  KEY `invoices_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `sales_order_id`, `buyer_id`, `customer_id`, `warehouse_id`, `invoice_date`, `due_date`, `status`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `paid_amount`, `due_amount`, `issued_at`, `remarks`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'INV-2026-001', 1, 2, 2, 3, '2026-08-23', '2026-09-22', 'paid', 22200.0000, 0.0000, 0.0000, 22200.0000, 22200.0000, 0.0000, NULL, 'Commercial export invoice approved by Buyer Sourcing.', 1, '2026-08-26 11:58:00', '2026-08-26 11:58:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `sales_order_item_id` bigint UNSIGNED DEFAULT NULL,
  `line_number` int UNSIGNED NOT NULL DEFAULT '1',
  `product_id` bigint UNSIGNED NOT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `unit_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `discount_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `tax_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `line_total` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_foreign` (`invoice_id`),
  KEY `invoice_items_sales_order_item_id_foreign` (`sales_order_item_id`),
  KEY `invoice_items_product_id_foreign` (`product_id`),
  KEY `invoice_items_product_variant_id_foreign` (`product_variant_id`),
  KEY `invoice_items_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `sales_order_item_id`, `line_number`, `product_id`, `product_variant_id`, `unit_id`, `quantity`, `unit_price`, `discount_amount`, `tax_amount`, `line_total`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 2, 2, 2, 1200.0000, 18.5000, 0.0000, 0.0000, 22200.0000, NULL, '2026-08-26 11:58:00', '2026-08-26 11:58:00');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

DROP TABLE IF EXISTS `materials`;
CREATE TABLE IF NOT EXISTS `materials` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `material_category_id` bigint UNSIGNED NOT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `material_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `materials_code_unique` (`code`),
  KEY `materials_material_category_id_foreign` (`material_category_id`),
  KEY `materials_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`id`, `material_category_id`, `unit_id`, `code`, `name`, `material_type`, `status`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 'FAB-COT-001', '100% Cotton Single Jersey 180 GSM', 'Fabric', 'active', 'Premium combed cotton yarn knit fabric.', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 1, 1, 'FAB-PIQUE-100', '100% Combed Cotton Pique 220 GSM', 'Fabric', 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(3, 2, 4, 'TRM-THREAD-120', 'Spun Polyester Sewing Thread 120/2', 'Trim', 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(4, 2, 2, 'TRM-BTN-4HOLE', 'Engraved Resin 4-Hole Buttons 18L', 'Trim', 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `material_categories`
--

DROP TABLE IF EXISTS `material_categories`;
CREATE TABLE IF NOT EXISTS `material_categories` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_categories_code_unique` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `material_categories`
--

INSERT INTO `material_categories` (`id`, `code`, `name`, `description`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'FABRIC', 'Knitted & Woven Fabrics', 'Raw textile rolls and fabrics', 'active', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 'TRIM', 'Trims & Accessories', 'Buttons, zippers, labels, and threads', 'active', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `material_consumptions`
--

DROP TABLE IF EXISTS `material_consumptions`;
CREATE TABLE IF NOT EXISTS `material_consumptions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `consumption_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `production_order_id` bigint UNSIGNED NOT NULL,
  `production_order_item_id` bigint UNSIGNED NOT NULL,
  `material_id` bigint UNSIGNED NOT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `inventory_transaction_id` bigint UNSIGNED DEFAULT NULL,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consumption_date` date NOT NULL,
  `recorded_by` bigint UNSIGNED NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_consumptions_consumption_number_unique` (`consumption_number`),
  UNIQUE KEY `material_consumptions_idempotency_key_unique` (`idempotency_key`),
  KEY `material_consumptions_production_order_id_foreign` (`production_order_id`),
  KEY `material_consumptions_production_order_item_id_foreign` (`production_order_item_id`),
  KEY `material_consumptions_material_id_foreign` (`material_id`),
  KEY `material_consumptions_unit_id_foreign` (`unit_id`),
  KEY `material_consumptions_inventory_transaction_id_foreign` (`inventory_transaction_id`),
  KEY `material_consumptions_recorded_by_foreign` (`recorded_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `material_consumptions`
--

INSERT INTO `material_consumptions` (`id`, `consumption_number`, `production_order_id`, `production_order_item_id`, `material_id`, `unit_id`, `quantity`, `inventory_transaction_id`, `idempotency_key`, `consumption_date`, `recorded_by`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'MAT-CON-2026-001', 1, 1, 2, 1, 336.0000, NULL, 'mat-con-demo-001', '2026-08-11', 1, 'Issued to Cutting Section.', '2026-08-26 11:57:31', '2026-08-26 11:57:31');

-- --------------------------------------------------------

--
-- Table structure for table `material_requirements`
--

DROP TABLE IF EXISTS `material_requirements`;
CREATE TABLE IF NOT EXISTS `material_requirements` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `mrp_run_id` bigint UNSIGNED NOT NULL,
  `material_id` bigint UNSIGNED NOT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `gross_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `available_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `allocated_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `net_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_requirements_mrp_run_id_foreign` (`mrp_run_id`),
  KEY `material_requirements_material_id_foreign` (`material_id`),
  KEY `material_requirements_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `material_requirements`
--

INSERT INTO `material_requirements` (`id`, `mrp_run_id`, `material_id`, `unit_id`, `gross_quantity`, `available_quantity`, `allocated_quantity`, `net_quantity`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 1, 336.0000, 2500.0000, 0.0000, 0.0000, 'covered', NULL, '2026-08-26 11:56:16', '2026-08-26 11:56:16'),
(2, 1, 4, 2, 3600.0000, 15000.0000, 0.0000, 0.0000, 'covered', NULL, '2026-08-26 11:56:16', '2026-08-26 11:56:16');

-- --------------------------------------------------------

--
-- Table structure for table `material_requirement_sources`
--

DROP TABLE IF EXISTS `material_requirement_sources`;
CREATE TABLE IF NOT EXISTS `material_requirement_sources` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `material_requirement_id` bigint UNSIGNED NOT NULL,
  `supply_plan_id` bigint UNSIGNED DEFAULT NULL,
  `product_id` bigint UNSIGNED DEFAULT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `bom_version_id` bigint UNSIGNED DEFAULT NULL,
  `bom_item_id` bigint UNSIGNED DEFAULT NULL,
  `material_id` bigint UNSIGNED DEFAULT NULL,
  `unit_id` bigint UNSIGNED DEFAULT NULL,
  `planned_product_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `bom_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `wastage_percentage` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `gross_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_requirement_sources_material_requirement_id_foreign` (`material_requirement_id`),
  KEY `material_requirement_sources_supply_plan_id_foreign` (`supply_plan_id`),
  KEY `material_requirement_sources_product_id_foreign` (`product_id`),
  KEY `material_requirement_sources_product_variant_id_foreign` (`product_variant_id`),
  KEY `material_requirement_sources_bom_version_id_foreign` (`bom_version_id`),
  KEY `material_requirement_sources_bom_item_id_foreign` (`bom_item_id`),
  KEY `material_requirement_sources_material_id_foreign` (`material_id`),
  KEY `material_requirement_sources_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2024_01_01_000000_create_users_table', 1),
(2, '2024_01_01_000001_create_cache_table', 1),
(3, '2024_01_01_000002_create_jobs_table', 1),
(4, '2024_01_01_000003_create_personal_access_tokens_table', 1),
(5, '2026_08_23_033600_create_authorization_tables', 1),
(6, '2026_08_23_050000_create_master_data_tables', 1),
(7, '2026_08_23_060000_create_bom_tables', 1),
(8, '2026_08_23_070000_create_buyer_order_tables', 1),
(9, '2026_08_23_080000_create_planning_tables', 1),
(10, '2026_08_23_090000_create_procurement_tables', 1),
(11, '2026_08_23_100000_create_inventory_tables', 1),
(12, '2026_08_23_110000_create_production_tables', 1),
(13, '2026_08_23_120000_create_sales_tables', 1),
(14, '2026_08_24_130000_create_delivery_tables', 1),
(15, '2026_08_24_140000_create_finance_tables', 1),
(16, '2026_08_24_150000_create_alerts_tables', 1);

-- --------------------------------------------------------

--
-- Table structure for table `mrp_runs`
--

DROP TABLE IF EXISTS `mrp_runs`;
CREATE TABLE IF NOT EXISTS `mrp_runs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `planning_date` date NOT NULL,
  `total_gross_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `total_net_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `inventory_data_available` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint UNSIGNED NOT NULL,
  `calculated_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mrp_runs_run_number_unique` (`run_number`),
  KEY `mrp_runs_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mrp_runs`
--

INSERT INTO `mrp_runs` (`id`, `run_number`, `status`, `planning_date`, `total_gross_quantity`, `total_net_quantity`, `inventory_data_available`, `created_by`, `calculated_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'MRP-2026-08-001', 'calculated', '2026-08-02', 336.0000, 0.0000, 1, 1, NULL, 'August Polo execution BOM explosion.', '2026-08-26 11:56:16', '2026-08-26 11:56:16');

-- --------------------------------------------------------

--
-- Table structure for table `order_approvals`
--

DROP TABLE IF EXISTS `order_approvals`;
CREATE TABLE IF NOT EXISTS `order_approvals` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_order_id` bigint UNSIGNED NOT NULL,
  `requested_by` bigint UNSIGNED NOT NULL,
  `reviewed_by` bigint UNSIGNED DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `requested_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_approvals_buyer_order_id_foreign` (`buyer_order_id`),
  KEY `order_approvals_requested_by_foreign` (`requested_by`),
  KEY `order_approvals_reviewed_by_foreign` (`reviewed_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_approvals`
--

INSERT INTO `order_approvals` (`id`, `buyer_order_id`, `requested_by`, `reviewed_by`, `status`, `remarks`, `requested_at`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 1, 'approved', NULL, '2026-08-26 12:17:07', '2026-08-26 12:17:14', '2026-08-26 12:17:07', '2026-08-26 12:17:14');

-- --------------------------------------------------------

--
-- Table structure for table `order_planning_inputs`
--

DROP TABLE IF EXISTS `order_planning_inputs`;
CREATE TABLE IF NOT EXISTS `order_planning_inputs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_order_id` bigint UNSIGNED NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready',
  `total_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `prepared_by` bigint UNSIGNED NOT NULL,
  `prepared_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_planning_inputs_buyer_order_id_foreign` (`buyer_order_id`),
  KEY `order_planning_inputs_prepared_by_foreign` (`prepared_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_planning_inputs`
--

INSERT INTO `order_planning_inputs` (`id`, `buyer_order_id`, `status`, `total_quantity`, `prepared_by`, `prepared_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 4, 'ready', 200.0000, 1, '2026-08-26 12:17:17', NULL, '2026-08-26 12:17:17', '2026-08-26 12:17:17');

-- --------------------------------------------------------

--
-- Table structure for table `order_status_histories`
--

DROP TABLE IF EXISTS `order_status_histories`;
CREATE TABLE IF NOT EXISTS `order_status_histories` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `buyer_order_id` bigint UNSIGNED NOT NULL,
  `previous_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` bigint UNSIGNED NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_status_histories_buyer_order_id_foreign` (`buyer_order_id`),
  KEY `order_status_histories_changed_by_foreign` (`changed_by`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status_histories`
--

INSERT INTO `order_status_histories` (`id`, `buyer_order_id`, `previous_status`, `new_status`, `changed_by`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 4, NULL, 'draft', 1, 'Draft order created.', '2026-08-26 12:17:00', '2026-08-26 12:17:00'),
(2, 4, 'draft', 'submitted', 1, 'Order submitted for approval.', '2026-08-26 12:17:07', '2026-08-26 12:17:07'),
(3, 4, 'submitted', 'pending_approval', 1, 'Order is awaiting approval.', '2026-08-26 12:17:07', '2026-08-26 12:17:07'),
(4, 4, 'pending_approval', 'submitted', 1, 'Order approved and ready for confirmation.', '2026-08-26 12:17:14', '2026-08-26 12:17:14'),
(5, 4, 'submitted', 'confirmed', 1, 'Order confirmed for future planning.', '2026-08-26 12:17:17', '2026-08-26 12:17:17'),
(6, 4, 'confirmed', 'planning', 1, NULL, '2026-08-26 12:17:20', '2026-08-26 12:17:20'),
(7, 4, 'planning', 'in_production', 1, NULL, '2026-08-26 12:17:23', '2026-08-26 12:17:23'),
(8, 4, 'in_production', 'ready', 1, NULL, '2026-08-26 12:17:26', '2026-08-26 12:17:26'),
(9, 4, 'ready', 'shipped', 1, NULL, '2026-08-26 12:17:44', '2026-08-26 12:17:44'),
(10, 4, 'shipped', 'delivered', 1, NULL, '2026-08-26 12:17:47', '2026-08-26 12:17:47'),
(11, 4, 'delivered', 'completed', 1, NULL, '2026-08-26 12:17:50', '2026-08-26 12:17:50');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `buyer_id` bigint UNSIGNED DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `reference_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `received_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_payment_number_unique` (`payment_number`),
  UNIQUE KEY `payments_idempotency_key_unique` (`idempotency_key`),
  KEY `payments_invoice_id_foreign` (`invoice_id`),
  KEY `payments_buyer_id_foreign` (`buyer_id`),
  KEY `payments_customer_id_foreign` (`customer_id`),
  KEY `payments_received_by_foreign` (`received_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `payment_number`, `invoice_id`, `buyer_id`, `customer_id`, `payment_date`, `amount`, `payment_method`, `reference_number`, `idempotency_key`, `status`, `remarks`, `received_by`, `created_at`, `updated_at`) VALUES
(1, 'PAY-2026-001', 1, 2, 2, '2026-08-25', 22200.0000, 'bank_transfer', 'TT-SEB-9988231', NULL, 'received', 'Settled in full via SEB Stockholm SWIFT TT transfer.', 1, '2026-08-26 11:58:00', '2026-08-26 11:58:00');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_slug_unique` (`slug`)
) ENGINE=MyISAM AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES
(1, 'View Dashboards', 'dashboard.view', 'Access overview dashboard', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(2, 'View Executive Dashboard', 'dashboard.executive.view', 'Access executive analytics', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(3, 'View Supply Chain Dashboard', 'dashboard.supply-chain.view', 'Access supply chain analytics', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(4, 'View Production Dashboard', 'dashboard.production.view', 'Access production analytics', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(5, 'View Procurement Dashboard', 'dashboard.procurement.view', 'Access procurement analytics', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(6, 'View Warehouse Dashboard', 'dashboard.warehouse.view', 'Access warehouse analytics', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(7, 'View Reports', 'reports.view', 'Access system reports', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(8, 'Export Reports', 'reports.export', 'Export reporting data to CSV', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(9, 'View Alerts', 'alerts.view', 'Access alert notifications', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(10, 'Manage Alerts', 'alerts.manage', 'Acknowledge and manage system alerts', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(11, 'View Master Data', 'master-data.view', 'Access master data registers', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(12, 'Manage Master Data', 'master-data.manage', 'Create and modify master data', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(13, 'View BOM', 'bom.view', 'View Bills of Materials', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(14, 'Manage BOM', 'bom.manage', 'Create, modify and activate Bills of Materials', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(15, 'View Buyer Orders', 'buyer-order.view', 'Access buyer order register', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(16, 'Manage Buyer Orders', 'buyer-order.manage', 'Create and modify buyer orders', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(17, 'Approve Buyer Orders', 'buyer-order.approve', 'Review and approve buyer orders', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(18, 'Confirm Buyer Orders', 'buyer-order.confirm', 'Confirm buyer orders for production', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(19, 'View Planning', 'planning.view', 'View forecasts and supply plans', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(20, 'Manage Planning', 'planning.manage', 'Create forecasts, supply plans and MRP runs', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(21, 'View Procurement', 'procurement.view', 'View purchase requisitions and purchase orders', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(22, 'Manage Procurement', 'procurement.manage', 'Create and edit procurement documents', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(23, 'Approve Procurement', 'procurement.approve', 'Approve requisitions and purchase orders', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(24, 'View Inventory', 'inventory.view', 'View stock balances and transactions', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(25, 'Manage Inventory', 'inventory.manage', 'Perform stock movements and transfers', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(26, 'Adjust Inventory', 'inventory.adjust', 'Perform stock adjustments', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(27, 'View Production', 'production.view', 'View production plans and orders', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(28, 'Manage Production', 'production.manage', 'Create production plans and orders', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(29, 'Approve Production', 'production.approve', 'Approve and schedule production plans', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(30, 'Override Production', 'production.override', 'Override material shortage during production start', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(31, 'View Sales', 'sales.view', 'View sales orders and history', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(32, 'Manage Sales', 'sales.manage', 'Create and update sales orders', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(33, 'Confirm Sales', 'sales.confirm', 'Confirm sales orders against inventory', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(34, 'Override Sales', 'sales.override', 'Override stock shortfall for sales order confirmation', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(35, 'View Deliveries', 'delivery.view', 'View deliveries and shipment tracking', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(36, 'Manage Deliveries', 'delivery.manage', 'Create and edit delivery notes', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(37, 'Dispatch Deliveries', 'delivery.dispatch', 'Dispatch deliveries and deduct finished stock', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(38, 'Override Deliveries', 'delivery.override', 'Override delivery validation constraints', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(39, 'View Finance', 'finance.view', 'View invoices and payment ledgers', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(40, 'Manage Finance', 'finance.manage', 'Generate and manage customer invoices', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(41, 'Process Payments', 'finance.pay', 'Record and manage payments against invoices', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(42, 'Override Finance', 'finance.override', 'Override finance controls', '2026-08-25 11:41:54', '2026-08-25 11:41:54');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 'App\\Models\\User', 1, 'garmentflow-web', '5cdb6eeb33ca51eeeb4750bce880fe3deefeb475e76ea221b2e85500ef16b45e', '[\"dashboard.view\",\"master-data.view\",\"master-data.manage\",\"bom.view\",\"bom.manage\",\"buyer-order.view\",\"buyer-order.manage\",\"buyer-order.approve\",\"buyer-order.confirm\",\"planning.view\",\"planning.manage\",\"procurement.view\",\"procurement.manage\",\"procurement.approve\",\"inventory.view\",\"inventory.manage\",\"inventory.adjust\",\"production.view\",\"production.manage\",\"production.approve\",\"production.override\",\"sales.view\",\"sales.manage\",\"sales.confirm\",\"sales.override\",\"delivery.view\",\"delivery.manage\",\"delivery.dispatch\",\"delivery.override\",\"finance.view\",\"finance.manage\",\"finance.pay\",\"finance.override\",\"reports.view\",\"reports.export\",\"alerts.view\",\"alerts.manage\",\"dashboard.executive.view\",\"dashboard.supply-chain.view\",\"dashboard.production.view\",\"dashboard.procurement.view\",\"dashboard.warehouse.view\"]', NULL, NULL, '2026-08-25 11:47:26', '2026-08-25 11:47:26'),
(9, 'App\\Models\\User', 1, 'garmentflow-web', 'a600c54074ae5035df0e68028288d399b520c54493064a38f455d2e300548a0c', '[\"dashboard.view\",\"master-data.view\",\"master-data.manage\",\"bom.view\",\"bom.manage\",\"buyer-order.view\",\"buyer-order.manage\",\"buyer-order.approve\",\"buyer-order.confirm\",\"planning.view\",\"planning.manage\",\"procurement.view\",\"procurement.manage\",\"procurement.approve\",\"inventory.view\",\"inventory.manage\",\"inventory.adjust\",\"production.view\",\"production.manage\",\"production.approve\",\"production.override\",\"sales.view\",\"sales.manage\",\"sales.confirm\",\"sales.override\",\"delivery.view\",\"delivery.manage\",\"delivery.dispatch\",\"delivery.override\",\"finance.view\",\"finance.manage\",\"finance.pay\",\"finance.override\",\"reports.view\",\"reports.export\",\"alerts.view\",\"alerts.manage\",\"dashboard.executive.view\",\"dashboard.supply-chain.view\",\"dashboard.production.view\",\"dashboard.procurement.view\",\"dashboard.warehouse.view\"]', '2026-08-26 12:51:24', NULL, '2026-08-26 12:10:25', '2026-08-26 12:51:24'),
(10, 'App\\Models\\User', 1, 'garmentflow-web', 'a2b2c03445e77da140db01c761485104501305b74024eb70c54822631457197f', '[\"dashboard.view\",\"master-data.view\",\"master-data.manage\",\"bom.view\",\"bom.manage\",\"buyer-order.view\",\"buyer-order.manage\",\"buyer-order.approve\",\"buyer-order.confirm\",\"planning.view\",\"planning.manage\",\"procurement.view\",\"procurement.manage\",\"procurement.approve\",\"inventory.view\",\"inventory.manage\",\"inventory.adjust\",\"production.view\",\"production.manage\",\"production.approve\",\"production.override\",\"sales.view\",\"sales.manage\",\"sales.confirm\",\"sales.override\",\"delivery.view\",\"delivery.manage\",\"delivery.dispatch\",\"delivery.override\",\"finance.view\",\"finance.manage\",\"finance.pay\",\"finance.override\",\"reports.view\",\"reports.export\",\"alerts.view\",\"alerts.manage\",\"dashboard.executive.view\",\"dashboard.supply-chain.view\",\"dashboard.production.view\",\"dashboard.procurement.view\",\"dashboard.warehouse.view\"]', '2026-08-26 12:21:58', NULL, '2026-08-26 12:21:33', '2026-08-26 12:21:58'),
(11, 'App\\Models\\User', 1, 'garmentflow-web', '347cae171a132ba49ab8cf8d3f1653f8996857337cb5fa17099e3c4b33c28aba', '[\"dashboard.view\",\"master-data.view\",\"master-data.manage\",\"bom.view\",\"bom.manage\",\"buyer-order.view\",\"buyer-order.manage\",\"buyer-order.approve\",\"buyer-order.confirm\",\"planning.view\",\"planning.manage\",\"procurement.view\",\"procurement.manage\",\"procurement.approve\",\"inventory.view\",\"inventory.manage\",\"inventory.adjust\",\"production.view\",\"production.manage\",\"production.approve\",\"production.override\",\"sales.view\",\"sales.manage\",\"sales.confirm\",\"sales.override\",\"delivery.view\",\"delivery.manage\",\"delivery.dispatch\",\"delivery.override\",\"finance.view\",\"finance.manage\",\"finance.pay\",\"finance.override\",\"reports.view\",\"reports.export\",\"alerts.view\",\"alerts.manage\",\"dashboard.executive.view\",\"dashboard.supply-chain.view\",\"dashboard.production.view\",\"dashboard.procurement.view\",\"dashboard.warehouse.view\"]', '2026-08-26 12:50:24', NULL, '2026-08-26 12:30:11', '2026-08-26 12:50:24');

-- --------------------------------------------------------

--
-- Table structure for table `procurement_status_histories`
--

DROP TABLE IF EXISTS `procurement_status_histories`;
CREATE TABLE IF NOT EXISTS `procurement_status_histories` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_id` bigint UNSIGNED NOT NULL,
  `previous_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` bigint UNSIGNED NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `procurement_status_histories_changed_by_foreign` (`changed_by`),
  KEY `procurement_status_histories_document_type_index` (`document_type`),
  KEY `procurement_status_histories_document_id_index` (`document_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `procurement_status_histories`
--

INSERT INTO `procurement_status_histories` (`id`, `document_type`, `document_id`, `previous_status`, `new_status`, `changed_by`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'purchase_requisition', 2, NULL, 'draft', 1, 'Purchase Requisition created.', '2026-08-26 12:36:17', '2026-08-26 12:36:17');

-- --------------------------------------------------------

--
-- Table structure for table `production_orders`
--

DROP TABLE IF EXISTS `production_orders`;
CREATE TABLE IF NOT EXISTS `production_orders` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `production_plan_id` bigint UNSIGNED NOT NULL,
  `buyer_order_id` bigint UNSIGNED DEFAULT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `bom_version_id` bigint UNSIGNED NOT NULL,
  `planned_quantity` decimal(15,4) NOT NULL,
  `completed_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `rejected_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `start_date` date DEFAULT NULL,
  `expected_completion_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `issue_warehouse_id` bigint UNSIGNED NOT NULL,
  `issue_warehouse_location_id` bigint UNSIGNED DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint UNSIGNED NOT NULL,
  `completed_by` bigint UNSIGNED DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `production_orders_order_number_unique` (`order_number`),
  KEY `production_orders_production_plan_id_foreign` (`production_plan_id`),
  KEY `production_orders_buyer_order_id_foreign` (`buyer_order_id`),
  KEY `production_orders_product_id_foreign` (`product_id`),
  KEY `production_orders_product_variant_id_foreign` (`product_variant_id`),
  KEY `production_orders_bom_version_id_foreign` (`bom_version_id`),
  KEY `production_orders_issue_warehouse_id_foreign` (`issue_warehouse_id`),
  KEY `production_orders_issue_warehouse_location_id_foreign` (`issue_warehouse_location_id`),
  KEY `production_orders_created_by_foreign` (`created_by`),
  KEY `production_orders_completed_by_foreign` (`completed_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `production_orders`
--

INSERT INTO `production_orders` (`id`, `order_number`, `production_plan_id`, `buyer_order_id`, `product_id`, `product_variant_id`, `bom_version_id`, `planned_quantity`, `completed_quantity`, `rejected_quantity`, `start_date`, `expected_completion_date`, `completed_date`, `issue_warehouse_id`, `issue_warehouse_location_id`, `status`, `created_by`, `completed_by`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'PROD-ORD-2026-001', 1, 1, 2, 2, 1, 1200.0000, 1200.0000, 0.0000, '2026-08-09', '2026-08-20', '2026-08-18', 2, 2, 'completed', 1, 1, 'Completed ahead of schedule with 0% defect rate.', '2026-08-26 11:57:05', '2026-08-26 11:57:05');

-- --------------------------------------------------------

--
-- Table structure for table `production_order_items`
--

DROP TABLE IF EXISTS `production_order_items`;
CREATE TABLE IF NOT EXISTS `production_order_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `production_order_id` bigint UNSIGNED NOT NULL,
  `bom_item_id` bigint UNSIGNED DEFAULT NULL,
  `material_id` bigint UNSIGNED NOT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `bom_quantity` decimal(15,4) NOT NULL,
  `wastage_percentage` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `required_quantity` decimal(15,4) NOT NULL,
  `consumed_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `production_order_items_production_order_id_foreign` (`production_order_id`),
  KEY `production_order_items_bom_item_id_foreign` (`bom_item_id`),
  KEY `production_order_items_material_id_foreign` (`material_id`),
  KEY `production_order_items_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `production_order_items`
--

INSERT INTO `production_order_items` (`id`, `production_order_id`, `bom_item_id`, `material_id`, `unit_id`, `bom_quantity`, `wastage_percentage`, `required_quantity`, `consumed_quantity`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 2, 1, 0.2800, 4.0000, 336.0000, 336.0000, NULL, '2026-08-26 11:57:18', '2026-08-26 11:57:18');

-- --------------------------------------------------------

--
-- Table structure for table `production_plans`
--

DROP TABLE IF EXISTS `production_plans`;
CREATE TABLE IF NOT EXISTS `production_plans` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `supply_plan_id` bigint UNSIGNED DEFAULT NULL,
  `buyer_order_id` bigint UNSIGNED DEFAULT NULL,
  `planned_quantity` decimal(15,4) NOT NULL,
  `planned_start_date` date NOT NULL,
  `planned_end_date` date NOT NULL,
  `priority` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint UNSIGNED NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `production_plans_plan_number_unique` (`plan_number`),
  KEY `production_plans_product_id_foreign` (`product_id`),
  KEY `production_plans_product_variant_id_foreign` (`product_variant_id`),
  KEY `production_plans_supply_plan_id_foreign` (`supply_plan_id`),
  KEY `production_plans_buyer_order_id_foreign` (`buyer_order_id`),
  KEY `production_plans_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `production_plans`
--

INSERT INTO `production_plans` (`id`, `plan_number`, `product_id`, `product_variant_id`, `supply_plan_id`, `buyer_order_id`, `planned_quantity`, `planned_start_date`, `planned_end_date`, `priority`, `status`, `created_by`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'PP-2026-POLO-001', 2, 2, 1, 1, 1200.0000, '2026-08-09', '2026-08-20', 'high', 'completed', 1, 'Assigned to Sewing Line 04 and Finishing Line 02.', '2026-08-26 11:57:05', '2026-08-26 11:57:05');

-- --------------------------------------------------------

--
-- Table structure for table `production_progress`
--

DROP TABLE IF EXISTS `production_progress`;
CREATE TABLE IF NOT EXISTS `production_progress` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `production_order_id` bigint UNSIGNED NOT NULL,
  `planned_quantity` decimal(15,4) NOT NULL,
  `completed_quantity` decimal(15,4) NOT NULL,
  `rejected_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remaining_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `progress_percentage` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `production_date` date NOT NULL,
  `recorded_by` bigint UNSIGNED NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `production_progress_production_order_id_foreign` (`production_order_id`),
  KEY `production_progress_recorded_by_foreign` (`recorded_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `production_progress`
--

INSERT INTO `production_progress` (`id`, `production_order_id`, `planned_quantity`, `completed_quantity`, `rejected_quantity`, `remaining_quantity`, `progress_percentage`, `production_date`, `recorded_by`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 1200.0000, 1200.0000, 0.0000, 0.0000, 100.0000, '2026-08-15', 1, 'All pieces passed 100% final needle detection inspection.', '2026-08-26 11:57:48', '2026-08-26 11:57:48');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` bigint UNSIGNED NOT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `standard_cost` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `standard_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_code_unique` (`code`),
  KEY `products_category_id_foreign` (`category_id`),
  KEY `products_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `unit_id`, `code`, `name`, `product_type`, `description`, `standard_cost`, `standard_price`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 2, 'TEE-CLASSIC', 'Classic Cotton Crewneck Tee', 'Finished Good', 'Standard regular fit classic t-shirt.', 5.0000, 12.0000, 'active', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 2, 2, 'POLO-PREM', 'Premium Men Pique Polo Shirt', NULL, 'Classic fit 100% combed cotton pique polo with ribbed collar.', 0.0000, 0.0000, 'active', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` bigint UNSIGNED NOT NULL,
  `size_id` bigint UNSIGNED DEFAULT NULL,
  `color_id` bigint UNSIGNED DEFAULT NULL,
  `sku` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variant_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_price` decimal(15,4) DEFAULT NULL,
  `selling_price` decimal(15,4) DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_variants_sku_unique` (`sku`),
  KEY `product_variants_product_id_foreign` (`product_id`),
  KEY `product_variants_size_id_foreign` (`size_id`),
  KEY `product_variants_color_id_foreign` (`color_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `size_id`, `color_id`, `sku`, `variant_name`, `cost_price`, `selling_price`, `status`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 'TEE-CLASSIC-M-NAVY', 'Classic Tee - M / Navy', 5.0000, 12.0000, 'active', 'Size M in solid Navy Blue.', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 2, 1, 1, 'POLO-PREM-NAVY-M', 'Premium Polo / Navy / M', 7.5000, 18.0000, 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_approvals`
--

DROP TABLE IF EXISTS `purchase_approvals`;
CREATE TABLE IF NOT EXISTS `purchase_approvals` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_id` bigint UNSIGNED NOT NULL,
  `requested_by` bigint UNSIGNED NOT NULL,
  `reviewed_by` bigint UNSIGNED DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `requested_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_approvals_requested_by_foreign` (`requested_by`),
  KEY `purchase_approvals_reviewed_by_foreign` (`reviewed_by`),
  KEY `purchase_approvals_document_type_index` (`document_type`),
  KEY `purchase_approvals_document_id_index` (`document_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` bigint UNSIGNED NOT NULL,
  `po_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `payment_terms` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_terms` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `tax_total` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `discount_total` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `total_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint UNSIGNED NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_orders_purchase_order_number_unique` (`purchase_order_number`),
  KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_orders_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `purchase_order_number`, `supplier_id`, `po_date`, `expected_delivery_date`, `currency`, `payment_terms`, `shipping_terms`, `subtotal`, `tax_total`, `discount_total`, `total_amount`, `status`, `created_by`, `remarks`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'PO-2026-PAC-001', 2, '2026-08-03', '2026-08-10', 'USD', NULL, NULL, 4500.0000, 225.0000, 0.0000, 4725.0000, 'fully_received', 1, 'Contract rate applied per Master Agreement.', '2026-08-26 11:56:27', '2026-08-26 11:56:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint UNSIGNED NOT NULL,
  `purchase_requisition_item_id` bigint UNSIGNED DEFAULT NULL,
  `material_id` bigint UNSIGNED NOT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `unit_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `line_total` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `received_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `line_number` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_items_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `purchase_order_items_purchase_requisition_item_id_foreign` (`purchase_requisition_item_id`),
  KEY `purchase_order_items_material_id_foreign` (`material_id`),
  KEY `purchase_order_items_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `purchase_order_id`, `purchase_requisition_item_id`, `material_id`, `unit_id`, `quantity`, `unit_price`, `line_total`, `received_quantity`, `remarks`, `line_number`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 2, 1, 1000.0000, 4.5000, 4500.0000, 1000.0000, NULL, 1, '2026-08-26 11:56:50', '2026-08-26 11:56:50');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requisitions`
--

DROP TABLE IF EXISTS `purchase_requisitions`;
CREATE TABLE IF NOT EXISTS `purchase_requisitions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `requisition_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_date` date NOT NULL,
  `requested_by` bigint UNSIGNED NOT NULL,
  `department_id` bigint UNSIGNED DEFAULT NULL,
  `source` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `required_date` date DEFAULT NULL,
  `priority` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_requisitions_requisition_number_unique` (`requisition_number`),
  KEY `purchase_requisitions_requested_by_foreign` (`requested_by`),
  KEY `purchase_requisitions_department_id_foreign` (`department_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_requisitions`
--

INSERT INTO `purchase_requisitions` (`id`, `requisition_number`, `request_date`, `requested_by`, `department_id`, `source`, `required_date`, `priority`, `status`, `remarks`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'PR-2026-001', '2026-08-02', 1, NULL, 'MRP-Auto', '2026-08-12', 'high', 'converted_to_po', 'Additional yarn replenishment for Q3 reserve.', '2026-08-26 11:56:16', '2026-08-26 11:56:16', NULL),
(2, 'PR-20260826-0001', '2026-08-26', 1, NULL, NULL, '2026-08-30', 'normal', 'draft', NULL, '2026-08-26 12:36:17', '2026-08-26 12:36:17', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requisition_items`
--

DROP TABLE IF EXISTS `purchase_requisition_items`;
CREATE TABLE IF NOT EXISTS `purchase_requisition_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_requisition_id` bigint UNSIGNED NOT NULL,
  `material_id` bigint UNSIGNED NOT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `material_requirement_id` bigint UNSIGNED DEFAULT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `converted_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `line_number` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_requisition_items_purchase_requisition_id_foreign` (`purchase_requisition_id`),
  KEY `purchase_requisition_items_material_id_foreign` (`material_id`),
  KEY `purchase_requisition_items_unit_id_foreign` (`unit_id`),
  KEY `purchase_requisition_items_material_requirement_id_foreign` (`material_requirement_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_requisition_items`
--

INSERT INTO `purchase_requisition_items` (`id`, `purchase_requisition_id`, `material_id`, `unit_id`, `material_requirement_id`, `quantity`, `converted_quantity`, `remarks`, `line_number`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 1, NULL, 1000.0000, 0.0000, '100% Cotton Pique 220 GSM', 1, '2026-08-26 11:56:16', '2026-08-26 11:56:16'),
(2, 2, 2, 1, NULL, 500.0000, 0.0000, NULL, 1, '2026-08-26 12:36:17', '2026-08-26 12:36:17');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Administrator', 'administrator', 'Full administrative access across all modules.', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(2, 'Operator', 'operator', 'General operational access.', '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(3, 'Chief Executive Officer (CEO)', 'ceo', 'Executive visibility across overall business KPIs, financials and operations.', '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(4, 'Supply Chain Manager', 'supply-chain-manager', 'Demand forecasting, supply planning, MRP and shortage mitigation.', '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(5, 'Production Manager', 'production-manager', 'Production scheduling, floor progress, material consumption and finished goods.', '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(6, 'Procurement Manager', 'procurement-manager', 'Supplier management, requisitions, purchase orders and goods receipt.', '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(7, 'Warehouse Manager', 'warehouse-manager', 'Inventory balance, warehouse tracking, stock in/out, transfers and adjustments.', '2026-08-26 11:52:49', '2026-08-26 11:52:49');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` bigint UNSIGNED NOT NULL,
  `permission_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permissions_role_id_permission_id_unique` (`role_id`,`permission_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`)
) ENGINE=MyISAM AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(2, 1, 2, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(3, 1, 3, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(4, 1, 4, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(5, 1, 5, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(6, 1, 6, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(7, 1, 7, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(8, 1, 8, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(9, 1, 9, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(10, 1, 10, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(11, 1, 11, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(12, 1, 12, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(13, 1, 13, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(14, 1, 14, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(15, 1, 15, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(16, 1, 16, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(17, 1, 17, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(18, 1, 18, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(19, 1, 19, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(20, 1, 20, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(21, 1, 21, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(22, 1, 22, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(23, 1, 23, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(24, 1, 24, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(25, 1, 25, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(26, 1, 26, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(27, 1, 27, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(28, 1, 28, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(29, 1, 29, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(30, 1, 30, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(31, 1, 31, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(32, 1, 32, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(33, 1, 33, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(34, 1, 34, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(35, 1, 35, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(36, 1, 36, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(37, 1, 37, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(38, 1, 38, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(39, 1, 39, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(40, 1, 40, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(41, 1, 41, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(42, 1, 42, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(43, 3, 1, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(44, 3, 2, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(45, 3, 3, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(46, 3, 4, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(47, 3, 5, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(48, 3, 6, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(49, 3, 7, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(50, 3, 8, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(51, 3, 9, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(52, 3, 10, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(53, 3, 11, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(54, 3, 12, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(55, 3, 13, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(56, 3, 14, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(57, 3, 15, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(58, 3, 16, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(59, 3, 17, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(60, 3, 18, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(61, 3, 19, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(62, 3, 20, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(63, 3, 21, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(64, 3, 22, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(65, 3, 23, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(66, 3, 24, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(67, 3, 25, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(68, 3, 26, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(69, 3, 27, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(70, 3, 28, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(71, 3, 29, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(72, 3, 30, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(73, 3, 31, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(74, 3, 32, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(75, 3, 33, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(76, 3, 34, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(77, 3, 35, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(78, 3, 36, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(79, 3, 37, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(80, 3, 38, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(81, 3, 39, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(82, 3, 40, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(83, 3, 41, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(84, 3, 42, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(85, 4, 10, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(86, 4, 9, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(87, 4, 13, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(88, 4, 15, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(89, 4, 3, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(90, 4, 1, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(91, 4, 24, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(92, 4, 20, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(93, 4, 19, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(94, 4, 7, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(95, 5, 10, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(96, 5, 9, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(97, 5, 13, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(98, 5, 4, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(99, 5, 1, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(100, 5, 24, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(101, 5, 29, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(102, 5, 28, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(103, 5, 30, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(104, 5, 27, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(105, 5, 7, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(106, 6, 10, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(107, 6, 9, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(108, 6, 5, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(109, 6, 1, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(110, 6, 24, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(111, 6, 11, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(112, 6, 23, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(113, 6, 22, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(114, 6, 21, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(115, 6, 7, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(116, 7, 1, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(117, 7, 6, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(118, 7, 7, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(119, 7, 9, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(120, 7, 10, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(121, 7, 11, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(122, 7, 24, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(123, 7, 25, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(124, 7, 26, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(125, 7, 35, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(126, 7, 36, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(127, 7, 37, '2026-08-26 11:52:49', '2026-08-26 11:52:49');

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

DROP TABLE IF EXISTS `sales_orders`;
CREATE TABLE IF NOT EXISTS `sales_orders` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `sales_order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_id` bigint UNSIGNED DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `order_date` date NOT NULL,
  `required_delivery_date` date DEFAULT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `delivery_address` text COLLATE utf8mb4_unicode_ci,
  `contact_information` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `subtotal` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `order_discount_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `order_tax_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `discount_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `tax_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `total_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `ordered_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `confirmed_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `delivered_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remaining_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_orders_sales_order_number_unique` (`sales_order_number`),
  KEY `sales_orders_buyer_id_foreign` (`buyer_id`),
  KEY `sales_orders_customer_id_foreign` (`customer_id`),
  KEY `sales_orders_warehouse_id_foreign` (`warehouse_id`),
  KEY `sales_orders_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_orders`
--

INSERT INTO `sales_orders` (`id`, `sales_order_number`, `buyer_id`, `customer_id`, `order_date`, `required_delivery_date`, `warehouse_id`, `delivery_address`, `contact_information`, `status`, `subtotal`, `order_discount_amount`, `order_tax_amount`, `discount_amount`, `tax_amount`, `total_amount`, `ordered_quantity`, `confirmed_quantity`, `delivered_quantity`, `remaining_quantity`, `confirmed_at`, `remarks`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'SO-2026-HM-001', 2, 2, '2026-08-19', '2026-08-28', 3, 'H&M Distribution Center, Hamburg Port Logistics, Germany', 'shipment@hm.example', 'confirmed', 22200.0000, 0.0000, 0.0000, 0.0000, 0.0000, 22200.0000, 1200.0000, 1200.0000, 1200.0000, 0.0000, NULL, 'Priority ocean shipment booking.', 1, '2026-08-26 11:57:48', '2026-08-26 11:57:48', NULL),
(2, 'SO-20260826-0001', NULL, 2, '2026-08-26', '2026-09-05', 1, 'test', '098765432', 'completed', 12300.0000, 10.0000, 12.0000, 12.0000, 13.0000, 12301.0000, 123.0000, 123.0000, 0.0000, 123.0000, '2026-08-26 12:31:13', 'sgs', 1, '2026-08-26 12:25:11', '2026-08-26 12:33:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sales_order_items`
--

DROP TABLE IF EXISTS `sales_order_items`;
CREATE TABLE IF NOT EXISTS `sales_order_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `sales_order_id` bigint UNSIGNED NOT NULL,
  `line_number` int UNSIGNED NOT NULL DEFAULT '1',
  `product_id` bigint UNSIGNED NOT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `ordered_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `confirmed_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `delivered_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remaining_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `unit_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `discount_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `tax_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `line_total` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_order_items_sales_order_id_foreign` (`sales_order_id`),
  KEY `sales_order_items_product_id_foreign` (`product_id`),
  KEY `sales_order_items_product_variant_id_foreign` (`product_variant_id`),
  KEY `sales_order_items_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_order_items`
--

INSERT INTO `sales_order_items` (`id`, `sales_order_id`, `line_number`, `product_id`, `product_variant_id`, `unit_id`, `ordered_quantity`, `confirmed_quantity`, `delivered_quantity`, `remaining_quantity`, `unit_price`, `discount_amount`, `tax_amount`, `line_total`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 2, 2, 2, 1200.0000, 0.0000, 1200.0000, 0.0000, 18.5000, 0.0000, 0.0000, 22200.0000, NULL, '2026-08-26 11:57:48', '2026-08-26 11:57:48'),
(3, 2, 1, 2, 2, 2, 123.0000, 123.0000, 0.0000, 123.0000, 100.0000, 2.0000, 1.0000, 12300.0000, NULL, '2026-08-26 12:25:38', '2026-08-26 12:31:13');

-- --------------------------------------------------------

--
-- Table structure for table `sales_order_status_histories`
--

DROP TABLE IF EXISTS `sales_order_status_histories`;
CREATE TABLE IF NOT EXISTS `sales_order_status_histories` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `sales_order_id` bigint UNSIGNED NOT NULL,
  `previous_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` bigint UNSIGNED NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_order_status_histories_sales_order_id_foreign` (`sales_order_id`),
  KEY `sales_order_status_histories_changed_by_foreign` (`changed_by`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_order_status_histories`
--

INSERT INTO `sales_order_status_histories` (`id`, `sales_order_id`, `previous_status`, `new_status`, `changed_by`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, 'draft', 1, 'Sales Order draft created.', '2026-08-26 12:25:11', '2026-08-26 12:25:11'),
(2, 2, 'draft', 'submitted', 1, 'Sales Order submitted for availability review.', '2026-08-26 12:30:58', '2026-08-26 12:30:58'),
(3, 2, 'submitted', 'confirmed', 1, 'Sales Order confirmed after finished-goods availability check.', '2026-08-26 12:31:13', '2026-08-26 12:31:13'),
(4, 2, 'confirmed', 'ready_for_delivery', 1, NULL, '2026-08-26 12:32:03', '2026-08-26 12:32:03'),
(5, 2, 'ready_for_delivery', 'delivered', 1, NULL, '2026-08-26 12:32:39', '2026-08-26 12:32:39'),
(6, 2, 'delivered', 'completed', 1, NULL, '2026-08-26 12:33:13', '2026-08-26 12:33:13');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

DROP TABLE IF EXISTS `sizes`;
CREATE TABLE IF NOT EXISTS `sizes` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sizes_code_unique` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sizes`
--

INSERT INTO `sizes` (`id`, `code`, `name`, `sort_order`, `status`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'M', 'Medium', 2, 'active', 'Standard Adult Medium', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 'L', 'Large', 3, 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

DROP TABLE IF EXISTS `stock_adjustments`;
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `adjustment_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `warehouse_location_id` bigint UNSIGNED DEFAULT NULL,
  `adjusted_by` bigint UNSIGNED NOT NULL,
  `adjustment_date` timestamp NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_adjustments_adjustment_number_unique` (`adjustment_number`),
  KEY `stock_adjustments_warehouse_id_foreign` (`warehouse_id`),
  KEY `stock_adjustments_warehouse_location_id_foreign` (`warehouse_location_id`),
  KEY `stock_adjustments_adjusted_by_foreign` (`adjusted_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustment_items`
--

DROP TABLE IF EXISTS `stock_adjustment_items`;
CREATE TABLE IF NOT EXISTS `stock_adjustment_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `stock_adjustment_id` bigint UNSIGNED NOT NULL,
  `inventory_balance_id` bigint UNSIGNED NOT NULL,
  `material_id` bigint UNSIGNED DEFAULT NULL,
  `product_id` bigint UNSIGNED DEFAULT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `line_number` int UNSIGNED NOT NULL DEFAULT '1',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_adjustment_items_stock_adjustment_id_foreign` (`stock_adjustment_id`),
  KEY `stock_adjustment_items_inventory_balance_id_foreign` (`inventory_balance_id`),
  KEY `stock_adjustment_items_material_id_foreign` (`material_id`),
  KEY `stock_adjustment_items_product_id_foreign` (`product_id`),
  KEY `stock_adjustment_items_product_variant_id_foreign` (`product_variant_id`),
  KEY `stock_adjustment_items_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE IF NOT EXISTS `stock_transfers` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_warehouse_id` bigint UNSIGNED NOT NULL,
  `source_location_id` bigint UNSIGNED DEFAULT NULL,
  `destination_warehouse_id` bigint UNSIGNED NOT NULL,
  `destination_location_id` bigint UNSIGNED DEFAULT NULL,
  `transferred_by` bigint UNSIGNED NOT NULL,
  `transfer_date` timestamp NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_transfers_transfer_number_unique` (`transfer_number`),
  KEY `stock_transfers_source_warehouse_id_foreign` (`source_warehouse_id`),
  KEY `stock_transfers_source_location_id_foreign` (`source_location_id`),
  KEY `stock_transfers_destination_warehouse_id_foreign` (`destination_warehouse_id`),
  KEY `stock_transfers_destination_location_id_foreign` (`destination_location_id`),
  KEY `stock_transfers_transferred_by_foreign` (`transferred_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfer_items`
--

DROP TABLE IF EXISTS `stock_transfer_items`;
CREATE TABLE IF NOT EXISTS `stock_transfer_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `stock_transfer_id` bigint UNSIGNED NOT NULL,
  `source_inventory_balance_id` bigint UNSIGNED NOT NULL,
  `destination_inventory_balance_id` bigint UNSIGNED NOT NULL,
  `material_id` bigint UNSIGNED DEFAULT NULL,
  `product_id` bigint UNSIGNED DEFAULT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `line_number` int UNSIGNED NOT NULL DEFAULT '1',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_transfer_items_stock_transfer_id_foreign` (`stock_transfer_id`),
  KEY `stock_transfer_items_source_inventory_balance_id_foreign` (`source_inventory_balance_id`),
  KEY `stock_transfer_items_destination_inventory_balance_id_foreign` (`destination_inventory_balance_id`),
  KEY `stock_transfer_items_material_id_foreign` (`material_id`),
  KEY `stock_transfer_items_product_id_foreign` (`product_id`),
  KEY `stock_transfer_items_product_variant_id_foreign` (`product_variant_id`),
  KEY `stock_transfer_items_unit_id_foreign` (`unit_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_code_unique` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `code`, `name`, `contact_name`, `email`, `phone`, `country`, `address`, `status`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'SUP-001', 'Prime Fabrics & Trims Supplier', 'Alex Wong', 'sales@primesupplier.example', '+880-1700-000000', 'Bangladesh', 'Plot 45, Export Processing Zone, Dhaka', 'active', 'Certified high-grade textile and trim supplier.', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 'SUP-TEX-001', 'Pacific Textile & Knit Mills', 'Nurul Islam', 'orders@pacifictextile.example', '+880-1711-100200', 'Bangladesh', 'Kashimpur Industrial Area, Gazipur, Dhaka', 'active', 'Specialist in 100% Combed Cotton, Pique, and Terry Fabrics.', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(3, 'SUP-YKK-002', 'YKK Fastening Solutions BD', 'Hiroshi Sato', 'sales@ykk-fasteners.example', '+880-2-988-1234', 'Bangladesh', 'Dhaka EPZ, Savar, Dhaka', 'active', 'Certified OEM zippers, sliders, and snap buttons.', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(4, 'SUP-COAT-003', 'Coats Industrial Threads', 'Arthur Pendelton', 'support@coats-threads.example', '+44-20-8210-5000', 'United Kingdom', 'The Pavilions, Bridgwater Road, Bristol', 'active', 'Epic & Gramax high-durability sewing threads.', '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_materials`
--

DROP TABLE IF EXISTS `supplier_materials`;
CREATE TABLE IF NOT EXISTS `supplier_materials` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint UNSIGNED NOT NULL,
  `material_id` bigint UNSIGNED NOT NULL,
  `supplier_sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `lead_time_days` int UNSIGNED NOT NULL DEFAULT '0',
  `minimum_order_quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `is_preferred` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_materials_supplier_id_material_id_unique` (`supplier_id`,`material_id`),
  KEY `supplier_materials_material_id_foreign` (`material_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_materials`
--

INSERT INTO `supplier_materials` (`id`, `supplier_id`, `material_id`, `supplier_sku`, `unit_price`, `currency`, `lead_time_days`, `minimum_order_quantity`, `is_preferred`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'SUP-FAB-001', 4.5000, 'USD', 7, 10.0000, 1, 'active', 'Contracted supplier rate.', '2026-08-25 11:41:55', '2026-08-26 11:58:12');

-- --------------------------------------------------------

--
-- Table structure for table `supply_plans`
--

DROP TABLE IF EXISTS `supply_plans`;
CREATE TABLE IF NOT EXISTS `supply_plans` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` bigint UNSIGNED NOT NULL,
  `product_variant_id` bigint UNSIGNED DEFAULT NULL,
  `period_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `confirmed_order_quantity` decimal(15,4) DEFAULT '0.0000',
  `forecast_quantity` decimal(15,4) DEFAULT '0.0000',
  `required_quantity` decimal(15,4) DEFAULT '0.0000',
  `available_quantity` decimal(15,4) DEFAULT '0.0000',
  `planned_production_quantity` decimal(15,4) DEFAULT '0.0000',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'calculated',
  `created_by` bigint UNSIGNED NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supply_plans_product_id_foreign` (`product_id`),
  KEY `supply_plans_product_variant_id_foreign` (`product_variant_id`),
  KEY `supply_plans_created_by_foreign` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supply_plans`
--

INSERT INTO `supply_plans` (`id`, `product_id`, `product_variant_id`, `period_type`, `period_start`, `period_end`, `confirmed_order_quantity`, `forecast_quantity`, `required_quantity`, `available_quantity`, `planned_production_quantity`, `status`, `created_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 'monthly', '2026-08-01', '2026-08-31', 1200.0000, 1500.0000, 1500.0000, 300.0000, 1200.0000, 'calculated', 1, 'Optimized against available stock capacity.', '2026-08-26 11:56:16', '2026-08-26 11:56:16');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
CREATE TABLE IF NOT EXISTS `units` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decimal_places` smallint UNSIGNED NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_code_unique` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `code`, `name`, `symbol`, `decimal_places`, `status`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'KG', 'Kilogram', 'kg', 4, 'active', 'Standard metric weight unit.', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 'PCS', 'Pieces', 'pcs', 0, 'active', 'Discrete item unit.', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(3, 'YDS', 'Yards', 'yds', 2, 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(4, 'CONE', 'Thread Cones', 'cone', 0, 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` bigint UNSIGNED DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `department_id`, `status`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'System Administrator', 'admin@garmentflow.com', NULL, '$2y$12$GF.HbFun9vlkK8Cr84UWzO1u.KOMcD.1Zp/YqOHQ/UXBYfZFPjVmm', 1, 'active', NULL, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(2, 'GarmentFlow Operator', 'operator@example.com', NULL, '$2y$12$VR7eWrtAr1lHQXBjzaLlQu8kcqRrblVIA3P/Ljlrm4bQ80Ua0TzUO', 1, 'active', NULL, '2026-08-25 11:41:55', '2026-08-25 11:41:55'),
(3, 'Sarah Jenkins (CEO)', 'ceo@garmentflow.com', NULL, '$2y$12$bKeGfy6E1VI3dJEA9mD.eu02sdcuf/wIa6p/YAPmr6mcpQ3jhyn5a', 1, 'active', NULL, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(4, 'Tariq Rahman (SCM Lead)', 'supplychain@garmentflow.com', NULL, '$2y$12$yYgnEzHNVZ3fAmYtrAbYeubEzeSEY7.nUmniFoqBL.B/4pr1DrrOu', 2, 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50'),
(5, 'Carlos Mendez (Production Head)', 'production@garmentflow.com', NULL, '$2y$12$.RjaALRbGZMNCN7myTvaQ.vGp/7wNnxDPidKfDEo06wfwbV9bKYFG', 3, 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50'),
(6, 'Li Wei (Procurement Lead)', 'procurement@garmentflow.com', NULL, '$2y$12$cVSAbRGscBXPlWDXkd/X1OBAEcWUgZw3y06ARaNSz9p3nmP2WdeMa', 4, 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50'),
(7, 'Kareem Mostafa (Warehouse Lead)', 'warehouse@garmentflow.com', NULL, '$2y$12$jeB9/N9QKwqcgYsexoq1fu9/YI.9j9EX2dU3rnhHUHK8.tIAI.z7O', 5, 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_roles_user_id_role_id_unique` (`user_id`,`role_id`),
  KEY `user_roles_role_id_foreign` (`role_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-08-25 11:41:54', '2026-08-25 11:41:54'),
(2, 2, 1, '2026-08-25 11:41:55', '2026-08-25 11:41:55'),
(3, 3, 3, '2026-08-26 11:52:49', '2026-08-26 11:52:49'),
(4, 4, 4, '2026-08-26 11:52:50', '2026-08-26 11:52:50'),
(5, 5, 5, '2026-08-26 11:52:50', '2026-08-26 11:52:50'),
(6, 6, 6, '2026-08-26 11:52:50', '2026-08-26 11:52:50'),
(7, 7, 7, '2026-08-26 11:52:50', '2026-08-26 11:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE IF NOT EXISTS `warehouses` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouses_code_unique` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`id`, `code`, `name`, `contact_name`, `phone`, `country`, `address`, `status`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'DHK-01', 'Dhaka Central Warehouse', 'Warehouse Manager', '+880-1800-000000', 'Bangladesh', 'Tejgaon Industrial Area, Dhaka', 'active', 'Central raw material and finished goods facility.', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 'WH-RAW-01', 'Central Fabric & Trims Warehouse', NULL, NULL, NULL, 'Block A, Industrial Park, Gazipur', 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(3, 'WH-FG-01', 'Finished Goods Export Warehouse', NULL, NULL, NULL, 'Export Zone, Chittagong Port Access Road', 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_locations`
--

DROP TABLE IF EXISTS `warehouse_locations`;
CREATE TABLE IF NOT EXISTS `warehouse_locations` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `warehouse_id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'storage',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouse_locations_warehouse_id_code_unique` (`warehouse_id`,`code`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouse_locations`
--

INSERT INTO `warehouse_locations` (`id`, `warehouse_id`, `code`, `name`, `location_type`, `status`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'A-01-01', 'Rack A-01 Bay 01', 'storage', 'active', 'Fabric storage bay', '2026-08-25 11:41:55', '2026-08-25 11:41:55', NULL),
(2, 2, 'LOC-RAW-A1', 'Fabric Staging Bay A1', 'storage', 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(3, 2, 'LOC-TRM-B1', 'Accessories Vault B1', 'storage', 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL),
(4, 3, 'LOC-FG-BAY1', 'Export Shipment Bay 1', 'storage', 'active', NULL, '2026-08-26 11:52:50', '2026-08-26 11:52:50', NULL);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
