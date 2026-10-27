<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<a class="text-sm text-slate-500" href="<?= site_url('operaciones') ?>">← Operaciones comerciales</a>
<div class="mb-7 mt-5 flex flex-wrap items-center justify-between gap-4">
<div>
<p class="eyebrow">
<?= esc($op['project_name']) ?>
</p>
<h1 class="mt-2 text-3xl font-semibold">Operación #<?= $op['id'] ?> · <?= esc($op['polygon_code'].'-'.$op['number']) ?>
</h1>
<p class="mt-2 text-sm text-slate-500">
<?= esc($states[$op['state']]) ?>
<?= $op['is_expired']?' · plazo vencido':'' ?> · <?= $op['origin']==='historical'?'Antecedente histórico verificado':'Seguimiento nuevo' ?>
</p>
</div>
<a class="btn-secondary" href="<?= site_url('inventario/'.$op['lot_id']) ?>">Ficha de propiedad</a>
</div>
<div class="grid gap-6 xl:grid-cols-[1fr_340px]">
<div class="space-y-6">
<section class="card p-7">
<h2 class="text-lg font-semibold">Condiciones registradas</h2>
<dl class="mt-6 grid gap-5 sm:grid-cols-2">
<div>
<dt class="eyebrow">Cliente</dt>
<dd class="mt-2">
<?= esc($op['full_name']) ?>
</dd>
</div>
<div>
<dt class="eyebrow">Responsable</dt>
<dd class="mt-2">
<?= esc($op['seller_name']??'Usuario #'.$op['seller_id']) ?>
</dd>
</div>
<div>
<dt class="eyebrow">Modalidad</dt>
<dd class="mt-2">
<?= $op['purchase_kind']===null?'Sin cotización':($op['purchase_kind']==='lot'?'Solo lote':'Lote más casa · '.esc($op['model_name'])) ?>
</dd>
</div>
<div>
<dt class="eyebrow">Fecha de origen</dt>
<dd class="mt-2">
<?= esc($op['operation_date']) ?>
</dd>
</div>
<?php if($op['expires_at']): ?>
<div>
<dt class="eyebrow">Fin de asignación</dt>
<dd class="mt-2">
<?= esc($op['expires_at']) ?> · <?= $op['negotiation_days'] ?> días</dd>
</div>
<?php endif ?>
<?php foreach(['reserved_on'=>'Fecha de reserva','sold_on'=>'Fecha de venta'] as $key=>$label): if(!$op[$key]) continue; ?>
<div>
<dt class="eyebrow">
<?= $label ?>
</dt>
<dd class="mt-2">
<?= esc($op[$key]) ?>
</dd>
</div>
<?php endforeach ?>
</dl>
<?php if($op['total_amount']!==null): ?>
<div class="mt-6 rounded-xl bg-brand-50 p-5">
<p class="text-xs text-brand-700">Precio pactado · <?= esc(['catalog'=>'Tarifa de catálogo conservada','negotiated'=>'Precio negociado autorizado','historical'=>'Precio histórico respaldado'][$op['price_source']]??'') ?>
</p>
<p class="mt-2 text-3xl font-semibold">
<?= esc($op['currency'].' '.$op['total_amount']) ?>
</p>
<p class="mt-3 text-sm text-slate-500">
<?= esc('Terreno: '.$op['land_amount'].' · Construcción: '.$op['construction_amount']) ?>
</p>
<?php if($op['price_reason']): ?>
<p class="mt-3 text-sm">
<?= esc($op['price_reason']) ?>
</p>
<?php endif ?>
</div>
<?php endif ?>
<p class="mt-5 whitespace-pre-line text-sm text-slate-500">
<?= esc($op['note']) ?>
</p>
<p class="mt-5 text-xs text-slate-400">El precio pactado no es dinero recibido. Cuotas, depósitos y saldos se incorporarán con cartera.</p>
</section>
<?php if($op['state']==='interested' && auth()->user()->can('commercial.manage')): ?>
<a class="btn" href="<?= site_url('inventario/'.$op['lot_id'].'/operacion') ?>">Iniciar negociación con plazo</a>
<?php endif ?>
<?php if($op['state']==='negotiating' && !$op['is_expired'] && auth()->user()->can('commercial.manage')): ?>
<a class="btn-secondary" href="<?= site_url('operaciones/'.$op['id'].'/editar') ?>">Revisar condiciones antes de reservar</a>
<?php endif ?>
<?php if(in_array($op['state'],['negotiating','reserved'],true) && !$op['is_expired'] && auth()->user()->can('commercial.manage')): $target=$op['state']==='negotiating'?'reservar':'vender'; ?>
<section class="card p-7">
<h2 class="text-lg font-semibold">
<?= $target==='reservar'?'Confirmar reserva':'Confirmar venta' ?>
</h2>
<form class="mt-5 space-y-4" method="post" enctype="multipart/form-data" action="<?= site_url('operaciones/'.$op['id'].'/'.$target) ?>" data-confirm="Se confirmará la etapa comercial conservando el precio pactado y el respaldo. No se registrará ningún depósito.">
<?= csrf_field() ?>
<input type="hidden" name="lock_version" value="<?= $op['lock_version'] ?>">
<div>
<label for="operation_date">Fecha del acuerdo</label>
<input type="date" name="operation_date" id="operation_date" required max="<?= date('Y-m-d') ?>">
</div>
<div>
<label for="transition_note">Detalle de la confirmación</label>
<textarea id="transition_note" name="note" minlength="5" maxlength="2000" rows="2" required>
</textarea>
</div>
<div>
<label for="support">Documento de respaldo</label>
<input type="file" id="support" name="support" accept=".pdf,.jpg,.jpeg,.png" required>
</div>
<button class="btn">
<?= $target==='reservar'?'Confirmar reserva':'Confirmar venta' ?>
</button>
</form>
</section>
<?php endif ?>
<?php if(in_array($op['state'],['interested','negotiating','reserved'],true) && !$op['is_expired'] && auth()->user()->can('commercial.cancel')): ?>
<details class="card p-6">
<summary class="cursor-pointer text-sm font-semibold">Cancelar seguimiento o reserva</summary>
<p class="my-4 text-xs text-slate-500">Se conservará el historial. Esta acción no registra devoluciones de dinero y no cancela ventas.</p>
<form method="post" action="<?= site_url('operaciones/'.$op['id'].'/cancelar') ?>" class="space-y-4" data-confirm="Se cancelará esta operación. Revisa los compromisos existentes antes de liberar la propiedad.">
<?= csrf_field() ?>
<input type="hidden" name="lock_version" value="<?= $op['lock_version'] ?>">
<label for="cancel_note">Motivo obligatorio</label>
<textarea id="cancel_note" name="note" rows="3" minlength="5" maxlength="2000" required>
</textarea>
<button class="btn-secondary">Cancelar operación</button>
</form>
</details>
<?php endif ?>
</div>
<aside class="space-y-6">
<section class="card p-6">
<h2 class="font-semibold">Documentos privados</h2>
<ul class="mt-4 space-y-3">
<?php foreach($op['documents'] as $doc): ?>
<li>
<a class="break-words text-sm text-brand-700 underline" href="<?= site_url('documentos-comerciales/'.$doc['id']) ?>">
<?= esc($doc['original_name']) ?>
</a>
<p class="mt-1 text-[11px] text-slate-400">
<?= esc($doc['created_at']) ?>
</p>
</li>
<?php endforeach ?>
<?php if(!$op['documents']): ?>
<li class="text-sm text-slate-400">Sin documentos adjuntos.</li>
<?php endif ?>
</ul>
<?php if(auth()->user()->can('commercial.manage')): ?>
<form class="mt-5 space-y-3 border-t border-slate-100 pt-4" method="post" enctype="multipart/form-data" action="<?= site_url('operaciones/'.$op['id'].'/documentos') ?>">
<?= csrf_field() ?>
<label for="extra_support">Agregar respaldo · hasta 5 MB</label>
<input type="file" id="extra_support" name="support" accept=".pdf,.jpg,.jpeg,.png" required>
<button class="btn-secondary">Adjuntar</button>
</form>
<?php endif ?>
</section>
<section class="card p-6">
<h2 class="font-semibold">Historial de la operación</h2>
<ol class="mt-5 space-y-5">
<?php foreach($op['events'] as $event): ?>
<li class="border-l-2 border-brand-100 pl-4">
<p class="text-sm font-semibold">
<?= esc($states[$event['to_state']]) ?>
</p>
<p class="mt-1 text-xs text-slate-500">
<?= esc($event['reason']) ?>
</p>
<p class="mt-2 text-[11px] text-slate-400">
<?= esc('Fecha efectiva: '.$event['effective_date'].' · Registrado: '.$event['created_at']) ?>
<br>
<?= $event['actor_id']?'Usuario #'.esc($event['actor_id']):'Vencimiento del sistema' ?>
</p>
</li>
<?php endforeach ?>
</ol>
</section>
</aside>
</div>
<?= $this->endSection() ?>
