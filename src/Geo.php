<?php
namespace Fluxion;

class Geo
{

    public static function area(array $pontos): float
    {

        $soma = 0;

        foreach ($pontos as $i => $ponto) {

            if (!isset($pontos[$i + 1])) {
                break;
            }

            $proximo = $pontos[$i + 1];

            $soma += ($ponto['lat'] * $proximo['lng'] - $proximo['lat'] * $ponto['lng']);

        }

        return $soma / 2;

    }

    public static function centro(array $pontos, int $casas = 6): array
    {

        $area = self::area($pontos);

        if ($area == 0) {

            return ['lat' => 0, 'lng' => 0];

        }

        $soma_lat = 0;
        $soma_lng = 0;

        foreach ($pontos as $i => $ponto) {

            if (!isset($pontos[$i + 1])) {
                break;
            }

            $proximo = $pontos[$i + 1];

            $parte = ($ponto['lat'] * $proximo['lng'] - $proximo['lat'] * $ponto['lng']);

            $soma_lat += ($ponto['lat'] + $proximo['lat']) * $parte;
            $soma_lng += ($ponto['lng'] + $proximo['lng']) * $parte;

        }

        $soma_lat /= (6 * $area);
        $soma_lng /= (6 * $area);

        return ['lat' => round($soma_lat, $casas), 'lng' => round($soma_lng, $casas)];

    }

}
