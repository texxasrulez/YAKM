<?php
declare(strict_types=1);
/**
 * Fill missing localization keys in localization/*.inc using en_US.inc
 * Each .inc must return an associative array of 'key' => 'value'
 */
$root = dirname(__DIR__);
$locDir = $root . '/localization';
$src = $locDir . '/en_US.inc';
if (!is_dir($locDir)) { echo "No localization dir at $locDir\n"; exit(1); }
if (!file_exists($src)) { echo "Missing $src\n"; exit(1); }
$base = include $src;
if (!is_array($base)) { echo "Invalid en_US.inc (must return array)\n"; exit(1); }
$files = glob($locDir . '/*.inc');
foreach ($files as $file) {
  if (basename($file) === 'en_US.inc') continue;
  $arr = include $file; if (!is_array($arr)) $arr = [];
  $changed = false;
  foreach ($base as $k => $v) {
    if (!array_key_exists($k, $arr)) { $arr[$k] = $v; $changed = true; }
  }
  if ($changed) {
    ksort($arr);
    $php = "<?php\nreturn " . var_export($arr, true) . ";\n";
    file_put_contents($file, $php);
    echo "Updated: " . basename($file) . "\n";
  }
}
echo "Locales updated.\n";
