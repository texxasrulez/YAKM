<?php
// test_assess.php — Standalone reCAPTCHA Enterprise assessment
// 1) Set these constants, deploy both files on the SAME domain authorized in your key.
// 2) Open recaptcha_smoketest.html in your browser and click "Execute & Verify".
// 3) On success, you'll see tokenProperties.valid=true and a score.

// ==== CONFIG: REPLACE THESE ====
const SITE_KEY   = 'YOUR_SITE_KEY';
const PROJECT_ID = 'PROJECT_ID';
const API_KEY    = 'YOUR_API_KEY';
// ===============================

header('Content-Type: application/json; charset=utf-8');

$token  = isset($_POST['token']) ? (string)$_POST['token'] : '';
$action = isset($_POST['action']) ? (string)$_POST['action'] : 'submit';

if ($token === '') {
  http_response_code(400);
  echo json_encode(['error' => 'missing token']);
  exit;
}

$payload = [
  'event' => [
    'token' => $token,
    'siteKey' => SITE_KEY,
    'userIpAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'expectedAction' => $action,
  ]
];

$url = 'https://recaptchaenterprise.googleapis.com/v1/projects/' . rawurlencode(PROJECT_ID) . '/assessments?key=' . rawurlencode(API_KEY);

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
  CURLOPT_TIMEOUT => 12,
]);

$body = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
  http_response_code(500);
  echo json_encode(['error' => 'http-error', 'detail' => $err], JSON_PRETTY_PRINT);
  exit;
}

http_response_code($code);
if ($code >= 200 && $code < 300) {
  // Pretty-print the JSON response
  $decoded = json_decode($body, true);
  echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
  echo json_encode(['error' => 'http-code-'.$code, 'body' => $body], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
