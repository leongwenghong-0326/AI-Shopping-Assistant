<?php
/**
 * Key/value settings helpers.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function &settings_cache(): array
{
    if (!isset($GLOBALS['__aisa_settings_cache']) || !is_array($GLOBALS['__aisa_settings_cache'])) {
        $GLOBALS['__aisa_settings_cache'] = [];
    }
    return $GLOBALS['__aisa_settings_cache'];
}

function setting_get(string $key, ?string $default = null): ?string
{
    $cache =& settings_cache();

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $value = $row === false ? $default : ($row['setting_value'] ?? $default);
        $cache[$key] = $value;
        return $value;
    } catch (Throwable $e) {
        error_log('setting_get failed: ' . $e->getMessage());
        return $default;
    }
}

function setting_set(string $key, ?string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);

    $cache =& settings_cache();
    $cache[$key] = $value;
}

function settings_get_many(array $keys): array
{
    if ($keys === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = db()->prepare(
        "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)"
    );
    $stmt->execute(array_values($keys));

    $out = array_fill_keys($keys, null);
    $cache =& settings_cache();
    while ($row = $stmt->fetch()) {
        $out[$row['setting_key']] = $row['setting_value'];
        $cache[$row['setting_key']] = $row['setting_value'];
    }
    return $out;
}

function ai_settings(): array
{
    $keys = [
        'ai_provider',
        'agnes_api_url',
        'agnes_api_key',
        'agnes_model',
        'agnes_fallback_models',
        'gemini_api_url',
        'gemini_api_key',
        'gemini_model',
        'gemini_fallback_models',
    ];

    $values = settings_get_many($keys);

    return [
        'ai_provider' => $values['ai_provider'] ?? 'agnes',
        'agnes_api_url' => $values['agnes_api_url'] ?? 'https://apihub.agnes-ai.com/v1',
        'agnes_api_key' => $values['agnes_api_key'] ?? '',
        'agnes_model' => $values['agnes_model'] ?? 'agnes-3.0-flash',
        'agnes_fallback_models' => $values['agnes_fallback_models'] ?? "agnes-2.5-flash\nagnes-2.0-flash",
        'gemini_api_url' => $values['gemini_api_url'] ?? 'https://generativelanguage.googleapis.com/v1beta',
        'gemini_api_key' => $values['gemini_api_key'] ?? '',
        'gemini_model' => $values['gemini_model'] ?? 'gemini-3.8-flash',
        'gemini_fallback_models' => $values['gemini_fallback_models'] ?? "gemini-3.7-flash\ngemini-3.6-flash\ngemini-3.5-flash",
    ];
}
