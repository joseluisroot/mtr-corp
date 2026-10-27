<?php
declare(strict_types=1);
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class ExpireNegotiations extends BaseCommand
{
    protected $group='Comercial';
    protected $name='commercial:expire';
    protected $description='Finaliza asignaciones de negociación vencidas, conservando el historial.';
    public function run(array $params)
    {
        $count=(new \App\Services\CommercialService())->expireAll();
        CLI::write('Negociaciones vencidas procesadas: '.$count);
    }
}
