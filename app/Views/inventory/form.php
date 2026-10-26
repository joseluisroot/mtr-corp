<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<a class="text-sm text-slate-500 hover:text-brand-700" href="<?= site_url(isset($lot['id'])?'inventario/'.$lot['id']:'inventario') ?>">← Volver</a>
<h1 class="mb-2 mt-5 text-3xl font-semibold">
<?= esc($title) ?>
</h1>
<p class="mb-7 text-sm text-slate-500">El código de propiedad se forma con el polígono y el número de lote.</p>
<form method="post" action="<?= site_url('inventario'.(isset($lot['id'])?'/'.$lot['id']:'')) ?>" class="card max-w-3xl p-7">
<?= csrf_field() ?>
<input type="hidden" name="lock_version" value="<?= esc($lot['lock_version']??0,'attr') ?>">
<div class="grid gap-5 sm:grid-cols-2">
<div>
<label for="polygon_id">Polígono *</label>
<select id="polygon_id" name="polygon_id" required>
<option value="">Seleccionar polígono</option>
<?php foreach($catalogs['polygons'] as $p): ?>
<option value="<?= $p['id'] ?>" <?= old('polygon_id',$lot['polygon_id']??'')==$p['id']?'selected':'' ?>>
<?= esc($p['name']) ?>
</option>
<?php endforeach ?>
</select>
</div>
<div>
<label for="number">Número de lote *</label>
<input id="number" name="number" maxlength="30" pattern="[A-Za-z0-9-]+" required value="<?= esc(old('number',$lot['number']??''),'attr') ?>" placeholder="Ejemplo: 01">
</div>
<div>
<label for="lot_type_id">Tipología de lote</label>
<select id="lot_type_id" name="lot_type_id">
<option value="">Sin asignar</option>
<?php foreach($catalogs['lot_types'] as $t): ?>
<option value="<?= $t['id'] ?>" <?= old('lot_type_id',$lot['lot_type_id']??'')==$t['id']?'selected':'' ?>>
<?= esc($t['name']) ?>
</option>
<?php endforeach ?>
</select>
</div>
<?php foreach(['front_m'=>'Frente (m)','depth_m'=>'Fondo (m)','area_m2'=>'Área (m²)'] as $key=>$label): ?>
<div>
<label for="<?= $key ?>">
<?= $label ?>
</label>
<input type="number" min="0.001" step="0.001" id="<?= $key ?>" name="<?= $key ?>" value="<?= esc(old($key,$lot[$key]??''),'attr') ?>">
</div>
<?php endforeach ?>
</div>
<label class="mt-6 flex items-start gap-3">
<input type="hidden" name="is_enabled" value="0">
<input type="checkbox" name="is_enabled" value="1" class="mt-1" <?= old('is_enabled',$lot['is_enabled']??0)?'checked':'' ?>>
<span>Habilitar comercialmente en un polígono parcial<span class="mt-1 block text-xs font-normal text-slate-400">Un polígono bloqueado seguirá bloqueado.</span>
</span>
</label>
<div class="mt-5">
<label for="notes">Observaciones</label>
<textarea id="notes" name="notes" rows="3" maxlength="2000">
<?= esc(old('notes',$lot['notes']??'')) ?>
</textarea>
</div>
<div class="mt-7 flex gap-3">
<button class="btn">Guardar lote</button>
<a class="btn-secondary" href="<?= site_url('inventario') ?>">Cancelar</a>
</div>
</form>
<?= $this->endSection() ?>
