<?php
// Auto-detect locale and provide tr() / _e() helpers.
// Order: explicit ?lang=xx_XX -> cookie 'locale' -> Accept-Language -> default 'en_US'.
// Available locales are the *.inc files in this directory.

if (!function_exists('kontact_supported_locales')) {
    function kontact_supported_locales(): array {
        // Build supported locales dynamically from files present
        $list = [];
        foreach (glob(__DIR__ . '/*.inc') as $f) {
            $base = basename($f, '.inc');
            if ($base !== 'template') $list[] = $base;
        }
        sort($list);
        return $list;
    }
}

if (!function_exists('kontact_negotiate_locale')) {
    function kontact_negotiate_locale(?string $accept, array $supported): string {
        // Parse Accept-Language like "en-US,en;q=0.9,fr;q=0.8"
        $candidates = [];
        if ($accept) {
            foreach (explode(',', $accept) as $part) {
                $bits = explode(';', trim($part), 2);
                $tag  = str_replace('-', '_', strtolower(trim($bits[0])));
                $q    = 1.0;
                if (isset($bits[1]) && preg_match('/q=([0-9.]+)/', $bits[1], $m)) { $q = (float)$m[1]; }
                if ($tag !== '') $candidates[$tag] = $q;
            }
            arsort($candidates, SORT_NUMERIC);
            foreach ($candidates as $tag => $_q) {
                // exact match
                foreach ($supported as $s) { if (strtolower($s) === $tag) return $s; }
                // language-only match (en -> en_US if present, else any en_*)
                $lang = substr($tag,0,2);
                $prefer = array_values(array_filter($supported, function($s) use ($lang) { return strtolower(substr($s,0,2)) === $lang; }));
                if ($prefer) return $prefer[0];
            }
        }
        return 'en_US';
    }
}

if (!function_exists('kontact_current_locale')) {
    function kontact_current_locale(): string {
        $supported = kontact_supported_locales();
        // 1) query param
        $q = $_GET['lang'] ?? $_GET['locale'] ?? null;
        if (is_string($q)) {
            $q = preg_replace('/[^a-zA-Z_\-]/','',$q);
            if ($q) {
                $q = str_replace('-', '_', $q);
                // set cookie for persistence
                @setcookie('locale', $q, time()+3600*24*180, '/');
                $a = strtoupper(substr($q,0,2));
                $b = strtoupper(substr($q,3,2) ?: substr($q,0,2));
                return $a . '_' . $b;
            }
        }
        // 2) cookie
        if (!empty($_COOKIE['locale']) && is_string($_COOKIE['locale'])) {
            $c = str_replace('-', '_', $_COOKIE['locale']);
            foreach ($supported as $s) { if (strtolower($s) === strtolower($c)) return $s; }
            $lang = substr($c,0,2);
            $match = array_values(array_filter($supported, function($s) use ($lang) { return strtolower(substr($s,0,2)) === strtolower($lang); }));
            if ($match) return $match[0];
        }
        // 3) Accept-Language
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        return kontact_negotiate_locale($accept, $supported);
    }
}

if (!function_exists('tr')) {
    function tr(string $key, ?string $default = null, array $params = []): string {
        static $L = null;
        if ($L === null) {
            // load array from localization/xx_XX.inc
            $loc   = kontact_current_locale();
            $file  = __DIR__ . '/' . $loc . '.inc';
            if (!is_file($file)) {
                // try language-only fallback
                $lang = substr($loc,0,2);
                $glob = glob(__DIR__ . '/' . strtoupper($lang) . '_*.inc');
                if ($glob) $file = $glob[0];
            }
            if (!is_file($file)) { $file = __DIR__ . '/en_US.inc'; }
            $arr = include $file;
            if (!is_array($arr)) $arr = [];
            $L = $arr;
        }
        $text = $L[$key] ?? ($default ?? $key);
        // simple named placeholder replacement: {n} etc.
        if ($params) {
            foreach ($params as $k=>$v) {
                $text = str_replace('{{{'.$k.'}}}', (string)$v, $text);
                $text = str_replace('{{'.$k.'}}', (string)$v, $text);
                $text = str_replace('{{ '.$k.' }}', (string)$v, $text);
                $text = str_replace('{'.$k.'}', (string)$v, $text);
            }
        }
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e(string $key, ?string $default = null, array $params = []): void {
        echo tr($key, $default, $params);
    }
}
