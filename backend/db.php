<?php
/**
 * backend/db.php
 * Connexion base de données — lit la config depuis config.env
 * config.env est local à chaque machine et jamais sur GitHub
 */
$envFile = __DIR__ . '/config.env';
if (!file_exists($envFile)) {
    die(json_encode([
        'erreur' => 'Fichier config.env manquant',
        'detail' => 'Créez le fichier backend/config.env (voir config.env.example)'
    ]));
}
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    if (str_contains($line, '=')) {
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}
define('DB_SERVER',  $_ENV['DB_SERVER']  ?? 'localhost\\SQLEXPRESS');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'Logycab');
define('DB_USER',    $_ENV['DB_USER']    ?? 'sa');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_TRUSTED', $_ENV['DB_TRUSTED'] ?? 'false');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "sqlsrv:Server=" . DB_SERVER . ";Database=" . DB_NAME . ";TrustServerCertificate=1";
        try {
            // Authentification Windows (pas de login/mot de passe)
            if (DB_TRUSTED === 'true') {
                $pdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } else {
                // Authentification SQL Server (login/mot de passe)
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            }
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