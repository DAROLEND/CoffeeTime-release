<?php
function is_super(): bool {
    return ($_SESSION['admin_role'] ?? '') === 'super';
}

function has_perm(string $perm): bool {
    if (is_super()) return true;
    return in_array($perm, $_SESSION['admin_perms'] ?? [], true);
}

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

function all_perms(): array {
    return [
        'orders_view'  => 'Переглядати замовлення',
        'orders_edit'  => 'Змінювати статус замовлень',
        'products'     => 'Управляти товарами',
        'content'      => 'Редагувати контент (слайдер, про нас, галерея)',
        'reviews'      => 'Управляти відгуками',
    ];
}
