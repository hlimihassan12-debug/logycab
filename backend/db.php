<?php
/**
 * db.php — Connexion centrale à SQL Server
 * Logycab — Cabinet Dr Hassan Hlimi — Tétouan
 */

define('DB_SERVER', 'localhost\SQLEXPRESS');
define('DB_NAME',   'Logycab');

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "sqlsrv:Server=" . DB_SERVER . ";Database=" . DB_NAME . ";TrustServerCertificate=1";

        try {
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            die(json_encode([
                'erreur' => 'Connexion base de données impossible',
                'detail' => $e->getMessage()
            ]));
        }
    }

    return $pdo;
}