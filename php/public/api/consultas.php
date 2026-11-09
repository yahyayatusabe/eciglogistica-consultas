<?php
/**
 * API de consultas
 *   GET  /api/consultas.php[?categoria=...&estado=...&urgencia=...]  -> listado + resumen
 *   POST /api/consultas.php  {nombre_tienda, email, texto} -> crea y clasifica al instante
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

$repo = new ConsultaRepository(Database::conexion());

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $categoria = $_GET['categoria'] ?? null;
        $estado = $_GET['estado'] ?? null;
        $urgencia = $_GET['urgencia'] ?? null;

        // Lista blanca: solo se aceptan valores conocidos en los filtros.
        if ($categoria !== null && !in_array($categoria, ConsultaRepository::CATEGORIAS, true)) {
            responderJson(['error' => 'Categoría no válida'], 400);
        }
        if ($estado !== null && !in_array($estado, ConsultaRepository::ESTADOS, true)) {
            responderJson(['error' => 'Estado no válido'], 400);
        }
        if ($urgencia !== null && !in_array($urgencia, ConsultaRepository::URGENCIAS, true)) {
            responderJson(['error' => 'Urgencia no válida'], 400);
        }

        responderJson([
            'consultas' => $repo->listar($categoria, $estado, $urgencia),
            'resumen' => $repo->resumen(),
        ]);

    case 'POST':
        $servicio = new ConsultaService(
            $repo,
            new ClasificadorClient(getenv('AI_SERVICE_URL') ?: 'http://localhost:5000')
        );

        $datos = leerJson();
        $errores = $servicio->validar($datos);
        if ($errores) {
            responderJson(['error' => 'Datos no válidos', 'campos' => $errores], 422);
        }

        // 201 Created: la consulta queda guardada siempre (clasificada o en error).
        responderJson($servicio->crearYClasificar($datos), 201);

    default:
        header('Allow: GET, POST');
        responderJson(['error' => 'Método no permitido'], 405);
}
