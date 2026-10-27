# Etapa 4 — clientes y relación con propiedades

## Activación

Desde la carpeta del proyecto, conservando `.env` y comprobando la base de destino:

```powershell
Set-Location C:\MTR\mtr-corp
composer install
php spark migrate:status
php spark migrate --all
php spark migrate:status
```

La migración nueva es **2026-10-26-000006_CreateClients**. Crea `clients` y `client_property_links`. No modifica migraciones anteriores, no necesita seeder y no importa automáticamente los clientes del Excel. Si 000005 aún no se aplicó, el comando la ejecutará antes de 000006. Respaldar antes de actualizar una instalación con información útil.

Hasta aplicar 000006, el menú Clientes muestra una instrucción de actualización y las fichas de inventario siguen funcionando.

## Uso del catálogo

En **Clientes** se pueden buscar expedientes por nombre, código, documento, correo y teléfono. La tabla pagina en servidor, 25 registros por página. Los perfiles con permiso pueden crear y editar clientes, o marcarlos inactivos. No hay eliminación física desde la interfaz.

Nombre completo es obligatorio; documento y contacto pueden quedar pendientes. El código es editable y único: puede usarse el identificador del registro previo o dejarlo vacío para generar uno. No se usa el nombre como identificador y se admiten homónimos.

Si se registra documento, se requiere tipo y país emisor de dos letras. Para evitar duplicados se compara país + tipo + número normalizado sin espacios o guiones. Esto no verifica autenticidad del documento ni consulta registros externos. El control de versión impide sobrescribir una edición simultánea sin recargar la ficha.

## Vincular un cliente existente

1. Abrir **Inventario**, seleccionar lote y buscar la sección **Cliente del expediente**.
2. Pulsar **Buscar y vincular cliente**.
3. Buscar en el catálogo, seleccionar un cliente activo y describir el antecedente que lo relaciona con la propiedad.
4. Confirmar. La ficha de propiedad muestra al cliente; su expediente muestra esa propiedad y cualquier otra vinculada.

Si todavía no existe, el buscador ofrece **Crear cliente** y permite volver al selector del lote después de guardarlo. La creación no lo vincula automáticamente: se confirma el vínculo expresamente.

Una propiedad tiene como máximo un cliente de expediente vigente en esta etapa. Un cliente puede estar vinculado a muchas propiedades. Para corregirlo se desvincula con motivo y se elige otro: el vínculo anterior conserva responsable, fechas y motivo. Una ficha antigua no puede desvincular por accidente un vínculo más reciente. Los clientes inactivos conservan sus antecedentes pero no se pueden seleccionar para vínculos nuevos.

Este vínculo es un antecedente de expediente, no prueba de titularidad ni contrato. No cambia estados comerciales, no aparta el lote y no confirma reservas o ventas. No modifica precios, cuotas, depósitos o saldos. Un lote importado sigue **Pendiente de expediente** después de vincular un cliente. La validación de su operación corresponderá al módulo comercial.

Los interesados múltiples se gestionarán mediante oportunidades y negociaciones; no se registran como varios titulares confirmados de una propiedad. La inversión comprometida y los depósitos se incorporarán al expediente con contratos y pagos reales; no se muestran totales financieros inventados.

## Permisos iniciales

| Perfil | Consulta de clientes | Crear/editar | Vincular/desvincular |
| --- | --- | --- | --- |
| admin | Sí | Sí | Sí |
| assistant | Sí | Sí | Sí |
| sales | Sí | Sí | Sí |
| ceo | Sí | No | No |
| finance | Sí | No | No |
| viewer | No | No | No |

Permisos independientes: `clients.view`, `clients.manage` y `clients.link`. La tabla es la asignación inicial de este módulo y se podrá ajustar con la administración de permisos. Los controles se aplican en servidor, no solo a botones. Un usuario solo de inventario no recibe nombres de clientes en la ficha de propiedad.

Cambios de clientes registran actor y campos modificados sin duplicar documentos o contactos en la auditoría general. Los vínculos conservan su historial propio. Datos personales y credenciales permanecen fuera del repositorio y de mensajes de error al usuario. No se enviaron correos ni se usaron servicios externos.

## Verificación

Suite completa en PHP 8.3.28 y MySQL 8.4.7 aislado: **14 pruebas, 170 aserciones**, sin fallos.

Prueba sintética de catálogo: documentos normalizados duplicados, homónimos, documentos incompletos, correo inválido, edición simultánea y clientes inactivos. Prueba de vínculos: un cliente con dos propiedades, rechazo de reemplazo silencioso, motivo de desvinculación, identificación de vínculo antiguo, conservación del historial y ausencia de cambios en disponibilidad o precios. Permisos de consulta/escritura y ocultación de datos a perfiles sin acceso.

En navegador se comprobó crear un cliente ficticio, seleccionarlo desde un lote importado de demostración y consultar la relación sin liberar el expediente pendiente. No se crearon clientes ni vínculos reales ni se aplicó 000006 a la instalación real durante el desarrollo.

Para ejecutar pruebas de integración, usar exclusivamente una base descartable `database.tests` cuyo nombre termine en `_test`:

```powershell
$env:MTR_MYSQL_TESTS = '1'
php vendor/bin/phpunit --no-coverage
Remove-Item Env:MTR_MYSQL_TESTS
```

## Próximos pasos

Importación revisada de clientes del Excel y mapeo a expedientes existentes; operaciones, reservas y negociaciones; cuotas, depósitos, recibos y conciliación. No usar rollback después de cargar información real: eliminaría tablas y vínculos; preparar una migración correctiva con respaldo.
