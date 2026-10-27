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
        'commercial.view'=>'Consultar operaciones y respaldos comerciales',
        'commercial.manage'=>'Registrar interesados, negociar y confirmar reservas/ventas',
        'commercial.history'=>'Verificar reservas y ventas históricas',
        'commercial.cancel'=>'Cancelar interesados, negociaciones y reservas con motivo',
        'commercial.price_override'=>'Autorizar precios pactados diferentes del catálogo',
        'prices.manage'=>'Administrar compatibilidad y precios',
        'settings.manage'=>'Modificar plazos generales',
    ];
    public array $matrix = [
        'admin'=>['inventory.view','inventory.manage','inventory.import','prices.manage','settings.manage','clients.view','clients.manage','clients.link','commercial.view','commercial.manage','commercial.history','commercial.cancel','commercial.price_override'],
        'assistant'=>['inventory.view','inventory.manage','clients.view','clients.manage','clients.link','commercial.view'],
        'ceo'=>['inventory.view','clients.view','commercial.view','commercial.history','commercial.cancel','commercial.price_override'], 'sales'=>['inventory.view','clients.view','clients.manage','clients.link','commercial.view','commercial.manage'],
        'finance'=>['inventory.view','clients.view','commercial.view'], 'viewer'=>['inventory.view'],
    ];
}
