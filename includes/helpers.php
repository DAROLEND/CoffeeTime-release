<?php

if (!function_exists('h')) {
    function h($str): string {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        if (session_status() === PHP_SESSION_NONE) session_start();

        $isAjax    = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                     strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected  = $_SESSION['csrf_token'] ?? '';

        if (empty($expected)) {
            csrf_token();
            if ($isAjax) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Сесія закінчилась. Оновіть сторінку.']);
                exit;
            }
            $_SESSION['flash_error'] = 'Сесія закінчилась. Спробуйте ще раз.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }

        if (empty($submitted) || !hash_equals($expected, $submitted)) {
            if ($isAjax) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Помилка безпеки. Оновіть сторінку.']);
                exit;
            }
            http_response_code(403);
            $_SESSION['flash_error'] = 'Помилка безпеки. Спробуйте ще раз.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }
    }
}

if (!function_exists('get_cafe_schedule')) {
    // 1=Пн … 7=Нд
    function get_cafe_schedule(): array {
        return [
            1 => ['open' => '08:00', 'close' => '20:00'],
            2 => ['open' => '08:00', 'close' => '20:00'],
            3 => ['open' => '08:00', 'close' => '20:00'],
            4 => ['open' => '08:00', 'close' => '20:00'],
            5 => ['open' => '08:00', 'close' => '20:00'],
            6 => ['open' => '10:00', 'close' => '20:00'],
            7 => ['open' => '12:00', 'close' => '20:00'],
        ];
    }
}

if (!function_exists('get_next_available_time')) {
    function get_next_available_time(): ?array {
        $schedule = get_cafe_schedule();
        $now      = new DateTime('now', new DateTimeZone('Europe/Kiev'));

        for ($i = 0; $i < 8; $i++) {
            $check = clone $now;
            if ($i > 0) {
                $check->modify("+{$i} day");
                $check->setTime(0, 0, 0);
            }

            $dow = (int)$check->format('N');
            $day = $schedule[$dow];

            [$open_h,  $open_m]  = explode(':', $day['open']);
            [$close_h, $close_m] = explode(':', $day['close']);

            $open  = clone $check; $open->setTime((int)$open_h,  (int)$open_m,  0);
            $close = clone $check; $close->setTime((int)$close_h, (int)$close_m, 0);

            $earliest = clone $now;
            $earliest->modify('+15 minutes');
            $rem = (int)$earliest->format('i') % 5;
            if ($rem !== 0) $earliest->modify('+' . (5 - $rem) . ' minutes');
            $earliest->setTime((int)$earliest->format('H'), (int)$earliest->format('i'), 0);

            if ($i === 0) {
                if ($now < $close && $earliest < $close) {
                    $t = $earliest < $open ? $open : $earliest;
                    return ['time' => $t->format('H:i'), 'date' => 'сьогодні',
                            'date_label' => '', 'is_today' => true, 'datetime' => $t];
                }
            } else {
                return ['time' => $day['open'], 'date' => $check->format('d.m'),
                        'date_label' => $i === 1 ? 'завтра' : $check->format('d.m'),
                        'is_today' => false, 'datetime' => $open];
            }
        }

        return null;
    }
}

if (!function_exists('is_cafe_open_at')) {
    function is_cafe_open_at(DateTime $dt): bool {
        $schedule = get_cafe_schedule();
        $dow      = (int)$dt->format('N');
        if (!isset($schedule[$dow])) return false;

        [$oh, $om] = explode(':', $schedule[$dow]['open']);
        [$ch, $cm] = explode(':', $schedule[$dow]['close']);

        $open  = clone $dt; $open->setTime((int)$oh, (int)$om, 0);
        $close = clone $dt; $close->setTime((int)$ch, (int)$cm, 0);

        return $dt >= $open && $dt < $close;
    }
}

if (!function_exists('item_img')) {
    // Returns empty string for blank/default paths so callers can do `if ($src)`
    function item_img(string $raw, string $prefix = '../'): string {
        $raw = trim($raw);
        if ($raw === '' || $raw === 'static/images/menu_items/default.jpg') return '';
        return $prefix . ltrim($raw, '/');
    }
}

if (!function_exists('save_cropped_image')) {
    function save_cropped_image(string $b64, string $destPath): string {
        if (!preg_match('/^data:image\/(jpeg|png|webp);base64,(.+)$/s', $b64, $m)) return '';
        $data = base64_decode($m[2]);
        if ($data === false || strlen($data) < 100) return '';
        $ext  = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $dest = preg_replace('/\.\w+$/', '.' . $ext, $destPath);
        return file_put_contents($dest, $data) !== false ? $ext : '';
    }
}
