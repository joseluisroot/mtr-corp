# Verificación de la primera entrega

Fecha: 26/10/2026. PHP 8.3.28, CodeIgniter 4.7.5, Shield 1.4.1, MySQL 8.4.7. MySQL temporal independiente, con directorio de datos separado y puerto local distinto; no se usaron bases existentes del usuario.

- Instalación de dependencias y comprobación de requisitos de plataforma: correctas.
- Validación de composer.json y composer.lock: correcta.
- Sintaxis PHP de migraciones, base de migración, seeder y prueba: correcta.
- `php spark migrate --all`: correcto, incluidas dependencias oficiales.
- `php spark db:seed InitialSettingsSeeder`: correcto; repetir no duplica registros.
- `php spark migrate:rollback`: correcto en la base descartable; revierte toda la tanda, incluidas dependencias.
- Nueva ejecución de migraciones y seeder: correcta.
- Repetición de `migrate --all` sin cambios pendientes: correcta.
- Prueba MySQL con prefijo `test_`: 1 prueba, 18 aserciones, sin advertencias al usar `--no-coverage`.
- Suite general del proyecto: 6 pruebas descubiertas, 6 aserciones y 1 omisión esperada (la integración MySQL requiere activación explícita y se ejecutó por separado).

La prueba verifica identificadores comerciales duplicados, claves foráneas, rechazo de estados financieros como estado comercial, medidas negativas, precios negativos, combinaciones inexistentes, cálculo decimal del paquete, períodos invertidos, protección de relaciones al borrar, valores iniciales de configuración y conservación de cambios al repetir el seeder. También comprueba creación, reversión y recreación de tablas.

No se probaron pantallas, autorizaciones operativas, cotizaciones, vencimientos, mora, pagos ni conciliación: aún no están implementados. El Excel se inspeccionó en lectura sin recalcularlo ni importarlo.
