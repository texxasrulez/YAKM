<?php
declare(strict_types=1);

if (!function_exists('kontact_log_quote')) {
    function kontact_log_quote(string $value): string
    {
        return strtr($value, [
            "\\" => "\\\\",
            '"' => '\\"',
            "\r" => '\r',
            "\n" => '\n',
            "\t" => '\t',
        ]);
    }
}

if (!function_exists('kontact_log_category')) {
    function kontact_log_category(string $file): string
    {
        $name = basename($file);
        $name = preg_replace('/\.gz$/i', '', $name) ?? $name;
        $name = preg_replace('/\.log(?:\.\d+)?$/i', '', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9_.-]+/i', '_', $name) ?? $name;
        return $name !== '' ? strtolower($name) : 'app';
    }
}

if (!function_exists('kontact_log_detect_user')) {
    function kontact_log_detect_user(): string
    {
        $sessionEmail = $_SESSION['admin_email'] ?? null;
        if (is_string($sessionEmail) && $sessionEmail !== '') {
            return $sessionEmail;
        }

        $sessionId = $_SESSION['admin_id'] ?? null;
        if ($sessionId !== null && $sessionId !== '') {
            return 'admin#' . (string)$sessionId;
        }

        $postEmail = $_POST['email'] ?? null;
        if (is_string($postEmail) && $postEmail !== '') {
            return $postEmail;
        }

        $authUser = $_SERVER['PHP_AUTH_USER'] ?? null;
        if (is_string($authUser) && $authUser !== '') {
            return $authUser;
        }

        return '';
    }
}

if (!function_exists('kontact_log_detect_ip')) {
    function kontact_log_detect_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) ? $ip : '';
    }
}

if (!function_exists('kontact_log_format')) {
    function kontact_log_format(string $category, string $message, array $context = []): string
    {
        $time = date(DATE_ATOM);
        $user = isset($context['user']) ? (string)$context['user'] : kontact_log_detect_user();
        $ip = isset($context['ip']) ? (string)$context['ip'] : kontact_log_detect_ip();

        return sprintf(
            "Time=\"%s\" Category=\"%s\" User=\"%s\" IP=\"%s\" Message=\"%s\"\n",
            kontact_log_quote($time),
            kontact_log_quote($category),
            kontact_log_quote($user),
            kontact_log_quote($ip),
            kontact_log_quote($message)
        );
    }
}

if (!function_exists('kontact_log_regex')) {
    function kontact_log_regex(): string
    {
        return '/^Time="(?P<time>(?:\\\\.|[^"])*)" Category="(?P<category>(?:\\\\.|[^"])*)" User="(?P<user>(?:\\\\.|[^"])*)" IP="(?P<ip>(?:\\\\.|[^"])*)" Message="(?P<message>(?:\\\\.|[^"])*)"$/';
    }
}

if (!function_exists('kontact_log')) {
    function kontact_log($file, $line, array $context = []): void
    {
        $logdir = __DIR__ . '/../storage/logs';
        if (!is_dir($logdir)) {
            @mkdir($logdir, 0775, true);
        }

        $filename = basename((string)$file);
        if ($filename === '') {
            $filename = 'app.log';
        }

        $category = isset($context['category']) && $context['category'] !== ''
            ? (string)$context['category']
            : kontact_log_category($filename);

        $message = is_scalar($line) || $line === null
            ? (string)$line
            : json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($message)) {
            $message = '[unserializable log message]';
        }

        @file_put_contents(
            $logdir . '/' . $filename,
            kontact_log_format($category, $message, $context),
            FILE_APPEND
        );
    }
}
