<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="mb-7 flex flex-wrap items-end justify-between gap-4">
<div>
<p class="eyebrow">Relación con tus clientes</p>
<h1 class="mt-2 text-3xl font-semibold">Clientes</h1>
<p class="mt-2 text-sm text-slate-500">Un expediente por persona, con todas sus propiedades vinculadas.</p>
</div>
<?php if($installed && auth()->user()->can('clients.manage')): ?>
<a class="btn" href="<?= site_url('clientes/nuevo') ?>">＋ Nuevo cliente</a>
<?php endif ?>
</div>
<?php if(!$installed): ?>
<div class="card p-7">
<h2 class="text-lg font-semibold">Actualización de base de datos pendiente</h2>
<p class="my-4 text-sm text-slate-500">Aplica la migración 000006 desde la carpeta del proyecto:</p>
<code>php spark migrate --all</code>
</div>
<?php else: ?>
<form method="get" class="card mb-6 flex flex-wrap items-end gap-3 p-5">
<div class="min-w-48 flex-1">
<label for="q">Buscar cliente</label>
<input id="q" name="q" maxlength="100" value="<?= esc($q,'attr') ?>" placeholder="Nombre, código, documento, correo o teléfono">
</div>
<button class="btn">Buscar</button>
<a class="btn-secondary" href="<?= site_url('clientes') ?>">Limpiar</a>
</form>
<section class="card overflow-x-auto p-5">
<div class="mb-4 text-sm text-slate-500">
<?= $result['total'] ?> clientes en esta selección</div>
<table class="w-full">
<thead>
<tr>
<th>Cliente</th>
<th>Código</th>
<th>Contacto</th>
<th>Estado</th>
</tr>
</thead>
<tbody>
<?php foreach($result['rows'] as $client): ?>
<tr>
<td>
<a class="font-semibold text-brand-700 underline" href="<?= site_url('clientes/'.$client['id']) ?>">
<?= esc($client['full_name']) ?>
</a>
</td>
<td class="text-sm">
<?= esc($client['code']) ?>
</td>
<td class="text-sm">
<p>
<?= esc($client['phone']??'') ?>
</p>
<p>
<?= esc($client['email']??'') ?>
</p>
</td>
<td class="text-sm">
<?= $client['is_active']?'Activo':'Inactivo' ?>
</td>
</tr>
<?php endforeach ?>
<?php if(!$result['rows']): ?>
<tr>
<td colspan="4" class="py-12 text-center text-slate-400">No hay clientes en esta selección.</td>
</tr>
<?php endif ?>
</tbody>
</table>
<div class="mt-5 flex items-center justify-between text-sm">
<span class="text-slate-400">Página <?= $result['page'] ?> de <?= $result['pages'] ?>
</span>
<div class="flex gap-2">
<?php if($result['page']>1): ?>
<a class="btn-secondary" href="<?= site_url('clientes').'?'.http_build_query(['q'=>$q,'page'=>$result['page']-1]) ?>">Anterior</a>
<?php endif ?>
<?php if($result['page']<$result['pages']): ?>
<a class="btn-secondary" href="<?= site_url('clientes').'?'.http_build_query(['q'=>$q,'page'=>$result['page']+1]) ?>">Siguiente</a>
<?php endif ?>
</div>
</div>
</section>
<?php endif ?>
<?= $this->endSection() ?>
