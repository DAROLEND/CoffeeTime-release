<?php
/**
 * Permission helpers.
 * Relies on $_SESSION['admin_role'] and $_SESSION['admin_perms'] (array)
 * set by auth_check.php after successful login verification.
 *
 * Roles:   'super' | 'staff'
 * Perms:   'orders_view' | 'orders_edit' | 'products' | 'content' | 'reviews'
 */

function is_super(): bool {
    return ($_SESSION['admin_role'] ?? '') === 'super';
}

function has_perm(string $perm): bool {
    if (is_super()) return true;
    return in_array($perm, $_SESSION['admin_perms'] ?? [], true);
}

/**
 * Hard-gate: redirect to dashboard with error if permission missing.
 * Call at the top of any admin page after auth_check.php.
 */
function require_perm(string $perm): void {
    if (!has_perm($perm)) {
        $_SESSION['admin_flash']      = 'У вас немає доступу до цього розділу.';
        $_SESSION['admin_flash_type'] = 'error';
        header('Location: ../admin/dashboard.php');
        exit;
    }
}

function require_super(): void {
    if (!is_super()) {
        $_SESSION['admin_flash']      = 'Цей розділ доступний тільки головному адміну.';
        $_SESSION['admin_flash_type'] = 'error';
        header('Location: ../admin/dashboard.php');
        exit;
    }
}

/** All defined permissions with human labels */
function all_perms(): array {
    return [
        'orders_view'  => 'Переглядати замовлення',
        'orders_edit'  => 'Змінювати статус замовлень',
        'products'     => 'Управляти товарами',
        'content'      => 'Редагувати контент (слайдер, про нас, галерея)',
        'reviews'      => 'Управляти відгуками',
    ];
}
