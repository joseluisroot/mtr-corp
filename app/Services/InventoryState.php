<?php
declare(strict_types=1);
namespace App\Services;

final class InventoryState
{
    public const LABELS = ['available'=>'Disponible','negotiating'=>'En negociación','reserved'=>'Reservado','sold'=>'Vendido','blocked'=>'Bloqueado'];

    public static function effective(array $lot): string
    {
        // Existing commitments remain visible even when future commercialization is blocked.
        if (in_array($lot['commercial_status'], ['reserved','sold'], true)) {
            return $lot['commercial_status'];
        }
        if (!(bool)$lot['project_active'] || $lot['sales_policy'] === 'blocked'
            || ($lot['sales_policy'] === 'partial' && !(bool)$lot['is_enabled'])) {
            return 'blocked';
        }
        return $lot['commercial_status'];
    }
}
