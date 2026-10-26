<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<a href="<?= site_url('inventario') ?>" class="text-sm text-slate-500">← Inventario de propiedades</a>
<div class="mb-7 mt-5 flex flex-wrap items-center justify-between gap-4">
<div>
<p class="eyebrow">
<?= esc($lot['project_name'].' / '.$lot['polygon_name']) ?>
</p>
<h1 class="mt-2 text-4xl font-semibold">Propiedad <?= esc($title) ?>
</h1>
</div>
<div class="flex items-center gap-3">
<span class="badge state-<?= esc($lot['effective_status']) ?>">
<?= esc($labels[$lot['effective_status']]) ?>
</span>
<?php if(auth()->user()->can('inventory.manage') && $lot['commercial_status']==='available'): ?>
<a class="btn-secondary" href="<?= site_url('inventario/'.$lot['id'].'/editar') ?>">Editar lote</a>
<?php endif ?>
</div>
</div>
<div class="grid gap-6 xl:grid-cols-[1fr_320px]">
<div class="space-y-6">
<section class="card p-7">
<h2 class="text-lg font-semibold">Información de la propiedad</h2>
<dl class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-4">
<?php foreach(['front_m'=>'Frente · m','depth_m'=>'Fondo · m','area_m2'=>'Área · m²','type_name'=>'Tipología'] as $key=>$label): ?>
<div>
<dt class="eyebrow">
<?= $label ?>
</dt>
<dd class="mt-2 text-base font-semibold">
<?= esc($lot[$key]??'Sin registrar') ?>
</dd>
</div>
<?php endforeach ?>
</dl>
<p class="mt-6 text-sm leading-relaxed text-slate-500">
<?= esc($lot['notes'] ?: 'Sin observaciones registradas.') ?>
</p>
</section>
<section class="card p-7">
<h2 class="text-lg font-semibold">Precio de solo lote</h2>
<?php $now=date('Y-m-d H:i:s');$current=array_values(array_filter($lot['prices'],fn($p)=>$p['valid_from']<=$now && (!$p['valid_until'] || $p['valid_until']>$now))); ?>
<p class="mb-1 mt-4 text-3xl font-semibold">
<?= $current?esc($lot['currency'].' '.number_format((float)$current[0]['amount'],2)): 'Sin precio vigente' ?>
</p>
<p class="text-xs text-slate-400">Tarifa de catálogo; cada futura venta conservará su precio pactado.</p>
<?php if(auth()->user()->can('prices.manage')): ?>
<form method="post" action="<?= site_url('inventario/'.$lot['id'].'/precios') ?>" class="mt-5 flex flex-wrap items-end gap-3" data-confirm="Se registrará una nueva tarifa vigente y se conservará la anterior en el historial.">
<?= csrf_field() ?>
<div>
<label for="amount">Nuevo precio · <?= esc($lot['currency']) ?>
</label>
<input id="amount" name="amount" type="number" min="0" step="0.01" required>
</div>
<button class="btn-secondary">Actualizar precio</button>
</form>
<?php endif ?>
<?php if(count($lot['prices'])>1): ?>
<details class="mt-5 text-sm">
<summary class="cursor-pointer text-slate-500">Historial de tarifas</summary>
<ul class="mt-3 space-y-2">
<?php foreach($lot['prices'] as $p): ?>
<li>
<?= esc($p['valid_from'].' · '.$lot['currency'].' '.$p['amount'].' · '.($p['valid_until']?'Hasta '.$p['valid_until']:'Sin fecha final')) ?>
</li>
<?php endforeach ?>
</ul>
</details>
<?php endif ?>
</section>
<section class="card p-7">
<h2 class="text-lg font-semibold">Modelos compatibles y precios</h2>
<p class="mt-2 text-sm text-slate-500">Solo se podrán ofrecer las casas habilitadas para este lote.</p>
<?php if(!$lot['models']): ?>
<p class="my-6 text-sm text-slate-400">Todavía no hay modelos asignados.</p>
<?php endif ?>
<?php foreach($lot['models'] as $model): ?>
<article class="mt-5 rounded-xl border border-slate-200 p-5">
<div class="flex items-center justify-between">
<h3 class="font-semibold">
<?= esc($model['name']) ?>
</h3>
<span class="text-xs text-slate-500">
<?= $model['is_enabled'] && $model['model_active']?'Habilitado':'No habilitado' ?>
</span>
</div>
<?php $prices=array_values(array_filter($model['prices'],fn($p)=>$p['valid_from']<=$now && (!$p['valid_until'] || $p['valid_until']>$now))); ?>
<p class="mt-3 text-xl font-semibold">
<?= $prices?esc($lot['currency'].' '.number_format((float)$prices[0]['total_amount'],2)):'Sin precio vigente' ?>
</p>
<?php if($prices): ?>
<p class="mt-1 text-xs text-slate-400">
<?= esc('Terreno: '.$prices[0]['land_amount'].' · Construcción: '.$prices[0]['construction_amount']) ?>
</p>
<?php endif ?>
<?php if(auth()->user()->can('prices.manage') && $model['is_enabled']): ?>
<form method="post" action="<?= site_url('inventario/'.$lot['id'].'/precios') ?>" class="mt-4 grid gap-3 sm:grid-cols-3" data-confirm="Se actualizará la tarifa de esta combinación, conservando el historial.">
<?= csrf_field() ?>
<input type="hidden" name="compatibility_id" value="<?= $model['id'] ?>">
<div>
<label for="land_<?= $model['id'] ?>">Terreno</label>
<input id="land_<?= $model['id'] ?>" name="land_amount" type="number" min="0" step="0.01" required>
</div>
<div>
<label for="construction_<?= $model['id'] ?>">Construcción</label>
<input id="construction_<?= $model['id'] ?>" name="construction_amount" type="number" min="0" step="0.01" required>
</div>
<button class="btn-secondary self-end">Actualizar tarifa</button>
</form>
<?php endif ?>
<?php if($model['prices']): ?>
<details class="mt-4 text-xs">
<summary class="cursor-pointer text-slate-500">Historial de combinación</summary>
<ul class="mt-2 space-y-2">
<?php foreach($model['prices'] as $p): ?>
<li>
<?= esc($p['valid_from'].' · Total '.$p['total_amount'].' · '.($p['valid_until']?:'Sin fecha final')) ?>
</li>
<?php endforeach ?>
</ul>
</details>
<?php endif ?>
</article>
<?php endforeach ?>
<?php if(auth()->user()->can('prices.manage')): ?>
<form method="post" action="<?= site_url('inventario/'.$lot['id'].'/modelos') ?>" class="mt-6 space-y-4 border-t border-slate-100 pt-5" data-confirm="Confirma que el modelo es compatible con las dimensiones y restricciones del lote.">
<?= csrf_field() ?>
<label for="house_model_id">Administrar compatibilidad</label>
<select id="house_model_id" name="house_model_id" required>
<option value="">Selecciona un modelo</option>
<?php foreach($catalogs['house_models'] as $m): ?>
<option value="<?= $m['id'] ?>">
<?= esc($m['name']) ?>
</option>
<?php endforeach ?>
</select>
<select name="is_enabled" aria-label="Habilitación del modelo">
<option value="1">Habilitar para este lote</option>
<option value="0">Deshabilitar para este lote</option>
</select>
<button class="btn-secondary">Guardar compatibilidad</button>
</form>
<?php endif ?>
</section>
</div>
<aside class="space-y-6">
<section class="rounded-2xl bg-[#183c2c] p-6 text-white">
<p class="text-[11px] uppercase tracking-widest text-emerald-200/70">Estado del expediente</p>
<h2 class="mt-4 text-xl font-semibold">Una propiedad,<br>toda su información.</h2>
<p class="mt-4 text-sm leading-relaxed text-emerald-100/70">Aquí se integrarán la operación comercial, los pagos y documentos de esta propiedad.</p>
</section>
<section class="card p-6">
<h2 class="font-semibold">Últimos cambios</h2>
<ol class="mt-5 space-y-5">
<?php foreach($lot['audit'] as $event): ?>
<li class="border-l-2 border-brand-100 pl-4">
<p class="text-xs font-semibold">
<?= esc(['create'=>'Lote registrado','update'=>'Datos actualizados','price_revision'=>'Tarifa actualizada','compatibility'=>'Compatibilidad actualizada'][$event['action']]??$event['action']) ?>
</p>
<p class="mt-1 text-[11px] text-slate-400">
<?= esc($event['created_at'].' · Usuario #'.$event['actor_id']) ?>
</p>
</li>
<?php endforeach ?>
<?php if(!$lot['audit']): ?>
<li class="text-sm text-slate-400">Sin cambios registrados.</li>
<?php endif ?>
</ol>
</section>
</aside>
</div>
<?= $this->endSection() ?>
