<?php
declare(strict_types=1);
namespace Config;

class AuthGroups extends \CodeIgniter\Shield\Config\AuthGroups
{
    public string $defaultGroup = 'viewer';
    public array $groups = [
        'admin' => ['title'=>'Administrador','description'=>'Configuración y administración del inventario.'],
        'ceo' => ['title'=>'CEO','description'=>'Consulta ejecutiva.'],
        'sales' => ['title'=>'Ventas','description'=>'Consulta de disponibilidad.'],
        'assistant' => ['title'=>'Asistente','description'=>'Consulta y mantenimiento del inventario.'],
        'finance' => ['title'=>'Finanzas','description'=>'Consulta de inventario.'],
        'viewer' => ['title'=>'Consulta','description'=>'Consulta del inventario.'],
    ];
    public array $permissions = [
        'inventory.view'=>'Consultar inventario',
        'inventory.manage'=>'Crear y editar lotes, polígonos y catálogos',
        'inventory.import'=>'Revisar y confirmar importaciones de inventario',
        'clients.view'=>'Consultar clientes y expedientes',
        'clients.manage'=>'Crear y editar clientes',
        'clients.link'=>'Vincular clientes a expedientes de propiedades',
        'prices.manage'=>'Administrar compatibilidad y precios',
        'settings.manage'=>'Modificar plazos generales',
    ];
    public array $matrix = [
        'admin'=>['inventory.view','inventory.manage','inventory.import','prices.manage','settings.manage','clients.view','clients.manage','clients.link'],
        'assistant'=>['inventory.view','inventory.manage','clients.view','clients.manage','clients.link'],
        'ceo'=>['inventory.view','clients.view'], 'sales'=>['inventory.view','clients.view','clients.manage','clients.link'],
        'finance'=>['inventory.view','clients.view'], 'viewer'=>['inventory.view'],
    ];
}
