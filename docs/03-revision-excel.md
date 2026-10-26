# Revisión estructural del origen

Revisión del 26/10/2026, en modo lectura. Archivo de origen: ControlAdministrativo.xlsx, hojas Consolidado, Movimientos, Clientes y Lotes. El original se conserva. Este informe contiene estructura y conteos, sin nombres de clientes, identificaciones, comprobantes ni importes privados.

## Registros encontrados

| Hoja | Registros con identificador |
| --- | ---: |
| Clientes | 3 |
| Lotes | 5 |
| Movimientos | 8 |
| Consolidado | 5 propiedades calculadas |

Las 200 filas de propiedades y 1,000 filas de movimientos son capacidad preparada; no equivalen a inventario real. Las fórmulas se revisaron estructuralmente y no se encontraron errores almacenados de Excel. No se ejecutó una recalculación en Excel, por lo que esto no demuestra que todas las fórmulas sean correctas.

## Controles realizados

- Identificadores de clientes y propiedades sin duplicados.
- Sin duplicados en la combinación polígono/número dentro de los lotes registrados.
- Los 8 movimientos apuntan a clientes y propiedades existentes.
- Los 5 lotes registrados figuran reservados; esto no describe todo el inventario del residencial.
- Consolidado es una salida calculada, no debe importarse como nuevas ventas o nuevos pagos.

## Pendientes antes de importar

- Un movimiento no tiene fecha de depósito (fila 8 de Movimientos). No inventar una fecha ni un mes.
- Los 8 movimientos carecen de referencia bancaria y número de recibo. Tener un nombre de archivo en la hoja no demuestra que el respaldo exista o coincida con un depósito bancario.
- Los 3 clientes carecen de identificación documentada en los campos DUI/pasaporte. Revisar teléfonos, direcciones y datos de contacto antes de completar expedientes.
- No hay columnas de dimensiones de lote; falta el inventario completo y las compatibilidades verificadas.
- La nomenclatura de modelos del Excel no coincide exactamente con el sitio público: se necesita un mapeo aprobado; no deducir equivalencias automáticamente.
- La nota del archivo describe A y B como reservados y la instrucción del usuario los describe bloqueados. Migrar la política comercial y las reservas de clientes como conceptos separados.
- No hay calendario completo de cuotas, por lo que no puede calcularse mora histórica confiable ni deducirse de «Próximo pago» solamente.
- Varias filas de movimientos pueden corresponder a aplicaciones de un mismo depósito. Sin referencias, agrupar solo tras verificar documentos; no fusionar por monto o cliente.
- La carga inicial no marcará pagos como conciliados sin estado bancario y revisión de Finanzas.

## Mapeo propuesto

| Origen | Destino futuro |
| --- | --- |
| Clientes | Expediente de cliente con ID de origen conservado |
| Lotes: identificación y condición | Inventario y política de comercialización |
| Lotes: cliente, compra, modelo y valor | Acuerdo comercial y precio pactado, no tarifa de catálogo |
| Movimientos | Depósitos y sus aplicaciones, tras revisar referencias y respaldos |
| Consolidado | Control de conciliación de la importación; no genera registros |

La importación tendrá previsualización, incidencias, control de duplicados, lote de importación y trazabilidad al registro de origen. Ningún dato personal del Excel será incluido en Git.
