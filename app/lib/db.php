<?php
/**
 * Accès à la base SQLite (PDO).
 *
 * Réglages imposés par les contraintes du Raspberry Pi :
 *  - mode WAL (lectures et écritures simultanées),
 *  - busy_timeout = 5000 ms (attente au lieu d'une erreur immédiate),
 *  - nouvelles tentatives (3 essais / 300 ms) pour toute écriture,
 *  - requêtes préparées partout (aucune concaténation de valeurs).
 */

declare(strict_types=1);

/** Connexion PDO unique (lazy). */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = (string) config('database');
    $dir  = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossible de créer le dossier de la base : ' . $dir);
        }
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Réglages de robustesse (WAL + délai d'attente).
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA temp_store = MEMORY');

    return $pdo;
}

/** L'erreur correspond-elle à une base verrouillée / occupée ? */
function db_is_busy_error(Throwable $e): bool
{
    $message = $e->getMessage();
    return stripos($message, 'database is locked') !== false
        || stripos($message, 'database table is locked') !== false
        || stripos($message, 'database is busy') !== false
        || stripos($message, 'SQLSTATE[HY000]: General error: 5') !== false;
}

/**
 * Exécute une opération en réessayant jusqu'à 3 fois (pause de 300 ms)
 * en cas de « database is locked ».
 */
function db_retry(callable $operation, int $attempts = 3, int $pauseMs = 300): mixed
{
    $lastError = null;
    for ($i = 1; $i <= $attempts; $i++) {
        try {
            return $operation();
        } catch (Throwable $e) {
            if (!db_is_busy_error($e) || $i === $attempts) {
                throw $e;
            }
            $lastError = $e;
            usleep($pauseMs * 1000);
        }
    }
    throw $lastError ?? new RuntimeException('Échec de l’opération en base.');
}

/** Prépare et exécute une requête (avec nouvelles tentatives). */
function db_query(string $sql, array $params = []): PDOStatement
{
    return db_retry(static function () use ($sql, $params): PDOStatement {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    });
}

/** Première ligne du résultat, ou null. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Toutes les lignes du résultat. */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** Première colonne de la première ligne. */
function db_value(string $sql, array $params = [], mixed $default = null): mixed
{
    $value = db_query($sql, $params)->fetchColumn();
    return $value === false ? $default : $value;
}

/** Exécute une écriture (INSERT/UPDATE/DELETE) et renvoie le nombre de lignes. */
function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->rowCount();
}

/**
 * Insère une ligne à partir d'un tableau associatif.
 * Les clés doivent être des noms de colonnes écrits dans le code (jamais
 * des valeurs venant de l'utilisateur).
 */
function db_insert(string $table, array $data): int
{
    $columns      = array_keys($data);
    $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(', ', $columns),
        implode(', ', $placeholders)
    );
    $params = [];
    foreach ($data as $column => $value) {
        $params[':' . $column] = $value;
    }
    db_query($sql, $params);
    return (int) db()->lastInsertId();
}

/** Met à jour une ligne identifiée par son id. */
function db_update(string $table, int $id, array $data): void
{
    if ($data === []) {
        return;
    }
    $sets = [];
    $params = [':id' => $id];
    foreach ($data as $column => $value) {
        $sets[] = $column . ' = :' . $column;
        $params[':' . $column] = $value;
    }
    $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $table, implode(', ', $sets));
    db_query($sql, $params);
}

/** Exécute une fonction dans une transaction (avec nouvelles tentatives). */
function db_transaction(callable $callback): mixed
{
    return db_retry(static function () use ($callback): mixed {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    });
}

// ---------------------------------------------------------------------------
// Migrations
// ---------------------------------------------------------------------------

/** Applique les fichiers SQL de app/migrations non encore exécutés. */
function db_migrate(): array
{
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        name TEXT PRIMARY KEY,
        applied_at INTEGER NOT NULL
    )');

    $applied = [];
    foreach (db_all('SELECT name FROM schema_migrations') as $row) {
        $applied[] = $row['name'];
    }

    $files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);

    $done = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $applied, true)) {
            continue;
        }
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            continue;
        }
        db_transaction(static function (PDO $pdo) use ($sql, $name): void {
            $pdo->exec($sql);
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (name, applied_at) VALUES (?, ?)');
            $stmt->execute([$name, time()]);
        });
        $done[] = $name;
    }

    return $done;
}

/** La base est-elle installée (tables créées) ? */
function db_is_installed(): bool
{
    try {
        $count = db_value(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'schema_migrations'"
        );
        return (int) $count > 0;
    } catch (Throwable) {
        return false;
    }
}
