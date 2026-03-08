<?php
require_once __DIR__ . '/../config/db.php';

function getSettingValue(PDO $pdo, string $key, string $default = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return is_string($val) ? $val : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Sends SMS via a configurable HTTP endpoint.
 *
 * Configure in `settings`:
 * - sms_api_url: endpoint URL that accepts JSON {to, message, sender_id, api_key}
 * - sms_api_key: API key/token for your SMS gateway
 * - sms_sender_id: sender name/id
 */
function send_sms(PDO $pdo, string $to, string $message): bool {
    $url = trim(getSettingValue($pdo, 'sms_api_url', ''));
    $apiKey = trim(getSettingValue($pdo, 'sms_api_key', ''));
    $sender = trim(getSettingValue($pdo, 'sms_sender_id', 'QuickFix'));

    if ($url === '' || $apiKey === '') {
        return false;
    }

    $payload = json_encode([
        'to' => $to,
        'message' => $message,
        'sender_id' => $sender,
        'api_key' => $apiKey,
    ], JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => 8,
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $payload,
        ],
    ]);

    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) {
        return false;
    }

    $data = json_decode($resp, true);
    if (is_array($data) && isset($data['ok'])) {
        return (bool)$data['ok'];
    }

    // If gateway doesn't return JSON, consider HTTP 200 as success.
    return true;
}

