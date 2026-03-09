-- ============================================================
-- Виправлення шляхів до зображень для Next.js
-- Було:  static/images/menu_items/coffee/espresso.png
-- Стало: images/menu_items/coffee/espresso.png
-- ============================================================

UPDATE `coffee_items`     SET image = REPLACE(image, 'static/', '') WHERE image LIKE 'static/%';
UPDATE `cold_drink_items` SET image = REPLACE(image, 'static/', '') WHERE image LIKE 'static/%';
UPDATE `dessert_items`    SET image = REPLACE(image, 'static/', '') WHERE image LIKE 'static/%';
UPDATE `fast_food_items`  SET image = REPLACE(image, 'static/', '') WHERE image LIKE 'static/%';
UPDATE `pizza_items`      SET image = REPLACE(image, 'static/', '') WHERE image LIKE 'static/%';
UPDATE `giftcards`        SET image = REPLACE(image, 'static/', '') WHERE image LIKE 'static/%';

-- ============================================================
-- Розширення email поля (було varchar(30) — замало)
-- ============================================================
ALTER TABLE `users` MODIFY `email` varchar(255) NOT NULL;
