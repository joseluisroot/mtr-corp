<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<a class="text-sm text-slate-500" href="<?= site_url('clientes') ?>">← Clientes</a>
<div class="mb-7 mt-5 flex flex-wrap items-center justify-between gap-4">
<div>
<p class="eyebrow">
<?= esc($client['code']) ?>
</p>
<h1 class="mt-2 text-3xl font-semibold">
<?= esc($client['full_name']) ?>
</h1>
<p class="mt-2 text-sm text-slate-500">
<?= $client['is_active']?'Cliente activo':'Cliente inactivo' ?>
</p>
</div>
<?php if(auth()->user()->can('clients.manage')): ?>
<a class="btn-secondary" href="<?= site_url('clientes/'.$client['id'].'/editar') ?>">Editar cliente</a>
<?php endif ?>
</div>
<section class="card p-7">
<h2 class="text-lg font-semibold">Datos del expediente</h2>
<dl class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
<?php foreach(['email'=>'Correo electrónico','phone'=>'Teléfono','document_number'=>'Identificación','document_country'=>'País de emisión'] as $key=>$label): ?>
<div>
<dt class="eyebrow">
<?= $label ?>
</dt>
<dd class="mt-2 break-words text-sm">
<?= esc($client[$key]??'Por completar') ?>
</dd>
</div>
<?php endforeach ?>
</dl>
<p class="mt-6 text-sm text-slate-500">
<?= esc($client['address']??'Dirección por completar') ?>
</p>
<p class="mt-3 whitespace-pre-line text-sm text-slate-500">
<?= esc($client['notes']??'') ?>
</p>
</section>
<section class="card mt-6 overflow-x-auto p-7">
<h2 class="text-lg font-semibold">Propiedades vinculadas al expediente</h2>
<p class="mb-5 mt-2 text-sm text-slate-500">Estos vínculos identifican antecedentes. Las ventas, inversión, depósitos y saldos se mostrarán al implementar sus módulos.</p>
<table class="w-full">
<thead>
<tr>
<th>Propiedad</th>
<th>Proyecto</th>
<th>Vínculo</th>
<th>Observación</th>
</tr>
</thead>
<tbody>
<?php foreach($client['properties'] as $link): ?>
<tr>
<td>
<a class="font-semibold text-brand-700 underline" href="<?= site_url('inventario/'.$link['lot_id']) ?>">
<?= esc($link['polygon_code'].'-'.$link['number']) ?>
</a>
</td>
<td class="text-sm">
<?= esc($link['project_name']) ?>
</td>
<td class="text-sm">
<?= $link['is_active']?'Vigente · por verificar':'Finalizado' ?>
</td>
<td class="max-w-sm text-sm text-slate-500">
<?= esc($link['is_active']?$link['note']:$link['end_reason']) ?>
</td>
</tr>
<?php endforeach ?>
<?php if(!$client['properties']): ?>
<tr>
<td colspan="4" class="py-10 text-center text-slate-400">Aún no hay propiedades vinculadas.</td>
</tr>
<?php endif ?>
</tbody>
</table>
</section>
<?= $this->endSection() ?>
