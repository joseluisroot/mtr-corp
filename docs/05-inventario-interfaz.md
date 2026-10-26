# Etapa 2 — acceso e inventario

## Actualizar una instalación existente

Trabajar con los archivos de esta entrega, conservar `.env` y comprobar que apunta a la base de desarrollo elegida. Hacer respaldo de la base antes de actualizar una instalación con datos.

```powershell
Set-Location C:\MTR\mtr-corp
composer install
php spark migrate:status
php spark migrate --all
php spark db:seed InitialSettingsSeeder
php spark migrate:status
```

La nueva migración es **2026-10-26-000004_CreateInventoryAudit**. Agrega exclusivamente la tabla `inventory_audit`; las tres migraciones anteriores permanecen sin cambios. El seeder conserva valores ya administrados. No ejecutar rollback para actualizar.

## Primer acceso

Crear la cuenta de administración desde la consola, completando los datos y contraseña en las preguntas del comando:

```powershell
php spark shield:user create -g admin
php spark shield:user list
php spark serve --host 127.0.0.1 --port 8080
```

Visitar `http://localhost:8080/login`. El valor de `app.baseURL` en `.env` debe coincidir con el host y puerto usados; no mezclar `localhost` y `127.0.0.1` durante una sesión. Los enlaces y formularios usan esa dirección. No hay usuario ni contraseña predeterminados. El registro público está deshabilitado. No ejecutar `shield:setup` sobre esta configuración: las rutas, filtros y vistas ya están preparados.

Para otra cuenta, usar el mismo comando con `-g viewer`, `sales`, `assistant`, `finance` o `ceo`. Agregar una cuenta existente a otro grupo con `php spark shield:user addgroup -n NOMBRE -g GRUPO`. Reemplazar los valores por los elegidos. Actualmente la administración de cuentas y grupos se hace por consola; todavía no existe pantalla de usuarios.

| Perfil | Inventario | Lotes y catálogos | Compatibilidad y precios | Plazos |
| --- | --- | --- | --- | --- |
| admin | Consulta | Administración | Administración | Administración |
| assistant | Consulta | Administración | Sin permiso inicial | Sin permiso inicial |
| ceo, sales, finance, viewer | Consulta | Sin permiso inicial | Sin permiso inicial | Sin permiso inicial |

Es una matriz inicial de mínimo acceso para este módulo. CEO puede recibir permisos adicionales expresamente, y los permisos de depósitos/conciliación se agregarán cuando se implemente ese módulo. Los permisos se verifican en servidor y los botones se muestran según el acceso. Se usan sesiones Shield, control de intentos de acceso, CSRF en formularios y páginas privadas sin caché.

## Qué se puede hacer

1. En **Catálogos**, crear proyecto, polígonos, tipologías y modelos. Editar nombre/código, actividad y dimensiones de referencia de la tipología. Las medidas reales de cada lote se conservan por separado. Un polígono registrado no se traslada entre proyectos.
2. Cambiar la política del polígono: bloqueado, parcial o habilitado. No se altera una reserva/venta existente al cambiar la política.
3. Crear un lote con número de texto, dimensiones, tipología y observaciones. La habilitación individual se aplica a polígonos parciales. Código y número son únicos dentro de su ámbito.
4. Consultar el tablero por polígono o la tabla DataTables, con búsqueda, orden y paginación. Los filtros generales afectan tarjetas, tabla y conteos; la búsqueda propia de DataTables solo filtra la tabla y lo indica en su contador.
5. Abrir la ficha, editar lotes todavía disponibles, habilitar/deshabilitar modelos y revisar precios de solo terreno o terreno/casa.
6. Registrar una nueva tarifa vigente ahora. Conserva la anterior y su intervalo; terreno y construcción son componentes independientes del precio de paquete. No modifica automáticamente otras combinaciones o tarifas. Las revisiones se serializan por lote y rechazan una revisión en el mismo segundo o tarifas futuras que requieran revisión manual.
7. Administrar los días de negociación y gracia con motivo obligatorio. Cada cambio conserva actor, valores y fecha. No se aplica retroactividad a negociaciones/cuotas porque sus módulos aún no existen.

El estado comercial no puede editarse desde el formulario de lote. Los futuros módulos de negociación y venta serán sus responsables. Las dimensiones y compatibilidad se validan por un responsable; el sistema no deduce automáticamente viabilidad arquitectónica por área.

La vista usa datos registrados: no carga automáticamente el Excel, modelos públicos ni precios de la web. Una instalación vacía muestra un estado inicial con acceso a catálogos. La demostración visual usa datos sintéticos de otra base local, nunca datos de clientes reales.

## Recursos visuales

Tailwind CSS, DataTables y SweetAlert2 se compilan y sirven localmente. Los archivos ya compilados están en `public/assets`; no hace falta Node para ejecutar PHP. Para modificar interfaces y recompilar, instalar Node compatible con el archivo de dependencias:

```powershell
npm ci
npm run build
```

El logo se conserva como imagen proporcionada por el usuario. No se generaron variantes ni se alteró el archivo original.

## Verificación

MySQL 8.4.7 y PHP 8.3.28, bases temporales independientes: **8 pruebas, 77 aserciones**, incluyendo la primera entrega. Verificadas restricciones, duplicados, edición simultánea por versión, bloqueo comercial, historial de precios, compatibilidad, rollback de escritura y auditoría, edición de parámetros, consultas permitidas y escrituras denegadas a un perfil de consulta. No se usaron bases reales del usuario.

Pruebas de integración con la conexión `database.tests` a una base descartable cuyo nombre termine en `_test`, siguiendo la guía de la etapa 1:

```powershell
$env:MTR_MYSQL_TESTS = '1'
php vendor/bin/phpunit --no-coverage
Remove-Item Env:MTR_MYSQL_TESTS
```

Se revisaron en navegador la pantalla de acceso, inventario adaptable, cambio a tabla, búsqueda, ficha y confirmación/guardado de un precio con historial. Ajustadas codificación de acentos y distribución de filtros en pantallas medianas. Los servicios fijan expresamente la zona horaria de negocio para que precios y auditoría coincidan entre web y consola.

## Alcance pendiente

Reservas y vencimientos, clientes, ventas, cuotas/mora, depósitos, recibos, conciliación, edición visual de permisos e importación. El tablero muestra estados comerciales, no indicadores financieros ficticios. Las tarifas por tipología y cotización de construcción posterior se agregarán con las operaciones.

DataTables pagina el inventario filtrado en el navegador. Para inventarios de muchos miles de lotes se incorporará paginación en servidor; esta entrega está dirigida al inventario inicial del residencial. Las tipologías pueden desactivarse, pero no se eliminan físicamente registros con historia.
