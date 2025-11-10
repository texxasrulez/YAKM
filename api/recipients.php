<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../lib/bootstrap.php';
use Kontact\Database;

$out = ['mode'=>'single','recipients'=>[],'version'=>3];
try {
    $db = new Database($GLOBALS['cfg']);
    $rows = $db->fetchAll('SELECT `key`,`value` FROM kontact_settings WHERE `key` IN ("RECIPIENT_MODE","RECIPIENTS_JSON","WEBMASTER_EMAIL")');
    $s = []; foreach ($rows as $r) { $s[$r['key']] = $r['value']; }
    $mode = $s['RECIPIENT_MODE'] ?? 'single';
    $out['mode'] = ($mode==='multiple') ? 'multiple' : 'single';

    $idx = 1;
    if (!empty($s['RECIPIENTS_JSON'])) {
        $decoded = json_decode((string)$s['RECIPIENTS_JSON'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                $label = isset($row['label']) ? trim((string)$row['label']) : '';
                $email = isset($row['email']) ? trim((string)$row['email']) : '';
                if ($label !== '' && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $out['recipients'][] = ['id'=>$idx,'label'=>$label];
                    $idx++;
                }
            }
        }
    }

    // Fallback if JSON empty: expose a single "Webmaster" option (read-only visual), id=1
    if (empty($out['recipients'])) {
        $wm = $s['WEBMASTER_EMAIL'] ?? ($GLOBALS['cfg']['WEBMASTER_EMAIL'] ?? '');
        if ($wm && filter_var($wm, FILTER_VALIDATE_EMAIL)) {
            $out['recipients'][] = ['id'=>1,'label'=>'Webmaster'];
            $out['mode'] = 'single';
        }
    }
} catch (\Throwable $e) {
    $wm = $GLOBALS['cfg']['WEBMASTER_EMAIL'] ?? '';
    if ($wm && filter_var($wm, FILTER_VALIDATE_EMAIL)) {
        $out['recipients'][] = ['id'=>1,'label'=>'Webmaster'];
        $out['mode'] = 'single';
    }
}
echo json_encode($out, JSON_UNESCAPED_SLASHES);
