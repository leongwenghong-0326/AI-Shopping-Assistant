<?php
/**
 * Barcode lookup API — returns product from MySQL only.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = read_json_body();
if ($input === []) {
    $input = $_POST;
}

$barcode = normalize_barcode((string) ($input['barcode'] ?? ''));

if ($barcode === '' || !is_valid_barcode($barcode)) {
    json_response([
        'success' => false,
        'message' => 'Invalid barcode.',
    ], 400);
}

try {
    $product = find_product_by_barcode($barcode);

    if (!$product) {
        json_response([
            'success' => true,
            'found' => false,
            'message' => 'Barcode detected, but this product is not in the catalog.',
            'barcode' => $barcode,
        ]);
    }

    json_response([
        'success' => true,
        'found' => true,
        'message' => 'Product found.',
        'product' => product_public_fields($product),
    ]);
} catch (Throwable $e) {
    error_log('barcode API error: ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Unable to look up barcode. Please try again.',
    ], 500);
}
