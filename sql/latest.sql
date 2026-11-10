-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: magnate
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `entity` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int unsigned DEFAULT NULL,
  `order_id` int unsigned DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta` json DEFAULT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_order` (`order_id`,`created_at`),
  KEY `idx_al_entity` (`entity`,`entity_id`),
  KEY `idx_al_user` (`user_id`,`created_at`),
  KEY `idx_al_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (1,1,'auth',1,NULL,'login','Signed in',NULL,'102.206.113.222','2026-10-28 13:08:09'),(2,1,'exchange_rate',3,NULL,'set','Set 1 EUR = 145 KES from 2026-10-28',NULL,'102.206.113.222','2026-10-28 13:08:43'),(3,1,'exchange_rate',4,NULL,'set','Set 1 GBP = 175 KES from 2026-10-28',NULL,'102.206.113.222','2026-10-28 13:08:43'),(4,1,'exchange_rate',2,NULL,'set','Set 1 USD = 130 KES from 2026-10-28',NULL,'102.206.113.222','2026-10-28 13:08:43'),(5,1,'auth',1,NULL,'login','Signed in',NULL,'102.206.113.222','2026-10-28 13:16:00'),(6,1,'auth',1,NULL,'login','Signed in',NULL,'105.161.36.90','2026-10-28 15:01:17'),(7,1,'auth',1,NULL,'login','Signed in',NULL,'217.199.144.90','2026-10-29 07:49:54'),(8,1,'auth',1,NULL,'login','Signed in',NULL,'102.206.113.138','2026-10-29 08:49:57'),(9,1,'auth',1,NULL,'login','Signed in',NULL,'217.199.144.90','2026-10-29 08:58:28'),(10,1,'auth',1,NULL,'login','Signed in',NULL,'102.206.113.138','2026-10-29 09:16:53'),(11,1,'user',2,NULL,'create','Created user alpha',NULL,'102.206.113.138','2026-10-29 09:17:49'),(12,1,'auth',1,NULL,'logout','Signed out',NULL,'102.206.113.138','2026-10-29 09:17:59'),(13,2,'auth',2,NULL,'login','Signed in',NULL,'102.206.113.138','2026-10-29 09:18:12'),(14,2,'auth',2,NULL,'login','Signed in',NULL,'102.206.113.138','2026-10-29 09:18:45'),(15,2,'order_by',1,NULL,'delete','Deleted order by (marks) \"Main\"','{\"id\": 1, \"name\": \"Main\", \"is_active\": 1}','102.206.113.138','2026-10-29 09:20:40'),(16,2,'order_by',2,NULL,'create','Created order by (marks) \"Stems\"',NULL,'196.96.81.35','2026-10-29 09:43:12'),(17,2,'order_by',2,NULL,'update','Updated order by (marks) \"Stems test\"',NULL,'196.96.81.35','2026-10-29 09:43:41'),(18,1,'auth',1,NULL,'logout','Signed out',NULL,'217.199.144.90','2026-10-29 09:44:20'),(19,1,'auth',1,NULL,'login','Signed in',NULL,'217.199.144.90','2026-10-29 09:47:02'),(20,1,'auth',1,NULL,'logout','Signed out',NULL,'217.199.144.90','2026-10-29 10:02:01'),(21,1,'auth',1,NULL,'login','Signed in',NULL,'217.199.144.90','2026-10-29 10:02:21'),(22,1,'order_by',2,NULL,'update','Updated order by (marks) \"Stems\"',NULL,'217.199.144.90','2026-10-29 10:02:48'),(23,1,'order_by',2,NULL,'update','Updated order by (marks) \"Stems\"',NULL,'217.199.144.90','2026-10-29 10:02:59'),(24,1,'order_by',3,NULL,'create','Created order by (marks) \"Boxes\"',NULL,'217.199.144.90','2026-10-29 10:04:09'),(25,1,'product_categories',1,NULL,'create','Created categorie \"T-Hybrid\"',NULL,'217.199.144.90','2026-10-29 10:04:49'),(26,1,'product_categories',2,NULL,'create','Created categorie \"Sprays\"',NULL,'217.199.144.90','2026-10-29 10:05:19'),(27,1,'customers',1,NULL,'create','Created customer \"DOUGLAS MUNGAI\"',NULL,'217.199.144.90','2026-10-29 10:19:20'),(28,1,'shipping_agents',1,NULL,'create','Created shipping agent \"KN\"',NULL,'217.199.144.90','2026-10-29 10:22:46'),(29,1,'order',1,1,'create','Order OD00001 captured',NULL,'217.199.144.90','2026-10-29 10:23:49'),(30,1,'varieties',1,NULL,'create','Created variety \"Abeba\"',NULL,'217.199.144.90','2026-10-29 10:25:43'),(31,1,'farms',1,NULL,'create','Created farm \"Red Lands Roses\"',NULL,'217.199.144.90','2026-10-29 10:30:18'),(32,1,'order_line',1,1,'save','Line Abeba 50: 300 stems across 1 farm(s)',NULL,'217.199.144.90','2026-10-29 10:31:39'),(33,1,'order',1,1,'stage:sourcing','Farm orders generated and sent to farms',NULL,'217.199.144.90','2026-10-29 10:34:50'),(34,1,'order',1,1,'confirm:save','Farm confirmations updated (1 line(s))',NULL,'217.199.144.90','2026-10-29 10:35:55'),(35,1,'order',1,1,'stage:confirmed','Farm confirmations completed: 300 of 300 stems confirmed',NULL,'217.199.144.90','2026-10-29 10:35:58'),(36,1,'order',1,1,'receive:save','Delivery quantities recorded',NULL,'217.199.144.90','2026-10-29 10:36:34'),(37,1,'order',1,1,'stage:received','Delivery check completed: 300 stems received vs 300 confirmed (+0), 0 rejected',NULL,'217.199.144.90','2026-10-29 10:36:36'),(38,1,'order',1,1,'stage:packing','Packing list PL260001 created',NULL,'217.199.144.90','2026-10-29 10:36:44'),(39,1,'drop_points',1,NULL,'create','Created drop point \"Holland\"',NULL,'217.199.144.90','2026-10-29 10:39:08'),(40,1,'order',1,1,'stage:invoiced','Invoice INV260001 issued: EUR 60.00',NULL,'217.199.144.90','2026-10-29 10:42:30'),(41,1,'auth',1,NULL,'login','Signed in',NULL,'217.199.144.90','2026-10-29 11:13:04'),(42,2,'auth',2,NULL,'login','Signed in',NULL,'102.206.113.106','2026-10-29 11:13:11'),(43,1,'farm_order',1,1,'update','Red Lands Roses: farm invoice no set to \"123\"',NULL,'217.199.144.90','2026-10-29 11:36:09'),(44,1,'farm_order',1,1,'update','Red Lands Roses: farm invoice amount set to \"51\"',NULL,'217.199.144.90','2026-10-29 11:36:39'),(45,1,'farm_order',1,1,'update','Red Lands Roses: phyto kg set to \"15\"',NULL,'217.199.144.90','2026-10-29 11:36:42'),(46,1,'farm_order',1,1,'update','Red Lands Roses: agent awb set to \"kn\"',NULL,'217.199.144.90','2026-10-29 11:36:48'),(47,1,'farm_order',1,1,'update','Red Lands Roses: comments set to \"test\"',NULL,'217.199.144.90','2026-10-29 11:36:55'),(48,1,'customer_payments',1,NULL,'create','Created payment \"Invoice payment\"',NULL,'217.199.144.90','2026-10-29 11:38:20'),(49,1,'farm_payments',1,NULL,'create','Created payment \"Invoice payment\"',NULL,'217.199.144.90','2026-10-29 11:39:31'),(50,1,'agent_payments',1,NULL,'create','Created payment \"Invoice payment\"',NULL,'217.199.144.90','2026-10-29 11:40:02'),(51,1,'other_sales',2,NULL,'create','Created other sale \"INV260002\"',NULL,'217.199.144.90','2026-10-29 11:41:02'),(52,1,'other_costs',1,NULL,'create','Created other cost \"Data Logger\"',NULL,'217.199.144.90','2026-10-29 11:42:05'),(53,1,'expenses',1,1,'create','Created expense \"Cheque Cost\"',NULL,'217.199.144.90','2026-10-29 11:43:32'),(54,1,'farm_price',NULL,NULL,'save','Farm price Red Lands Roses / Abeba (1 length(s)): buy 0.18, sell 0.2',NULL,'217.199.144.90','2026-10-29 11:48:13'),(55,1,'selling_price',1,NULL,'set','Selling price Abeba @ 50 set to EUR 0.2',NULL,'217.199.144.90','2026-10-29 11:48:53'),(56,1,'user',3,NULL,'create','Created user test',NULL,'217.199.144.90','2026-10-29 11:50:20'),(57,1,'user',3,NULL,'update','Updated user gladys',NULL,'217.199.144.90','2026-10-29 12:06:43'),(58,1,'auth',1,NULL,'logout','Signed out',NULL,'217.199.144.90','2026-10-29 12:06:52'),(59,3,'auth',3,NULL,'login','Signed in',NULL,'217.199.144.90','2026-10-29 12:07:06'),(60,3,'user',3,NULL,'password','Changed own password',NULL,'217.199.144.90','2026-10-29 12:08:17'),(61,3,'user',3,NULL,'update','Updated user magnate',NULL,'217.199.144.90','2026-10-29 12:10:08'),(62,3,'user',3,NULL,'update','Updated user magnate',NULL,'217.199.144.90','2026-10-29 12:10:24'),(63,3,'auth',3,NULL,'logout','Signed out',NULL,'217.199.144.90','2026-10-29 12:10:35'),(64,3,'auth',3,NULL,'login','Signed in',NULL,'217.199.144.90','2026-10-29 12:10:54'),(65,1,'auth',1,NULL,'login','Signed in',NULL,'102.202.189.66','2026-10-30 08:54:16'),(66,1,'auth',1,NULL,'login','Signed in',NULL,'102.202.189.66','2026-11-02 07:55:45'),(67,1,'exchange_rate',3,NULL,'set','Set 1 EUR = 145 KES from 2026-11-02',NULL,'102.202.189.66','2026-11-02 07:56:02'),(68,1,'exchange_rate',4,NULL,'set','Set 1 GBP = 175 KES from 2026-11-02',NULL,'102.202.189.66','2026-11-02 07:56:02'),(69,1,'exchange_rate',2,NULL,'set','Set 1 USD = 130 KES from 2026-11-02',NULL,'102.202.189.66','2026-11-02 07:56:02'),(70,1,'auth',1,NULL,'login','Signed in',NULL,'102.206.112.53','2026-11-03 09:13:05'),(71,1,'auth',1,NULL,'login','Signed in',NULL,'102.202.189.66','2026-11-10 07:25:49'),(72,1,'auth',1,NULL,'login','Signed in',NULL,'102.206.113.87','2026-11-10 08:50:33');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agent_payments`
--

DROP TABLE IF EXISTS `agent_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `payment_date` date NOT NULL,
  `shipping_agent_id` int unsigned NOT NULL,
  `details` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `exchange_rate` decimal(16,6) NOT NULL,
  `base_amount` decimal(14,2) NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ap_agent` (`shipping_agent_id`,`payment_date`),
  KEY `fk_apay_cur` (`currency_id`),
  CONSTRAINT `fk_apay_agent` FOREIGN KEY (`shipping_agent_id`) REFERENCES `shipping_agents` (`id`),
  CONSTRAINT `fk_apay_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agent_payments`
--

LOCK TABLES `agent_payments` WRITE;
/*!40000 ALTER TABLE `agent_payments` DISABLE KEYS */;
INSERT INTO `agent_payments` VALUES (1,'2026-10-29',1,'Invoice payment','SWIFT','123',200.00,2,130.000000,26000.00,NULL,'2026-10-29 11:40:02');
/*!40000 ALTER TABLE `agent_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_accounts`
--

DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `swift_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_id` int unsigned DEFAULT NULL,
  `show_on_invoice` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_accounts`
--

LOCK TABLES `bank_accounts` WRITE;
/*!40000 ALTER TABLE `bank_accounts` DISABLE KEYS */;
INSERT INTO `bank_accounts` VALUES (1,'Equity Bank','Westlands','Magnate Flowers Limited','1234567890','EQBLKENA',NULL,1,1,1),(2,'KCB Bank','Upper Hill','Magnate Flowers Limited','2345678901','KCBLKENX',NULL,1,1,2),(3,'Co-operative Bank','Karen','Magnate Flowers Limited','3456789012','COOPKENA',NULL,1,1,3);
/*!40000 ALTER TABLE `bank_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `box_types`
--

DROP TABLE IF EXISTS `box_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `box_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `length_cm` decimal(6,1) DEFAULT NULL,
  `width_cm` decimal(6,1) DEFAULT NULL,
  `height_cm` decimal(6,1) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `box_types`
--

LOCK TABLES `box_types` WRITE;
/*!40000 ALTER TABLE `box_types` DISABLE KEYS */;
INSERT INTO `box_types` VALUES (1,'Standard',100.0,50.0,30.0,1),(2,'Half Box',100.0,40.0,15.0,1),(3,'Quarter Box',100.0,25.0,15.0,1);
/*!40000 ALTER TABLE `box_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso2` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries`
--

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
INSERT INTO `countries` VALUES (1,'Kenya','KE',1),(2,'Netherlands','NL',1),(3,'United Kingdom','GB',1),(4,'Germany','DE',1),(5,'United Arab Emirates','AE',1),(6,'Kuwait','KW',1),(7,'Iraq','IQ',1),(8,'Saudi Arabia','SA',1),(9,'Qatar','QA',1),(10,'Russia','RU',1),(11,'Japan','JP',1),(12,'Australia','AU',1),(13,'Uganda','UG',1),(14,'Tanzania','TZ',1),(15,'Rwanda','RW',1);
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `currencies`
--

DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_base` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currencies`
--

LOCK TABLES `currencies` WRITE;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` VALUES (1,'KES','Kenya Shilling','KSh',1,1),(2,'USD','US Dollar','$',0,1),(3,'EUR','Euro','€',0,1),(4,'GBP','Pound Sterling','£',0,1);
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_other_costs`
--

DROP TABLE IF EXISTS `customer_other_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_other_costs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cost_date` date NOT NULL,
  `invoice_id` int unsigned NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `details` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `exchange_rate` decimal(16,6) NOT NULL,
  `selling_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `buying_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `selling_base` decimal(14,2) NOT NULL DEFAULT '0.00',
  `buying_base` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_coc_inv` (`invoice_id`),
  KEY `idx_coc_cust` (`customer_id`,`cost_date`),
  KEY `fk_coc_cur` (`currency_id`),
  CONSTRAINT `fk_coc_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_coc_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_coc_inv` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_other_costs`
--

LOCK TABLES `customer_other_costs` WRITE;
/*!40000 ALTER TABLE `customer_other_costs` DISABLE KEYS */;
INSERT INTO `customer_other_costs` VALUES (1,'2026-10-29',2,1,'Data Logger',3,145.000000,0.10,0.05,14.50,7.25,1,'2026-10-29 11:42:05');
/*!40000 ALTER TABLE `customer_other_costs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_payments`
--

DROP TABLE IF EXISTS `customer_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `payment_date` date NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `details` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `exchange_rate` decimal(16,6) NOT NULL,
  `base_amount` decimal(14,2) NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cp_cust` (`customer_id`,`payment_date`),
  KEY `fk_cp_cur` (`currency_id`),
  CONSTRAINT `fk_cp_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_cp_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_payments`
--

LOCK TABLES `customer_payments` WRITE;
/*!40000 ALTER TABLE `customer_payments` DISABLE KEYS */;
INSERT INTO `customer_payments` VALUES (1,'2026-10-29',1,'Invoice payment',NULL,NULL,2000.00,3,145.000000,290000.00,NULL,'2026-10-29 11:38:20');
/*!40000 ALTER TABLE `customer_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_id` int unsigned DEFAULT NULL,
  `drop_point_id` int unsigned DEFAULT NULL,
  `truck` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_id` int unsigned NOT NULL,
  `terms` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_days` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_cust_country` (`country_id`),
  KEY `fk_cust_drop` (`drop_point_id`),
  KEY `fk_cust_cur` (`currency_id`),
  CONSTRAINT `fk_cust_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cust_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_cust_drop` FOREIGN KEY (`drop_point_id`) REFERENCES `drop_points` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'ED01','DOUGLAS MUNGAI','DOUGLAS MUNGAI','dikeidaggy@gmail.com','+254184258753','Test',3,NULL,NULL,3,'FOB',0,1);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drop_points`
--

DROP TABLE IF EXISTS `drop_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `drop_points` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drop_points`
--

LOCK TABLES `drop_points` WRITE;
/*!40000 ALTER TABLE `drop_points` DISABLE KEYS */;
INSERT INTO `drop_points` VALUES (1,'Holland','10, Ruiru',1);
/*!40000 ALTER TABLE `drop_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exchange_rates`
--

DROP TABLE IF EXISTS `exchange_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_rates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `currency_id` int unsigned NOT NULL,
  `rate` decimal(16,6) NOT NULL,
  `effective_date` date NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_day` (`currency_id`,`effective_date`),
  CONSTRAINT `fk_er_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exchange_rates`
--

LOCK TABLES `exchange_rates` WRITE;
/*!40000 ALTER TABLE `exchange_rates` DISABLE KEYS */;
INSERT INTO `exchange_rates` VALUES (1,1,1.000000,'2000-01-01','Base currency',NULL,'2026-10-28 11:07:02'),(2,3,145.000000,'2026-10-28','Set from monthly prompt',1,'2026-10-28 13:08:43'),(3,4,175.000000,'2026-10-28','Set from monthly prompt',1,'2026-10-28 13:08:43'),(4,2,130.000000,'2026-10-28','Set from monthly prompt',1,'2026-10-28 13:08:43'),(5,3,145.000000,'2026-11-02','Set from monthly prompt',1,'2026-11-02 07:56:02'),(6,4,175.000000,'2026-11-02','Set from monthly prompt',1,'2026-11-02 07:56:02'),(7,2,130.000000,'2026-11-02','Set from monthly prompt',1,'2026-11-02 07:56:02');
/*!40000 ALTER TABLE `exchange_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_types`
--

DROP TABLE IF EXISTS `expense_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_types`
--

LOCK TABLES `expense_types` WRITE;
/*!40000 ALTER TABLE `expense_types` DISABLE KEYS */;
INSERT INTO `expense_types` VALUES (1,'Freight & Handling',1),(2,'Clearing & Documentation',1),(3,'Phytosanitary',1),(4,'Packaging Materials',1),(5,'Transport / Trucking',1),(6,'Fuel & Parking',1),(7,'Salaries & Wages',1),(8,'Rent & Utilities',1),(9,'Bank Charges',1),(10,'Communication',1),(11,'Office Supplies',1),(12,'Other',1);
/*!40000 ALTER TABLE `expense_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `expense_date` date NOT NULL,
  `expense_type_id` int unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payee` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_id` int unsigned DEFAULT NULL,
  `freight_booking_id` int unsigned DEFAULT NULL,
  `shipping_agent_id` int unsigned DEFAULT NULL,
  `method` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `exchange_rate` decimal(16,6) NOT NULL,
  `base_amount` decimal(14,2) NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exp_date` (`expense_date`),
  KEY `idx_exp_order` (`order_id`),
  KEY `fk_exp_type` (`expense_type_id`),
  KEY `fk_exp_fb` (`freight_booking_id`),
  KEY `fk_exp_agent` (`shipping_agent_id`),
  KEY `fk_exp_cur` (`currency_id`),
  CONSTRAINT `fk_exp_agent` FOREIGN KEY (`shipping_agent_id`) REFERENCES `shipping_agents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exp_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_exp_fb` FOREIGN KEY (`freight_booking_id`) REFERENCES `freight_bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exp_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_exp_type` FOREIGN KEY (`expense_type_id`) REFERENCES `expense_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,'2026-10-29',9,'Cheque Cost','Equity Bank',1,NULL,1,'Bank Transfer','123',200.00,1,1.000000,200.00,1,'2026-10-29 11:43:32');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `farm_orders`
--

DROP TABLE IF EXISTS `farm_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `farm_id` int unsigned NOT NULL,
  `farm_order_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','sent','confirmed','received','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `currency_id` int unsigned NOT NULL,
  `exchange_rate` decimal(16,6) NOT NULL DEFAULT '1.000000',
  `cost_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `farm_invoice_no` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `farm_invoice_amount` decimal(14,2) DEFAULT NULL,
  `phyto_kg` decimal(10,2) DEFAULT NULL,
  `agent_awb` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `sent_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `farm_order_no` (`farm_order_no`),
  UNIQUE KEY `uq_fo` (`order_id`,`farm_id`),
  KEY `idx_fo_farm` (`farm_id`),
  KEY `fk_fo_cur` (`currency_id`),
  CONSTRAINT `fk_fo_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_fo_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_fo_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `farm_orders`
--

LOCK TABLES `farm_orders` WRITE;
/*!40000 ALTER TABLE `farm_orders` DISABLE KEYS */;
INSERT INTO `farm_orders` VALUES (1,1,1,'OD00001-FM01','received',2,130.000000,51.00,'123',51.00,15.00,'kn','test','2026-10-29 10:34:50','2026-10-29 10:35:58','2026-10-29 10:36:36');
/*!40000 ALTER TABLE `farm_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `farm_payments`
--

DROP TABLE IF EXISTS `farm_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `payment_date` date NOT NULL,
  `farm_id` int unsigned NOT NULL,
  `details` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `exchange_rate` decimal(16,6) NOT NULL,
  `base_amount` decimal(14,2) NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fp_farm_date` (`farm_id`,`payment_date`),
  KEY `fk_fpay_cur` (`currency_id`),
  CONSTRAINT `fk_fpay_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_fpay_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `farm_payments`
--

LOCK TABLES `farm_payments` WRITE;
/*!40000 ALTER TABLE `farm_payments` DISABLE KEYS */;
INSERT INTO `farm_payments` VALUES (1,'2026-10-29',1,'Invoice payment','Bank Transfer','123',500.00,2,130.000000,65000.00,NULL,'2026-10-29 11:39:31');
/*!40000 ALTER TABLE `farm_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `farm_prices`
--

DROP TABLE IF EXISTS `farm_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_prices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` int unsigned NOT NULL,
  `variety_id` int unsigned NOT NULL,
  `length_id` int unsigned NOT NULL,
  `buying_price` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `selling_price` decimal(12,4) DEFAULT NULL,
  `currency_id` int unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fp` (`farm_id`,`variety_id`,`length_id`),
  KEY `idx_fp_vl` (`variety_id`,`length_id`),
  KEY `fk_fp_len` (`length_id`),
  KEY `fk_fp_cur` (`currency_id`),
  CONSTRAINT `fk_fp_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_fp_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fp_len` FOREIGN KEY (`length_id`) REFERENCES `lengths` (`id`),
  CONSTRAINT `fk_fp_var` FOREIGN KEY (`variety_id`) REFERENCES `varieties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `farm_prices`
--

LOCK TABLES `farm_prices` WRITE;
/*!40000 ALTER TABLE `farm_prices` DISABLE KEYS */;
INSERT INTO `farm_prices` VALUES (1,1,1,5,0.1800,0.2000,2,1,1,'2026-10-29 11:48:13');
/*!40000 ALTER TABLE `farm_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `farms`
--

DROP TABLE IF EXISTS `farms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `farms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_id` int unsigned NOT NULL,
  `payment_terms_days` int NOT NULL DEFAULT '30',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_farm_cur` (`currency_id`),
  CONSTRAINT `fk_farm_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `farms`
--

LOCK TABLES `farms` WRITE;
/*!40000 ALTER TABLE `farms` DISABLE KEYS */;
INSERT INTO `farms` VALUES (1,'FM01','Red Lands Roses','10, Ruiru','DOUGLAS MUNGAI','dikeidaggy@gmail.com','+254184258753',2,30,NULL,1);
/*!40000 ALTER TABLE `farms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `freight_allocations`
--

DROP TABLE IF EXISTS `freight_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `freight_allocations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int unsigned NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `goods_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hawb_no` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boxes` int unsigned NOT NULL DEFAULT '0',
  `gross_kg` decimal(10,2) NOT NULL DEFAULT '0.00',
  `volume_kg` decimal(10,2) NOT NULL DEFAULT '0.00',
  `chargeable_kg` decimal(10,2) NOT NULL DEFAULT '0.00',
  `rate_per_kg` decimal(12,4) NOT NULL,
  `extra_charges` decimal(14,2) NOT NULL DEFAULT '0.00',
  `currency_id` int unsigned NOT NULL,
  `exchange_rate` decimal(16,6) NOT NULL DEFAULT '1.000000',
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `base_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `invoice_id` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fa_booking` (`booking_id`),
  KEY `fk_fa_cust` (`customer_id`),
  KEY `fk_fa_cur` (`currency_id`),
  KEY `fk_fa_inv` (`invoice_id`),
  CONSTRAINT `fk_fa_booking` FOREIGN KEY (`booking_id`) REFERENCES `freight_bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fa_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_fa_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_fa_inv` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `freight_allocations`
--

LOCK TABLES `freight_allocations` WRITE;
/*!40000 ALTER TABLE `freight_allocations` DISABLE KEYS */;
/*!40000 ALTER TABLE `freight_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `freight_bookings`
--

DROP TABLE IF EXISTS `freight_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `freight_bookings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `booking_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_agent_id` int unsigned NOT NULL,
  `airline` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flight_no` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `awb_no` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NBO',
  `destination` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `departure_date` date NOT NULL,
  `capacity_kg` decimal(10,2) NOT NULL,
  `cost_per_kg` decimal(12,4) NOT NULL,
  `other_costs` decimal(14,2) NOT NULL DEFAULT '0.00',
  `currency_id` int unsigned NOT NULL,
  `exchange_rate` decimal(16,6) NOT NULL DEFAULT '1.000000',
  `total_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `base_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('open','closed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_no` (`booking_no`),
  KEY `fk_fb_agent` (`shipping_agent_id`),
  KEY `fk_fb_cur` (`currency_id`),
  CONSTRAINT `fk_fb_agent` FOREIGN KEY (`shipping_agent_id`) REFERENCES `shipping_agents` (`id`),
  CONSTRAINT `fk_fb_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `freight_bookings`
--

LOCK TABLES `freight_bookings` WRITE;
/*!40000 ALTER TABLE `freight_bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `freight_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_lines`
--

DROP TABLE IF EXISTS `invoice_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_lines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `variety_id` int unsigned DEFAULT NULL,
  `length_label` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_by_label` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'stems',
  `pack_rate` decimal(10,2) DEFAULT NULL,
  `boxes` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_il_inv` (`invoice_id`),
  CONSTRAINT `fk_il_inv` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_lines`
--

LOCK TABLES `invoice_lines` WRITE;
/*!40000 ALTER TABLE `invoice_lines` DISABLE KEYS */;
INSERT INTO `invoice_lines` VALUES (1,1,'Abeba',1,'50','Stems',300.00,'stems',300.00,1.00,0.2000,60.00,0),(2,2,'Consolidation',NULL,NULL,NULL,10.00,'boxes',NULL,10.00,0.0500,0.50,0);
/*!40000 ALTER TABLE `invoice_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` enum('order','other_sale','freight') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'order',
  `order_id` int unsigned DEFAULT NULL,
  `packing_list_id` int unsigned DEFAULT NULL,
  `freight_booking_id` int unsigned DEFAULT NULL,
  `customer_id` int unsigned NOT NULL,
  `bill_to_customer_id` int unsigned NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `currency_id` int unsigned NOT NULL,
  `exchange_rate` decimal(16,6) NOT NULL DEFAULT '1.000000',
  `total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `base_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `shipment_details` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s_number` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boxes` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('open','void') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `idx_inv_billto` (`bill_to_customer_id`,`invoice_date`),
  KEY `idx_inv_order` (`order_id`),
  KEY `fk_inv_pl` (`packing_list_id`),
  KEY `fk_inv_fb` (`freight_booking_id`),
  KEY `fk_inv_cust` (`customer_id`),
  KEY `fk_inv_cur` (`currency_id`),
  CONSTRAINT `fk_inv_billto` FOREIGN KEY (`bill_to_customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_inv_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_inv_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_inv_fb` FOREIGN KEY (`freight_booking_id`) REFERENCES `freight_bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_pl` FOREIGN KEY (`packing_list_id`) REFERENCES `packing_lists` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,'INV260001','order',1,1,NULL,1,1,'2026-10-29','2026-10-29',3,145.000000,60.00,8700.00,'KN',NULL,1.00,NULL,'open',1,'2026-10-29 10:42:30'),(2,'INV260002','other_sale',NULL,NULL,NULL,1,1,'2026-10-29','2026-10-29',3,145.000000,0.50,72.50,'Consolidation','123',10.00,'Test','open',1,'2026-10-29 11:41:02');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lengths`
--

DROP TABLE IF EXISTS `lengths`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lengths` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lengths`
--

LOCK TABLES `lengths` WRITE;
/*!40000 ALTER TABLE `lengths` DISABLE KEYS */;
INSERT INTO `lengths` VALUES (1,'30',30,1),(2,'35',35,1),(3,'40',40,1),(4,'45',45,1),(5,'50',50,1),(6,'55',55,1),(7,'60',60,1),(8,'70',70,1),(9,'80',80,1);
/*!40000 ALTER TABLE `lengths` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `line_allocations`
--

DROP TABLE IF EXISTS `line_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `line_allocations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_line_id` int unsigned NOT NULL,
  `farm_id` int unsigned NOT NULL,
  `allocated_stems` int unsigned NOT NULL,
  `buying_price` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `currency_id` int unsigned NOT NULL,
  `confirmed_stems` int unsigned DEFAULT NULL,
  `pack_rate` decimal(10,2) DEFAULT NULL,
  `confirmed_boxes` decimal(10,2) DEFAULT NULL,
  `received_stems` int unsigned DEFAULT NULL,
  `received_boxes` decimal(10,2) DEFAULT NULL,
  `rejected_stems` int unsigned NOT NULL DEFAULT '0',
  `variance_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_la_line` (`order_line_id`),
  KEY `idx_la_farm` (`farm_id`),
  KEY `fk_la_cur` (`currency_id`),
  CONSTRAINT `fk_la_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_la_farm` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`),
  CONSTRAINT `fk_la_line` FOREIGN KEY (`order_line_id`) REFERENCES `order_lines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `line_allocations`
--

LOCK TABLES `line_allocations` WRITE;
/*!40000 ALTER TABLE `line_allocations` DISABLE KEYS */;
INSERT INTO `line_allocations` VALUES (1,1,1,300,0.1700,2,300,300.00,1.00,300,1.00,0,NULL,'2026-10-29 10:35:55','2026-10-29 10:36:34');
/*!40000 ALTER TABLE `line_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_la_user_time` (`username`,`attempted_at`),
  KEY `idx_la_ip_time` (`ip`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,'bisoft','102.206.113.222',1,'2026-10-28 13:08:09'),(2,'bisoft','102.206.113.222',1,'2026-10-28 13:16:00'),(3,'alpha','196.97.1.117',0,'2026-10-28 15:00:41'),(4,'bisoft','105.161.36.90',1,'2026-10-28 15:01:17'),(5,'bisoft','217.199.144.90',1,'2026-10-29 07:49:54'),(6,'bisoft','102.206.113.138',1,'2026-10-29 08:49:57'),(7,'bisoft','217.199.144.90',1,'2026-10-29 08:58:28'),(8,'bisoft','102.206.113.138',1,'2026-10-29 09:16:53'),(9,'alpha','102.206.113.138',1,'2026-10-29 09:18:12'),(10,'alpha','102.206.113.138',1,'2026-10-29 09:18:45'),(11,'bisoft','217.199.144.90',1,'2026-10-29 09:47:01'),(12,'bisoft','217.199.144.90',1,'2026-10-29 10:02:21'),(13,'bisoft','217.199.144.90',1,'2026-10-29 11:13:04'),(14,'alpha','102.206.113.106',1,'2026-10-29 11:13:11'),(15,'Gladys','217.199.144.90',1,'2026-10-29 12:07:06'),(16,'magnate','217.199.144.90',1,'2026-10-29 12:10:54'),(17,'bisoft','102.202.189.66',1,'2026-10-30 08:54:16'),(18,'bisift','102.202.189.66',0,'2026-11-02 07:55:24'),(19,'bisoft','102.202.189.66',1,'2026-11-02 07:55:45'),(20,'bisoft','102.206.112.53',1,'2026-11-03 09:13:05'),(21,'bisoft','102.202.189.66',1,'2026-11-10 07:25:49'),(22,'bisoft','102.206.113.87',1,'2026-11-10 08:50:33');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_by`
--

DROP TABLE IF EXISTS `order_by`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_by` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_by`
--

LOCK TABLES `order_by` WRITE;
/*!40000 ALTER TABLE `order_by` DISABLE KEYS */;
INSERT INTO `order_by` VALUES (2,'Stems',1),(3,'Boxes',1);
/*!40000 ALTER TABLE `order_by` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_lines`
--

DROP TABLE IF EXISTS `order_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_lines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `variety_id` int unsigned NOT NULL,
  `length_id` int unsigned NOT NULL,
  `order_by_id` int unsigned DEFAULT NULL,
  `stems` int unsigned NOT NULL,
  `selling_price` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ol_order` (`order_id`),
  KEY `fk_ol_var` (`variety_id`),
  KEY `fk_ol_len` (`length_id`),
  KEY `fk_ol_by` (`order_by_id`),
  CONSTRAINT `fk_ol_by` FOREIGN KEY (`order_by_id`) REFERENCES `order_by` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ol_len` FOREIGN KEY (`length_id`) REFERENCES `lengths` (`id`),
  CONSTRAINT `fk_ol_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ol_var` FOREIGN KEY (`variety_id`) REFERENCES `varieties` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_lines`
--

LOCK TABLES `order_lines` WRITE;
/*!40000 ALTER TABLE `order_lines` DISABLE KEYS */;
INSERT INTO `order_lines` VALUES (1,1,1,5,2,300,0.2000,'Test',1,'2026-10-29 10:31:39');
/*!40000 ALTER TABLE `order_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `order_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `box_type_id` int unsigned DEFAULT NULL,
  `shipping_agent_id` int unsigned DEFAULT NULL,
  `drop_point_id` int unsigned DEFAULT NULL,
  `currency_id` int unsigned NOT NULL,
  `exchange_rate` decimal(16,6) NOT NULL DEFAULT '1.000000',
  `stage` enum('draft','sourcing','confirmed','received','packing','invoiced','closed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `customer_ref` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `sourcing_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `packed_at` datetime DEFAULT NULL,
  `invoiced_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `idx_orders_stage` (`stage`,`departure_date`),
  KEY `idx_orders_customer` (`customer_id`,`order_date`),
  KEY `fk_o_box` (`box_type_id`),
  KEY `fk_o_agent` (`shipping_agent_id`),
  KEY `fk_o_drop` (`drop_point_id`),
  KEY `fk_o_cur` (`currency_id`),
  CONSTRAINT `fk_o_agent` FOREIGN KEY (`shipping_agent_id`) REFERENCES `shipping_agents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_o_box` FOREIGN KEY (`box_type_id`) REFERENCES `box_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_o_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_o_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_o_drop` FOREIGN KEY (`drop_point_id`) REFERENCES `drop_points` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'OD00001',1,'2026-10-29','2026-10-31',1,1,NULL,3,145.000000,'invoiced',NULL,NULL,'2026-10-29 10:34:50','2026-10-29 10:35:58','2026-10-29 10:36:36','2026-10-29 10:36:44','2026-10-29 10:42:30',NULL,1,'2026-10-29 10:23:49',1,'2026-10-29 10:42:30');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packing_lists`
--

DROP TABLE IF EXISTS `packing_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `packing_lists` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `packing_list_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `consignment_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `drop_point_id` int unsigned DEFAULT NULL,
  `awb_no` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flight_no` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_weight_kg` decimal(10,2) DEFAULT NULL,
  `volume_weight_kg` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('open','invoiced') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  UNIQUE KEY `packing_list_no` (`packing_list_no`),
  UNIQUE KEY `consignment_no` (`consignment_no`),
  KEY `fk_pl_drop` (`drop_point_id`),
  CONSTRAINT `fk_pl_drop` FOREIGN KEY (`drop_point_id`) REFERENCES `drop_points` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pl_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packing_lists`
--

LOCK TABLES `packing_lists` WRITE;
/*!40000 ALTER TABLE `packing_lists` DISABLE KEYS */;
INSERT INTO `packing_lists` VALUES (1,1,'PL260001','GL260001',NULL,NULL,NULL,NULL,NULL,NULL,'invoiced',1,'2026-10-29 10:36:44');
/*!40000 ALTER TABLE `packing_lists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT INTO `product_categories` VALUES (1,'T-Hybrid',1),(2,'Sprays',1);
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` int unsigned NOT NULL,
  `permission` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`role_id`,`permission`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (2,'customers.create'),(2,'customers.edit'),(2,'customers.view'),(2,'dashboard.view'),(2,'exchange_rates.view'),(2,'farm_prices.edit'),(2,'farm_prices.view'),(2,'farm_records.edit'),(2,'farm_records.view'),(2,'farms.create'),(2,'farms.edit'),(2,'farms.view'),(2,'freight.create'),(2,'freight.edit'),(2,'freight.invoice'),(2,'freight.view'),(2,'invoices.view'),(2,'orders.confirm'),(2,'orders.create'),(2,'orders.delete'),(2,'orders.edit'),(2,'orders.invoice'),(2,'orders.pack'),(2,'orders.receive'),(2,'orders.view'),(2,'packing.edit'),(2,'packing.view'),(2,'reports.view'),(2,'selling_prices.edit'),(2,'selling_prices.view'),(2,'setup.edit'),(2,'setup.view'),(2,'shipping_agents.create'),(2,'shipping_agents.edit'),(2,'shipping_agents.view'),(2,'varieties.create'),(2,'varieties.edit'),(2,'varieties.view'),(3,'agent_payments.create'),(3,'agent_payments.delete'),(3,'agent_payments.edit'),(3,'agent_payments.view'),(3,'customer_payments.create'),(3,'customer_payments.delete'),(3,'customer_payments.edit'),(3,'customer_payments.view'),(3,'customers.view'),(3,'dashboard.view'),(3,'exchange_rates.edit'),(3,'exchange_rates.view'),(3,'expenses.create'),(3,'expenses.delete'),(3,'expenses.edit'),(3,'expenses.view'),(3,'farm_payments.create'),(3,'farm_payments.delete'),(3,'farm_payments.edit'),(3,'farm_payments.view'),(3,'farm_prices.view'),(3,'farm_records.edit'),(3,'farm_records.view'),(3,'farms.view'),(3,'freight.invoice'),(3,'freight.view'),(3,'invoices.view'),(3,'invoices.void'),(3,'orders.invoice'),(3,'orders.view'),(3,'other_costs.create'),(3,'other_costs.delete'),(3,'other_costs.edit'),(3,'other_costs.view'),(3,'other_sales.create'),(3,'other_sales.delete'),(3,'other_sales.edit'),(3,'other_sales.view'),(3,'packing.view'),(3,'reports.financial'),(3,'reports.view'),(3,'selling_prices.view'),(3,'setup.view'),(3,'shipping_agents.view'),(3,'statements.view'),(3,'varieties.view'),(4,'customers.view'),(4,'dashboard.view'),(4,'exchange_rates.view'),(4,'farm_prices.view'),(4,'farm_records.view'),(4,'farms.view'),(4,'freight.view'),(4,'invoices.view'),(4,'orders.view'),(4,'packing.view'),(4,'reports.view'),(4,'selling_prices.view'),(4,'shipping_agents.view'),(4,'varieties.view');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_super` tinyint(1) NOT NULL DEFAULT '0',
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrator','Full access to everything',1,1,'2026-10-28 11:07:02'),(2,'Operations Manager','Runs the order pipeline, freight and master data',0,1,'2026-10-28 11:07:02'),(3,'Accountant','Payments, costs, expenses, statements and reports',0,1,'2026-10-28 11:07:02'),(4,'Viewer','Read-only access',0,1,'2026-10-28 11:07:02');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `selling_prices`
--

DROP TABLE IF EXISTS `selling_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `selling_prices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `variety_id` int unsigned NOT NULL,
  `length_id` int unsigned NOT NULL,
  `price` decimal(12,4) NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sp` (`variety_id`,`length_id`),
  KEY `fk_sp_len` (`length_id`),
  KEY `fk_sp_cur` (`currency_id`),
  CONSTRAINT `fk_sp_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_sp_len` FOREIGN KEY (`length_id`) REFERENCES `lengths` (`id`),
  CONSTRAINT `fk_sp_var` FOREIGN KEY (`variety_id`) REFERENCES `varieties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `selling_prices`
--

LOCK TABLES `selling_prices` WRITE;
/*!40000 ALTER TABLE `selling_prices` DISABLE KEYS */;
INSERT INTO `selling_prices` VALUES (1,1,5,0.2000,3,1,'2026-10-29 11:48:53');
/*!40000 ALTER TABLE `selling_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sequences`
--

DROP TABLE IF EXISTS `sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sequences` (
  `name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `next_value` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`name`,`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sequences`
--

LOCK TABLES `sequences` WRITE;
/*!40000 ALTER TABLE `sequences` DISABLE KEYS */;
INSERT INTO `sequences` VALUES ('invoice','26',3),('order','',2),('packing','26',2);
/*!40000 ALTER TABLE `sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('base_currency','KES','2026-10-28 11:07:02'),('company_address','P.O. Box 27742-00506, Nairobi, Kenya','2026-10-28 11:07:02'),('company_email','info@magnateflowers.co.ke','2026-10-28 11:07:02'),('company_name','Magnate Flowers Limited','2026-10-28 11:07:02'),('company_phone','+254 712 345 678','2026-10-28 11:07:02'),('company_pin','P051234567V','2026-10-28 11:07:02'),('company_tagline','Fresh-cut flower exporters','2026-10-28 11:07:02'),('company_website','www.magnateflowers.co.ke','2026-10-28 11:07:02'),('default_origin','NBO','2026-10-28 11:07:02'),('doc_accent','#BE185D','2026-10-28 11:07:02'),('invoice_terms','All invoices to be paid before the day of shipping.','2026-10-28 11:07:02'),('origin_declaration','The exporter of the products covered by this document declares that, except where otherwise clearly indicated, these products are of Kenyan preferential origin.','2026-10-28 11:07:02'),('volumetric_divisor','6000','2026-10-28 11:07:02');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shipping_agents`
--

DROP TABLE IF EXISTS `shipping_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shipping_agents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency_id` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_agent_cur` (`currency_id`),
  CONSTRAINT `fk_agent_cur` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shipping_agents`
--

LOCK TABLES `shipping_agents` WRITE;
/*!40000 ALTER TABLE `shipping_agents` DISABLE KEYS */;
INSERT INTO `shipping_agents` VALUES (1,'KN','DOUGLAS MUNGAI','dikeidaggy@gmail.com','+254184258753',2,1);
/*!40000 ALTER TABLE `shipping_agents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `user_id` int unsigned NOT NULL,
  `permission` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `allow` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`user_id`,`permission`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permissions`
--

LOCK TABLES `user_permissions` WRITE;
/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int unsigned NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme` enum('light','dark','auto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `last_login_at` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Bisoft Dev','bisoft','bisoftsystemsltd@gmail.com',NULL,NULL,'$2y$10$c98jADI46GGGJwAco4OSBORBu3MScPbK7LWgWZKnKQxNsvPwOq29m',1,NULL,'light','active',0,'2026-11-10 08:50:33','2026-10-28 11:07:56',NULL,'2026-10-28 11:07:56','2026-11-10 08:50:33'),(2,'Alphaxard','alpha','timcatech@gmail.com','0711235022','Dev','$2y$10$ok6Qm9iG8UOoYyoHcNafMukT7W.vRZtDQA3WOw4kS9UVRF9q7tlLK',1,NULL,'light','active',0,'2026-10-29 11:13:11',NULL,1,'2026-10-29 09:17:49','2026-10-29 11:31:51'),(3,'Magnate','magnate','info@magnateflowers.com','+254184258753','Director','$2y$10$gWKu4BAX7vP3WrtpZMDyd.Uqs1MyNmnf5bPNVNbNqMupZcxtOVcea',1,NULL,'auto','active',0,'2026-10-29 12:10:54','2026-10-29 12:10:08',1,'2026-10-29 11:50:20','2026-10-29 12:10:54');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `varieties`
--

DROP TABLE IF EXISTS `varieties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `varieties` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `colour` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_var_cat` (`category_id`),
  CONSTRAINT `fk_var_cat` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `varieties`
--

LOCK TABLES `varieties` WRITE;
/*!40000 ALTER TABLE `varieties` DISABLE KEYS */;
INSERT INTO `varieties` VALUES (1,'Abeba',2,'Gold',1);
/*!40000 ALTER TABLE `varieties` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-10  7:10:30
