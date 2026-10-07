<?php
/**
 * AI provider abstraction (Agnes / OpenAI-compatible + Gemini-compatible).
 * API keys never leave the server.
 */

declare(strict_types=1);

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/images.php';
require_once __DIR__ . '/catalog.php';

/**
 * High-level chat entry used by the rest of the application.
 *
 * @param array<int, array{role:string, content:mixed}> $messages
 * @return array{ok:bool, content?:string, provider?:string, model?:string, error?:string}
 */
function ai_chat(array $messages, ?array $options = null): array
{
    $settings = ai_settings();
    $provider = strtolower((string) ($settings['ai_provider'] ?? 'agnes'));

    if ($provider === 'gemini') {
        return gemini_chat($messages, $settings, $options);
    }

    return openai_chat($messages, $settings, $options);
}

/**
 * OpenAI-compatible chat (Agnes AI and similar).
 */
function openai_chat(array $messages, ?array $settings = null, ?array $options = null): array
{
    $settings = $settings ?? ai_settings();
    $apiUrl = rtrim((string) ($settings['agnes_api_url'] ?? ''), '/');
    $apiKey = (string) ($settings['agnes_api_key'] ?? '');
    $primary = trim((string) ($settings['agnes_model'] ?? ''));
    $fallbacks = parse_fallback_models((string) ($settings['agnes_fallback_models'] ?? ''));

    if ($apiUrl === '' || $apiKey === '' || $primary === '') {
        return ['ok' => false, 'error' => 'AI provider is not configured. Please set API URL, key, and model in Admin Settings.'];
    }

    $models = array_values(array_unique(array_filter(array_merge([$primary], $fallbacks))));
    $endpoint = $apiUrl . '/chat/completions';
    $lastError = 'AI request failed.';

    foreach ($models as $model) {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.1,
        ];

        if (!empty($options['response_json'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $result = http_json_request(
            $endpoint,
            'POST',
            $payload,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ]
        );

        if (!$result['ok']) {
            $lastError = $result['error'] ?? $lastError;
            continue;
        }

        $content = $result['data']['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            $lastError = 'AI returned an empty response.';
            continue;
        }

        return [
            'ok' => true,
            'content' => $content,
            'provider' => 'agnes',
            'model' => $model,
        ];
    }

    return ['ok' => false, 'error' => $lastError];
}

/**
 * Gemini-compatible generateContent API.
 */
function gemini_chat(array $messages, ?array $settings = null, ?array $options = null): array
{
    $settings = $settings ?? ai_settings();
    $apiUrl = rtrim((string) ($settings['gemini_api_url'] ?? ''), '/');
    $apiKey = (string) ($settings['gemini_api_key'] ?? '');
    $primary = trim((string) ($settings['gemini_model'] ?? ''));
    $fallbacks = parse_fallback_models((string) ($settings['gemini_fallback_models'] ?? ''));

    if ($apiUrl === '' || $apiKey === '' || $primary === '') {
        return ['ok' => false, 'error' => 'Gemini is not configured. Please set API URL, key, and model in Admin Settings.'];
    }

    $systemText = '';
    $userParts = [];

    foreach ($messages as $message) {
        $role = $message['role'] ?? 'user';
        $content = $message['content'] ?? '';

        if ($role === 'system') {
            $systemText .= (is_string($content) ? $content : '') . "\n";
            continue;
        }

        if (is_array($content)) {
            foreach ($content as $part) {
                if (($part['type'] ?? '') === 'text') {
                    $userParts[] = ['text' => (string) ($part['text'] ?? '')];
                } elseif (($part['type'] ?? '') === 'image_url') {
                    $url = (string) ($part['image_url']['url'] ?? '');
                    if (preg_match('#^data:(image/[^;]+);base64,(.+)$#', $url, $m)) {
                        $userParts[] = [
                            'inline_data' => [
                                'mime_type' => $m[1],
                                'data' => $m[2],
                            ],
                        ];
                    }
                }
            }
        } else {
            $userParts[] = ['text' => (string) $content];
        }
    }

    if ($systemText !== '') {
        array_unshift($userParts, ['text' => trim($systemText)]);
    }

    $models = array_values(array_unique(array_filter(array_merge([$primary], $fallbacks))));
    $lastError = 'AI request failed.';

    foreach ($models as $model) {
        $endpoint = $apiUrl . '/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $userParts,
                ],
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.1,
                'responseMimeType' => !empty($options['response_json']) ? 'application/json' : 'text/plain',
            ],
        ];

        $result = http_json_request($endpoint, 'POST', $payload, ['Content-Type: application/json']);

        if (!$result['ok']) {
            $lastError = $result['error'] ?? $lastError;
            continue;
        }

        $content = $result['data']['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            $lastError = 'AI returned an empty response.';
            continue;
        }

        return [
            'ok' => true,
            'content' => $content,
            'provider' => 'gemini',
            'model' => $model,
        ];
    }

    return ['ok' => false, 'error' => $lastError];
}

/**
 * Test AI connection with a simple prompt.
 * Pass $providerForce as "agnes" or "gemini" to test that provider
 * regardless of the currently selected active provider.
 *
 * @return array{ok:bool, message:string, provider?:string, model?:string}
 */
function test_ai_connection(?string $providerForce = null): array
{
    $settings = ai_settings();
    $provider = strtolower((string) ($providerForce ?: ($settings['ai_provider'] ?? 'agnes')));
    if (!in_array($provider, ['agnes', 'gemini'], true)) {
        $provider = 'agnes';
    }

    $providerLabel = $provider === 'gemini' ? 'Google Gemini' : 'Agnes AI';
    $messages = [
        [
            'role' => 'user',
            'content' => 'Reply with exactly: OK',
        ],
    ];
    $options = ['temperature' => 0];

    if ($provider === 'gemini') {
        $result = gemini_chat($messages, $settings, $options);
    } else {
        $result = openai_chat($messages, $settings, $options);
    }

    if (!$result['ok']) {
        return [
            'ok' => false,
            'message' => 'Connection failed. Please check API URL, API key, model, and internet connection.',
            'provider' => $providerLabel,
            'error' => $result['error'] ?? null,
        ];
    }

    return [
        'ok' => true,
        'message' => 'Connection successful.',
        'provider' => $providerLabel,
        'model' => $result['model'] ?? '',
    ];
}

/**
 * Identify a product image against the local catalog via AI vision.
 *
 * @return array{ok:bool, ai?:array, product?:array, confidence?:float, method?:string, message?:string, error?:string}
 */
function recognize_product_image(string $imagePath): array
{
    $catalog = catalog_for_ai();
    if ($catalog === []) {
        return ['ok' => false, 'error' => 'Product catalog is empty. Please add products first.'];
    }

    $base64 = image_file_to_base64($imagePath);
    $dataUrl = 'data:image/jpeg;base64,' . $base64;

    $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $system = <<<PROMPT
You are a retail product recognition assistant.

Analyze the provided product image.

Identify the product based only on the available image and the supplied product catalog.

Return JSON only.

Do not invent prices.

Do not invent SKU values.

If you cannot confidently identify a product, return null for the SKU.

Use the catalog information to determine the closest matching product.

Return exactly this JSON shape:
{
  "sku": "...",
  "product_name": "...",
  "confidence": 0.0,
  "reason": "..."
}
PROMPT;

    $userText = "Product catalog (JSON):\n" . $catalogJson .
        "\n\nIdentify the product in the image. Return JSON only.";

    $messages = [
        ['role' => 'system', 'content' => $system],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $userText],
                [
                    'type' => 'image_url',
                    'image_url' => ['url' => $dataUrl],
                ],
            ],
        ],
    ];

    $result = ai_chat($messages, [
        'temperature' => 0.1,
        'response_json' => true,
    ]);

    if (!$result['ok']) {
        $error = $result['error'] ?? 'Unable to identify the product.';
        if (stripos($error, 'timeout') !== false) {
            $error = 'The AI service took too long to respond. Please try again.';
        }
        return ['ok' => false, 'error' => $error];
    }

    $parsed = parse_ai_json((string) $result['content']);
    if ($parsed === null) {
        return ['ok' => false, 'error' => 'Unable to identify the product. Please try another photo.'];
    }

    $match = match_ai_result($parsed);
    if ($match['product'] === null) {
        return [
            'ok' => false,
            'ai' => $parsed,
            'confidence' => $match['confidence'],
            'error' => 'Product not found in the store catalog.',
        ];
    }

    return [
        'ok' => true,
        'ai' => $parsed,
        'product' => $match['product'],
        'confidence' => $match['confidence'],
        'method' => $match['method'],
        'message' => 'Product found.',
    ];
}

/**
 * Extract and validate structured AI JSON.
 */
function parse_ai_json(string $content): ?array
{
    $content = trim($content);

    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $content, $m)) {
        $content = $m[1];
    } elseif (preg_match('/\{.*\}/s', $content, $m)) {
        $content = $m[0];
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        return null;
    }

    $sku = $data['sku'] ?? null;
    if ($sku === null || $sku === '' || $sku === 'null') {
        $data['sku'] = null;
    } else {
        $data['sku'] = (string) $sku;
    }

    $data['product_name'] = isset($data['product_name']) ? (string) $data['product_name'] : '';
    $data['reason'] = isset($data['reason']) ? (string) $data['reason'] : '';
    $confidence = isset($data['confidence']) ? (float) $data['confidence'] : 0.0;
    if ($confidence > 1 && $confidence <= 100) {
        $confidence = $confidence / 100;
    }
    $data['confidence'] = max(0.0, min(1.0, $confidence));

    return $data;
}

/**
 * @return array<int, string>
 */
function parse_fallback_models(string $raw): array
{
    $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
    $models = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $models[] = $part;
        }
    }
    return $models;
}

/**
 * HTTP JSON helper using cURL.
 *
 * @return array{ok:bool, data?:array, error?:string, status?:int}
 */
function http_json_request(string $url, string $method, ?array $payload, array $headers = []): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is not enabled on this server.'];
    }

    $ch = curl_init($url);
    $method = strtoupper($method);

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => AI_REQUEST_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    ];

    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno === CURLE_OPERATION_TIMEDOUT || $errno === 28) {
        return ['ok' => false, 'error' => 'The AI service took too long to respond. Please try again.', 'status' => $status];
    }

    if ($body === false) {
        error_log('AI HTTP error: ' . $error);
        return ['ok' => false, 'error' => 'Unable to reach the AI service. Please try again.', 'status' => $status];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        error_log('AI non-JSON response HTTP ' . $status);
        return ['ok' => false, 'error' => 'AI service returned an unexpected response.', 'status' => $status];
    }

    if ($status < 200 || $status >= 300) {
        $msg = $data['error']['message'] ?? ($data['error'] ?? 'AI request failed.');
        if (is_array($msg)) {
            $msg = $msg['message'] ?? 'AI request failed.';
        }
        error_log('AI HTTP ' . $status . ': ' . (string) $msg);
        return ['ok' => false, 'error' => 'AI recognition failed. Please check settings and try again.', 'status' => $status];
    }

    return ['ok' => true, 'data' => $data, 'status' => $status];
}
