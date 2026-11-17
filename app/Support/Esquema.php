<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Esquema
{
    private static array $cache = [];

    public static function existeTabla(string $tabla): bool
    {
        return Schema::hasTable($tabla);
    }

    public static function columnas(string $tabla): array
    {
        if (isset(self::$cache[$tabla])) {
            return self::$cache[$tabla];
        }

        if (! self::existeTabla($tabla)) {
            return self::$cache[$tabla] = [];
        }

        $base = DB::connection()->getDatabaseName();

        /*
        |--------------------------------------------------------------------------
        | MySQL 8 / information_schema
        |--------------------------------------------------------------------------
        |
        | MySQL puede devolver COLUMN_NAME, DATA_TYPE, etc. con su
        | capitalización canónica. Usamos alias propios para evitar
        | depender de ello.
        |
        */
        $filas = DB::table('information_schema.columns')
            ->where('table_schema', $base)
            ->where('table_name', $tabla)
            ->orderBy('ordinal_position')
            ->get([
                DB::raw('COLUMN_NAME AS nombre_columna'),
                DB::raw('DATA_TYPE AS tipo_dato'),
                DB::raw('COLUMN_TYPE AS tipo_completo'),
                DB::raw('IS_NULLABLE AS permite_nulo'),
                DB::raw('COLUMN_DEFAULT AS valor_predeterminado'),
                DB::raw('EXTRA AS extra_columna'),
                DB::raw('GENERATION_EXPRESSION AS expresion_generada'),
            ]);

        $columnas = [];

        foreach ($filas as $fila) {
            $nombre = (string) $fila->nombre_columna;

            $columnas[$nombre] = [
                'nombre' => $nombre,
                'tipo' => strtolower((string) $fila->tipo_dato),
                'tipo_completo' => (string) $fila->tipo_completo,
                'nullable' => strtoupper((string) $fila->permite_nulo) === 'YES',
                'default' => $fila->valor_predeterminado,
                'extra' => (string) $fila->extra_columna,
                'generada' => trim((string) $fila->expresion_generada) !== '',
            ];
        }

        return self::$cache[$tabla] = $columnas;
    }

    public static function tiene(string $tabla, string $columna): bool
    {
        return array_key_exists(
            $columna,
            self::columnas($tabla)
        );
    }

    public static function resolver(
        string $tabla,
        array|string $candidatas
    ): ?string {
        foreach ((array) $candidatas as $columna) {
            if (self::tiene($tabla, $columna)) {
                return $columna;
            }
        }

        return null;
    }

    public static function enum(
        string $tabla,
        string $columna
    ): array {
        $meta = self::columnas($tabla)[$columna] ?? null;

        if (
            ! $meta ||
            ! str_starts_with(
                strtolower($meta['tipo_completo']),
                'enum('
            )
        ) {
            return [];
        }

        preg_match_all(
            "/'((?:[^'\\\\]|\\\\.)*)'/",
            $meta['tipo_completo'],
            $m
        );

        return array_map(
            static fn ($v) => stripcslashes($v),
            $m[1] ?? []
        );
    }

    public static function filtrar(
        string $tabla,
        array $datos
    ): array {
        $columnas = self::columnas($tabla);
        $salida = [];

        foreach ($datos as $columna => $valor) {
            if (! isset($columnas[$columna])) {
                continue;
            }

            if ($columnas[$columna]['generada']) {
                continue;
            }

            if (
                str_contains(
                    strtolower($columnas[$columna]['extra']),
                    'auto_increment'
                )
            ) {
                continue;
            }

            $salida[$columna] = $valor;
        }

        return $salida;
    }

    public static function agregarCreadoEn(
        string $tabla,
        array $datos
    ): array {
        $columna = self::resolver(
            $tabla,
            ['creado_en', 'created_at']
        );

        if (
            $columna &&
            ! array_key_exists($columna, $datos)
        ) {
            $datos[$columna] = now();
        }

        return $datos;
    }

    public static function agregarActualizadoEn(
        string $tabla,
        array $datos
    ): array {
        $columna = self::resolver(
            $tabla,
            ['actualizado_en', 'updated_at']
        );

        if ($columna) {
            $datos[$columna] = now();
        }

        return $datos;
    }

    public static function etiquetaEnum(
        string $valor
    ): string {
        return ucfirst(
            mb_strtolower(
                str_replace('_', ' ', $valor)
            )
        );
    }
}