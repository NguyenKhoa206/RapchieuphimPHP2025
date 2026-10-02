<?php

declare(strict_types=1);

namespace App\Support;

final class Database
{
    public static function connection(): \PDO
    {
        static $connection;
        if (!$connection) {
            $c = config('database');
            $connection = new \PDO(
                "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",
                $c['user'],
                $c['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                 \PDO::ATTR_EMULATE_PREPARES => false, \PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]
            );
            $connection->exec('SET time_zone = ' . $connection->quote(date('P')));
        }
        return $connection;
    }

    public static function all(string $sql, array $params = []): array
    {
        $query = self::connection()->prepare($sql);
        $query->execute($params);
        return $query->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        return self::all($sql, $params)[0] ?? null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $query = self::connection()->prepare($sql);
        $query->execute($params);
        return $query->rowCount();
    }
}
