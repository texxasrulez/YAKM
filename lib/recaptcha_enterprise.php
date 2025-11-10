<?php
declare(strict_types=1);
namespace Kontact;

/**
 * reCAPTCHA Enterprise (Cloud) verification only.
 * Requires cfg keys:
 *   - RECAPTCHA_SITE
 *   - RECAPTCHA_PROJECT_ID
 *   - RECAPTCHA_API_KEY
 *
 * Usage:
 *   [$ok, $score, $reason, $raw] = \Kontact\recaptcha_enterprise_verify($GLOBALS['cfg'], $token, $_SERVER['REMOTE_ADDR'] ?? '');
 */
function http_post_json(string $url, array $payload, array $headers = []): array {
    $ch = \curl_init($url);
    $json = \json_encode($payload, JSON_UNESCAPED_SLASHES);
    $headers = \array_merge(['Content-Type: application/json'], $headers);
    \curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_TIMEOUT => 12,
    ]);
    $body = \curl_exec($ch);
    $err  = \curl_error($ch);
    $code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
    \curl_close($ch);
    return [$code, $body, $err];
}

function recaptcha_enterprise_verify(array $cfg, ?string $token, string $remote_ip = ''): array {
    $token = (string)($token ?? '');
    if ($token === '') {
        return [false, 0.0, 'missing-token', null];
    }
    $site_key   = (string)($cfg['RECAPTCHA_SITE'] ?? '');
    $project_id = (string)($cfg['RECAPTCHA_PROJECT_ID'] ?? '');
    $api_key    = (string)($cfg['RECAPTCHA_API_KEY'] ?? '');
    if ($site_key === '' || $project_id === '' || $api_key === '') {
        return [false, 0.0, 'enterprise-config-missing', null];
    }
    $url = "https://recaptchaenterprise.googleapis.com/v1/projects/{$project_id}/assessments?key={$api_key}";
    $expectedAction = isset($_POST['recaptcha_action']) ? (string)$_POST['recaptcha_action'] : 'submit';
$payload = [
        'event' => [
            'token' => $token,
            'siteKey' => $site_key,
            'userIpAddress' => $remote_ip,
            'expectedAction' => $expectedAction,
        ]
    ];
    [$code, $body, $err] = http_post_json($url, $payload);
    if ($err)  return [false, 0.0, 'http-error:'.$err, null];
    if ($code < 200 || $code >= 300) {
    // Try to parse error message for easier diagnostics
    $msg = 'http-code:'.$code;
    $j = json_decode((string)$body, true);
    if (isset($j['error']['message'])) { $msg .= ':' . $j['error']['message']; }
    // Log for server-side inspection
    $logdir = __DIR__ . '/../storage/logs'; if (!is_dir($logdir)) @mkdir($logdir, 0775, true);
    @file_put_contents($logdir.'/recaptcha.log', '['.date('c')."] $msg
$body

", FILE_APPEND);
    return [false, 0.0, $msg, $body];
}
    $data = \json_decode((string)$body, true);
    $score  = (float)($data['riskAnalysis']['score'] ?? 0.0);
    $reasons = $data['riskAnalysis']['reasons'] ?? [];
    $ok = ($data['tokenProperties']['valid'] ?? false) === true;
    $reason = $ok ? 'ok' : 'invalid-token';
    if (!$ok && isset($data['tokenProperties']['invalidReason'])) {
        $reason = 'invalid:'.$data['tokenProperties']['invalidReason'];
    }
    return [$ok, $score, \implode(',', (array)$reasons) ?: $reason, $data];
}
