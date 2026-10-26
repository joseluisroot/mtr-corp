# Etapa 1: estructura de inventario

Este documento conserva las instrucciones de la primera entrega. Para el avance actual con interfaces y la migración 000004, continuar con [la guía de etapa 2](05-inventario-interfaz.md).

Esta entrega prepara las tablas. Todavía no incorpora las pantallas, importación, ventas, pagos ni conciliación. Los permisos operativos se incorporarán junto con las acciones que protegen. Shield está instalado y el registro público está deshabilitado; no se han creado cuentas ni publicado rutas de acceso.

## Requisitos

PHP 8.2 o superior compatible con las dependencias fijadas, Composer 2 y MySQL 8.0.16 o posterior. Extensiones PHP: intl, mbstring, mysqli y las exigidas por Composer. Se verificó con PHP 8.3.28 y MySQL 8.4.7. No usar MariaDB ni SQLite con estas migraciones.

## Preparación

En PowerShell, desde la carpeta del proyecto:

```powershell
Set-Location C:\MTR\mtr-corp
composer install
composer check-platform-reqs
Copy-Item .env.example .env
```

Copiar el ejemplo solo en la primera instalación; no sobrescribir un `.env` existente. Editar `.env` con servidor, puerto, nombre de base, usuario y contraseña propios. No ejecutar los siguientes pasos hasta terminar esta configuración. No guardar credenciales en Git.

Crear una base vacía desde la consola de MySQL o phpMyAdmin:

```sql
CREATE DATABASE mtr_corp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Usar un usuario con permisos sobre esa base; la instalación de tablas requiere CREATE, ALTER, INDEX y REFERENCES, además de lectura/escritura. DROP solo es necesario para reversiones autorizadas. Nunca usar credenciales de las pruebas en producción.

## Aplicar la primera entrega

```powershell
php spark migrate:status
php spark migrate --all
php spark db:seed InitialSettingsSeeder
php spark migrate:status
```

`--all` incluye las migraciones oficiales de Shield y Settings antes de las de la aplicación. Es necesario porque las referencias a usuarios dependen de Shield. En avances posteriores, ejecutar otra vez `php spark migrate --all`: solo aplica migraciones pendientes. El seeder inicial no sobrescribe configuraciones existentes y puede repetirse secuencialmente.

Verificación desde MySQL:

```sql
SELECT setting_key, value_days FROM business_settings ORDER BY setting_key;
SHOW TABLES;
```

Valores iniciales esperados: `negotiation_days = 15`, `payment_grace_days = 10`. No se cargan lotes, clientes ni precios reales. Los polígonos nacen bloqueados y los lotes sin habilitación comercial; disponibilidad comercial debe calcularse considerando ambas reglas y la actividad del proyecto.

## Migraciones propias

| Archivo | Contenido |
| --- | --- |
| 2026-10-26-000001_CreateBusinessSettings.php | Plazos configurables e historial |
| 2026-10-26-000002_CreatePropertyInventory.php | Proyectos, polígonos, tipologías, lotes, casas y compatibilidad |
| 2026-10-26-000003_CreateInventoryPrices.php | Precios por lote y combinación, con vigencia y desglose |

Las tablas soportan el prefijo configurado en `.env`. Moneda pertenece al proyecto; importes usan DECIMAL. El total lote/casa se calcula en MySQL a partir de sus componentes. La compatibilidad se registra antes de asignar precio a una combinación. Medidas son metros y metros cuadrados; dimensiones originales todavía deben verificarse.

Precio por tipología, tarifa de construcción posterior, acuerdos y precios pactados se agregarán con sus módulos. En esta etapa la tipología clasifica medidas y cada precio pertenece a un lote concreto. No se ha implementado todavía resolución de tarifas, validación de períodos superpuestos, modificación auditada de parámetros, control de concurrencia comercial ni vencimiento automático. Las tablas por sí solas no ejecutan estas reglas: se incorporarán en servicios con pruebas antes de habilitar pantallas.

## Revertir únicamente en una base de desarrollo descartable

```powershell
php spark migrate:rollback
php spark migrate:status
```

El rollback revierte la última tanda completa y elimina sus tablas y datos, incluidas las de Shield y Settings si se instalaron en la misma tanda. Este comando no admite limitar por namespace. No usarlo como procedimiento de actualización ni en una base con información útil. MySQL realiza confirmaciones implícitas del DDL: una migración interrumpida puede dejar tablas parciales. Revisar el error y el esquema antes de repetir; no usar `migrate:refresh` para resolverlo. Con datos reales, hacer respaldo previo y preparar una migración correctiva.

## Pruebas opcionales de MySQL

Usar otra base vacía y descartable cuyo nombre termine en `_test`, por ejemplo `mtr_foundation_test`. Configurar en `.env` las claves `database.tests.hostname`, `database.tests.database`, `database.tests.username`, `database.tests.password`, `database.tests.port`, `database.tests.DBDriver = MySQLi`, `database.tests.DBPrefix = test_`, `database.tests.charset = utf8mb4`, `database.tests.DBCollat = utf8mb4_unicode_ci`.

```powershell
$env:MTR_MYSQL_TESTS = '1'
php vendor/bin/phpunit --no-coverage tests/database/FoundationTest.php
Remove-Item Env:MTR_MYSQL_TESTS
```

La prueba crea y elimina sus tablas en esa conexión. Comprueba duplicados, claves foráneas, estados, medidas, precios decimales, períodos y seeder repetible, y ejecuta creación/reversión/recreación. No apuntar a una base real, ni siquiera con prefijo. Sin habilitación explícita la prueba se omite.

## Próxima entrega

Servicios de inventario/precios/configuración y permisos por acción, seguidos de las pantallas. Antes de importar el histórico, resolver los pendientes del informe del Excel. Las siguientes migraciones serán archivos nuevos; no editar migraciones ya ejecutadas.
