<?php
/**
 * Connexion PDO (singleton).
 */

require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Aligne la connexion sur utf8mb4, comme les tables.
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit(
                '<div style="font-family:system-ui;max-width:640px;margin:80px auto;padding:24px;'
                . 'border:1px solid #fecaca;background:#fef2f2;border-radius:12px;color:#991b1b">'
                . '<h2 style="margin:0 0 8px">Connexion à la base de données impossible</h2>'
                . '<p style="margin:0 0 12px">' . htmlspecialchars($e->getMessage()) . '</p>'
                . '<p style="margin:0;font-size:14px">Vérifiez que MySQL est démarré et que la base '
                . '<code>' . DB_NAME . '</code> a bien été importée (database/schema.sql).</p></div>'
            );
        }
    }

    return $pdo;
}

/** Raccourci : exécute une requête préparée et retourne le statement. */
function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Retourne toutes les lignes. */
function fetchAll(string $sql, array $params = []): array
{
    return query($sql, $params)->fetchAll();
}

/** Retourne une seule ligne, ou null. */
function fetchOne(string $sql, array $params = []): ?array
{
    $row = query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Retourne la première colonne de la première ligne. */
function fetchValue(string $sql, array $params = [])
{
    $value = query($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}
