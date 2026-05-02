<?php
/**
 * SunatBuilder — Construye el payload JSON que la API Laravel espera.
 *
 * Convierte los datos del dominio TechRepair (venta, cliente, venta_detalle)
 * al formato que pide GenerarComprobanteRequest.
 */
class SunatBuilder
{
    /**
     * @param array $venta   Fila de `ventas` con tipo_doc, serie_doc, num_doc, created_at.
     * @param array $cliente Fila de `clientes` (tipo, nombre, ruc_dni, direccion).
     * @param array $items   Filas de `venta_detalle` enriquecidas con prod_nombre.
     */
    public static function buildComprobante(array $venta, array $cliente, array $items): array
    {
        $tipo = $venta['tipo_doc']; // 'factura' | 'boleta'

        return [
            'endpoint'      => SUNAT_ENDPOINT,
            'documento'     => $tipo,
            'empresa'       => self::empresa(),
            'cliente'       => self::cliente($cliente, $tipo),
            'serie'         => $venta['serie_doc'],
            'numero'        => (string) $venta['num_doc'],
            'fecha_emision' => $venta['created_at'] ?? date('Y-m-d H:i:s'),
            'moneda'        => 'PEN',
            'forma_pago'    => 'contado',
            'detalles'      => self::detalles($items),
        ];
    }

    private static function empresa(): array
    {
        return [
            'ruc'             => SUNAT_RUC,
            'usuario'         => SUNAT_USUARIO_SOL,
            'clave'           => SUNAT_CLAVE_SOL,
            'razon_social'    => SUNAT_RAZON_SOCIAL,
            'nombreComercial' => SUNAT_NOMBRE_COMERCIAL,
            'direccion'       => SUNAT_DIRECCION,
            'ubigueo'         => SUNAT_UBIGEO,
            'distrito'        => SUNAT_DISTRITO,
            'provincia'       => SUNAT_PROVINCIA,
            'departamento'    => SUNAT_DEPARTAMENTO,
        ];
    }

    /**
     * En TechRepair, `clientes` usa un único campo `ruc_dni` que puede ser
     * DNI (8 dígitos) o RUC (11 dígitos). Discriminamos por longitud.
     */
    private static function cliente(array $c, string $tipo): array
    {
        $doc = trim($c['ruc_dni'] ?? '');
        $nom = trim($c['nombre'] ?? '') ?: 'CLIENTE';
        $dir = trim($c['direccion'] ?? '-') ?: '-';

        if ($tipo === 'factura') {
            if (strlen($doc) !== 11) {
                throw new RuntimeException("El cliente '$nom' no tiene RUC válido (11 dígitos). Las facturas requieren RUC.");
            }
            return ['tipo_doc' => '6', 'num_doc' => $doc, 'rzn_social' => $nom, 'direccion' => $dir];
        }

        // Boleta
        if (strlen($doc) === 8) {
            return ['tipo_doc' => '1', 'num_doc' => $doc, 'rzn_social' => $nom, 'direccion' => $dir];
        }
        return ['tipo_doc' => '0', 'num_doc' => '00000000', 'rzn_social' => $nom !== '' ? $nom : 'CLIENTE VARIOS', 'direccion' => $dir];
    }

    /**
     * `precio_unit` se asume CON IGV incluido (el servicio Greenter divide
     * entre 1.18 internamente).
     */
    private static function detalles(array $items): array
    {
        $out = [];
        foreach ($items as $i => $it) {
            $out[] = [
                'cod_producto' => (string) ($it['prod_codigo'] ?? $it['producto_id'] ?? ($i + 1)),
                'unidad'       => 'NIU',
                'descripcion'  => $it['prod_nombre'] ?? 'Producto',
                'cantidad'     => (float) ($it['cantidad'] ?? 1),
                'precio'       => (float) ($it['precio_unit'] ?? 0),
            ];
        }
        return $out;
    }
}
