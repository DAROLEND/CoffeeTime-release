<?php
/**
 * Coffee Time — Security helpers
 * Include this file on every page that outputs user data or processes forms.
 */

/* ── XSS: safe echo ────────────────────────────────────────────────────────── */
if (!function_exists('h')) {
    function h($str): string {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/* ── CSRF helpers ──────────────────────────────────────────────────────────── */

if (!function_exists('csrf_token')) {
    /**
     * Return the current CSRF token (creates one if absent).
     */
    function csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Return a hidden <input> with the CSRF token.
     */
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    }
}

/* ── Café schedule helpers ─────────────────────────────────────────────────── */

if (!function_exists('get_cafe_schedule')) {
    /**
     * Return the weekly schedule keyed by ISO day number (1=Mon … 7=Sun).
     */
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
    /**
     * Return the next bookable time slot (minimum +15 min from now, Kyiv tz).
     * Returns an array with keys: time, date, date_label, is_today, datetime.
     * Returns null if nothing available within 8 days.
     */
    function get_next_available_time(): ?array {
        $schedule = get_cafe_schedule();
        $now      = new DateTime('now', new DateTimeZone('Europe/Kiev'));

        for ($i = 0; $i < 8; $i++) {
            $check = clone $now;
            if ($i > 0) {
                $check->modify("+{$i} day");
                $check->setTime(0, 0, 0);
            }

            $dow          = (int)$check->format('N');
            $day_schedule = $schedule[$dow];

            [$open_h,  $open_m]  = explode(':', $day_schedule['open']);
            [$close_h, $close_m] = explode(':', $day_schedule['close']);

            $open  = clone $check; $open->setTime((int)$open_h,  (int)$open_m,  0);
            $close = clone $check; $close->setTime((int)$close_h, (int)$close_m, 0);

            $earliest = clone $now;
            $earliest->modify('+15 minutes');
            // Round up to next 5-minute slot
            $rem = (int)$earliest->format('i') % 5;
            if ($rem !== 0) $earliest->modify('+' . (5 - $rem) . ' minutes');
            $earliest->setTime((int)$earliest->format('H'), (int)$earliest->format('i'), 0);

            if ($i === 0) {
                if ($now < $close && $earliest < $close) {
                    $result_time = $earliest < $open ? $open : $earliest;
                    return [
                        'time'       => $result_time->format('H:i'),
                        'date'       => 'сьогодні',
                        'date_label' => '',
                        'is_today'   => true,
                        'datetime'   => $result_time,
                    ];
                }
            } else {
                return [
                    'time'       => $day_schedule['open'],
                    'date'       => $check->format('d.m'),
                    'date_label' => $i === 1 ? 'завтра' : $check->format('d.m'),
                    'is_today'   => false,
                    'datetime'   => $open,
                ];
            }
        }

        return null;
    }
}

if (!function_exists('is_cafe_open_at')) {
    /**
     * Return true if the café is open at the given DateTime (Kyiv tz assumed).
     */
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

if (!function_exists('verify_csrf')) {
    /**
     * Verify CSRF token on POST requests.
     * On failure: redirects back with a session error message instead of a raw die().
     */
    function verify_csrf(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $expected  = $_SESSION['csrf_token'] ?? '';

        if (empty($expected)) {
            csrf_token(); // generate fresh token for next attempt
            if ($isAjax) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Сесія закінчилась. Оновіть сторінку.']);
                exit;
            }
            http_response_code(403);
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
