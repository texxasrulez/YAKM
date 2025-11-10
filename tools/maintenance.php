<?php
declare(strict_types=1);

namespace Kontact;

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/Maintenance.php';

$cfg = $GLOBALS['cfg'] ?? [];
$summary = run_maintenance($cfg);

echo "[maintenance] done @ ".date('c')."\n";
echo "rotated: ".implode(', ', $summary['rotated'])."\n";
echo "purged_logs: ".$summary['purged_logs']."\n";
echo "pruned_audit: ".$summary['pruned_audit']."\n";
if (!empty($summary['indexes'])) echo "indexes_added: ".implode(', ', $summary['indexes'])."\n";
if (!empty($summary['errors'])) echo "errors: ".implode(' | ', $summary['errors'])."\n";

if (empty($summary['errors'])) { echo "OK\n"; }
