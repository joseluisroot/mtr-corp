<?php if(auth()->user()->can('commercial.view')): ?>
<section class="card mt-6 overflow-x-auto p-7">
<h2 class="text-lg font-semibold">Operaciones e inversión pactada</h2>
<p class="mt-2 text-sm text-slate-500">El valor pactado vigente incluye reservas y ventas activas. No representa depósitos recibidos ni saldo pendiente.</p>
<div class="my-5 flex flex-wrap gap-4">
<?php foreach($commitments as $commitment): ?>
<div class="rounded-xl bg-brand-50 p-5">
<p class="text-xs text-brand-700">Valor pactado vigente</p>
<p class="mt-2 text-2xl font-semibold">
<?= esc($commitment['currency'].' '.$commitment['agreed_total']) ?>
</p>
</div>
<?php endforeach ?>
</div>
<table class="w-full">
<thead>
<tr>
<th>Operación</th>
<th>Propiedad</th>
<th>Estado</th>
<th>Precio pactado</th>
</tr>
</thead>
<tbody>
<?php foreach($commercialOperations as $commercialOp): ?>
<tr>
<td>
<a class="text-brand-700 underline" href="<?= site_url('operaciones/'.$commercialOp['id']) ?>">#<?= $commercialOp['id'] ?>
</a>
</td>
<td>
<?= esc($commercialOp['polygon_code'].'-'.$commercialOp['number']) ?>
</td>
<td class="text-sm">
<?= esc(\App\Services\CommercialService::STATES[$commercialOp['state']]) ?>
</td>
<td class="text-sm">
<?= $commercialOp['total_amount']!==null?esc($commercialOp['currency'].' '.$commercialOp['total_amount']):'Sin cotización' ?>
</td>
</tr>
<?php endforeach ?>
<?php if(!$commercialOperations): ?>
<tr>
<td colspan="4" class="py-8 text-center text-slate-400">Sin operaciones comerciales registradas.</td>
</tr>
<?php endif ?>
</tbody>
</table>
</section>
<?php endif ?>
