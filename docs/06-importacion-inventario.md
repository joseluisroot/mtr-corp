# Etapa 3 — importación del inventario con revisión previa

## Actualizar la instalación

Conservar `.env`, verificar el destino de la conexión y respaldar la base si ya contiene información. Desde PowerShell:

```powershell
Set-Location C:\MTR\mtr-corp
composer install
composer check-platform-reqs
php spark migrate:status
php spark migrate --all
php spark migrate:status
```

La migración nueva es **2026-10-26-000005_CreateInventoryImports**. Agrega las tablas de revisiones y filas de importación, y los campos `import_review_required` y `legacy_status` a los lotes. No modifica las cuatro migraciones anteriores ni carga registros comerciales. No se necesita ejecutar otro seeder.

Se incorpora PhpSpreadsheet mediante Composer para leer Excel. La aplicación PHP necesita las extensiones requeridas por Composer, entre ellas zip, xml, iconv y gd. `composer.lock` fija la versión instalada. El servidor PHP debe admitir una carga de 5 MB: revisar `upload_max_filesize` y `post_max_size` si el servidor rechaza archivos antes de que lleguen a la aplicación. Para seleccionar hasta 2,000 filas y sus políticas, configurar `max_input_vars` al menos en 5,000. Si PHP recorta el formulario, faltará la confirmación final y se rechazará la carga.

Los estilos compilados están incluidos. Para regenerarlos: `npm ci` y `npm run build`.

## Uso

1. Entrar con un perfil que tenga `inventory.import`. En la matriz inicial solo lo tiene `admin`.
2. Crear el proyecto en **Catálogos** si todavía no existe.
3. Ir a **Importar inventario**, seleccionar proyecto y archivo `.xlsx` y pulsar **Analizar y revisar**.
4. Revisar filas, errores y registros existentes. Este paso solo guarda una revisión; no crea polígonos, lotes, clientes, ventas ni pagos.
5. Marcar únicamente los lotes a cargar y elegir la política de los polígonos nuevos. Se proponen bloqueados; los polígonos existentes conservan su política. En un polígono parcial, los lotes nuevos nacen sin habilitación individual.
6. Confirmar la advertencia y la selección. La carga se aplica en una transacción; ante un conflicto no se importa ningún lote de esa selección.
7. Consultar el resultado y abrir las fichas desde sus enlaces. Una revisión confirmada no se aplica de nuevo. Para cargar filas antes omitidas, analizar nuevamente el archivo; los lotes existentes se señalarán y no se modificarán.

## Formato admitido y límites

Solo `.xlsx`, máximo 5 MB, hasta 2,000 registros. Debe existir una hoja llamada **Lotes**. Dentro de las primeras 20 filas deben aparecer las columnas A: ID propiedad, B: Polígono, C: Número de lote. Se admiten las variantes «Lote» y «Número de lote» para el tercer encabezado. Se reconocen por encabezado ID cliente, Estado actual/Estado y Condición del lote para identificar restricciones; nunca se retiene el ID del cliente.

Se valida el contenido ZIP, el número de componentes y el tamaño descomprimido. No se admiten macros. Se lee solo la hoja Lotes y las columnas necesarias hasta M. No se ejecutan fórmulas. Si el ID propiedad es una fórmula o está vacío pero el polígono y número son literales válidos, se deriva la clave polígono-número. Las fórmulas en polígono o número bloquean esa fila. Filas preparadas con fórmulas de ID pero sin polígono/número no generan propiedades.

Los números de lote siguen siendo texto y conservan ceros escritos como texto o mediante un formato numérico simple de ceros. Los códigos admiten letras, números y guion. Identificadores ausentes, inválidos o duplicados dentro del archivo bloquean esas filas. Los conflictos con la identificación ya registrada se omiten sin sobrescribirla.

## Qué se carga y qué se conserva pendiente

Se cargan identificadores de inventario y el vínculo al proyecto. Se conserva el archivo de origen mediante nombre y SHA-256, fila de origen y lote resultante. La subida original se procesa temporalmente; no se guarda el Excel completo ni se pone a disposición de descarga. La revisión conserva únicamente campos de inventario permitidos, no nombres, documentos, IDs de clientes ni importes privados de otras hojas.

Los estados del archivo son antecedentes. Cuando el origen indica reserva, venta, pago, negociación, bloqueo, una condición restrictiva o relación con cliente, el lote queda **Pendiente de expediente**. Es un indicador de revisión independiente del estado comercial y evita presentarlo como disponible. El estado previo normalizado se muestra en la ficha, sin crear una reserva o contrato ficticio. Una fórmula de estado solo aporta su valor almacenado, si existe; no se recalcula ni se considera validada.

No se importan dimensiones, tipos de lote, compatibilidades, precios, clientes, operaciones, cuotas, depósitos ni saldos. Los campos de venta del Excel no son tarifas de catálogo. La habilitación comercial y la liberación de los antecedentes se completarán después de verificar el expediente; no hay una casilla para omitir esa verificación.

## Integridad y permisos

La confirmación comprueba propietario de la revisión, permiso de ruta, pertenencia de filas, estado abierto y cambios de inventario desde el análisis. Bloquea revisión y proyecto durante la transacción, vuelve a consultar duplicados y mantiene las restricciones únicas de MySQL. Las filas inválidas o existentes no pueden forzarse modificando el formulario. Los nuevos lotes y polígonos generan auditoría. Una carga parcial seleccionada se cierra con las filas restantes marcadas como omitidas.

Cada usuario ve únicamente sus revisiones. El historial muestra las últimas 20. Los archivos no se incluyen en el repositorio. CSRF protege análisis y confirmación. Las tablas de revisiones conservan trazabilidad y no tienen eliminación desde la interfaz.

## Verificación

Suite completa en PHP 8.3.28 y MySQL 8.4.7 aislado: **13 pruebas, 132 aserciones**, sin fallos. Compilación de recursos y validación de dependencias satisfactorias.

Se verificó el lector contra el Excel original en lectura: detectó sus 5 lotes con antecedentes de reserva y los marcó para revisión de expediente. No se importaron en la instalación real ni se modificó el archivo.

Pruebas sintéticas: fórmulas de ID sin cálculo, filas preparadas vacías, duplicados de identificación, fórmulas inválidas en coordenadas, hojas ausentes, archivos no Excel, exclusión de datos de cliente, selección de filas, revisiones de otro usuario, confirmación repetida, cambios posteriores al análisis, reversión completa ante error y permisos. Pruebas de migración con creación, reversión y recreación.

Prueba visual en la base de demostración: carga de un archivo ficticio de cuatro filas, dos identificaciones válidas y dos duplicadas; revisión, confirmación SweetAlert y carga de solo dos lotes. Los duplicados quedaron rechazados. Esta demostración no usa datos personales.

Las pruebas de MySQL requieren la conexión `database.tests` a una base descartable cuyo nombre termine en `_test`:

```powershell
$env:MTR_MYSQL_TESTS = '1'
php vendor/bin/phpunit --no-coverage
Remove-Item Env:MTR_MYSQL_TESTS
```

## Reversión

No ejecutar rollback como actualización. En una base descartable, `php spark migrate:rollback` revierte la última tanda completa, no solo este archivo. La reversión de 000005 elimina revisiones y campos de revisión del inventario; después de una importación real perdería las señales que impiden ofrecer lotes pendientes de expediente. Con información útil, respaldar y preparar una migración correctiva en lugar de revertir.

## Pendiente de próximas entregas

Expedientes de clientes, operaciones históricas y resolución de lotes pendientes de expediente; negociaciones y reservas de 15 días; cuotas con gracia de 10 días; depósitos, recibos y conciliación. La importación de inventario no los sustituye ni calcula mora.
