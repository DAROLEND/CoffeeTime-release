-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: localhost    Database: CoffeeTime
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super','staff') NOT NULL DEFAULT 'staff',
  `permissions` text NOT NULL DEFAULT '[]',
  `display_name` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','$2y$12$f6ZuyZrN66JlTlKLazIXPet9G8rq9y/TTo2Mco8tD5/FHKEfyEyEK','super','[]',''),(2,'admin_menu','$2y$10$rrRaquAM7otDAUC.FiXxPu6EHI9Bp1aKXQxi0bP6zDsNSMoO7MtYy','staff','[\"products\"]','AS'),(3,'admin1','$2y$10$U2eSKqpf.QbE2THhwfFv9.Hj6c4Un8/wBdwy/.FThW/gmzc3yNsda','staff','[\"orders_view\"]','AA'),(4,'admin2','$2y$10$fmSiyh.mZ2mrEwBC5CBXSOBami5HX7jt6CLBxZwhSKgnDAHJihXU.','staff','[\"orders_view\",\"orders_edit\"]','QW'),(5,'admin3','$2y$10$hxFk8KPo3r7F/fBJXiCWuOa98qCdSYOwnzO5S5J.OZv.lUmnkAeJu','staff','[\"content\",\"reviews\"]','QQ'),(6,'admin_full','$2y$10$VTrFkPGnFiI8bqZ8f25LLOWtn7YjrfBMUC93RLtmCmaweSgfV0Ifu','staff','[\"orders_view\",\"orders_edit\",\"products\",\"content\",\"reviews\"]','SSS'),(7,'admin5','$2y$10$hIznRcBImBF1Uz.G5g3iAuZqZhgWbz5QdmG.CbYqJvfxm8cmMr8ea','staff','[\"products\",\"content\"]','aaa'),(8,'addmin','$2y$10$2.mKdBg7gZ6uH4a1eM/.8.7lUYHb38a5HwaBQGzkU3iH3tcnzN7Re','staff','[]','q');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cake_items`
--

DROP TABLE IF EXISTS `cake_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cake_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL DEFAULT 'static/images/menu_items/default.jpg',
  `price_per_kg` decimal(10,2) NOT NULL DEFAULT 1000.00,
  `min_weight` decimal(4,1) NOT NULL DEFAULT 1.0,
  `is_custom_order` tinyint(1) NOT NULL DEFAULT 1,
  `popularity` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) GENERATED ALWAYS AS (`price_per_kg`) STORED,
  PRIMARY KEY (`id`),
  KEY `popularity` (`popularity`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cake_items`
--

LOCK TABLES `cake_items` WRITE;
/*!40000 ALTER TABLE `cake_items` DISABLE KEYS */;
INSERT INTO `cake_items` VALUES (1,'Медовик','Класичний медовий торт з вершковим кремом та горіховою крихтою','static/images/menu_items/default.jpg',850.00,1.0,1,0,850.00),(2,'Наполеон','Торт з тонких листкових коржів з ніжним заварним кремом','static/images/menu_items/default.jpg',900.00,1.0,1,0,900.00),(3,'Шоколадний','Насичений шоколадний бісквіт з ганашем і свіжими ягодами','static/images/menu_items/default.jpg',950.00,1.0,1,0,950.00),(4,'Фрезьє','Полуничний мусовий торт з дзеркальною глазур\'ю','static/images/menu_items/default.jpg',1100.00,1.0,1,0,1100.00),(5,'Дитячий торт','Яскравий торт на замовлення з будь-яким декором для дитини','static/images/menu_items/default.jpg',1000.00,1.0,1,0,1000.00);
/*!40000 ALTER TABLE `cake_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coffee_items`
--

DROP TABLE IF EXISTS `coffee_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coffee_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_cold` tinyint(1) NOT NULL DEFAULT 0,
  `popularity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `popularity` (`popularity`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coffee_items`
--

LOCK TABLES `coffee_items` WRITE;
/*!40000 ALTER TABLE `coffee_items` DISABLE KEYS */;
INSERT INTO `coffee_items` VALUES (4,'Еспресо','Чорна кава · 30 мл','static/images/menu_items/coffee/item_6a0f761d8df7d0.27240681.webp',30.00,0,5),(5,'Віденська','Подвійний еспресо з вершками · 110 мл','static/images/menu_items/coffee/item_6a0f7614ac4448.90849070.webp',35.00,0,7),(6,'Американо','Еспресо з гарячою водою','static/images/menu_items/coffee/item_6a0f760e611eb8.68826366.webp',40.00,0,6),(7,'Американо з молоком','Американо та молоко','static/images/menu_items/coffee/item_6a0f76071b4a30.03719053.webp',45.00,0,0),(8,'Лайт','Американо з молоком · 200 мл','static/images/menu_items/coffee/item_6a0f760073bd84.27111817.webp',50.00,0,1),(9,'Капучіно','Еспресо з молочною пінкою','static/images/menu_items/coffee/item_6a0f75923eb1d5.24173735.webp',50.00,0,4),(10,'Раф','Еспресо, вершки, ванільний сироп','static/images/menu_items/coffee/item_6a0f758a926bd8.59759911.webp',55.00,0,0),(11,'Гляссе','Еспресо з морозивом · 250 мл','static/images/menu_items/coffee/item_6a0f758379b0f8.92288231.webp',60.00,0,1),(12,'Мокачіно','Еспресо, шоколад, молоко','static/images/menu_items/coffee/item_6a0f757c053015.34653728.webp',65.00,0,0),(13,'Флет-вайт','Подвійний еспресо з молоком · 350 мл','static/images/menu_items/coffee/item_6a0f7575909683.25042389.webp',65.00,0,0),(14,'Лате','Еспресо з паровим молоком · 400 мл','static/images/menu_items/coffee/item_6a0f756b721cb9.32286118.webp',65.00,0,0),(15,'Лате XL','Еспресо з паровим молоком, великий','static/images/menu_items/coffee/item_6a0f75652ddef1.39774850.webp',80.00,0,0),(16,'Флет-вайт XL','Подвійний еспресо з молоком · 500 мл','static/images/menu_items/coffee/item_6a0f755dca8bb0.39911222.webp',80.00,0,0),(17,'Гарячий шоколад','Збитий шоколадний напій · 250 мл','static/images/menu_items/coffee/item_6a0f7557760266.42020631.webp',65.00,0,0),(18,'Спінене молоко','Гаряче спінене молоко · 350 мл','static/images/menu_items/coffee/item_6a0f754f765db9.83171201.webp',40.00,0,0),(19,'Какао','Натуральне какао','static/images/menu_items/coffee/item_6a0f7549152888.90743072.webp',55.00,0,0),(20,'Чай','Чорний або трав\'яний чай','static/images/menu_items/coffee/item_6a0f753e5681c7.98860958.webp',35.00,0,0),(25,'Фрапучіно','Збитий холодний кавовий коктейль · 350 мл','static/images/menu_items/coffee/item_6a0f75388c1c15.07994550.webp',80.00,1,0),(26,'Фругочіно','Фруктово-кавовий фраппе','static/images/menu_items/coffee/item_6a0f752f6a1e61.69866381.webp',90.00,1,0),(27,'Айс лате','Лате з льодом','static/images/menu_items/coffee/item_6a0f7522103ed2.06802674.webp',75.00,1,0),(28,'Еспресо тонік','Еспресо з тонічною водою та льодом','static/images/menu_items/coffee/item_6a0f751ba8fe72.30919612.webp',65.00,1,0);
/*!40000 ALTER TABLE `coffee_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cold_drink_items`
--

DROP TABLE IF EXISTS `cold_drink_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cold_drink_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `popularity` (`popularity`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cold_drink_items`
--

LOCK TABLES `cold_drink_items` WRITE;
/*!40000 ALTER TABLE `cold_drink_items` DISABLE KEYS */;
INSERT INTO `cold_drink_items` VALUES (4,'Мохіто','Освіжаючий мохіто з м\'ятою · 500 мл','static/images/menu_items/cold_drinks/mohito.jpg',70.00,0),(5,'Молочний коктейль','Густий молочний коктейль','static/images/menu_items/cold_drinks/lemonade.png',90.00,0),(6,'Фруктовий коктейль','Свіжий фруктовий коктейль','static/images/menu_items/cold_drinks/smoothie.png',95.00,0),(7,'Бабл ті','Чай з молоком та тапіокою','static/images/menu_items/default.jpg',100.00,0),(8,'Какао бабл','Какао з молочними бульбашками','static/images/menu_items/default.jpg',110.00,0);
/*!40000 ALTER TABLE `cold_drink_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dessert_items`
--

DROP TABLE IF EXISTS `dessert_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dessert_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `popularity` (`popularity`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dessert_items`
--

LOCK TABLES `dessert_items` WRITE;
/*!40000 ALTER TABLE `dessert_items` DISABLE KEYS */;
INSERT INTO `dessert_items` VALUES (1,'Львівський сирник','Крафтовий, ніжний, з ідеальним балансом смаку','static/images/menu_items/desserts/lviv_sirnik.png',90.00,1),(2,'Карамельний тарт','Хрумка основа, ніжна карамель і неймовірний шоколад ','static/images/menu_items/desserts/tart.png',80.00,2),(3,'Мусовий десерт','Шоколад, карамель, велюр — гармонія в кожному шматочку','static/images/menu_items/desserts/muss.png',110.00,1);
/*!40000 ALTER TABLE `dessert_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fast_food_items`
--

DROP TABLE IF EXISTS `fast_food_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fast_food_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0,
  `variant_options` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `popularity` (`popularity`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fast_food_items`
--

LOCK TABLES `fast_food_items` WRITE;
/*!40000 ALTER TABLE `fast_food_items` DISABLE KEYS */;
INSERT INTO `fast_food_items` VALUES (5,'Стріпси','Курячі стрипси','static/images/menu_items/default.jpg',70.00,0,NULL),(6,'Сирні палички','Хрусткі сирні палички','static/images/menu_items/default.jpg',120.00,2,NULL),(7,'Цибулеві кільця','Хрусткі кільця цибулі','static/images/menu_items/default.jpg',75.00,0,NULL),(8,'Нагетси','Курячі нагетси','static/images/menu_items/default.jpg',95.00,0,NULL),(9,'Соус порційний','Порційний соус на вибір','static/images/menu_items/default.jpg',10.00,0,'{\"type\":\"size\",\"label\":\"Вид соусу\",\"options\":[{\"id\":\"ketchup\",\"label\":\"Кетчуп\",\"price_diff\":0},{\"id\":\"sweet_sour\",\"label\":\"Кисло-солодкий\",\"price_diff\":0},{\"id\":\"garlic\",\"label\":\"Часниковий\",\"price_diff\":0},{\"id\":\"bbq\",\"label\":\"Барбекю\",\"price_diff\":0},{\"id\":\"mayo\",\"label\":\"Майонез\",\"price_diff\":0}]}'),(10,'Картопляні кульки','Картопляні кульки у клярі','static/images/menu_items/default.jpg',75.00,0,NULL),(11,'Картопля Фрі','Золотиста картопля Фрі','static/images/menu_items/fast_food/fries_potato.png',50.00,1,'{\"type\":\"size\",\"label\":\"Розмір\",\"options\":[{\"id\":\"small\",\"label\":\"Мала\",\"price_diff\":0},{\"id\":\"large\",\"label\":\"Велика\",\"price_diff\":10}]}'),(12,'Картопля по-селянськи','Картопля по-селянськи зі спеціями','',65.00,1,NULL),(13,'Чізбургер','Котлета, плавлений сир, салат, соус','static/images/menu_items/fast_food/burger.png',130.00,1,'{\"type\":\"filling\",\"label\":\"Котлета\",\"options\":[{\"id\":\"beef\",\"label\":\"Яловича\",\"price_diff\":0,\"sizes\":[{\"id\":\"single\",\"label\":\"Одинарний\",\"price_diff\":0},{\"id\":\"double\",\"label\":\"Подвійний\",\"price_diff\":55}]},{\"id\":\"chicken\",\"label\":\"Куряча\",\"price_diff\":0,\"sizes\":[{\"id\":\"single\",\"label\":\"Одинарний\",\"price_diff\":0},{\"id\":\"double\",\"label\":\"Подвійний\",\"price_diff\":55}]}]}'),(14,'Гамбургер','Котлета, салат, соус','static/images/menu_items/fast_food/burger.png',155.00,1,'{\"type\":\"filling\",\"label\":\"Котлета\",\"options\":[{\"id\":\"beef\",\"label\":\"Яловича\",\"price_diff\":0,\"sizes\":[{\"id\":\"single\",\"label\":\"Одинарний\",\"price_diff\":0},{\"id\":\"double\",\"label\":\"Подвійний\",\"price_diff\":60}]},{\"id\":\"chicken\",\"label\":\"Куряча\",\"price_diff\":0,\"sizes\":[{\"id\":\"single\",\"label\":\"Одинарний\",\"price_diff\":0},{\"id\":\"double\",\"label\":\"Подвійний\",\"price_diff\":60}]}]}'),(15,'Французький хот-дог','Бургер у французькому стилі','static/images/menu_items/default.jpg',75.00,0,'{\"type\":\"size\",\"label\":\"Сосиска\",\"options\":[{\"id\":\"classic\",\"label\":\"Класична\",\"price_diff\":0},{\"id\":\"bavarian\",\"label\":\"Баварська\",\"price_diff\":10}]}'),(16,'Американський хот-дог','Класичний американський хот-дог','static/images/menu_items/fast_food/hot-dog.png',95.00,25,'{\"type\":\"size\",\"label\":\"Сосиска\",\"options\":[{\"id\":\"classic\",\"label\":\"Класична\",\"price_diff\":0},{\"id\":\"bavarian\",\"label\":\"Баварська\",\"price_diff\":10}]}'),(17,'Лаваш','Лаваш з куркою, овочами та соусом','static/images/menu_items/default.jpg',75.00,0,'{\"type\":\"filling\",\"label\":\"Начинка\",\"options\":[{\"id\":\"chicken\",\"label\":\"З куркою\",\"price_diff\":0,\"sizes\":[{\"id\":\"small\",\"label\":\"Малий\",\"price_diff\":0},{\"id\":\"large\",\"label\":\"Великий\",\"price_diff\":35}]},{\"id\":\"sausage\",\"label\":\"З сосискою\",\"price_diff\":20},{\"id\":\"kobasa\",\"label\":\"З ковбаскою\",\"price_diff\":35}]}'),(22,'Кукурудза з сиром','Варена кукурудза з вершковим сиром','static/images/menu_items/default.jpg',85.00,0,NULL),(23,'Паніні','Хрустке паніні з шинкою та сиром','static/images/menu_items/default.jpg',80.00,0,'{\"type\":\"filling\",\"label\":\"Начинка\",\"options\":[{\"id\":\"ham\",\"label\":\"З шинкою\",\"price_diff\":0},{\"id\":\"chicken\",\"label\":\"З куркою\",\"price_diff\":0},{\"id\":\"salami\",\"label\":\"З салямі\",\"price_diff\":0}]}');
/*!40000 ALTER TABLE `fast_food_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gallery`
--

DROP TABLE IF EXISTS `gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gallery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `alt` varchar(255) NOT NULL DEFAULT '',
  `category` enum('food','interior') NOT NULL DEFAULT 'food',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gallery`
--

LOCK TABLES `gallery` WRITE;
/*!40000 ALTER TABLE `gallery` DISABLE KEYS */;
INSERT INTO `gallery` VALUES (1,'photo1.png','Фаст-фуд','food','2026-04-18 03:20:15'),(2,'photo2.png','Десерти','food','2026-04-18 03:20:15'),(3,'photo3.png','Бургери','food','2026-04-18 03:20:15'),(4,'photo4.png','Піца','food','2026-04-18 03:20:15'),(5,'photo5.png','Інтер\'єр','interior','2026-04-18 03:20:15'),(6,'photo6.png','Інтер\'єр','interior','2026-04-18 03:20:15'),(7,'photo7.png','Інтер\'єр','interior','2026-04-18 03:20:15'),(8,'photo8.png','Інтер\'єр','interior','2026-04-18 03:20:15'),(9,'photo9.png','Піца','food','2026-04-18 03:20:15'),(10,'photo10.png','Десерти','food','2026-04-18 03:20:15');
/*!40000 ALTER TABLE `gallery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hero_slides`
--

DROP TABLE IF EXISTS `hero_slides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hero_slides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image` varchar(255) NOT NULL,
  `label` varchar(100) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `subtitle` varchar(255) NOT NULL DEFAULT '',
  `sort_order` tinyint(3) unsigned DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hero_slides`
--

LOCK TABLES `hero_slides` WRITE;
/*!40000 ALTER TABLE `hero_slides` DISABLE KEYS */;
INSERT INTO `hero_slides` VALUES (5,'static/images/main/hero1.webp','Щоранку з любов\'ю','Кава, яка починає день','Свіжозмелені зерна, ручне приготування, затишок у кожному ковтку',1,1),(6,'static/images/main/hero2.webp','Гаряче й смачне','Їжа, що гріє зсередини','Піца, суші, салати — замовляй онлайн і забирай готовим',2,1),(7,'static/images/main/hero3.webp','Гусятин','Твоє місце для відпочинку','Замовляй онлайн — і забирай без черги',3,1);
/*!40000 ALTER TABLE `hero_slides` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ice_cream_items`
--

DROP TABLE IF EXISTS `ice_cream_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ice_cream_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL DEFAULT 'static/images/menu_items/default.jpg',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `variant_options` text DEFAULT NULL,
  `popularity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_popularity` (`popularity`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ice_cream_items`
--

LOCK TABLES `ice_cream_items` WRITE;
/*!40000 ALTER TABLE `ice_cream_items` DISABLE KEYS */;
INSERT INTO `ice_cream_items` VALUES (1,'Морозиво','Ніжне вершкове морозиво — оберіть кількість кульок','static/images/menu_items/default.jpg',45.00,'{\"type\":\"scoops\",\"label\":\"Кількість кульок\",\"options\":[{\"id\":\"1\",\"label\":\"1 кулька\",\"price_diff\":0},{\"id\":\"2\",\"label\":\"2 кульки\",\"price_diff\":20},{\"id\":\"3\",\"label\":\"3 кульки\",\"price_diff\":40}]}',0);
/*!40000 ALTER TABLE `ice_cream_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mini_pizza_items`
--

DROP TABLE IF EXISTS `mini_pizza_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mini_pizza_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL DEFAULT 'static/images/menu_items/default.jpg',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sauce_type` enum('tomato','cream','bbq') NOT NULL DEFAULT 'tomato',
  `is_spicy` tinyint(1) NOT NULL DEFAULT 0,
  `ingredients_tags` varchar(500) DEFAULT NULL,
  `popularity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mini_pizza_items`
--

LOCK TABLES `mini_pizza_items` WRITE;
/*!40000 ALTER TABLE `mini_pizza_items` DISABLE KEYS */;
INSERT INTO `mini_pizza_items` VALUES (1,'Міні Салямі','Моцарела, салямі','static/images/menu_items/default.jpg',75.00,'tomato',0,'салямі',0),(2,'Міні Сирна','Моцарела, 4 сири','static/images/menu_items/default.jpg',75.00,'cream',0,'4 сири',0);
/*!40000 ALTER TABLE `mini_pizza_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `category` varchar(50) NOT NULL,
  `selected_size` varchar(20) DEFAULT 'small',
  `selected_variant` text DEFAULT NULL,
  `cheese_crust` tinyint(1) NOT NULL DEFAULT 0,
  `takeaway` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (13,22,2,1,210.00,'pizza_items','small',NULL,0,0),(14,22,1,1,90.00,'dessert_items','small',NULL,0,0),(15,23,2,1,60.00,'coffee_items','small',NULL,0,0),(17,24,2,1,60.00,'coffee_items','small',NULL,0,0),(19,25,2,1,60.00,'coffee_items','small',NULL,0,0),(21,27,1,3,50.00,'coffee_items','small',NULL,0,0),(22,28,2,1,60.00,'coffee_items','small',NULL,0,0),(23,29,2,1,60.00,'coffee_items','small',NULL,0,0),(24,30,2,1,60.00,'cold_drink_items','small',NULL,0,0),(25,31,3,1,65.00,'coffee_items','small',NULL,0,0),(26,32,2,1,60.00,'coffee_items','small',NULL,0,0),(27,33,2,6,60.00,'coffee_items','small',NULL,0,0),(28,33,3,1,65.00,'coffee_items','small',NULL,0,0),(29,34,3,1,65.00,'cold_drink_items','small',NULL,0,0),(30,34,2,1,60.00,'coffee_items','small',NULL,0,0),(31,34,3,1,65.00,'coffee_items','small',NULL,0,0),(32,35,2,1,60.00,'coffee_items','small',NULL,0,0),(33,35,3,1,65.00,'coffee_items','small',NULL,0,0),(34,36,2,1,60.00,'coffee_items','small',NULL,0,0),(35,37,2,1,60.00,'cold_drink_items','small',NULL,0,0),(36,38,2,3,210.00,'pizza_items','small',NULL,0,0),(37,38,3,3,220.00,'pizza_items','small',NULL,0,0),(38,39,2,1,80.00,'dessert_items','small',NULL,0,0),(39,40,2,1,60.00,'coffee_items','small',NULL,0,0),(40,41,2,1,80.00,'dessert_items','small',NULL,0,0),(41,41,3,1,65.00,'coffee_items','small',NULL,0,0),(42,42,2,1,60.00,'coffee_items','small',NULL,0,0),(43,43,8,3,185.00,'pizza_items','small',NULL,0,0),(44,44,5,1,35.00,'coffee_items','small',NULL,0,0),(45,45,9,10,195.00,'pizza_items','small',NULL,0,0),(46,48,5,1,35.00,'coffee_items','small',NULL,0,0),(47,49,5,1,35.00,'coffee_items','small',NULL,0,0),(48,50,33,1,160.00,'pizza_items','small',NULL,0,0),(49,51,33,1,160.00,'pizza_items','small',NULL,0,0),(50,52,4,1,30.00,'coffee_items','small',NULL,0,0),(51,53,33,2,160.00,'pizza_items','small',NULL,0,0),(52,54,6,1,120.00,'fast_food_items','small',NULL,0,0),(53,55,6,1,120.00,'fast_food_items','small',NULL,0,0),(54,56,1,1,210.00,'salad_items','small',NULL,0,0),(55,57,33,1,225.00,'pizza_items','small',NULL,1,0),(56,58,35,1,185.00,'pizza_items','small',NULL,0,0),(57,59,8,1,50.00,'coffee_items','small',NULL,0,0),(58,60,33,1,160.00,'pizza_items','small',NULL,0,0),(59,61,33,1,160.00,'pizza_items','small',NULL,0,0),(60,62,33,1,160.00,'pizza_items','small',NULL,0,0),(61,63,6,1,40.00,'coffee_items','small',NULL,0,0),(62,64,5,1,35.00,'coffee_items','small',NULL,0,0),(63,65,33,1,160.00,'pizza_items','small',NULL,0,0),(64,66,48,1,185.00,'pizza_items','small',NULL,0,0),(65,67,33,1,160.00,'pizza_items','small',NULL,0,0),(66,68,33,1,160.00,'pizza_items','small',NULL,0,0),(67,69,33,1,160.00,'pizza_items','small',NULL,0,0),(68,70,16,22,95.00,'fast_food_items','small','{\"type\":\"size\",\"scoop_id\":\"classic\",\"scoop_label\":\"Класична\",\"price_diff\":0}',0,0),(69,71,36,11,260.00,'pizza_items','small',NULL,1,0),(70,72,37,1,200.00,'pizza_items','small',NULL,0,0),(71,73,6,1,40.00,'coffee_items','small',NULL,0,0),(72,74,6,1,40.00,'coffee_items','small',NULL,0,0),(73,75,6,1,40.00,'coffee_items','small',NULL,0,0),(74,76,6,1,40.00,'coffee_items','small',NULL,0,0),(75,76,4,1,30.00,'coffee_items','small',NULL,0,0),(76,76,34,1,185.00,'pizza_items','small',NULL,0,0),(77,77,5,1,35.00,'coffee_items','small',NULL,0,0),(78,77,16,1,95.00,'fast_food_items','small','{\"type\":\"size\",\"scoop_id\":\"classic\",\"scoop_label\":\"Класична\",\"price_diff\":0}',0,0),(79,78,5,1,35.00,'coffee_items','small',NULL,0,0),(80,78,16,1,95.00,'fast_food_items','small','{\"type\":\"size\",\"scoop_id\":\"classic\",\"scoop_label\":\"Класична\",\"price_diff\":0}',0,0),(81,79,4,1,30.00,'coffee_items','small',NULL,0,0),(82,80,9,1,50.00,'coffee_items','small',NULL,0,0),(83,81,9,1,50.00,'coffee_items','small',NULL,0,0),(84,82,9,1,50.00,'coffee_items','small',NULL,0,0),(85,83,9,1,50.00,'coffee_items','small',NULL,0,0),(86,83,16,1,105.00,'fast_food_items','small','{\"type\":\"size\",\"scoop_id\":\"bavarian\",\"scoop_label\":\"Баварська\",\"price_diff\":10}',0,0),(87,83,14,1,215.00,'fast_food_items','small','{\"type\":\"filling\",\"filling_id\":\"chicken\",\"filling_label\":\"Куряча\",\"price_diff\":60,\"size_label\":\"Подвійний\",\"size_diff\":60}',0,0),(88,83,39,1,425.00,'pizza_items','large',NULL,1,0),(89,84,13,1,185.00,'fast_food_items','small','{\"type\":\"filling\",\"filling_id\":\"chicken\",\"filling_label\":\"Куряча\",\"price_diff\":55,\"size_label\":\"Подвійний\",\"size_diff\":55}',0,0),(90,84,11,1,60.00,'fast_food_items','small','{\"type\":\"size\",\"scoop_id\":\"large\",\"scoop_label\":\"Велика\",\"price_diff\":10}',0,0),(91,84,2,1,610.00,'sushi_sets','small',NULL,0,0),(92,85,12,1,65.00,'fast_food_items','small',NULL,0,0),(93,86,4,1,30.00,'coffee_items','small',NULL,0,0),(94,87,2,1,230.00,'sushi_items','small',NULL,0,0),(95,87,6,1,195.00,'sushi_items','small',NULL,0,0),(96,88,4,1,30.00,'coffee_items','small',NULL,0,0),(97,89,6,1,40.00,'coffee_items','small',NULL,0,0),(98,89,33,1,160.00,'pizza_items','small',NULL,0,0),(99,90,33,2,160.00,'pizza_items','small',NULL,0,0),(100,90,11,1,60.00,'coffee_items','small',NULL,0,0),(101,91,3,1,265.00,'sushi_items','small',NULL,0,0),(102,92,5,1,35.00,'coffee_items','small',NULL,0,0),(103,92,33,1,160.00,'pizza_items','small',NULL,0,0);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_ratings`
--

DROP TABLE IF EXISTS `order_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_user` (`order_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_ratings`
--

LOCK TABLES `order_ratings` WRITE;
/*!40000 ALTER TABLE `order_ratings` DISABLE KEYS */;
INSERT INTO `order_ratings` VALUES (1,42,3,5,'2026-05-08 17:53:40'),(2,76,3,5,'2026-05-09 12:28:00'),(3,84,3,5,'2026-05-13 21:10:27'),(4,83,3,4,'2026-05-14 12:46:20'),(5,85,3,5,'2026-05-14 13:12:33');
/*!40000 ALTER TABLE `order_ratings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_reminders`
--

DROP TABLE IF EXISTS `order_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `type` enum('email_customer','telegram_admin') NOT NULL,
  `send_at` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `fail_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pending` (`status`,`send_at`),
  KEY `fk_reminders_order` (`order_id`),
  CONSTRAINT `fk_reminders_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_reminders`
--

LOCK TABLES `order_reminders` WRITE;
/*!40000 ALTER TABLE `order_reminders` DISABLE KEYS */;
INSERT INTO `order_reminders` VALUES (2,86,'telegram_admin','2026-05-16 23:17:23','2026-05-17 02:17:23','sent',NULL,'2026-05-17 02:17:23'),(3,87,'telegram_admin','2026-05-17 02:22:32','2026-05-17 02:22:32','sent',NULL,'2026-05-17 02:18:26'),(4,87,'email_customer','2026-05-17 02:22:32','2026-05-17 02:22:36','sent',NULL,'2026-05-17 02:18:26'),(5,89,'telegram_admin','2026-05-25 09:00:00',NULL,'pending',NULL,'2026-05-22 01:25:34'),(6,89,'email_customer','2026-05-25 09:00:00',NULL,'pending',NULL,'2026-05-22 01:25:34'),(7,89,'telegram_admin','2026-05-24 19:00:00',NULL,'pending',NULL,'2026-05-22 01:25:34'),(8,89,'email_customer','2026-05-24 19:00:00',NULL,'pending',NULL,'2026-05-22 01:25:34'),(9,89,'telegram_admin','2026-05-23 19:00:00',NULL,'pending',NULL,'2026-05-22 01:25:34'),(10,89,'email_customer','2026-05-23 19:00:00',NULL,'pending',NULL,'2026-05-22 01:25:34'),(11,90,'telegram_admin','2026-05-25 10:30:00',NULL,'pending',NULL,'2026-05-22 23:36:11'),(12,90,'email_customer','2026-05-25 10:30:00',NULL,'pending',NULL,'2026-05-22 23:36:11'),(13,90,'telegram_admin','2026-05-24 19:00:00',NULL,'pending',NULL,'2026-05-22 23:36:11'),(14,90,'email_customer','2026-05-24 19:00:00',NULL,'pending',NULL,'2026-05-22 23:36:11'),(15,90,'telegram_admin','2026-05-23 19:00:00',NULL,'pending',NULL,'2026-05-22 23:36:11'),(16,90,'email_customer','2026-05-23 19:00:00',NULL,'pending',NULL,'2026-05-22 23:36:11'),(17,91,'telegram_admin','2026-05-23 08:30:00',NULL,'pending',NULL,'2026-05-23 01:44:02');
/*!40000 ALTER TABLE `order_reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_address` varchar(255) DEFAULT '',
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('new','processing','ready','done','cancelled') NOT NULL DEFAULT 'new',
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_surname` varchar(100) DEFAULT NULL,
  `customer_email` varchar(180) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `ready_time` varchar(20) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('pending','paid','failed','cash') DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `liqpay_order_id` varchar(100) DEFAULT NULL,
  `order_type` enum('dine_in','takeaway') NOT NULL DEFAULT 'dine_in',
  PRIMARY KEY (`order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (22,5,300.00,'','+380991508970','new','Андрій','Слабіцький',NULL,'','19:30','cash_on_pickup','2025-06-05 20:00:17','pending',NULL,NULL,'dine_in'),(23,5,560.00,'','+380991508970','new','Андрій','Слабіцький',NULL,'Сертифікат для друга','19:30','cash_on_pickup','2025-06-05 20:48:33','pending',NULL,NULL,'dine_in'),(24,5,560.00,'','+380991508970','new','Андрій','Слаб',NULL,'Друг','19:30','cash_on_pickup','2025-06-05 20:49:02','pending',NULL,NULL,'dine_in'),(25,5,560.00,'','+380991508970','new','German','Germanich',NULL,'=','19:30','cash_on_pickup','2025-06-05 20:50:37','pending',NULL,NULL,'dine_in'),(27,NULL,150.00,'','+38 (068) 946-13-35','new','ан','сла',NULL,'','08:00','card_online','2026-04-16 03:32:48','pending',NULL,NULL,'dine_in'),(28,NULL,60.00,'','+38 (068) 944-13-32','new','ааа','аа',NULL,'','08:00','card_online','2026-04-16 03:47:09','pending',NULL,NULL,'dine_in'),(29,NULL,60.00,'','+38 (066) 645-53-45','new','аа','вв',NULL,'','08:00','card_online','2026-04-16 03:51:37','pending',NULL,'coffeetime_29','dine_in'),(30,NULL,60.00,'','+38 (066) 666-66-66','new','пп','пп',NULL,'','08:00','cash_on_pickup','2026-04-16 03:57:07','pending',NULL,NULL,'dine_in'),(31,NULL,65.00,'','+38 (011) 111-11-11','new','іі','іі',NULL,'','08:00','cash_on_pickup','2026-04-16 04:03:21','pending',NULL,NULL,'dine_in'),(32,NULL,60.00,'','+38 (022) 222-22-22','new','аа','аа',NULL,'','08:00','cash_on_pickup','2026-04-16 04:06:16','pending',NULL,NULL,'dine_in'),(33,NULL,425.00,'','+38 (055) 555-55-55','new','ff','bgb',NULL,'','18:32','card_online','2026-04-16 18:17:21','pending',NULL,'coffeetime_33','dine_in'),(34,NULL,190.00,'','+38 (011) 111-11-11','new','аа','вв',NULL,'','12:00','cash_on_pickup','2026-04-18 21:28:16','pending',NULL,NULL,'dine_in'),(35,NULL,125.00,'','+38 (055) 453-45-34','new','аааа','авваав',NULL,'','12:00','card_online','2026-04-18 21:54:59','pending',NULL,'coffeetime_35','dine_in'),(36,NULL,60.00,'','+38 (083) 483-42-38','new','ввв','ввввв',NULL,'','12:00','card_online','2026-04-18 22:07:51','pending',NULL,'coffeetime_36','dine_in'),(37,NULL,60.00,'','+38 (032) 323-12-31','new','ввв','вввв',NULL,'','12:00','card_online','2026-04-18 22:08:49','pending',NULL,'coffeetime_37','dine_in'),(38,NULL,1290.00,'','+38 (054) 565-74-67','new','оноа','папрпаао',NULL,'','12:00','card_online','2026-04-18 22:11:32','pending',NULL,'coffeetime_38','dine_in'),(39,NULL,80.00,'','+38 (076) 666-66-66','done','пммр','нпор',NULL,'','12:00','card_online','2026-04-18 22:12:54','pending',NULL,'coffeetime_39','dine_in'),(40,NULL,60.00,'','+38 (054) 543-54-53','new','ак','сс',NULL,'','12:00','card_online','2026-04-18 22:18:34','pending',NULL,'coffeetime_40','dine_in'),(41,NULL,145.00,'','+38 (043) 333-33-33','done','вф','іівф',NULL,'','16:25','card_online','2026-04-20 16:10:38','pending',NULL,'coffeetime_41','dine_in'),(42,3,60.00,'','+38 (099) 150-89-70','done','Андрій','Слаб',NULL,'','08:00','cash_on_pickup','2026-04-21 02:05:19','pending',NULL,NULL,'dine_in'),(43,NULL,555.00,'','+38 (033) 333-33-33','new','аа','фафіавфі',NULL,'','12:20','card_online','2026-04-23 12:01:47','pending',NULL,'coffeetime_43','dine_in'),(44,NULL,35.00,'','+38 (033) 333-33-33','new','ввв','вввв',NULL,'','12:20','card_online','2026-04-23 12:02:27','pending',NULL,'coffeetime_44','dine_in'),(45,NULL,1950.00,'','+38 (034) 433-43-44','new','фф','увів',NULL,'','12:00','cash_on_pickup','2026-04-25 19:49:55','pending',NULL,NULL,'dine_in'),(46,NULL,90.00,'','+38 (033) 333-33-33','new','аа','аа',NULL,'','08:00','cash_on_pickup','2026-04-30 03:10:24','pending',NULL,NULL,'takeaway'),(47,NULL,30.00,'','+38 (033) 333-33-33','new','аа','аа',NULL,'','08:00','cash_on_pickup','2026-04-30 03:13:25','pending',NULL,NULL,'dine_in'),(48,NULL,35.00,'','+38 (022) 222-22-22','done','ff','dda',NULL,'','12:40','card_online','2026-04-30 12:26:03','pending',NULL,'coffeetime_48','dine_in'),(49,NULL,35.00,'','+38 (033) 333-33-33','new','fff','gg',NULL,'','12:45','card_online','2026-04-30 12:26:46','pending',NULL,'coffeetime_49','dine_in'),(50,3,160.00,'','+38 (099) 150-89-70','new','Андрій','Слабіцький',NULL,'','18:25','card_online','2026-05-04 18:09:08','pending',NULL,'coffeetime_50','dine_in'),(51,3,160.00,'','+38 (099) 150-89-70','new','Андрій','Слабіцький',NULL,'','18:30','card_online','2026-05-04 18:15:09','pending',NULL,'coffeetime_51','dine_in'),(52,3,30.00,'','+38 (099) 150-89-70','new','Андрій','Слабіцький',NULL,'','18:30','card_online','2026-05-04 18:15:33','pending',NULL,'coffeetime_52','dine_in'),(53,3,320.00,'','+38 (099) 150-89-70','cancelled','Андрій','Слабіцький',NULL,'','08:00','card_online','2026-05-04 20:00:50','failed',NULL,'coffeetime_53','dine_in'),(54,3,120.00,'','+38 (099) 150-89-70','new','Андрій','Слабіцький',NULL,'','08:00','card_online','2026-05-04 20:37:23','pending',NULL,'coffeetime_54','dine_in'),(55,3,120.00,'','+38 (099) 150-89-70','cancelled','Андрій','Слабіцький',NULL,'','08:00','card_online','2026-05-04 20:38:24','failed',NULL,'coffeetime_55','dine_in'),(56,NULL,210.00,'','+38 (023) 244-43-34','new','vf','fdsd',NULL,'','11:35','card_online','2026-05-05 11:19:58','pending',NULL,'coffeetime_56','dine_in'),(57,NULL,225.00,'','+38 (033) 333-33-33','new','ff','ff',NULL,'','11:45','card_online','2026-05-05 11:29:47','pending',NULL,'coffeetime_57','dine_in'),(58,3,185.00,'','+38 (099) 150-89-70','ready','Андрій','Слабіцький',NULL,'','18:10','card_online','2026-05-05 17:51:59','pending',NULL,'coffeetime_58','dine_in'),(59,NULL,50.00,'','+38 (077) 555-56-65','new','t6t','hvuy',NULL,'','18:10','card_online','2026-05-05 17:52:47','pending',NULL,'coffeetime_59','dine_in'),(60,NULL,160.00,'','+38 (011) 111-11-11','new','dd','dd',NULL,'','18:15','card_online','2026-05-05 17:59:22','pending',NULL,'coffeetime_60','dine_in'),(61,NULL,160.00,'','+38 (033) 333-33-33','new','ff','ff',NULL,'','18:25','card_online','2026-05-05 18:10:05','pending',NULL,'coffeetime_61','dine_in'),(62,NULL,160.00,'','+38 (044) 444-44-44','new','рр','аа',NULL,'','18:40','card_online','2026-05-05 18:22:38','pending',NULL,'coffeetime_62','dine_in'),(63,NULL,40.00,'','+38 (033) 334-43-33','new','йй','йй',NULL,'','18:40','card_online','2026-05-05 18:23:12','pending',NULL,'coffeetime_63','dine_in'),(64,NULL,35.00,'','+38 (055) 555-55-55','new','пп','пп',NULL,'','18:40','card_online','2026-05-05 18:24:04','pending',NULL,'coffeetime_64','dine_in'),(65,NULL,160.00,'','+38 (055) 555-55-55','new','пп','пп',NULL,'','18:45','card_online','2026-05-05 18:26:55','pending',NULL,'coffeetime_65','dine_in'),(66,NULL,185.00,'','+38 (044) 444-44-44','new','gg','gg',NULL,'','08:00','card_online','2026-05-06 22:01:06','pending',NULL,'coffeetime_66','dine_in'),(67,NULL,160.00,'','+38 (044) 444-44-44','new','ПР','ПР',NULL,'','08:00','card_online','2026-05-06 22:16:09','pending',NULL,'coffeetime_67','dine_in'),(68,NULL,160.00,'','+38 (033) 333-33-33','done','аа','вв',NULL,'','08:00','card_online','2026-05-06 22:22:09','paid','2026-05-06 21:22:32','coffeetime_68','dine_in'),(69,NULL,160.00,'','+38 (033) 333-33-33','done','gg','dd',NULL,'','08:00','card_online','2026-05-06 22:31:38','paid','2026-05-06 21:32:03','coffeetime_69','dine_in'),(70,NULL,2090.00,'','+38 (044) 442-42-23','done','ии','ии',NULL,'','08:00','card_online','2026-05-07 23:38:10','paid','2026-05-07 22:38:25','coffeetime_70','dine_in'),(71,NULL,2860.00,'','+38 (022) 222-22-22','new','іі','фф',NULL,'','08:00','card_online','2026-05-08 00:03:07','pending',NULL,'coffeetime_71','dine_in'),(72,NULL,200.00,'','+38 (044) 444-44-44','new','рр','рр',NULL,'','08:00','card_online','2026-05-08 00:14:08','pending',NULL,'coffeetime_72','dine_in'),(73,NULL,40.00,'','+38 (022) 222-22-22','new','аа','аа',NULL,'','08:00','card_online','2026-05-08 00:29:05','pending',NULL,'coffeetime_73','dine_in'),(74,NULL,40.00,'','+38 (011) 111-11-11','new','йй','йй',NULL,'','08:00','card_online','2026-05-08 00:31:23','pending',NULL,'coffeetime_74','dine_in'),(75,NULL,40.00,'','+38 (033) 333-33-33','cancelled','пп','пп',NULL,'','08:00','card_online','2026-05-08 00:41:04','pending',NULL,'coffeetime_75','dine_in'),(76,3,255.00,'','+38 (099) 150-89-70','done','Андрій','Слабіцький',NULL,'','08:00','cash_on_pickup','2026-05-08 03:26:31','pending',NULL,NULL,'dine_in'),(77,3,130.00,'','+38 (099) 150-89-70','cancelled','Андрій','Слабіцький',NULL,'','19:10','card_online','2026-05-09 18:54:33','failed',NULL,'coffeetime_77','dine_in'),(78,3,130.00,'','+38 (099) 150-89-70','new','Андрій','Слабіцький',NULL,'','19:15','card_online','2026-05-09 18:57:59','paid','2026-05-09 17:58:17','coffeetime_78','dine_in'),(79,3,30.00,'','+38 (099) 150-89-70','done','Андрій','Слабіцький',NULL,'','19:25','card_online','2026-05-09 19:10:10','paid','2026-05-09 18:10:41','coffeetime_79','dine_in'),(80,3,50.00,'','+38 (099) 150-89-70','cancelled','Андрій','Слабіцький',NULL,'','19:30','card_online','2026-05-09 19:11:05','failed',NULL,'coffeetime_80','dine_in'),(81,3,50.00,'','+38 (099) 150-89-70','cancelled','Андрій','Слабіцький',NULL,'','19:30','card_online','2026-05-09 19:12:59','failed',NULL,'coffeetime_81','dine_in'),(82,3,50.00,'','+38 (099) 150-89-70','cancelled','Андрій','Слабіцький',NULL,'','19:35','card_online','2026-05-09 19:16:23','failed',NULL,'coffeetime_82','dine_in'),(83,3,795.00,'','+38 (099) 150-89-70','done','Андрій','Слабіцький',NULL,'','19:35','cash_on_pickup','2026-05-09 19:18:01','pending',NULL,NULL,'dine_in'),(84,3,855.00,'','+38 (099) 150-89-70','done','Андрій','Слабіцький',NULL,'','08:00','card_online','2026-05-14 00:07:11','paid','2026-05-13 23:07:38','coffeetime_84','dine_in'),(85,3,65.00,'','+38 (099) 150-89-70','done','Андрій','Слабіцький',NULL,'','16:05','cash_on_pickup','2026-05-14 15:46:46','pending',NULL,NULL,'dine_in'),(86,NULL,30.00,'','+38 (022) 222-22-22','new','dsdas','dsas',NULL,'','08:00','cash_on_pickup','2026-05-15 02:29:31','pending',NULL,NULL,'dine_in'),(87,NULL,425.00,'','+38 (011) 111-11-11','new','ффф','ффффф','slabitskyia@gmail.com','','2026-05-17 12:45','cash_on_pickup','2026-05-17 02:18:26','pending',NULL,NULL,'dine_in'),(88,3,30.00,'','+38 (099) 150-89-70','new','Андрій','Слабіцький','slabitskyi1221@gmail.com','','2026-05-17 19:15','cash_on_pickup','2026-05-17 18:35:17','pending',NULL,NULL,'dine_in'),(89,NULL,200.00,'','+38 (068) 999-12-21','new','Андрій','Слабіцький','slabitAndrii@gmail.com','','2026-05-25 11:00','card_online','2026-05-22 01:25:34','pending',NULL,'coffeetime_89','dine_in'),(90,3,380.00,'','+38 (099) 150-89-70','new','Андрій','Слабіцький','slabitskyi1221@gmail.com','','2026-05-25 12:30','card_online','2026-05-22 23:36:11','pending',NULL,'coffeetime_90','dine_in'),(91,NULL,265.00,'','+38 (033) 333-33-33','new','ввв','вввв','','','2026-05-23 10:30','cash_on_pickup','2026-05-23 01:44:02','pending',NULL,NULL,'dine_in'),(92,3,195.00,'','+38 (099) 150-89-70','new','Андрій','Слабіцький','slabitskyi1221@gmail.com','','2026-05-25 15:30','card_online','2026-05-25 14:55:24','pending',NULL,'coffeetime_92','dine_in');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,'magicrush1221@gmail.com','7f33e1447dc38e10f5865b1819dc329f5bfee5f0a42e8979c2dfe42498d4b0a7','2025-05-31 20:58:42','2025-05-31 17:58:42'),(2,'magicrush1221@gmail.com','fd304a7d95d138a23d75b61ec4d9ce3c9f85b3e7eb86df5d663c571ed1d5065e','2025-05-31 21:00:34','2025-05-31 18:00:34'),(4,'magicrush1221@gmail.com','170d25be3927b5cd3e19c6bcac99f0dc81dbf89c7ffa8707d7f53a831b8ca028','2025-05-31 21:32:45','2025-05-31 18:32:45'),(5,'magicrush1221@gmail.com','36ed9f9655cf1858a51e5cf059c0c65ae5ca6918cf19b66e2fcc84a053c107f0','2025-06-04 17:52:22','2025-06-04 14:52:22'),(6,'magicrush1221@gmail.com','37e683a4b105329d0a90d62fa7b82caac3fd7a785a932824833fa3ff1798c247','2025-06-05 14:59:47','2025-06-05 11:59:47'),(9,'magicrush1221@gmail.com','6d85891d964c07e107fcadf26159d3d69d14b96c86852a972ffd7424cbea61cd','2025-06-05 15:04:45','2025-06-05 12:04:45'),(12,'slabitskyi1221@gmail.com','e5245562018c67155ba4d06ed5e3a07cdc3426728e4cbc01fc9c8e5f165f90ea','2026-05-17 12:54:55','2026-05-17 09:54:55');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pizza_items`
--

DROP TABLE IF EXISTS `pizza_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pizza_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_large` decimal(10,2) DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0,
  `sauce_type` enum('tomato','cream','bbq') NOT NULL DEFAULT 'tomato',
  `is_spicy` tinyint(1) NOT NULL DEFAULT 0,
  `has_size_choice` tinyint(1) NOT NULL DEFAULT 1,
  `ingredients_tags` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `popularity` (`popularity`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pizza_items`
--

LOCK TABLES `pizza_items` WRITE;
/*!40000 ALTER TABLE `pizza_items` DISABLE KEYS */;
INSERT INTO `pizza_items` VALUES (33,'Маргарита','Моцарела, оливкова олія','static/images/menu_items/pizza/item_6a0f60c8802219.16656909.webp',160.00,235.00,16,'tomato',0,1,'моцарела,оливкова олія'),(34,'Салямі','Моцарела, салямі, помідори, маслини','static/images/menu_items/default.jpg',185.00,260.00,1,'tomato',0,1,'салямі'),(35,'М\'ясна','Моцарела, шинка, бекон, салямі','static/images/menu_items/default.jpg',185.00,270.00,1,'tomato',0,1,'шинка,салямі'),(36,'Капрічоза','Моцарела, печериці, шинка, помідори','static/images/menu_items/pizza/item_6a0f60d01d65f5.09400982.webp',195.00,285.00,11,'tomato',0,1,'шинка,печериці'),(37,'З куркою','Моцарела, куряча грудка, печериці, маринована цибуля та огірок','static/images/menu_items/default.jpg',200.00,295.00,1,'tomato',0,1,'курка,цибуля,печериці'),(39,'Мега м\'ясна','Моцарела, шинка, бекон, салямі, куряча грудка, копчена ковбаска','static/images/menu_items/default.jpg',230.00,325.00,1,'tomato',0,1,'шинка,салямі,копчена ковбаска,курка'),(41,'Діабла','Моцарела, салямі, халапеньо, маслини','static/images/menu_items/default.jpg',210.00,275.00,0,'tomato',1,1,'салямі'),(42,'З картоплею Фрі','Моцарела, сосиски, картопля Фрі','static/images/menu_items/default.jpg',230.00,350.00,0,'tomato',0,1,'ковбаска'),(43,'Прошуто','Моцарела, прошуто, рукола, чері, пармезан','static/images/menu_items/default.jpg',250.00,360.00,0,'tomato',0,1,'шинка'),(45,'Весняна BBQ','Моцарела, салямі, бекон, кукурудза, помідори','static/images/menu_items/default.jpg',195.00,285.00,0,'bbq',0,1,'салямі'),(46,'Козацька BBQ','Моцарела, копчена ковбаска, печериці, маринована цибуля','static/images/menu_items/default.jpg',220.00,325.00,0,'bbq',0,1,'копчена ковбаска,цибуля,печериці'),(48,'4 сезони','Моцарела, помідори, шинка, салямі, печериці','static/images/menu_items/default.jpg',185.00,275.00,1,'tomato',0,1,'шинка,салямі,печериці'),(49,'Шинка гриби','Моцарела, печериці, шинка','static/images/menu_items/default.jpg',190.00,280.00,0,'cream',0,1,'шинка,печериці'),(50,'Цезар','Моцарела, куряча грудка, бекон, мікс салат, чері, пармезан','static/images/menu_items/default.jpg',245.00,345.00,0,'cream',0,1,'курка,шинка,салат'),(52,'Гавайська','Моцарела, куряча грудка, ананас, помідори','static/images/menu_items/default.jpg',205.00,315.00,0,'tomato',0,1,'курка,ананас'),(53,'Мисливська','Моцарела, копчена ковбаска, печериці, болгарський перець','static/images/menu_items/default.jpg',235.00,330.00,0,'cream',0,1,'копчена ковбаска,печериці'),(54,'4 види сиру','Моцарела, голандський, дорблю, пармезан','static/images/menu_items/default.jpg',240.00,350.00,0,'cream',0,1,'4 сири'),(55,'З лососем','Моцарела, вершковий сир, лосось','static/images/menu_items/default.jpg',260.00,380.00,0,'cream',0,1,'морепродукти'),(56,'Палермо','Моцарела, шинка, салямі, куряча грудка, кукурудза','static/images/menu_items/default.jpg',195.00,285.00,0,'tomato',0,1,'шинка,салямі,курка'),(60,'Салямі плюс','Моцарела, салямі, болгарський перець, маслини','static/images/menu_items/default.jpg',215.00,310.00,0,'tomato',0,1,'салямі'),(61,'Чікен','Моцарела, куряча грудка, печериці, маринована цибуля та огірок','static/images/menu_items/default.jpg',215.00,305.00,0,'tomato',0,1,'курка,цибуля,печериці'),(62,'Поло','Моцарела, куряча грудка, печериці, помідори','static/images/menu_items/default.jpg',215.00,305.00,0,'tomato',0,1,'курка,печериці'),(63,'Чілійська','Моцарела, копчена ковбаска, салямі, халапеньо, мариновані цибуля та огірок','static/images/menu_items/default.jpg',235.00,340.00,0,'tomato',1,1,'копчена ковбаска,салямі,цибуля'),(64,'Авокадочка','Моцарела, вершковий сир, авокадо, соус унагі','static/images/menu_items/default.jpg',230.00,330.00,0,'cream',0,1,'авокадо'),(65,'З тунцем','Моцарела, тунець, маринована цибуля, сік лимона','static/images/menu_items/default.jpg',280.00,440.00,0,'tomato',0,1,'морепродукти,цибуля'),(66,'Хлібні палички','Хрусткі хлібні палички з часниковим маслом','static/images/menu_items/default.jpg',45.00,55.00,0,'tomato',0,1,'');
/*!40000 ALTER TABLE `pizza_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `table_number` int(10) NOT NULL,
  `location` enum('indoor','terrace') NOT NULL,
  `reservation_datetime` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `client_phone` varchar(20) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_reservations_user` (`user_id`),
  CONSTRAINT `fk_reservations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservations`
--

LOCK TABLES `reservations` WRITE;
/*!40000 ALTER TABLE `reservations` DISABLE KEYS */;
INSERT INTO `reservations` VALUES (1,5,2,'indoor','2025-06-10 10:00:00','2025-06-01 03:08:59','+380991508970','Андрій'),(2,5,3,'indoor','2025-06-09 09:30:00','2025-06-01 03:34:07','+380991508970','Андрій');
/*!40000 ALTER TABLE `reservations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salad_items`
--

DROP TABLE IF EXISTS `salad_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salad_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL DEFAULT 'static/images/menu_items/default.jpg',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `popularity` (`popularity`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salad_items`
--

LOCK TABLES `salad_items` WRITE;
/*!40000 ALTER TABLE `salad_items` DISABLE KEYS */;
INSERT INTO `salad_items` VALUES (1,'Цезар з лососем','Мікс салат, копчений лосось, помідори чері, перепелині яйця, соус, грінки','static/images/menu_items/default.jpg',210.00,1),(2,'Грецький','Перець болгарський, помідори чері, сир Фета, маслини, огірок, оливкова олія','static/images/menu_items/default.jpg',125.00,0),(3,'Айсберг','Листя айсбергу, перець болгарський, куряча грудинка, кукурудза, медово-гірчична заправка','static/images/menu_items/default.jpg',130.00,0),(4,'Цезар з куркою','Мікс салат, куряча грудинка, бекон, помідори чері, перепелині яйця, соус, грінки','static/images/menu_items/default.jpg',180.00,0),(5,'Мисливський','Листя айсбергу, копчена ковбаска, мариновані огірок, картопля Фрі, чері, яйце перепелине, медово-гірчична заправка','static/images/menu_items/default.jpg',200.00,0),(6,'З тунцем','Мікс салат, тунець, кукурудза, чері, перепелине яйце, маслини, бальзамічний оцет','static/images/menu_items/default.jpg',210.00,0);
/*!40000 ALTER TABLE `salad_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sauces`
--

DROP TABLE IF EXISTS `sauces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sauces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `emoji` varchar(10) DEFAULT '?',
  `active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sauces`
--

LOCK TABLES `sauces` WRITE;
/*!40000 ALTER TABLE `sauces` DISABLE KEYS */;
INSERT INTO `sauces` VALUES (1,'Кетчуп',15.00,'🍅',1,1,'2026-04-29 09:56:55',''),(2,'Барбекю',15.00,'🍖',1,2,'2026-04-29 09:56:55',''),(3,'Сирний',20.00,'🧀',1,3,'2026-04-29 09:56:55',''),(4,'Кисло-солодкий',15.00,'🍯',1,4,'2026-04-29 09:56:55',''),(5,'Часниковий',15.00,'🧄',1,5,'2026-04-29 09:56:55',''),(6,'Гірчиця',10.00,'💛',1,6,'2026-04-29 09:56:55',''),(7,'Майонез',10.00,'🤍',1,7,'2026-04-29 09:56:55','');
/*!40000 ALTER TABLE `sauces` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_reviews`
--

DROP TABLE IF EXISTS `site_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rating` tinyint(4) DEFAULT 0,
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'approved',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_reviews`
--

LOCK TABLES `site_reviews` WRITE;
/*!40000 ALTER TABLE `site_reviews` DISABLE KEYS */;
INSERT INTO `site_reviews` VALUES (1,'Андрій','Все чудово','2025-06-03 19:41:58',4,'approved'),(2,'Ліхтарик','1','2025-06-03 19:54:11',5,'approved'),(3,'Ліхтарик1','TOP','2025-06-04 17:53:35',5,'approved'),(5,'Ліхтарик3','Все супер','2025-06-05 02:42:11',4,'approved'),(6,'Andrii Leoniev','Красота','2025-06-05 02:42:25',5,'approved');
/*!40000 ALTER TABLE `site_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_settings` (
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL DEFAULT '',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES ('about_founded_year','2016'),('about_menu_count','50'),('about_photo','static/images/main/about-photo.png'),('about_rating','4.8'),('about_text','Coffee Time – це затишне кафе в серці міста, де ми щодня готуємо свіжі десерти та каву з любов\'ю. Ніяких заморожених напівфабрикатів — тільки справжнє та смачне.'),('about_title','Місце, де час зупиняється'),('dessert_banner_btn','Дивитись десерти →'),('dessert_banner_desc','Мусові торти, еклери та макарони —\r\nготуємо кожного ранку зі свіжих інгредієнтів'),('dessert_banner_image',''),('dessert_banner_label','Щодня нове'),('dessert_banner_title','Десерт дня');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sushi_items`
--

DROP TABLE IF EXISTS `sushi_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sushi_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `subcategory` varchar(80) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `weight` varchar(20) NOT NULL DEFAULT '',
  `image` varchar(255) NOT NULL DEFAULT 'static/images/menu_items/default.jpg',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `popularity` (`popularity`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sushi_items`
--

LOCK TABLES `sushi_items` WRITE;
/*!40000 ALTER TABLE `sushi_items` DISABLE KEYS */;
INSERT INTO `sushi_items` VALUES (1,'Філадельфія з лососем','Філадельфія','Рис, норі, сир вершковий, огірок, лосось, японський майонез','275г','static/images/menu_items/default.jpg',240.00,0),(2,'Філадельфія з креветкою','Філадельфія','Рис, норі, сир вершковий, авокадо, креветка','270г','static/images/menu_items/default.jpg',230.00,1),(3,'Філадельфія з коп. лососем','Філадельфія','Рис, норі, сир вершковий, авокадо, копчений лосось','270г','static/images/menu_items/default.jpg',265.00,1),(4,'Філадельфія з тунцем','Філадельфія','Рис, норі, сир вершковий, огірок, тунець, соус спайсі','280г','static/images/menu_items/default.jpg',220.00,0),(5,'Філадельфія з вугрем','Філадельфія','Рис, норі, сир вершковий, авокадо, вугор, соус унагі, кунжут','285г','static/images/menu_items/default.jpg',255.00,0),(6,'Філадельфія з авокадо','Філадельфія','Рис, норі, сир вершковий, огірок, авокадо, унагі соус','280г','static/images/menu_items/default.jpg',195.00,1),(7,'Каліфорнія з коп. лососем','Каліфорнія','Рис, норі, коп. лосось, авокадо, огірок, сурімі, яп. майонез, кунжут, с. спайсі','235г','static/images/menu_items/default.jpg',220.00,0),(8,'Каліфорнія з вугрем','Каліфорнія','Рис, норі, вугор, авокадо, огірок, сурімі, японський майонез, ікра масаго','245г','static/images/menu_items/default.jpg',235.00,0),(9,'Каліфорнія з креветкою','Каліфорнія','Рис, норі, креветка, авокадо, огірок, сурімі, японський майонез, ікра тобіко','235г','static/images/menu_items/default.jpg',225.00,0),(10,'Запечений з лососем','Запечені роли','Рис, норі, лосось, вершковий сир, айсберг, сирна шапка, кунжут, соус унагі','265г','static/images/menu_items/default.jpg',245.00,0),(12,'Запечений з крабом','Запечені роли','Рис, норі, сурімі, вершковий сир, айсберг, сирна шапка, кунжут, соус унагі','265г','static/images/menu_items/default.jpg',195.00,0),(13,'Запечений з креветкою','Запечені роли','Рис, норі, креветка, сир вершковий, айсберг, сирна шапка, кунжут, соус унагі','265г','static/images/menu_items/default.jpg',220.00,0),(14,'Зелений дракон','Дракони','Рис, норі, сир вершковий, вугор, ікра масаго, огірок, авокадо, соус унагі, кунжут','285г','static/images/menu_items/default.jpg',235.00,0),(15,'Бронзовий дракон','Дракони','Рис, норі, сир вершковий, креветка, авокадо, лосось, ікра тобіко','270г','static/images/menu_items/default.jpg',285.00,0),(16,'Чорний дракон','Дракони','Рис, норі, сир вершковий, коп. лосось, авокадо, ікра масаго, вугор, унагі, кунжут','280г','static/images/menu_items/default.jpg',295.00,0),(17,'Лосось гриль','Авторські роли','Рис, норі, сир вершковий, авокадо, ікра масаго, лосось гриль, соус тіріраті','255г','static/images/menu_items/default.jpg',270.00,0),(18,'Чіз рол','Авторські роли','Рис, норі, сир вершковий, сир твердий, дорблю, чедер, пармезан','240г','static/images/menu_items/default.jpg',180.00,0),(19,'Крабовий чіз','Авторські роли','Рис, норі, сир вершковий, сурімі, огірок, чедер, соус теріякі','260г','static/images/menu_items/default.jpg',185.00,0),(20,'Осло','Авторські роли','Рис, норі, сир вершковий, лосось копчений, огірок, ікра тобіко','230г','static/images/menu_items/default.jpg',245.00,0),(21,'Токіо','Авторські роли','Рис, норі, сир вершковий, тунець, авокадо, ікра масаго','230г','static/images/menu_items/default.jpg',230.00,0),(22,'Ріо','Авторські роли','Рис, норі, сир вершковий, лосось гриль, огірок, авокадо, соус теріякі','280г','static/images/menu_items/default.jpg',260.00,0),(45,'Червоний дракон','Дракони','Рис, норі, авокадо, креветка, сир вершковий, тунець, ікра масаго, соус спайсі','275г','static/images/menu_items/default.jpg',270.00,0),(46,'Золотий дракон','Дракони','Рис, норі, огірок, вугор, сир вершковий, ікра масаго, креветка','270г','static/images/menu_items/default.jpg',250.00,0);
/*!40000 ALTER TABLE `sushi_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sushi_sets`
--

DROP TABLE IF EXISTS `sushi_sets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sushi_sets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `weight` varchar(20) NOT NULL DEFAULT '',
  `image` varchar(255) NOT NULL DEFAULT 'static/images/menu_items/default.jpg',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pieces` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `popularity` int(11) NOT NULL DEFAULT 0,
  `pieces_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `popularity` (`popularity`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sushi_sets`
--

LOCK TABLES `sushi_sets` WRITE;
/*!40000 ALTER TABLE `sushi_sets` DISABLE KEYS */;
INSERT INTO `sushi_sets` VALUES (1,'Сет Каліфорнія','Каліфорнія з коп. лососем, Каліфорнія з вугрем, Каліфорнія з креветкою','725г','static/images/menu_items/default.jpg',650.00,24,0,0),(2,'Сет Теплий вечір','Запечений з лососем, Запечений з креветкою, Запечений з крабом','775г','static/images/menu_items/default.jpg',610.00,24,1,0),(3,'Сет Грильовані','Лосось гриль, Ріо, Запечений з лососем','810г','static/images/menu_items/default.jpg',730.00,24,0,0),(4,'Сет Гурман','Чіз рол, Крабовий чіз, Філадельфія з авокадо','780г','static/images/menu_items/default.jpg',530.00,24,0,0),(5,'Сет Філадельфія','Філадельфія з лososем, Філадельфія з коп. лososем, Філадельфія з вугрем, Філадельфія з тунцем, Філадельфія з креветкою, Філадельфія з авокадо','1650г','static/images/menu_items/default.jpg',1250.00,48,0,0),(6,'Сет Дракон','Бронзовий дракон, Чорний дракон, Червоний дракон, Золотий дракон, Зелений дракон','1385г','static/images/menu_items/default.jpg',1250.00,40,0,0),(7,'Сет Особливий','Філадельфія з лososем, Філадельфія з авокадо, Золотий дракон, Каліфорнія з коп. лososем, Крабовий чіз','1330г','static/images/menu_items/default.jpg',1040.00,40,0,0),(9,'Сет Секрет шефа','Осло, Запечений з креветкою, Ріо, Токіо','985г','static/images/menu_items/default.jpg',900.00,32,0,0);
/*!40000 ALTER TABLE `sushi_sets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `client_id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(60) NOT NULL,
  `email` varchar(30) NOT NULL,
  `password` varchar(60) NOT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `client_surname` varchar(255) DEFAULT NULL,
  `client_PhoneNumber` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (3,'daro','slabitskyi1221@gmail.com','$2y$10$AoiNmgD97PcXMiRpRpVJzuw6T4Bd9XHzWP7YJDnfBQlrwDOhWrSEK','Андрій','Слабіцький','+380991508970','2025-05-13 21:18:12'),(4,'DARØ','slabik@gmail.com','$2y$10$hGu9mRrpTwInzMwgMAESSOzyRPL5sSKYLMY/m7YoHYSuNFx7FGicy','Андрій','Слабіцький','+380991508970','2025-05-14 20:46:33'),(5,'Ander','magicrush1221@gmail.com','$2y$10$vnDi1kpTi8lBs2xscvw6pe8IzoaHiyGDHYgDsFdnkMJRypeo.CbcK',NULL,NULL,NULL,'2025-05-31 17:58:14'),(6,'admin','admin@coffeetime.local','$2y$12$PfhF9VyB.HEkc4M.UtJudeIzB4HZ2DE69YLJZ054SNv1ZW1EOwHRi','Admin','',NULL,'2026-04-16 21:54:03');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-07 18:07:12
