<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

echo "GET[lang]: " . (isset($_GET['lang']) ? $_GET['lang'] : "(none)") . "\n";
echo "SESSION[lang]: " . (isset($_SESSION['lang']) ? $_SESSION['lang'] : "(none)") . "\n";
echo "COOKIE[lang]: " . (isset($_COOKIE['lang']) ? $_COOKIE['lang'] : "(none)") . "\n";

if (function_exists('kontact_available_locales')) {
  $avs = kontact_available_locales();
  echo "available: " . implode(",", $avs) . "\n";
} else {
  echo "available: (no kontact_available_locales)\n";
}

echo "locale: " . (function_exists('kontact_current_locale') ? kontact_current_locale() : "(no kontact_current_locale)") . "\n";
echo "tr(settings): " . (function_exists('tr') ? tr('settings','Settings') : "(no tr)") . "\n";
echo "_e(save_settings): ";
if (function_exists('_e')) { _e('save_settings','Save Settings'); } else { echo "(no _e)"; }
echo "\n";
