<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<p class="eyebrow">Reglas del negocio</p>
<h1 class="mt-2 text-3xl font-semibold">Configuración general</h1>
<p class="mb-8 mt-2 text-sm text-slate-500">Plazos centralizados, administrables y con historial de cambios.</p>
<form method="post" action="<?= site_url('configuracion') ?>" class="card max-w-2xl p-7" data-confirm="Se actualizarán los parámetros generales y se registrará el motivo del cambio.">
<?= csrf_field() ?>
<div class="space-y-6">
<?php foreach($settings as $setting): ?>
<div>
<label for="<?= esc($setting['setting_key']) ?>">
<?= esc($setting['description']) ?>
</label>
<div class="flex items-center gap-3">
<input class="max-w-40" type="number" min="<?= $setting['setting_key']==='negotiation_days'?1:0 ?>" max="3650" required name="<?= esc($setting['setting_key'],'attr') ?>" id="<?= esc($setting['setting_key'],'attr') ?>" value="<?= esc(old($setting['setting_key'],$setting['value_days']),'attr') ?>">
<span class="text-sm text-slate-400">días</span>
</div>
</div>
<?php endforeach ?>
<div>
<label for="reason">Motivo del cambio</label>
<textarea id="reason" name="reason" required minlength="5" maxlength="500" rows="3">
<?= esc(old('reason')) ?>
</textarea>
</div>
</div>
<p class="my-5 text-xs leading-relaxed text-slate-400">Los parámetros quedan preparados para los módulos de negociaciones y cuotas. Todavía no se ejecutan vencimientos automáticos ni cálculos de mora.</p>
<button class="btn">Guardar configuración</button>
</form>
<?= $this->endSection() ?>
