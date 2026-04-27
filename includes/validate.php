<?php
/**
 * Coffee Time — Input validation & sanitization helpers
 */

if (!function_exists('sanitize_string')) {
    function sanitize_string(mixed $val, int $max = 255): string {
        $str = trim(strip_tags((string)$val));
        return mb_substr($str, 0, $max, 'UTF-8');
    }
}

if (!function_exists('validate_email')) {
    function validate_email(mixed $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('validate_phone')) {
    function validate_phone(mixed $phone): string|false {
        $digits = preg_replace('/[^0-9]/', '', (string)$phone);
        if (preg_match('/^380(\d{9})$/', $digits, $m) ||
            preg_match('/^0(\d{9})$/',   $digits, $m)) {
            return '+380' . $m[1];
        }
        return false;
    }
}

if (!function_exists('validate_price')) {
    function validate_price(mixed $price): bool {
        if (!is_numeric($price)) return false;
        $f = (float)$price;
        return $f > 0 && $f < 100_000;
    }
}

if (!function_exists('validate_int')) {
    function validate_int(mixed $val, int $min = 1, int $max = PHP_INT_MAX): bool {
        $v = filter_var($val, FILTER_VALIDATE_INT);
        return $v !== false && $v >= $min && $v <= $max;
    }
}

if (!function_exists('safe_int')) {
    function safe_int(mixed $val, int $min = 0, int $max = PHP_INT_MAX): int {
        $v = (int)$val;
        return max($min, min($max, $v));
    }
}
