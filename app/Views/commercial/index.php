<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<p class="eyebrow">Seguimiento comercial</p>
<h1 class="mt-2 text-3xl font-semibold">Operaciones comerciales</h1>
<p class="mb-7 mt-2 text-sm text-slate-500">Interesados, asignaciones de negociación, reservas y ventas con respaldo.</p>
<?php if(!$installed): ?>
<section class="card p-7">
<h2 class="text-lg font-semibold">Actualización pendiente</h2>
<p class="my-4 text-sm text-slate-500">Aplica la migración 000007 desde la carpeta del proyecto:</p>
<code>php spark migrate --all</code>
</section>
<?php else: ?>
<form class="card mb-6 grid gap-4 p-5 md:grid-cols-[2fr_1fr_auto]" method="get">
<div>
<label for="q">Cliente, código, lote o polígono</label>
<input id="q" name="q" maxlength="100" value="<?= esc($q,'attr') ?>">
</div>
<div>
<label for="state">Estado</label>
<select id="state" name="state">
<option value="">Todos</option>
<?php foreach($states as $value=>$label): ?>
<option value="<?= $value ?>" <?= $state===$value?'selected':'' ?>>
<?= $label ?>
</option>
<?php endforeach ?>
</select>
</div>
<button class="btn self-end">Filtrar</button>
</form>
<section class="card overflow-x-auto p-5">
<p class="mb-5 text-sm text-slate-500">
<?= $result['total'] ?> operaciones en esta selección</p>
<table class="w-full">
<thead>
<tr>
<th>Operación</th>
<th>Propiedad</th>
<th>Cliente</th>
<th>Estado</th>
<th>Responsable</th>
<th>Precio pactado</th>
</tr>
</thead>
<tbody>
<?php foreach($result['rows'] as $row): ?>
<tr>
<td>
<a class="font-semibold text-brand-700 underline" href="<?= site_url('operaciones/'.$row['id']) ?>">#<?= $row['id'] ?>
</a>
</td>
<td>
<?= esc($row['polygon_code'].'-'.$row['number']) ?>
</td>
<td class="text-sm">
<?= esc($row['full_name']) ?>
</td>
<td class="text-sm">
<?= esc($states[$row['state']]) ?>
<?= $row['state']==='negotiating' && $row['expires_at']<=date('Y-m-d H:i:s')?' · plazo vencido':'' ?>
</td>
<td class="text-sm">
<?= esc($row['seller_name']??'Usuario #'.$row['seller_id']) ?>
</td>
<td class="whitespace-nowrap text-sm">
<?= $row['total_amount']!==null?esc($row['currency'].' '.$row['total_amount']):'Sin cotización' ?>
</td>
</tr>
<?php endforeach ?>
<?php if(!$result['rows']): ?>
<tr>
<td colspan="6" class="py-12 text-center text-slate-400">No hay operaciones. Inicia el seguimiento desde la ficha de una propiedad.</td>
</tr>
<?php endif ?>
</tbody>
</table>
<div class="mt-5 flex items-center justify-between text-sm">
<span>Página <?= $result['page'] ?> de <?= $result['pages'] ?>
</span>
<div class="flex gap-2">
<?php foreach([-1=>'Anterior',1=>'Siguiente'] as $delta=>$label): $page=$result['page']+$delta;if($page<1 || $page>$result['pages']) continue; ?>
<a class="btn-secondary" href="<?= site_url('operaciones').'?'.http_build_query(['q'=>$q,'state'=>$state,'page'=>$page]) ?>">
<?= $label ?>
</a>
<?php endforeach ?>
</div>
</div>
</section>
<p class="mt-5 text-xs text-slate-400">Las negociaciones vencidas no mantienen exclusividad. Las reservas requieren revisión expresa; no vencen automáticamente.</p>
<?php endif ?>
<?= $this->endSection() ?>
