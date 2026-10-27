<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<p class="eyebrow">Administración del inventario</p>
<h1 class="mb-2 mt-2 text-3xl font-semibold">Catálogos del proyecto</h1>
<p class="mb-8 text-sm text-slate-500">Registra primero el proyecto y sus polígonos; después, los lotes y modelos.</p>
<div class="grid gap-6 xl:grid-cols-2">
<?php foreach(['projects'=>'Proyectos','polygons'=>'Polígonos','lot_types'=>'Tipologías de lote','house_models'=>'Modelos de casa'] as $kind=>$name): ?>
<section class="card p-6">
<div class="flex items-center justify-between">
<h2 class="text-lg font-semibold">
<?= $name ?>
</h2>
<span class="text-xs text-slate-400">
<?= count($catalogs[$kind]) ?> registros</span>
</div>
<ul class="my-5 max-h-72 space-y-3 overflow-y-auto">
<?php foreach($catalogs[$kind] as $row): ?>
<li class="rounded-xl border border-slate-100 p-3">
<div class="flex items-center justify-between gap-3">
<div>
<span class="text-xs font-semibold text-brand-700">
<?= esc($row['code']) ?>
</span>
<p class="text-sm">
<?= esc($row['name']) ?>
</p>
<a class="text-xs text-brand-700 underline" href="<?= site_url('catalogos/'.$kind.'/'.$row['id']) ?>">Editar</a>
</div>
<?php if($kind==='polygons'): ?>
<form method="post" action="<?= site_url('poligonos/'.$row['id'].'/politica') ?>" class="flex items-center gap-2" data-confirm="La política modifica la disponibilidad comercial de los lotes del polígono.">
<?= csrf_field() ?>
<select name="sales_policy" aria-label="Política de <?= esc($row['name'],'attr') ?>">
<?php foreach(['blocked'=>'Bloqueado','partial'=>'Parcial','enabled'=>'Habilitado'] as $value=>$label): ?>
<option value="<?= $value ?>" <?= $row['sales_policy']===$value?'selected':'' ?>>
<?= $label ?>
</option>
<?php endforeach ?>
</select>
<button class="text-xs font-semibold text-brand-700">Aplicar</button>
</form>
<?php endif ?>
</div>
</li>
<?php endforeach ?>
<?php if(!$catalogs[$kind]): ?>
<li class="text-sm text-slate-400">Sin registros todavía.</li>
<?php endif ?>
</ul>
<details class="border-t border-slate-100 pt-4">
<summary class="cursor-pointer text-sm font-semibold text-brand-700">＋ Agregar registro</summary>
<form class="mt-4 space-y-4" method="post" action="<?= site_url('catalogos/'.$kind) ?>">
<?= csrf_field() ?>
<div class="grid grid-cols-2 gap-3">
<div>
<label for="code_<?= $kind ?>">Código</label>
<input id="code_<?= $kind ?>" name="code" maxlength="30" pattern="[A-Za-z0-9-]+" required>
</div>
<div>
<label for="name_<?= $kind ?>">Nombre</label>
<input id="name_<?= $kind ?>" name="name" maxlength="120" required>
</div>
</div>
<?php if($kind==='polygons'): ?>
<div>
<label for="project_id">Proyecto</label>
<select name="project_id" id="project_id" required>
<option value="">Seleccionar proyecto</option>
<?php foreach($catalogs['projects'] as $p): ?>
<option value="<?= $p['id'] ?>">
<?= esc($p['name']) ?>
</option>
<?php endforeach ?>
</select>
</div>
<div>
<label for="policy">Comercialización</label>
<select id="policy" name="sales_policy">
<option value="blocked">Bloqueado</option>
<option value="partial">Parcialmente habilitado</option>
<option value="enabled">Completamente habilitado</option>
</select>
</div>
<?php endif ?>
<button class="btn-secondary">Crear registro</button>
</form>
</details>
</section>
<?php endforeach ?>
</div>
<?= $this->endSection() ?>
