<?php if(auth()->user()->can('commercial.view')): ?>
<?php $hasActive=false;foreach($commercialOperations as $commercialOp) { if(in_array($commercialOp['state'],['reserved','sold'],true) || ($commercialOp['state']==='negotiating' && $commercialOp['expires_at']>date('Y-m-d H:i:s'))) $hasActive=true; } ?>
<section class="card p-7">
<h2 class="text-lg font-semibold">Seguimiento comercial</h2>
<?php if(!$commercialInstalled): ?>
<p class="mt-4 text-sm text-slate-500">Aplica la migración 000007 para habilitar interesados, negociaciones y operaciones históricas.</p>
<?php else: ?>
<div class="mt-5 flex flex-wrap gap-3">
<?php if(auth()->user()->can('commercial.history') && !$hasActive): ?>
<a class="btn-secondary" href="<?= site_url('inventario/'.$lot['id'].'/historico') ?>">Verificar reserva o venta histórica</a>
<?php endif ?>
<?php if(auth()->user()->can('commercial.manage') && empty($lot['import_review_required']) && !in_array($lot['commercial_status'],['reserved','sold'],true)): ?>
<a class="btn" href="<?= site_url('inventario/'.$lot['id'].'/operacion') ?>">Registrar interesado o negociación</a>
<?php endif ?>
</div>
<ul class="mt-5 space-y-3">
<?php foreach($commercialOperations as $commercialOp): ?>
<li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 p-3">
<a class="text-sm font-semibold text-brand-700 underline" href="<?= site_url('operaciones/'.$commercialOp['id']) ?>">#<?= $commercialOp['id'] ?> · <?= esc($commercialOp['full_name']) ?>
</a>
<span class="text-xs text-slate-500">
<?= esc(\App\Services\CommercialService::STATES[$commercialOp['state']]) ?>
</span>
</li>
<?php endforeach ?>
<?php if(!$commercialOperations): ?>
<li class="text-sm text-slate-400">Aún no hay seguimiento comercial registrado.</li>
<?php endif ?>
</ul>
<?php endif ?>
</section>
<?php endif ?>
