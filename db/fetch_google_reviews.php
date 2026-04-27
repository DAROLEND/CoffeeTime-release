<?php
/**
 * Отримання відгуків з Google Places API + збереження в кеш-файл.
 *
 * ЯК НАЛАШТУВАТИ:
 * 1. Зайди на https://console.cloud.google.com/
 * 2. Створи проект → "APIs & Services" → "Enable APIs" → увімкни "Places API (New)"
 * 3. "Credentials" → "Create credentials" → "API key" → скопіюй ключ
 * 4. Знайди Place ID свого кафе:
 *    https://developers.google.com/maps/documentation/places/web-service/place-id
 *    Або просто: відкрий Google Maps → знайди кафе → в URL буде 1s0x...:0x...
 *    Або перейди: https://www.google.com/maps/place/Coffee+Time/@49.07,26.2
 *    і в URL після /place/ буде place_id в параметрі
 * 5. Встав значення нижче і запусти цей файл один раз:
 *    php fetch_google_reviews.php
 *    або відкрий у браузері: http://localhost/CoffeeTime-release/db/fetch_google_reviews.php
 */

define('GOOGLE_API_KEY', 'ВАШ_API_КЛЮЧ_ТУТ');
define('PLACE_ID',       'ВАШ_PLACE_ID_ТУТ');  // напр. ChIJ...

// ---- не міняй нижче ----

$url = 'https://places.googleapis.com/v1/places/' . PLACE_ID
     . '?fields=reviews,rating,userRatingCount'
     . '&key=' . GOOGLE_API_KEY
     . '&languageCode=uk';   // відгуки українською якщо є

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['X-Goog-Api-Key: ' . GOOGLE_API_KEY],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo "Помилка API: HTTP $httpCode\n";
    echo $response ?: 'Немає відповіді';
    exit;
}

$data = json_decode($response, true);

if (empty($data['reviews'])) {
    echo "Відгуків не знайдено. Відповідь:\n";
    print_r($data);
    exit;
}

// Перетворюємо у формат, сумісний з index.php
$reviews = [];
foreach ($data['reviews'] as $r) {
    if (($r['rating'] ?? 0) < 4) continue; // тільки ≥ 4 зірки
    $reviews[] = [
        'name'   => $r['authorAttribution']['displayName'] ?? 'Гість',
        'text'   => $r['text']['text']                     ?? '',
        'rating' => (int)($r['rating']                     ?? 5),
        'role'   => 'Google відгук',
    ];
}

// Зберігаємо кеш на 24 години
$cacheFile = __DIR__ . '/google_reviews_cache.json';
file_put_contents($cacheFile, json_encode($reviews, JSON_UNESCAPED_UNICODE));

echo "Готово! Збережено " . count($reviews) . " відгуків у $cacheFile\n";
print_r($reviews);
