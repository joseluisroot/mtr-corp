# Etapa 5 — operaciones comerciales

## Actualización

Conservar `.env`, comprobar la base de destino y respaldar antes de actualizar una instalación con datos. Desde PowerShell:

```powershell
Set-Location C:\MTR\mtr-corp
composer install
php spark migrate:status
php spark migrate --all
php spark migrate:status
```

La migración nueva es **2026-10-27-000007_CreateCommercialOperations**. Crea operaciones, eventos con condiciones históricas y documentos privados. No cambia migraciones anteriores ni modifica automáticamente C-1 u otra propiedad real. No hay nuevo seeder. Los recursos compilados están incluidos; para recompilar: `npm ci` y `npm run build`.

## Caso C-1: registrar el antecedente que ya existe

1. Abrir **Inventario → C-1**. Revisar primero que el cliente vinculado sea el correcto.
2. En **Seguimiento comercial**, elegir **Verificar reserva o venta histórica**. Requiere `commercial.history`.
3. Elegir reserva o venta, según el acuerdo verificado; ingresar su fecha real y el responsable de seguimiento.
4. Indicar solo lote o lote más casa. Para casa, configurar primero un modelo compatible y habilitado en la ficha del lote. El sistema no decide viabilidad arquitectónica por área.
5. Ingresar importes históricos separados para terreno y construcción, y referencia que los respalda. No usar una tarifa actual para inventar el precio de un acuerdo pasado. Si faltan datos, completar la verificación antes de confirmar.
6. Adjuntar respaldo PDF/JPG/PNG y confirmar expresamente la verificación. La confirmación crea la operación, conserva sus condiciones y respaldo, resuelve la señal de expediente pendiente y deja el estado comercial en reservado o vendido.

No hay que iniciar una negociación de 15 días para una reserva histórica. Un antecedente puede registrarse en un polígono actualmente bloqueado porque documenta un compromiso existente; no habilita ventas nuevas. La fecha no puede ser futura. Registrar una venta histórica no implica que esté pagada: cartera sigue pendiente.

## Interesados y negociaciones nuevas

Desde una propiedad comercialmente habilitada, elegir **Registrar interesado o negociación**. El buscador muestra hasta 25 clientes activos por búsqueda. Seleccionar cliente, responsable y detalle.

- **Interesado:** conserva el seguimiento sin apartar el lote. Puede haber varios clientes interesados, sin duplicar el mismo interesado abierto por cliente/lote.
- **Negociación asignada:** requiere modalidad y tarifa vigente. Tiene una asignación exclusiva por lote. Si ese cliente ya era interesado, se promueve su mismo seguimiento. Los demás interesados permanecen registrados.
- El plazo usa el valor vigente de `business_settings.negotiation_days`, inicialmente 15. Se conserva ese número y la hora de vencimiento; modificar la configuración no cambia plazos iniciados.
- Son días calendario desde el momento de asignación, con zona horaria America/El_Salvador. Cambiar condiciones de precio no reinicia el plazo.
- Un lote con expediente pendiente, proyecto inactivo, polígono bloqueado, habilitación parcial no concedida, reserva o venta vigente no admite una negociación nueva.

## Precios y compatibilidad

Primero se elige lote y después uno de sus modelos permitidos. Solo lote toma su tarifa de terreno; lote más casa toma el desglose de esa combinación. Se exige una única tarifa vigente. No se suman dos veces importes de terreno.

Importes vacíos usan la referencia de catálogo. Un precio distinto necesita `commercial.price_override` y motivo. Se conservan referencia, precio pactado y origen del precio. Los importes se validan y suman con precisión exacta, sin aritmética monetaria de punto flotante.

Durante una negociación vigente puede revisarse modalidad/modelo/precio, con historial de condiciones por evento y control de versión. Al reservar, las condiciones quedan fijas. Cambios posteriores del catálogo o desactivación de modelos no reescriben el acuerdo. Las modificaciones contractuales posteriores a una reserva se incorporarán mediante un flujo específico; no se editan silenciosamente.

## Confirmar reserva y venta

Una negociación vigente pasa a reserva con fecha, detalle y respaldo obligatorio. La reserva pasa a venta con su fecha y documento, conservando precio, cliente y modelo. Las fechas no pueden ser futuras ni anteriores a la etapa previa.

Antes de confirmar se comprueba el cliente del expediente. Si es otro, se exige corregir expresamente ese vínculo antes de continuar. Si no existe vínculo, se crea como consecuencia de la operación. Una reserva/venta vigente impide desvincular al cliente desde el catálogo.

Las reservas no vencen automáticamente: no se acordó esa política. Cancelar interesado, negociación o reserva requiere `commercial.cancel` y motivo; conserva historia y no registra devoluciones. No se permite cancelar una venta por este mecanismo. Los vínculos históricos de expediente se conservan; si corresponde cambiar el cliente después de cancelar una reserva, corregirlo con motivo en la ficha.

## Vencimientos

El tablero considera vencida una negociación cuando `expires_at <= ahora`; deja de presentarla como negociación vigente aun si no se ejecutó mantenimiento. Las acciones sobre ese lote procesan primero su vencimiento, sin eliminar interesados ni liberar reservas/ventas. Se conserva un evento del sistema.

Para persistir el cierre de todos los plazos vencidos:

```powershell
php spark commercial:expire
```

El comando es repetible y puede programarse en el servidor, por ejemplo cada cinco minutos. No se creó una tarea programada en el equipo durante esta entrega. No usa el período de gracia de cuotas: los 10 días de pagos siguen preparados para cartera.

## Documentos y permisos

Los respaldos se guardan fuera de `public/`, en `writable/uploads/commercial`, con nombres aleatorios, límite de 5 MB, tipo MIME PDF/JPEG/PNG y SHA-256. Solo usuarios con `commercial.view` pueden descargarlos mediante una ruta autenticada; no existe URL pública al archivo. La descarga verifica integridad y usa respuesta privada sin caché. Respaldar base y carpeta privada coordinadamente. Revisar `upload_max_filesize` y `post_max_size` si PHP limita la carga antes de la aplicación.

| Perfil | Consulta | Interesados/reservas/ventas nuevas | Antecedentes | Cancelar seguimientos/reservas | Autorizar precio distinto |
| --- | --- | --- | --- | --- | --- |
| admin | Sí | Sí | Sí | Sí | Sí |
| sales | Sí | Sí | No | No | No |
| ceo | Sí | No | Sí | Sí | Sí |
| assistant / finance | Sí | No | No | No | No |
| viewer | No | No | No | No | No |

Los permisos son independientes. Para revisar condiciones de negociaciones nuevas se requiere además `commercial.manage`; la matriz inicial de CEO permite antecedentes y autorizaciones sin darle toda la operación diaria. Los responsables seleccionables son cuentas activas; atribuir seguimiento no les concede permisos adicionales.

## Expediente de cliente e integridad

El cliente muestra operaciones y valor pactado vigente por moneda, sumando reservas y ventas activas. No se mezclan monedas ni se cuentan interesados, negociaciones, cancelaciones o vencimientos como inversión comprometida. Este valor no es dinero recibido ni saldo pendiente.

Las mutaciones bloquean proyecto, polígono, lote y registros relevantes. Restricciones únicas de MySQL impiden dos operaciones exclusivas sobre el mismo lote. Lecturas actuales con bloqueo evitan actuar sobre una versión anterior después de esperar otra transacción. La fecha efectiva del acuerdo se conserva separada de la fecha de registro. Los documentos, estados y condiciones históricas no se borran al cambiar de etapa.

## Verificación y límites

Suite completa: **18 pruebas, 245 aserciones**, sin fallos.

Pruebas en MySQL 8.4.7 y PHP 8.3.28 aislados: escenarios de interesados múltiples, exclusividad, vencimiento idempotente, configuración de plazo no retroactiva, modelos incompatibles, ausencia de tarifa, permisos para diferencias de precio, condiciones fijas, reservas/ventas con respaldo, antecedentes, vínculo de cliente, importes exactos, consulta privada y descarga autorizada.

Una prueba con dos conexiones conserva una reserva confirmada por otra conexión aunque exista una instantánea anterior de la negociación. Se verifican creación, reversión y recreación de migraciones junto con los módulos previos.

En navegador se registró una reserva histórica sobre **R-01 de demostración**, con cliente, fecha y PDF ficticios, y se verificaron su estado y respaldo privado. No se registró ni verificó C-1 real, no se cargaron documentos reales y no se aplicó 000007 en la base real durante el desarrollo.

Pendiente: adendas contractuales, construcción contratada después de comprar terreno, anulación de ventas y sus efectos financieros, calendarios de cuotas, depósitos, recibos y conciliación bancaria. Esta etapa no registra cobros. No usar rollback después de registrar acuerdos: eliminaría su historia y documentos asociados; usar respaldo y migraciones correctivas.
