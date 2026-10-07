<?php
/**
 * Product catalog queries and AI result matching.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function product_public_fields(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'sku' => $row['sku'],
        'barcode' => $row['barcode'],
        'name' => $row['name'],
        'price' => number_format((float) $row['price'], 2, '.', ''),
        'description' => $row['description'],
        'image' => $row['image_path'],
        'detect_keywords' => $row['detect_keywords'] ?? null,
    ];
}

function find_product_by_barcode(string $barcode): ?array
{
    $barcode = normalize_barcode($barcode);
    if (!is_valid_barcode($barcode)) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT * FROM products WHERE barcode = ? LIMIT 1'
    );
    $stmt->execute([$barcode]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_product_by_sku(string $sku): ?array
{
    $sku = normalize_sku($sku);
    if ($sku === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM products WHERE sku = ? LIMIT 1');
    $stmt->execute([$sku]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_product_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @return array<int, array>
 */
function get_all_products(?string $search = null): array
{
    if ($search !== null && trim($search) !== '') {
        $q = '%' . trim($search) . '%';
        $stmt = db()->prepare(
            'SELECT * FROM products
             WHERE name LIKE ? OR sku LIKE ? OR barcode LIKE ? OR detect_keywords LIKE ?
             ORDER BY name ASC'
        );
        $stmt->execute([$q, $q, $q, $q]);
        return $stmt->fetchAll();
    }

    return db()->query('SELECT * FROM products ORDER BY name ASC')->fetchAll();
}

/**
 * Compact catalog for AI prompting.
 *
 * @return array<int, array{sku:string,name:string,detect_keywords:?string,description:?string}>
 */
function catalog_for_ai(): array
{
    $rows = db()->query(
        'SELECT sku, name, detect_keywords, description FROM products ORDER BY name ASC'
    )->fetchAll();

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'sku' => $row['sku'],
            'name' => $row['name'],
            'detect_keywords' => $row['detect_keywords'],
            'description' => mb_substr((string) ($row['description'] ?? ''), 0, 160),
        ];
    }
    return $out;
}

/**
 * Match AI structured result against MySQL catalog.
 * Priority: SKU → product name → detection keywords → normalized text.
 *
 * @return array{product:?array, method:?string, confidence:float}
 */
function match_ai_result(array $ai): array
{
    $confidence = isset($ai['confidence']) ? (float) $ai['confidence'] : 0.0;
    $confidence = max(0.0, min(1.0, $confidence));

    $sku = trim((string) ($ai['sku'] ?? ''));
    if ($sku !== '' && strcasecmp($sku, 'null') !== 0) {
        $product = find_product_by_sku($sku);
        if ($product) {
            return ['product' => $product, 'method' => 'sku', 'confidence' => $confidence];
        }
    }

    $name = trim((string) ($ai['product_name'] ?? ''));
    if ($name !== '') {
        $byName = match_by_product_name($name);
        if ($byName) {
            return ['product' => $byName, 'method' => 'name', 'confidence' => $confidence];
        }

        $byKeywords = match_by_keywords($name);
        if ($byKeywords) {
            return ['product' => $byKeywords, 'method' => 'keywords', 'confidence' => $confidence];
        }

        $byNormalized = match_by_normalized_text($name);
        if ($byNormalized) {
            return ['product' => $byNormalized, 'method' => 'normalized', 'confidence' => $confidence];
        }
    }

    // Also try reason / free text fields lightly via keywords
    $reason = trim((string) ($ai['reason'] ?? ''));
    if ($reason !== '') {
        $byKeywords = match_by_keywords($reason);
        if ($byKeywords) {
            return ['product' => $byKeywords, 'method' => 'keywords', 'confidence' => $confidence];
        }
    }

    return ['product' => null, 'method' => null, 'confidence' => $confidence];
}

function match_by_product_name(string $name): ?array
{
    $normalized = normalize_match_text($name);
    if ($normalized === '') {
        return null;
    }

    $stmt = db()->query('SELECT * FROM products');
    $best = null;
    $bestScore = 0.0;

    while ($row = $stmt->fetch()) {
        $candidate = normalize_match_text((string) $row['name']);
        if ($candidate === '') {
            continue;
        }
        if ($candidate === $normalized) {
            return $row;
        }
        similar_text($normalized, $candidate, $percent);
        if ($percent > $bestScore) {
            $bestScore = $percent;
            $best = $row;
        }
    }

    return $bestScore >= 82.0 ? $best : null;
}

function match_by_keywords(string $text): ?array
{
    $hay = normalize_match_text($text);
    if ($hay === '') {
        return null;
    }

    $stmt = db()->query('SELECT * FROM products WHERE detect_keywords IS NOT NULL AND detect_keywords != ""');
    $best = null;
    $bestHits = 0;

    while ($row = $stmt->fetch()) {
        $keywords = preg_split('/[,;|]+/', (string) $row['detect_keywords']) ?: [];
        $hits = 0;
        foreach ($keywords as $kw) {
            $kw = normalize_match_text($kw);
            if ($kw !== '' && str_contains($hay, $kw)) {
                $hits++;
            }
        }
        if ($hits > $bestHits) {
            $bestHits = $hits;
            $best = $row;
        }
    }

    return $bestHits > 0 ? $best : null;
}

function match_by_normalized_text(string $text): ?array
{
    $normalized = normalize_match_text($text);
    if ($normalized === '') {
        return null;
    }

    $tokens = array_values(array_filter(explode(' ', $normalized), static fn($t) => mb_strlen($t) >= 3));
    if ($tokens === []) {
        return null;
    }

    $stmt = db()->query('SELECT * FROM products');
    $best = null;
    $bestScore = 0;

    while ($row = $stmt->fetch()) {
        $blob = normalize_match_text(
            ($row['name'] ?? '') . ' ' . ($row['detect_keywords'] ?? '') . ' ' . ($row['description'] ?? '')
        );
        $score = 0;
        foreach ($tokens as $token) {
            if (str_contains($blob, $token)) {
                $score++;
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $row;
        }
    }

    $needed = max(2, (int) ceil(count($tokens) * 0.5));
    return $bestScore >= $needed ? $best : null;
}
