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
        private int $timeoutSegundos = 30   // la IA también redacta un borrador de respuesta
    ) {}

    /**
     * @return array{categoria: string, urgencia: string, resumen: ?string,
     *               respuesta_sugerida: ?string, clasificado_por: string}
     */
    public function clasificar(string $texto, ?string $nombreTienda = null): array
    {
        $ch = curl_init(rtrim($this->urlBase, '/') . '/clasificar');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(
                ['texto' => $texto, 'nombre_tienda' => $nombreTienda],
                JSON_UNESCAPED_UNICODE
            ),
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

        // Los campos de ayuda no son críticos: si llegan mal, se guardan con un valor seguro.
        $urgencia = $datos['urgencia'] ?? null;

        return [
            'categoria' => $datos['categoria'],
            'urgencia' => in_array($urgencia, ConsultaRepository::URGENCIAS, true) ? $urgencia : 'media',
            'resumen' => self::textoOpcional($datos['resumen'] ?? null, 255),
            'respuesta_sugerida' => self::textoOpcional($datos['respuesta_sugerida'] ?? null, 2000),
            'clasificado_por' => (string) ($datos['clasificado_por'] ?? 'desconocido'),
        ];
    }

    private static function textoOpcional(mixed $valor, int $maximo): ?string
    {
        if (!is_string($valor) || trim($valor) === '') {
            return null;
        }
        return mb_substr(trim($valor), 0, $maximo);
    }
}
