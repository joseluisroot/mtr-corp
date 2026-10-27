<?php
declare(strict_types=1);
namespace App\Services;
use DomainException;

final class CommercialMoney
{
    public static function amount(mixed $value): string
    {
        if(!is_scalar($value) || !preg_match('/^\d{1,12}(\.\d{1,2})?$/',(string)$value)) throw new DomainException('Los importes deben ser no negativos, sin separadores de miles y con hasta dos decimales.');
        [$whole,$fraction]=array_pad(explode('.',(string)$value,2),2,'');
        return (ltrim($whole,'0')?:'0').'.'.str_pad($fraction,2,'0');
    }
    public static function cents(string $amount): int { return (int)str_replace('.','',self::amount($amount)); }
    public static function checkTotal(string $land,string $construction): void
    {
        $total=self::cents($land)+self::cents($construction);
        if($total<=0 || $total>99999999999999) throw new DomainException('El total pactado debe ser positivo y estar dentro del rango admitido.');
    }
}
