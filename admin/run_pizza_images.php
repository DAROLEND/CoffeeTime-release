<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/auth_check.php';
require_super();

$sql = file_get_contents(__DIR__ . '/../db/migrations/pizza_images.sql');
$queries = array_filter(array_map('trim', explode(';', $sql)));

$ok = 0; $fail = 0;
foreach ($queries as $q) {
    if (!$q || str_starts_with($q, '--')) continue;
    if ($conn->query($q)) $ok++;
    else { $fail++; error_log('[migration] ' . $conn->error . ' | ' . $q); }
}

header('Content-Type: text/plain; charset=utf-8');
echo "Done. Updated $ok queries OK, $fail failed.\n";
echo "You can delete this file now: admin/run_pizza_images.php\n";
