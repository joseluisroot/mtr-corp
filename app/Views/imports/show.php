<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php
$done=$batch['status']==='committed';
$counts=array_count_values(array_column($batch['rows'],'disposition'));
$newPolygons=[];$existingPolygons=[];
foreach($batch['polygons'] as $polygon) $existingPolygons[$polygon['code']]=$polygon;
foreach($batch['rows'] as $row) if($row['disposition']==='ready' && !isset($existingPolygons[$row['polygon_code']])) $newPolygons[$row['polygon_code']]=true;
?>
<a class="text-sm text-slate-500" href="<?= site_url('importaciones') ?>">← Importaciones</a>
<div class="mb-7 mt-4 flex flex-wrap items-center justify-between gap-4">
    <div><p class="eyebrow"><?= $done?'Carga completada':'Paso 2 de 2 · Revisión previa' ?></p>
    <h1 class="mt-2 text-3xl font-semibold"><?= $done?'Resultado de la importación':'Revisar inventario' ?></h1>
    <p class="mt-2 text-sm text-slate-500"><?= esc($batch['file_name'].' · '.$batch['project_name']) ?></p></div>
    <?php if($done): ?><a class="btn" href="<?= site_url('inventario') ?>">Ver inventario</a><?php endif ?>
</div>
<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <?php foreach(['Filas detectadas'=>count($batch['rows']),($done?'Lotes cargados':'Listas para seleccionar')=>($done?$batch['imported_count']:($counts['ready']??0)),'Existentes · no se alteran'=>$counts['existing']??0,'Con errores'=>$counts['invalid']??0] as $label=>$count): ?>
    <div class="card p-5"><p class="text-xs text-slate-500"><?= $label ?></p><p class="mt-3 text-3xl font-semibold"><?= $count ?></p></div>
    <?php endforeach ?>
</div>
<div class="alert border-orange-200 bg-orange-50 text-orange-900">
    <strong>Los estados del Excel son antecedentes, no operaciones verificadas.</strong>
    <p class="mt-2">Los lotes ligados a clientes o con estado distinto de disponible quedarán como <strong>Pendiente de expediente</strong>. No se crearán reservas, ventas ni saldos.</p>
    <p class="mt-2">Dimensiones, tipologías, modelos compatibles y tarifas quedan pendientes. El valor de venta del Excel no se usará como precio de catálogo.</p>
</div>
<?php if($done): ?>
    <div class="alert border-brand-100 bg-brand-50 text-brand-700"><?= $batch['imported_count'] ?> lotes importados el <?= esc($batch['committed_at']) ?>. La revisión queda cerrada y no puede aplicarse otra vez.</div>
<?php endif ?>
<form method="post" action="<?= site_url('importaciones/'.$batch['id'].'/confirmar') ?>" data-confirm="Se crearán únicamente los lotes seleccionados y los polígonos nuevos necesarios. Los existentes no se modificarán.">
    <?= csrf_field() ?>
    <section class="card overflow-hidden">
        <div class="border-b border-slate-100 p-5"><h2 class="text-lg font-semibold">Lotes encontrados</h2><p class="mt-1 text-xs text-slate-400"><?= $done?'Se conserva el resultado de cada fila.':'Selecciona las filas a cargar.' ?> Los errores deben corregirse en el archivo y analizarse nuevamente.</p></div>
        <div class="max-h-[600px] overflow-auto"><table class="w-full"><thead class="sticky top-0 bg-white"><tr><th>Cargar</th><th>Fila</th><th>Identificación</th><th>Estado de origen</th><th>Tratamiento</th><th>Observaciones</th></tr></thead><tbody>
        <?php foreach($batch['rows'] as $row): ?>
        <tr><td><?php if(!$done && $row['disposition']==='ready'): ?><input type="checkbox" name="rows[]" value="<?= $row['id'] ?>" checked aria-label="Cargar fila <?= $row['source_row'] ?>"><?php else: ?>—<?php endif ?></td>
        <td><?= $row['source_row'] ?></td>
        <td><p class="font-semibold"><?= esc(($row['polygon_code']??'?').'-'.($row['lot_number']??'?')) ?></p><p class="mt-1 text-xs text-slate-400"><?= esc($row['source_key']??'Revisar identificador') ?></p></td>
        <td class="text-sm"><?= esc($row['legacy_status']) ?></td>
        <td class="text-sm"><?php if($row['disposition']==='imported'): ?><a class="text-brand-700 underline" href="<?= site_url('inventario/'.$row['lot_id']) ?>">Lote cargado</a><?php else: ?><?= esc(['ready'=>$row['review_required']?'Pendiente de expediente':'Crear lote','existing'=>'Ya existe','invalid'=>'No se puede cargar','omitted'=>'No seleccionado'][$row['disposition']]??$row['disposition']) ?><?php endif ?></td>
        <td class="max-w-sm text-xs leading-relaxed text-slate-500"><?php foreach(json_decode($row['issues'],true) as $issue): ?><p><?= esc($issue) ?></p><?php endforeach ?><?php if($row['disposition']==='ready'): ?><p>Sin dimensiones verificadas. Sin precio de catálogo.</p><?php endif ?></td></tr>
        <?php endforeach ?></tbody></table></div>
    </section>
    <?php if(!$done && ($counts['ready']??0)>0): ?>
    <section class="card mt-6 p-6">
        <h2 class="text-lg font-semibold">Política de los polígonos nuevos</h2>
        <p class="mb-5 mt-2 text-sm text-slate-500">Por seguridad se proponen bloqueados. En polígonos parciales, habilita después cada lote desde su ficha.</p>
        <div class="grid gap-4 md:grid-cols-3"><?php foreach($newPolygons as $code=>$_): ?><div><label for="policy_<?= esc($code,'attr') ?>">Polígono <?= esc($code) ?></label><select name="policies[<?= esc($code,'attr') ?>]" id="policy_<?= esc($code,'attr') ?>" required><option value="blocked">Bloqueado</option><option value="partial">Parcial</option><option value="enabled">Habilitado</option></select></div><?php endforeach ?></div>
        <?php if(!$newPolygons): ?><p class="text-sm text-slate-400">Todos los polígonos ya existen. Sus políticas se conservarán.</p><?php endif ?>
        <label class="mt-6 flex items-start gap-3"><input class="mt-1" type="checkbox" name="acknowledge" value="1" required><span>Revisé la selección y las políticas. Entiendo que clientes, operaciones, dimensiones y precios pendientes no se completarán automáticamente.</span></label>
        <div class="mt-6 flex flex-wrap gap-3"><button class="btn">Confirmar importación seleccionada</button><a class="btn-secondary" href="<?= site_url('importaciones') ?>">Volver sin importar</a></div>
    </section>
    <?php endif ?>
</form>
<p class="mt-5 break-all text-[11px] text-slate-400">Revisión #<?= $batch['id'] ?> · Huella del archivo: <?= esc($batch['file_sha256']) ?></p>
<?= $this->endSection() ?>
