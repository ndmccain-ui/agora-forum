<?php
declare(strict_types=1);
defined('AGORA') or exit;

/**
 * SQLite database access. All queries use prepared statements —
 * never put user input directly into SQL.
 */
final class DB
{
    private static ?PDO $pdo = null;

    public static function connect(string $file): PDO
    {
        self::$pdo = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        return self::$pdo;
    }

    public static function connected(): bool
    {
        return self::$pdo !== null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('Database is not connected.');
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** Insert a row and return its new id. Column names come from code, never from users. */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(fn ($c) => ':' . $c, $columns))
        );
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function transaction(callable $work): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Bring the database up to date. Each file in app/migrations is named
     * NNN_description.php and returns a function that receives the PDO.
     * Uploading a new Agora version adds files here; they run once, automatically.
     */
    public static function migrate(): void
    {
        $pdo = self::pdo();
        $current = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        $files = glob(AGORA_ROOT . '/app/migrations/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $version = (int) basename($file);
            if ($version <= $current) {
                continue;
            }
            $migration = require $file;
            $pdo->beginTransaction();
            try {
                $migration($pdo);
                $pdo->exec('PRAGMA user_version = ' . $version);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw new RuntimeException('Database upgrade ' . basename($file) . ' failed: ' . $e->getMessage(), 0, $e);
            }
            $current = $version;
        }
    }
}
