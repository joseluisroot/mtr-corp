# MTR Corp — Villa Verde

Plataforma administrativa en construcción. PHP, CodeIgniter 4.7.5 y MySQL; Shield 1.4.1 como base de autenticación. Dependencias exactas en composer.lock.

Etapa 3: acceso con Shield, inventario, catálogos, compatibilidad, precios y plazos con auditoría; importación de lotes desde Excel con revisión previa y confirmación. Los módulos de clientes, operaciones y finanzas siguen pendientes.

- [Instalación y comandos de migraciones](docs/01-migraciones.md)
- [Modelo de datos y etapas](docs/02-modelo-de-datos.md)
- [Revisión estructural del Excel](docs/03-revision-excel.md)
- [Etapa 2: actualización, acceso y pantallas](docs/05-inventario-interfaz.md)
- [Etapa 3: importación con revisión previa y migración 000005](docs/06-importacion-inventario.md)

No incluir Excel, documentos de clientes, comprobantes, estados de cuenta ni credenciales en este repositorio público. Configurar el servidor web para servir únicamente `public/` cuando se habilite la aplicación.
