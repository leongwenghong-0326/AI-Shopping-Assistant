<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';
require dirname(__DIR__) . '/includes/db.php';

$pdo = db();
$images = $pdo->query('SELECT image_path FROM products')->fetchAll(PDO::FETCH_COLUMN);

foreach ($images as $img) {
    if (!$img) {
        continue;
    }
    $full = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $img);
    if (is_file($full)) {
        unlink($full);
        echo "Deleted image: {$img}\n";
    }
}

$n = $pdo->exec('DELETE FROM products');
$left = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

echo "Removed products: {$n}\n";
echo "Remaining products: {$left}\n";
