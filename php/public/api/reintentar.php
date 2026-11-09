<?php
/**
 * POST /api/reintentar.php  {id}
 * Vuelve a clasificar una consulta que quedó en estado 'error'.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responderJson(['error' => 'Método no permitido'], 405);
}

$id = filter_var(leerJson()['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    responderJson(['error' => 'Id no válido'], 400);
}

$repo = new ConsultaRepository(Database::conexion());
$consulta = $repo->buscar($id);

if ($consulta === null) {
    responderJson(['error' => 'Consulta no encontrada'], 404);
}
if ($consulta['estado'] !== 'error') {
    responderJson(['error' => 'Solo se pueden reintentar consultas en estado error'], 409);
}

responderJson(crearConsultaService($repo)->clasificar($id));
