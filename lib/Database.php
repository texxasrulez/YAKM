<?php
declare(strict_types=1);
namespace Kontact;

use PDO;

class Database {
    private PDO $pdo;

    public function __construct(?array $cfg = null) {
        if (!$cfg || !\is_array($cfg)) {
            $cfg = $GLOBALS['cfg'] ?? [];
            if (empty($cfg)) {
                $path = __DIR__ . '/../config/config.inc.php';
                if (\is_file($path)) {
                    $loaded = require $path;
                    if (\is_array($loaded)) { $cfg = $loaded; $GLOBALS['cfg'] = $loaded; }
                }
            }
        }
        $host = $cfg['DB_HOST'] ?? ($cfg['DATABASE_HOST'] ?? 'localhost');
        $name = $cfg['DB_NAME'] ?? ($cfg['DATABASE_NAME'] ?? '');
        $user = $cfg['DB_USER'] ?? ($cfg['DATABASE_USER'] ?? '');
        $pass = $cfg['DB_PASS'] ?? ($cfg['DATABASE_PASS'] ?? '');
        $charset = 'utf8mb4';
        if ($name === '') { throw new \RuntimeException('Database name is not configured.'); }
        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        $this->pdo = new PDO($dsn, (string)$user, (string)$pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function pdo(): PDO { return $this->pdo; }

    public function fetchAll(string $sql, array $params = []): array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function exec(string $sql, array $params = []): int {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }
}
