<?php
declare(strict_types=1);

/** Error al clasificar (servicio caído, timeout, respuesta no válida...). */
final class ClasificacionException extends RuntimeException {}

/**
 * Cliente HTTP del microservicio Python de IA (POST /clasificar).
 * Lanza ClasificacionException con un mensaje legible si algo falla.
 */
final class ClasificadorClient
{
    public function __construct(
        private string $urlBase,
        private int $timeoutSegundos = 15
    ) {}

    /** @return array{categoria: string, clasificado_por: string} */
    public function clasificar(string $texto): array
    {
        $ch = curl_init(rtrim($this->urlBase, '/') . '/clasificar');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['texto' => $texto], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,                       // no esperar si el servicio está caído
            CURLOPT_TIMEOUT        => $this->timeoutSegundos,  // límite total de la petición
        ]);

        $cuerpo = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorCurl = curl_error($ch);
        curl_close($ch);

        if ($cuerpo === false) {
            error_log('[IA] cURL: ' . $errorCurl);  // detalle técnico solo en el log
            throw new ClasificacionException('Servicio de IA no disponible');
        }

        $datos = json_decode($cuerpo, true);

        if ($codigo !== 200) {
            $detalle = is_array($datos) && isset($datos['error']) ? $datos['error'] : "HTTP $codigo";
            throw new ClasificacionException('Error del servicio de IA: ' . $detalle);
        }

        // Doble validación: aunque Python ya valida, PHP no se fía de datos externos.
        if (!is_array($datos) || !in_array($datos['categoria'] ?? null, ConsultaRepository::CATEGORIAS, true)) {
            throw new ClasificacionException('El servicio de IA devolvió una respuesta no válida');
        }

        return [
            'categoria' => $datos['categoria'],
            'clasificado_por' => (string) ($datos['clasificado_por'] ?? 'desconocido'),
        ];
    }
}
