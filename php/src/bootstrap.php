<?php
/**
 * Arranque común de la API: carga clases, configura errores y helpers JSON.
 */
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ConsultaRepository.php';
require_once __DIR__ . '/ClasificadorClient.php';
require_once __DIR__ . '/ConsultaService.php';

date_default_timezone_set(getenv('TZ') ?: 'Europe/Madrid');

// Nunca mostramos errores de PHP al cliente: se registran en el log del servidor.
ini_set('display_errors', '0');
error_reporting(E_ALL);

/** Envía una respuesta JSON con su código HTTP y termina. */
function responderJson(mixed $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Lee el cuerpo de la petición como JSON (array vacío si no es JSON válido). */
function leerJson(): array
{
    $datos = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($datos) ? $datos : [];
}

// Cualquier excepción no controlada -> 500 genérico (sin filtrar detalles internos).
set_exception_handler(function (Throwable $e): void {
    error_log('[API] ' . $e::class . ': ' . $e->getMessage());
    responderJson(['error' => 'Error interno del servidor'], 500);
});
