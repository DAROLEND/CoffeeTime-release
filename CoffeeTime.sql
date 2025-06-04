-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Час створення: Чрв 04 2025 р., 10:41
-- Версія сервера: 10.4.28-MariaDB
-- Версія PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База даних: `CoffeeTime`
--

-- --------------------------------------------------------

--
-- Структура таблиці `coffee_items`
--

CREATE TABLE `coffee_items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `coffee_items`
--

INSERT INTO `coffee_items` (`id`, `name`, `description`, `image`, `price`, `popularity`) VALUES
(1, 'Еспресо', 'Сильний та ароматний еспресо', 'static/images/categories/coffee_category.jpg', 50.00, 0),
(2, 'Капучино', 'Кремовий капучино з молоком', 'static/images/categories/coffee_category.jpg', 60.00, 13),
(3, 'Латте', 'Латте з ніжним молоком', 'static/images/categories/coffee_category.jpg', 65.00, 0);

-- --------------------------------------------------------

--
-- Структура таблиці `cold_drink_items`
--

CREATE TABLE `cold_drink_items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `cold_drink_items`
--

INSERT INTO `cold_drink_items` (`id`, `name`, `description`, `image`, `price`, `popularity`) VALUES
(1, 'Лимонад', 'Смачний лимонад на основі цитрусових', 'static/images/menu_items/lemonade.png', 50.00, 0),
(2, 'Мохіто', 'Охолоджений мохіто з м’ятою', 'static/images/menu_items/mohito.jpg', 60.00, 0),
(3, 'Смузі', 'Фрукти в пікантному смузі', 'static/images/menu_items/smoothie.png', 65.00, 5);

-- --------------------------------------------------------

--
-- Структура таблиці `dessert_items`
--

CREATE TABLE `dessert_items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `dessert_items`
--

INSERT INTO `dessert_items` (`id`, `name`, `description`, `image`, `price`, `popularity`) VALUES
(1, 'Торт', 'Ніжний торт з шоколадною глазур’ю', 'static/images/categories/dessert.jpg', 90.00, 0),
(2, 'Пиріжок', 'Пиріжок з фруктовою начинкою', 'static/images/categories/dessert.jpg', 50.00, 0),
(3, 'Мафін', 'Мафін з ягодами та шоколадом', 'static/images/categories/dessert.jpg', 45.00, 1);

-- --------------------------------------------------------

--
-- Структура таблиці `fast_food_items`
--

CREATE TABLE `fast_food_items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `fast_food_items`
--

INSERT INTO `fast_food_items` (`id`, `name`, `description`, `image`, `price`, `popularity`) VALUES
(1, 'Бургер', 'Соковитий бургер з м’ясом', 'static/images/menu_items/burger.png', 120.00, 1),
(2, 'Фрі', 'Хрустка картопля фрі', 'static/images/menu_items/fries_potato.png', 40.00, 0),
(3, 'Хот-Дог', 'Класичний французький хот-дог', 'static/images/menu_items/hot-dog.png', 70.00, 4);

-- --------------------------------------------------------

--
-- Структура таблиці `giftcards`
--

CREATE TABLE `giftcards` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `price` int(11) NOT NULL,
  `image` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `giftcards`
--

INSERT INTO `giftcards` (`id`, `title`, `price`, `image`) VALUES
(1, 'Сертифікат 500 ₴', 500, 'static/images/main/logo.png'),
(2, 'Сертифікат 1000 ₴', 1000, 'static/images/main/logo.png'),
(3, 'Сертифікат 2000 ₴', 2000, 'static/images/main/logo.png');

-- --------------------------------------------------------

--
-- Структура таблиці `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_address` varchar(255) DEFAULT '',
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_surname` varchar(100) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `ready_time` varchar(20) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `total`, `delivery_address`, `phone`, `status`, `customer_name`, `customer_surname`, `comment`, `ready_time`, `payment_method`, `created_at`) VALUES
(6, 3, 230.00, '', '+380991508970', 'pending', 'Андрій', 'Слаб', '', '10:30', 'cash_on_pickup', '2025-05-14 09:58:02'),
(7, 4, 240.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '19:30', 'cash_on_pickup', '2025-05-15 15:56:17'),
(8, 4, 630.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '19:00', 'cash_on_pickup', '2025-05-26 15:22:12'),
(9, 4, 60.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '11:00', 'cash_on_pickup', '2025-05-31 04:11:12'),
(10, 4, 60.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '11:00', 'cash_on_pickup', '2025-05-31 04:21:51'),
(11, 4, 60.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '12:00', 'cash_on_pickup', '2025-05-31 04:22:04'),
(12, 4, 60.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '12:00', 'cash_on_pickup', '2025-05-31 04:24:49'),
(13, 5, 120.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '12:30', 'cash_on_pickup', '2025-06-01 18:46:09'),
(14, 5, 280.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '19:30', 'cash_on_pickup', '2025-06-01 18:47:12'),
(15, 5, 280.00, '', '+380991508970', 'pending', 'А', 'Слаб', '', '19:30', 'cash_on_pickup', '2025-06-01 18:54:24'),
(16, 5, 325.00, '', '+380991508970', 'pending', 'А', 'Слаб', '', '19:30', 'cash_on_pickup', '2025-06-01 18:54:57'),
(17, 5, 220.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '18:30', 'cash_on_pickup', '2025-06-01 19:02:15'),
(18, 5, 360.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '19:30', 'cash_on_pickup', '2025-06-01 19:05:24'),
(19, 5, 420.00, '', '+380991508970', 'pending', 'Андрій', 'Слабіцький', '', '19:30', 'cash_on_pickup', '2025-06-01 19:08:44'),
(20, 5, 475.00, '', '+380991508970', 'pending', 'Андрій', 'Слаб', '', '19:30', 'cash_on_pickup', '2025-06-01 19:13:13');

-- --------------------------------------------------------

--
-- Структура таблиці `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 12, 2, 1, 60.00),
(2, 13, 1, 1, 120.00),
(3, 14, 3, 4, 70.00),
(4, 15, 3, 4, 70.00),
(5, 16, 3, 5, 65.00),
(6, 17, 3, 1, 220.00),
(7, 18, 2, 6, 60.00),
(8, 19, 2, 7, 60.00),
(9, 20, 2, 1, 210.00),
(10, 20, 3, 1, 220.00),
(11, 20, 3, 1, 45.00);

-- --------------------------------------------------------

--
-- Структура таблиці `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(1, 'magicrush1221@gmail.com', '7f33e1447dc38e10f5865b1819dc329f5bfee5f0a42e8979c2dfe42498d4b0a7', '2025-05-31 20:58:42', '2025-05-31 17:58:42'),
(2, 'magicrush1221@gmail.com', 'fd304a7d95d138a23d75b61ec4d9ce3c9f85b3e7eb86df5d663c571ed1d5065e', '2025-05-31 21:00:34', '2025-05-31 18:00:34'),
(4, 'magicrush1221@gmail.com', '170d25be3927b5cd3e19c6bcac99f0dc81dbf89c7ffa8707d7f53a831b8ca028', '2025-05-31 21:32:45', '2025-05-31 18:32:45');

-- --------------------------------------------------------

--
-- Структура таблиці `pizza_items`
--

CREATE TABLE `pizza_items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `popularity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `pizza_items`
--

INSERT INTO `pizza_items` (`id`, `name`, `description`, `image`, `price`, `popularity`) VALUES
(1, 'Маргарита', 'Класична піца з моцарелою', 'static/images/menu_items/pizza2.png', 180.00, 0),
(2, 'Пепероні', 'Піца з пепероні та сиром', 'static/images/menu_items/pizza1.png', 210.00, 1),
(3, 'Гавайська', 'Піца з ананасами та куркою', 'static/images/menu_items/pizza.png', 220.00, 2);

-- --------------------------------------------------------

--
-- Структура таблиці `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `table_number` int(10) NOT NULL,
  `location` enum('indoor','terrace') NOT NULL,
  `reservation_datetime` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `client_phone` varchar(20) NOT NULL,
  `client_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `table_number`, `location`, `reservation_datetime`, `created_at`, `client_phone`, `client_name`) VALUES
(1, 5, 2, 'indoor', '2025-06-10 10:00:00', '2025-06-01 03:08:59', '+380991508970', 'Андрій'),
(2, 5, 3, 'indoor', '2025-06-09 09:30:00', '2025-06-01 03:34:07', '+380991508970', 'Андрій');

-- --------------------------------------------------------

--
-- Структура таблиці `site_reviews`
--

CREATE TABLE `site_reviews` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rating` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `site_reviews`
--

INSERT INTO `site_reviews` (`id`, `name`, `text`, `created_at`, `rating`) VALUES
(1, 'Андрій', 'Все чудово', '2025-06-03 19:41:58', 4),
(2, 'Ліхтарик', '1', '2025-06-03 19:54:11', 5);

-- --------------------------------------------------------

--
-- Структура таблиці `users`
--

CREATE TABLE `users` (
  `client_id` int(11) NOT NULL,
  `login` varchar(60) NOT NULL,
  `email` varchar(30) NOT NULL,
  `password` varchar(60) NOT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `client_surname` varchar(255) DEFAULT NULL,
  `client_PhoneNumber` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `users`
--

INSERT INTO `users` (`client_id`, `login`, `email`, `password`, `client_name`, `client_surname`, `client_PhoneNumber`, `created_at`) VALUES
(3, 'daro', 'slabitskyi1221@gmail.com', '$2y$10$Rom1o9RxOlwvGrtp773hLOwGDtYFOv16eIt20V2O0TBoVhlSSkO26', 'Андрій', 'Слаб', '+380991508970', '2025-05-13 21:18:12'),
(4, 'DARØ', 'slabik@gmail.com', '$2y$10$hGu9mRrpTwInzMwgMAESSOzyRPL5sSKYLMY/m7YoHYSuNFx7FGicy', 'Андрій', 'Слабіцький', '+380991508970', '2025-05-14 20:46:33'),
(5, 'Ander', 'magicrush1221@gmail.com', '$2y$10$BO.6F2lWGJhyZRL.iacw3eNpIWmMWUvKp9hmMvorFT0JLaanLsFQ2', NULL, NULL, NULL, '2025-05-31 17:58:14');

--
-- Індекси збережених таблиць
--

--
-- Індекси таблиці `coffee_items`
--
ALTER TABLE `coffee_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `popularity` (`popularity`);

--
-- Індекси таблиці `cold_drink_items`
--
ALTER TABLE `cold_drink_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `popularity` (`popularity`);

--
-- Індекси таблиці `dessert_items`
--
ALTER TABLE `dessert_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `popularity` (`popularity`);

--
-- Індекси таблиці `fast_food_items`
--
ALTER TABLE `fast_food_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `popularity` (`popularity`);

--
-- Індекси таблиці `giftcards`
--
ALTER TABLE `giftcards`
  ADD PRIMARY KEY (`id`);

--
-- Індекси таблиці `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Індекси таблиці `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Індекси таблиці `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Індекси таблиці `pizza_items`
--
ALTER TABLE `pizza_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `popularity` (`popularity`);

--
-- Індекси таблиці `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_reservations_user` (`user_id`);

--
-- Індекси таблиці `site_reviews`
--
ALTER TABLE `site_reviews`
  ADD PRIMARY KEY (`id`);

--
-- Індекси таблиці `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`client_id`);

--
-- AUTO_INCREMENT для збережених таблиць
--

--
-- AUTO_INCREMENT для таблиці `coffee_items`
--
ALTER TABLE `coffee_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблиці `cold_drink_items`
--
ALTER TABLE `cold_drink_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблиці `dessert_items`
--
ALTER TABLE `dessert_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблиці `fast_food_items`
--
ALTER TABLE `fast_food_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблиці `giftcards`
--
ALTER TABLE `giftcards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблиці `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT для таблиці `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблиці `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблиці `pizza_items`
--
ALTER TABLE `pizza_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблиці `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблиці `site_reviews`
--
ALTER TABLE `site_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблиці `users`
--
ALTER TABLE `users`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Обмеження зовнішнього ключа збережених таблиць
--

--
-- Обмеження зовнішнього ключа таблиці `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`client_id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`client_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
