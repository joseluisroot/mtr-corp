<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<p class="eyebrow">Carga inicial de información</p>
<h1 class="mt-2 text-3xl font-semibold">Importar inventario</h1>
<p class="mb-8 mt-2 text-sm text-slate-500">Primero revisa el archivo. El inventario se actualiza únicamente cuando confirmas las filas seleccionadas.</p>
<?php if(!$installed): ?>
<div class="card max-w-3xl p-7">
    <h2 class="text-lg font-semibold">Actualización de base de datos pendiente</h2>
    <p class="my-4 text-sm text-slate-500">Esta función necesita la migración 000005. Desde la carpeta del proyecto, ejecuta:</p>
    <code class="block rounded-xl bg-slate-100 p-4 text-sm">php spark migrate --all</code>
</div>
<?php else: ?>
<div class="grid gap-6 xl:grid-cols-[1fr_320px]">
    <section class="card p-7">
        <div class="eyebrow">Paso 1 de 2</div>
        <h2 class="mb-5 mt-2 text-xl font-semibold">Seleccionar archivo y proyecto</h2>
        <?php if(!$projects): ?>
            <p class="my-5 text-sm text-slate-500">Primero registra el proyecto al que pertenecerán estos lotes.</p>
            <a class="btn" href="<?= site_url('catalogos') ?>">Crear proyecto en Catálogos</a>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data" action="<?= site_url('importaciones') ?>" class="space-y-5">
            <?= csrf_field() ?>
            <div><label for="project_id">Proyecto de destino</label><select id="project_id" name="project_id" required>
                <option value="">Seleccionar proyecto</option>
                <?php foreach($projects as $project): ?><option value="<?= $project['id'] ?>"><?= esc($project['name']) ?></option><?php endforeach ?>
            </select></div>
            <div class="rounded-xl border border-dashed border-brand-500 bg-brand-50 p-6">
                <label for="workbook">Archivo Control Administrativo</label>
                <input id="workbook" type="file" name="workbook" accept=".xlsx" required>
                <p class="mt-3 text-xs text-slate-500">Formato .xlsx · Máximo 5 MB · Hasta 2,000 lotes · Hoja Lotes</p>
            </div>
            <button class="btn">Analizar y revisar →</button>
        </form>
        <?php endif ?>
    </section>
    <aside class="rounded-2xl bg-[#183c2c] p-7 text-white">
        <p class="text-xs uppercase tracking-widest text-emerald-200">Una carga con control</p>
        <h2 class="mt-4 text-xl font-semibold">Tu información, sin perder su origen.</h2>
        <ul class="mt-5 space-y-4 text-sm leading-relaxed text-emerald-100/80">
            <li>Revisión de identificadores y duplicados antes de cargar.</li>
            <li>Sin sobrescribir propiedades existentes.</li>
            <li>Estados históricos pendientes de expediente, sin ofrecerlos como disponibles.</li>
            <li>Clientes, ventas y depósitos se incorporarán con sus módulos.</li>
        </ul>
    </aside>
</div>
<section class="card mt-7 overflow-x-auto p-6">
    <h2 class="mb-5 text-lg font-semibold">Tus últimas revisiones</h2>
    <table class="w-full"><thead><tr><th>Archivo</th><th>Fecha</th><th>Filas</th><th>Estado</th><th>Resultado</th></tr></thead>
    <tbody><?php foreach($history as $batch): ?><tr>
        <td><a class="font-semibold text-brand-700 underline" href="<?= site_url('importaciones/'.$batch['id']) ?>"><?= esc($batch['file_name']) ?></a></td>
        <td class="text-sm"><?= esc($batch['created_at']) ?></td><td><?= $batch['row_count'] ?></td>
        <td class="text-sm"><?= $batch['status']==='committed'?'Importado':'En revisión' ?></td>
        <td class="text-sm"><?= $batch['status']==='committed'?$batch['imported_count'].' lotes cargados':'Sin modificar inventario' ?></td>
    </tr><?php endforeach ?>
    <?php if(!$history): ?><tr><td colspan="5" class="py-10 text-center text-sm text-slate-400">Todavía no has analizado archivos.</td></tr><?php endif ?></tbody></table>
</section>
<?php endif ?>
<?= $this->endSection() ?>
