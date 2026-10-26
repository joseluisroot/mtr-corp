<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
<div>
<p class="eyebrow">Control del proyecto</p>
<h1 class="mt-2 text-3xl font-semibold tracking-tight">Inventario de propiedades</h1>
<p class="mt-2 text-sm text-slate-500">Una vista clara de cada lote y su disponibilidad comercial.</p>
</div>
<?php if(auth()->user()->can('inventory.manage')): ?>
<a class="btn" href="<?= site_url('inventario/nuevo') ?>">
<span aria-hidden="true">＋</span> Nuevo lote</a>
<?php endif ?>
</div>
<div class="mb-7 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
<div class="card p-5">
<p class="text-xs text-slate-500">Lotes en esta selección</p>
<p class="mt-3 text-3xl font-semibold">
<?= count($rows) ?>
</p>
<p class="mt-2 text-[11px] text-slate-400">Inventario registrado</p>
</div>
<?php foreach($labels as $key=>$label): ?>
<div class="card p-5">
<span class="badge state-<?= esc($key) ?>">
<?= esc($label) ?>
</span>
<p class="mt-3 text-3xl font-semibold">
<?= $counts[$key] ?>
</p>
<p class="mt-2 text-[11px] text-slate-400">Estado comercial</p>
</div>
<?php endforeach ?>
</div>
<form method="get" action="<?= site_url('inventario') ?>" class="card mb-7 grid items-end gap-4 p-5 md:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_auto]">
<div>
<label for="q">Buscar propiedad</label>
<input id="q" name="q" placeholder="Número de lote, polígono o proyecto" value="<?= esc($filters['q'],'attr') ?>" maxlength="100">
</div>
<div>
<label for="polygon">Polígono</label>
<select id="polygon" name="polygon">
<option value="">Todos los polígonos</option>
<?php foreach($catalogs['polygons'] as $p): ?>
<option value="<?= $p['id'] ?>" <?= (string)$p['id']===$filters['polygon']?'selected':'' ?>>
<?= esc($p['name']) ?>
</option>
<?php endforeach ?>
</select>
</div>
<div>
<label for="status">Estado</label>
<select id="status" name="status">
<option value="">Todos los estados</option>
<?php foreach($labels as $key=>$label): ?>
<option value="<?= $key ?>" <?= $filters['status']===$key?'selected':'' ?>>
<?= esc($label) ?>
</option>
<?php endforeach ?>
</select>
</div>
<div class="flex gap-2">
<button class="btn">Filtrar</button>
<a class="btn-secondary" href="<?= site_url('inventario') ?>" aria-label="Limpiar filtros">↺</a>
</div>
</form>
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
<div>
<h2 class="text-lg font-semibold">Disponibilidad por polígono</h2>
<p class="mt-1 text-xs text-slate-400">Selecciona un lote para consultar su ficha.</p>
</div>
<div class="flex rounded-xl border border-slate-200 bg-white p-1">
<button type="button" data-view="grid" aria-pressed="true" class="rounded-lg bg-brand-50 px-4 py-2 text-xs font-semibold">▦ Tarjetas</button>
<button type="button" data-view="table" aria-pressed="false" class="rounded-lg px-4 py-2 text-xs font-semibold">☷ Tabla</button>
</div>
</div>
<?php if(!$rows): ?>
<div class="card px-6 py-16 text-center">
<div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-2xl text-brand-600" aria-hidden="true">▦</div>
<h2 class="text-lg font-semibold">No hay lotes en esta selección</h2>
<p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Prueba otros filtros o registra los polígonos y lotes para comenzar.</p>
<?php if(auth()->user()->can('inventory.manage')): ?>
<a class="btn-secondary mt-6" href="<?= site_url('catalogos') ?>">Administrar catálogos</a>
<?php endif ?>
</div>
<?php endif ?>
<div data-grid class="space-y-6">
<?php $groups=[];foreach($rows as $row) $groups[$row['polygon_id']][]=$row; foreach($groups as $group): $first=$group[0]; ?>
<section class="card overflow-hidden">
<header class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
<div class="flex items-center gap-3">
<span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#f2f5f1] text-lg font-semibold">
<?= esc($first['polygon_code']) ?>
</span>
<div>
<h3 class="text-sm font-semibold">
<?= esc($first['polygon_name']) ?>
</h3>
<p class="mt-0.5 text-xs text-slate-400">
<?= esc($first['project_name']) ?>
</p>
</div>
</div>
<span class="text-xs text-slate-400">
<?= count($group) ?> lotes</span>
</header>
<div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 xl:grid-cols-5">
<?php foreach($group as $lot): ?>
<a class="lot-card <?= 'state-'.esc($lot['effective_status']) ?>" href="<?= site_url('inventario/'.$lot['id']) ?>">
<div class="flex items-center justify-between">
<span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Lote</span>
<span aria-hidden="true" class="text-slate-300">↗</span>
</div>
<p class="mb-3 mt-1 text-2xl font-semibold text-ink">
<?= esc($lot['polygon_code'].'-'.$lot['number']) ?>
</p>
<span class="badge state-<?= esc($lot['effective_status']) ?>">
<?= esc($labels[$lot['effective_status']]) ?>
</span>
<p class="mt-3 text-[11px] text-slate-400">
<?= $lot['area_m2']?esc(rtrim(rtrim($lot['area_m2'],'0'),'.')).' m²':'Área por registrar' ?>
</p>
</a>
<?php endforeach ?>
</div>
</section>
<?php endforeach ?>
</div>
<div data-table-panel hidden class="card overflow-x-auto p-5">
<table data-inventory-table class="w-full">
<thead>
<tr>
<th>Propiedad</th>
<th>Proyecto</th>
<th>Polígono</th>
<th>Estado</th>
<th>Área m²</th>
<th>Tipología</th>
</tr>
</thead>
<tbody>
<?php foreach($rows as $lot): ?>
<tr>
<td>
<a class="font-semibold text-brand-700 underline-offset-4 hover:underline" href="<?= site_url('inventario/'.$lot['id']) ?>">
<?= esc($lot['polygon_code'].'-'.$lot['number']) ?>
</a>
</td>
<td>
<?= esc($lot['project_name']) ?>
</td>
<td>
<?= esc($lot['polygon_name']) ?>
</td>
<td>
<span class="badge state-<?= esc($lot['effective_status']) ?>">
<?= esc($labels[$lot['effective_status']]) ?>
</span>
</td>
<td>
<?= esc($lot['area_m2']??'Sin registrar') ?>
</td>
<td>
<?= esc($lot['type_name']??'Sin asignar') ?>
</td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
<p class="mt-5 text-xs leading-relaxed text-slate-400">Los estados reflejan la situación comercial. Pagos, mora y conciliación se incorporarán con el módulo de cartera.</p>
<?= $this->endSection() ?>
