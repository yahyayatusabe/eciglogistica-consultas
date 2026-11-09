<?php
declare(strict_types=1);

/**
 * Conexión PDO única (se crea la primera vez que se pide).
 * Los datos de conexión vienen de variables de entorno (docker-compose / .env).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: 'localhost',
                getenv('DB_PORT') ?: '3306',
                getenv('DB_NAME') ?: 'eciglogistica'
            );

            self::$pdo = new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // errores como excepciones
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // filas como arrays asociativos
                PDO::ATTR_EMULATE_PREPARES   => false,                  // consultas preparadas reales
                // NOW() de MySQL en la misma zona horaria que PHP (hora de España)
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . date('P') . "'",
            ]);
        }
        return self::$pdo;
    }
}
