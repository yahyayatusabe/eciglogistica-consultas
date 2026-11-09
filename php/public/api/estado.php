<?php
/**
 * GET /api/estado.php -> indica si el servicio de IA está activo y en qué modo
 * (IA real o simulado). Lo usa la cabecera del frontend.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

try {
    responderJson(crearClasificador()->estado());
} catch (ClasificacionException) {
    responderJson(['modo' => null, 'error' => 'Servicio de IA no disponible'], 503);
}
