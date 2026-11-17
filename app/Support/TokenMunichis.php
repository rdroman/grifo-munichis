<?php

namespace App\Support;

final class TokenMunichis
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generarSecreto(): string
    {
        $secreto = '';
        foreach (unpack('C*', random_bytes(32)) as $byte) {
            $secreto .= self::ALFABETO[$byte & 31];
        }

        return $secreto;
    }

    public static function pasoValido(string $secreto, string $codigo, ?int $ultimo, ?int $instante = null): ?int
    {
        $actual = intdiv($instante ?? time(), 30);
        foreach ([$actual, $actual - 1, $actual + 1] as $paso) {
            if ($paso > ($ultimo ?? -1) && hash_equals(self::codigo($secreto, $paso), $codigo)) {
                return $paso;
            }
        }

        return null;
    }

    private static function codigo(string $secreto, int $paso): string
    {
        $binario = '';
        $bits = 0;
        $acumulado = 0;
        foreach (str_split($secreto) as $caracter) {
            $acumulado = ($acumulado << 5) | strpos(self::ALFABETO, $caracter);
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $binario .= chr(($acumulado >> $bits) & 255);
                $acumulado &= (1 << $bits) - 1;
            }
        }

        $mac = hash_hmac('sha1', pack('N2', intdiv($paso, 4294967296), $paso % 4294967296), $binario, true);
        $inicio = ord($mac[19]) & 15;
        $numero = unpack('N', substr($mac, $inicio, 4))[1] & 0x7fffffff;

        return str_pad((string) ($numero % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
