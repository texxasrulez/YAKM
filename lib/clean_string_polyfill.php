<?php
declare(strict_types=1);
if (!function_exists('clean_string')) {
    function clean_string($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
