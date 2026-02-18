<?php
namespace Fluxion;

use Exception as _Exception;
use DateTime;
use DateInterval;

enum Time: string
{

    case LAST_YEAR = '-1 year|Y';
    case THIS_YEAR = '|Y';
    case NEXT_YEAR = '+1 year|Y';

    case YESTERDAY = '-1 day|Y-m-d';
    case TODAY = '|Y-m-d';
    case TOMORROW = '+1 day|Y-m-d';
    case ONE_WEEK_LATER = '+1 week|Y-m-d';

    case ONE_HOUR_AGO = '-1 hour';
    case NOW = '';
    case ONE_HOUR_LATER = '+1 hour';

    public function value(): ?string
    {

        try {

            $parts = explode('|', $this->value);

            $date = new DateTime($parts[0]);

            return $date->format($parts[1] ?? 'Y-m-d H:i:s');

        } catch (_Exception) {
            return null;
        }

    }

    public static function convert(?string $value, string $format = 'Y-m-d', ?array $formats = null): ?string
    {

        if (is_null($value)) {
            return null;
        }

        if (empty($formats)) {
            $formats = ['Y-m-d H:i:s.v', 'Y-m-d H:i:s.v', 'Y-m-d H:i:s', 'd/m/Y H:i:s', 'Y-m-d', 'd/m/Y', 'd.m.Y'];
        }

        $date = false;

        foreach ($formats as $format_in) {

            $date = DateTime::createFromFormat($format_in, $value);

            if ($date !== false) {

                if ($date->format('Y') < 1500 || $date->format('Y') > 2100) {
                    return null;
                }

                break;

            }

        }

        if ($date === false) {
            return null;
        }

        return $date->format($format);

    }

    public static function asValue(null|string|self $value = null): string
    {

        if (is_null($value)) {
            $value = self::TODAY->value();
        }

        elseif ($value instanceof self) {
            $value = $value->value();
        }

        return $value;

    }

    /**
     * @throws Exception
     */
    public static function modify(null|string|self $date, $modifier): string
    {

        $date = self::asValue($date);

        $d = DateTime::createFromFormat('Y-m-d', $date);

        if ($d === false) {
            throw new Exception("Data $date inválida!");
        }

        return $d->modify($modifier)->format('Y-m-d');

    }

    public static function getEasterDate(int $year): DateTime
    {

        $base = new DateTime("$year-03-21");

        $days = easter_days($year);

        return $base->add(new DateInterval("P{$days}D"));

    }

    public static function holidaysByYear(int $year): array
    {

        $key = "__holidays__$year";

        if (!Cache::hasValue($key)) {

            $f = [
                '01-01', // Confraternização universal
                '21-04', // Tiradentes
                '01-05', // Dia do Trabalho
                '07-09', // Independência do Brasil
                '12-10', // Nsa. Sra. Aparecida
                '02-11', // Finados
                '15-11', // Proclamação da República
                '25-12', // Natal
                //'31-12', // Sem movimento bancário
            ];

            if ($year >= 2024) {
                $f[] = '20-11'; // Consciência Negra
            }

            if ($p = self::getEasterDate($year)) {

                $f[] = (clone $p)->modify('-48 days')->format('d-m'); // Segunda-feira de Carnaval
                $f[] = (clone $p)->modify('-47 days')->format('d-m'); // Terça-feira de Carnaval
                $f[] = (clone $p)->modify('-2 days')->format('d-m'); // Sexta-feira Santa
                $f[] = (clone $p)->modify('+60 days')->format('d-m'); // Corpus Christi

            }

            Cache::setValue($key, $f);

        }

        return Cache::getValue($key);

    }

    /**
     * @throws Exception
     */
    public static function workDay(null|string|self $date = null): ?bool
    {

        $date = self::asValue($date);

        $d = DateTime::createFromFormat('Y-m-d', $date);

        if ($d === false) {
            throw new Exception("Data $date inválida!");
        }

        return (

            // De segunda (1) à sexta (5)
            $d->format('N') <= 5

            // Demais feriados fixos e móveis
            && !in_array($d->format('d-m'), self::holidaysByYear($d->format('Y')))

        );

    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    public static function nextWorkDay(null|string|self $date = null): string
    {

        $date = self::asValue($date);

        $i = 0;

        while (!self::workDay($date)) {

            $date = self::modify($date, '+1 day');

            if ($i++ >= 10) {
                throw new Exception("Algo deu errado!");
            }

        }

        return $date;

    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    public static function lastWorkDay(null|string|self $date = null): string
    {

        $date = self::asValue($date);

        $i = 0;

        while (!self::workDay($date)) {

            $date = self::modify($date, '-1 day');

            if ($i++ >= 10) {
                throw new Exception("Algo deu errado!");
            }

        }

        return $date;

    }

    /**
     * @throws Exception
     * @noinspection PhpUnused
     */
    public static function workDaysBetween(null|string|self $date1, null|string|self $date2): int
    {

        $date1 = self::asValue($date1);
        $date2 = self::asValue($date2);

        $d1 = DateTime::createFromFormat('Y-m-d', $date1);

        if ($d1 === false) {
            throw new Exception("Data $date1 inválida!");
        }

        $d2 = DateTime::createFromFormat('Y-m-d', $date2);

        if ($d2 === false) {
            throw new Exception("Data $date2 inválida!");
        }

        $date1 = $d1->format('Y-m-d');
        $date2 = $d2->format('Y-m-d');

        if ($date1 == $date2) {
            return 0;
        }

        $days = 0;
        $multiply = 1;

        $date = $date1;
        $target = $date2;

        if ($date > $date2) {

            $date = $date2;
            $target = $date1;

            $multiply = -1;

        }

        while ($date < $target) {

            $date = self::modify($date, '+1 day');

            if (self::workDay($date)) {
                $days++;
            }

        }

        return $days * $multiply;

    }

}
