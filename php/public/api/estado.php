<?php
/**
 * GET /api/estado.php -> indica si el servicio de IA está activo y en qué modo
 * (IA real o simulado). Lo usa la cabecera del frontend.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

$url = rtrim(getenv('AI_SERVICE_URL') ?: 'http://localhost:5000', '/') . '/health';

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 3]);
$cuerpo = curl_exec($ch);
$codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$datos = is_string($cuerpo) ? json_decode($cuerpo, true) : null;

if ($codigo !== 200 || !is_array($datos)) {
    responderJson(['modo' => null, 'error' => 'Servicio de IA no disponible'], 503);
}

responderJson(['modo' => $datos['modo'] ?? null, 'modelo' => $datos['modelo'] ?? null]);
