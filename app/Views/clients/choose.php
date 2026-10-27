<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<a class="text-sm text-slate-500" href="<?= site_url('inventario/'.$lot['id']) ?>">← Ficha de propiedad</a>
<h1 class="mb-2 mt-5 text-3xl font-semibold">Vincular cliente · <?= esc($lot['polygon_code'].'-'.$lot['number']) ?>
</h1>
<p class="mb-7 text-sm text-slate-500">Busca un cliente existente para relacionarlo con el expediente de esta propiedad.</p>
<?php if($current): ?>
<div class="card p-7">
<p class="text-sm">Esta propiedad ya tiene un cliente vinculado. Revisa la ficha para desvincularlo con motivo antes de cambiarlo.</p>
<a class="btn-secondary mt-5" href="<?= site_url('inventario/'.$lot['id']) ?>">Volver a la ficha</a>
</div>
<?php else: ?>
<form method="get" class="card mb-6 flex flex-wrap items-end gap-3 p-5">
<div class="flex-1">
<label for="q">Buscar por nombre, código o contacto</label>
<input id="q" name="q" value="<?= esc($q,'attr') ?>" maxlength="100">
</div>
<button class="btn">Buscar</button>
<?php if(auth()->user()->can('clients.manage')): ?>
<a class="btn-secondary" href="<?= site_url('clientes/nuevo').'?return_lot='.$lot['id'] ?>">Crear cliente</a>
<?php endif ?>
</form>
<form method="post" action="<?= site_url('inventario/'.$lot['id'].'/cliente') ?>" class="card p-6" data-confirm="Se vinculará el cliente al expediente. Esto no reserva el lote ni confirma una venta.">
<?= csrf_field() ?>
<div class="overflow-x-auto">
<table class="w-full">
<thead>
<tr>
<th>Elegir</th>
<th>Cliente</th>
<th>Código</th>
<th>Contacto</th>
</tr>
</thead>
<tbody>
<?php foreach($result['rows'] as $client): if(!$client['is_active']) continue; ?>
<tr>
<td>
<input type="radio" required name="client_id" value="<?= $client['id'] ?>" aria-label="Seleccionar <?= esc($client['full_name'],'attr') ?>">
</td>
<td class="font-semibold">
<?= esc($client['full_name']) ?>
</td>
<td class="text-sm">
<?= esc($client['code']) ?>
</td>
<td class="text-sm">
<?= esc($client['phone']??$client['email']??'Sin contacto') ?>
</td>
</tr>
<?php endforeach ?>
</tbody>
</table>
</div>
<p class="my-5 text-xs text-slate-500">Página <?= $result['page'] ?> de <?= $result['pages'] ?>. Solo pueden vincularse clientes activos.</p>
<div class="mb-5 flex gap-3">
<?php if($result['page']>1): ?>
<a class="btn-secondary" href="<?= current_url().'?'.http_build_query(['q'=>$q,'page'=>$result['page']-1]) ?>">Anterior</a>
<?php endif ?>
<?php if($result['page']<$result['pages']): ?>
<a class="btn-secondary" href="<?= current_url().'?'.http_build_query(['q'=>$q,'page'=>$result['page']+1]) ?>">Siguiente</a>
<?php endif ?>
</div>
<label for="note">Antecedente u observación del vínculo</label>
<textarea id="note" name="note" required minlength="5" maxlength="500" rows="3" placeholder="Describe qué registro o documento relaciona al cliente con este lote.">
</textarea>
<p class="my-5 text-xs text-slate-500">Vincular un cliente no aparta el lote, no cambia su estado y no libera expedientes pendientes.</p>
<button class="btn">Vincular al expediente</button>
</form>
<?php endif ?>
<?= $this->endSection() ?>
