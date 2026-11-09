<?php
declare(strict_types=1);

/**
 * Acceso a datos de la tabla `consultas`.
 * Todo el SQL del proyecto está aquí y SIEMPRE con consultas preparadas
 * (los datos del usuario nunca se concatenan en el SQL -> sin inyección SQL).
 */
final class ConsultaRepository
{
    public const CATEGORIAS = ['Soporte Técnico', 'Duda Comercial', 'Agradecimiento'];
    public const ESTADOS = ['pendiente', 'clasificada', 'error'];

    private const COLUMNAS = 'id, nombre_tienda, email, texto, categoria, estado,
                              clasificado_por, error_msg, created_at, processed_at';

    public function __construct(private PDO $db) {}

    /** Listado con filtros opcionales, más recientes primero. */
    public function listar(?string $categoria = null, ?string $estado = null, int $limite = 100): array
    {
        $where = [];
        $params = [];

        if ($categoria !== null) {
            $where[] = 'categoria = :categoria';
            $params['categoria'] = $categoria;
        }
        if ($estado !== null) {
            $where[] = 'estado = :estado';
            $params['estado'] = $estado;
        }

        $sql = 'SELECT ' . self::COLUMNAS . ' FROM consultas'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY created_at DESC, id DESC LIMIT :limite';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue(":$clave", $valor);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Totales por categoría y por estado (para los contadores del frontend). */
    public function resumen(): array
    {
        $resumen = [
            'total' => 0,
            'por_categoria' => array_fill_keys(self::CATEGORIAS, 0),
            'por_estado' => array_fill_keys(self::ESTADOS, 0),
        ];

        $filas = $this->db->query(
            'SELECT categoria, estado, COUNT(*) AS n FROM consultas GROUP BY categoria, estado'
        )->fetchAll();

        foreach ($filas as $fila) {
            $n = (int) $fila['n'];
            $resumen['total'] += $n;
            $resumen['por_estado'][$fila['estado']] += $n;
            if ($fila['categoria'] !== null) {
                $resumen['por_categoria'][$fila['categoria']] += $n;
            }
        }
        return $resumen;
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUMNAS . ' FROM consultas WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Guarda la consulta en estado 'pendiente' y devuelve su id. */
    public function crear(string $nombreTienda, string $email, string $texto): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO consultas (nombre_tienda, email, texto, estado)
             VALUES (:nombre_tienda, :email, :texto, \'pendiente\')'
        );
        $stmt->execute(['nombre_tienda' => $nombreTienda, 'email' => $email, 'texto' => $texto]);
        return (int) $this->db->lastInsertId();
    }

    public function marcarClasificada(int $id, string $categoria, string $clasificadoPor): void
    {
        $stmt = $this->db->prepare(
            'UPDATE consultas
                SET categoria = :categoria, estado = \'clasificada\', clasificado_por = :por,
                    error_msg = NULL, processed_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute(['categoria' => $categoria, 'por' => $clasificadoPor, 'id' => $id]);
    }

    public function marcarError(int $id, string $mensaje): void
    {
        $stmt = $this->db->prepare(
            'UPDATE consultas
                SET estado = \'error\', error_msg = :mensaje, processed_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute(['mensaje' => mb_substr($mensaje, 0, 255), 'id' => $id]);
    }
}
