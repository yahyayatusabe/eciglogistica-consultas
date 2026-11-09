<?php
declare(strict_types=1);

/**
 * Flujo de negocio: validar -> guardar -> clasificar con IA -> actualizar.
 * La consulta se guarda ANTES de llamar a la IA: si la IA falla, no se pierde
 * (queda en estado 'error' y se puede reintentar).
 */
final class ConsultaService
{
    public const MAX_TEXTO = 2000;

    public function __construct(
        private ConsultaRepository $repo,
        private ClasificadorClient $clasificador
    ) {}

    /** Devuelve la lista de errores de validación (vacía si todo es correcto). */
    public function validar(array $datos): array
    {
        $errores = [];
        $nombre = trim((string) ($datos['nombre_tienda'] ?? ''));
        $email = trim((string) ($datos['email'] ?? ''));
        $texto = trim((string) ($datos['texto'] ?? ''));

        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 120) {
            $errores['nombre_tienda'] = 'El nombre de la tienda debe tener entre 2 y 120 caracteres';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errores['email'] = 'El email no es válido';
        }
        if (mb_strlen($texto) < 5) {
            $errores['texto'] = 'La consulta debe tener al menos 5 caracteres';
        } elseif (mb_strlen($texto) > self::MAX_TEXTO) {
            $errores['texto'] = 'La consulta no puede superar ' . self::MAX_TEXTO . ' caracteres';
        }
        return $errores;
    }

    /** Crea la consulta y la clasifica en el momento. Devuelve la consulta final. */
    public function crearYClasificar(array $datos): array
    {
        $id = $this->repo->crear(
            trim((string) $datos['nombre_tienda']),
            trim((string) $datos['email']),
            trim((string) $datos['texto'])
        );
        return $this->clasificar($id);
    }

    /** Vuelve a intentar clasificar una consulta (p. ej. tras un fallo de la IA). */
    public function clasificar(int $id): array
    {
        $consulta = $this->repo->buscar($id);
        if ($consulta === null) {
            throw new InvalidArgumentException('Consulta no encontrada');
        }

        try {
            $resultado = $this->clasificador->clasificar($consulta['texto']);
            $this->repo->marcarClasificada($id, $resultado['categoria'], $resultado['clasificado_por']);
        } catch (ClasificacionException $e) {
            // Solo errores de la IA; un fallo de BD sí debe llegar al manejador global.
            error_log("[IA] Consulta $id: " . $e->getMessage());
            $this->repo->marcarError($id, $e->getMessage());
        }

        return $this->repo->buscar($id);
    }
}
