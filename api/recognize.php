<?php
/**
 * AI image recognition API.
 * Browser → PHP → AI Provider → PHP catalog match → Browser
 * Prices always come from MySQL, never from the AI.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/ai.php';
require_once dirname(__DIR__) . '/includes/images.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = read_json_body();
$imageData = (string) ($input['image'] ?? '');

if ($imageData === '') {
    json_response([
        'success' => false,
        'message' => 'No image provided.',
    ], 400);
}

$tempPath = null;

try {
    $saved = save_temp_capture_image($imageData);
    if (!$saved['ok']) {
        json_response([
            'success' => false,
            'message' => $saved['error'] ?? 'Unable to process image.',
        ], 400);
    }

    $tempPath = $saved['path'];
    $result = recognize_product_image($tempPath);

    if (!$result['ok']) {
        json_response([
            'success' => false,
            'message' => $result['error'] ?? 'Unable to identify the product. Please try another photo.',
            'confidence' => $result['confidence'] ?? null,
        ]);
    }

    $product = product_public_fields($result['product']);
    $confidence = (float) ($result['confidence'] ?? 0);

    json_response([
        'success' => true,
        'found' => true,
        'message' => 'Product found.',
        'confidence' => $confidence,
        'match_method' => $result['method'] ?? null,
        'product' => $product,
        // Intentionally omit AI price / API details
        'ai' => [
            'sku' => $result['ai']['sku'] ?? null,
            'product_name' => $result['ai']['product_name'] ?? null,
            'reason' => $result['ai']['reason'] ?? null,
            'confidence' => $confidence,
        ],
    ]);
} catch (Throwable $e) {
    error_log('recognize API error: ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Unable to identify the product. Please try another photo.',
    ], 500);
} finally {
    delete_temp_image($tempPath);
}
