<?php
declare(strict_types=1);
namespace Kontact;

/**
 * Dual-mode reCAPTCHA verification.
 * Modes:
 *   - classic: uses GOOGLE siteverify with secret
 *   - enterprise: uses Cloud reCAPTCHA Enterprise Assessments API with API key
 *
 * Required cfg keys:
 *   classic:
 *     - RECAPTCHA_SITE
 *     - RECAPTCHA_SECRET
 *   enterprise:
 *     - RECAPTCHA_SITE
 *     - RECAPTCHA_PROJECT_ID
 *     - RECAPTCHA_API_KEY
 *
 * Usage:
 *   [$ok, $score, $reason, $raw] = \Kontact\recaptcha_verify($GLOBALS['cfg'], $token, $_SERVER['REMOTE_ADDR'] ?? '');
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
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = \curl_exec($ch);
    $err  = \curl_error($ch);
    $code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
    \curl_close($ch);
    return [$code, $body, $err];
}

function http_post_form(string $url, array $fields): array {
    $ch = \curl_init($url);
    \curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields, '', '&'),
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = \curl_exec($ch);
    $err  = \curl_error($ch);
    $code = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
    \curl_close($ch);
    return [$code, $body, $err];
}

function recaptcha_verify(array $cfg, ?string $token, string $remote_ip = ''): array {
    $mode = strtolower((string)($cfg['RECAPTCHA_MODE'] ?? 'classic'));
    $token = (string)($token ?? '');

    if ($token === '') {
        return [false, 0.0, 'missing-token', null];
    }

    if ($mode === 'enterprise') {
        $site_key   = (string)($cfg['RECAPTCHA_SITE'] ?? '');
        $project_id = (string)($cfg['RECAPTCHA_PROJECT_ID'] ?? '');
        $api_key    = (string)($cfg['RECAPTCHA_API_KEY'] ?? '');
        if ($site_key === '' || $project_id === '' || $api_key === '') {
            return [false, 0.0, 'enterprise-config-missing', null];
        }

        $url = "https://recaptchaenterprise.googleapis.com/v1/projects/{$project_id}/assessments?key={$api_key}";
        $payload = [
            'event' => [
                'token' => $token,
                'siteKey' => $site_key,
                'userIpAddress' => $remote_ip,
            ]
        ];
        [$code, $body, $err] = http_post_json($url, $payload);
        if ($err)  return [false, 0.0, 'http-error:'.$err, null];
        if ($code < 200 || $code >= 300) return [false, 0.0, 'http-code:'.$code, $body];
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

    // classic mode
    $secret = (string)($cfg['RECAPTCHA_SECRET'] ?? '');
    if ($secret === '') {
        return [false, 0.0, 'classic-secret-missing', null];
    }
    [$code, $body, $err] = http_post_form('https://www.google.com/recaptcha/api/siteverify', [
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => $remote_ip,
    ]);
    if ($err)  return [false, 0.0, 'http-error:'.$err, null];
    if ($code < 200 || $code >= 300) return [false, 0.0, 'http-code:'.$code, $body];
    $data = \json_decode((string)$body, true);
    $ok = (bool)($data['success'] ?? false);
    $score = isset($data['score']) ? (float)$data['score'] : ($ok ? 1.0 : 0.0);
    $reason = ($ok ? 'ok' : \implode(',', (array)($data['error-codes'] ?? ['verify-failed'])));
    return [$ok, $score, $reason, $data];
}
