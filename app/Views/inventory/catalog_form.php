<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<a class="text-sm text-slate-500" href="<?= site_url('catalogos') ?>">← Catálogos</a>
<h1 class="mb-2 mt-5 text-3xl font-semibold">Editar <?= esc($row['name']) ?>
</h1>
<p class="mb-7 text-sm text-slate-500">Los cambios mantienen el identificador del registro y quedan en el historial.</p>
<form class="card max-w-2xl space-y-5 p-7" method="post" action="<?= site_url('catalogos/'.$kind.'/'.$row['id']) ?>" data-confirm="Se actualizará este catálogo. Las tarifas y operaciones anteriores conservarán sus importes.">
<?= csrf_field() ?>
<div>
<label for="code">Código</label>
<input id="code" name="code" maxlength="30" pattern="[A-Za-z0-9-]+" required value="<?= esc(old('code',$row['code']),'attr') ?>">
</div>
<div>
<label for="name">Nombre</label>
<input id="name" name="name" maxlength="120" required value="<?= esc(old('name',$row['name']),'attr') ?>">
</div>
<?php if($kind==='polygons'): ?>
<input type="hidden" name="project_id" value="<?= $row['project_id'] ?>">
<div>
<label for="sales_policy">Comercialización</label>
<select id="sales_policy" name="sales_policy">
<?php foreach(['blocked'=>'Bloqueado','partial'=>'Parcial','enabled'=>'Habilitado'] as $value=>$label): ?>
<option value="<?= $value ?>" <?= old('sales_policy',$row['sales_policy'])===$value?'selected':'' ?>>
<?= $label ?>
</option>
<?php endforeach ?>
</select>
</div>
<?php else: ?>
<div>
<label for="is_active">Disponibilidad en el catálogo</label>
<select id="is_active" name="is_active">
<option value="1" <?= old('is_active',$row['is_active'])?'selected':'' ?>>Activo</option>
<option value="0" <?= !old('is_active',$row['is_active'])?'selected':'' ?>>Inactivo</option>
</select>
</div>
<?php endif ?>
<?php if($kind==='lot_types'): ?>
<div class="grid gap-4 sm:grid-cols-3">
<?php foreach(['front_m'=>'Frente (m)','depth_m'=>'Fondo (m)','area_m2'=>'Área (m²)'] as $key=>$label): ?>
<div>
<label for="<?= $key ?>">
<?= $label ?>
</label>
<input id="<?= $key ?>" name="<?= $key ?>" type="number" min="0.001" step="0.001" value="<?= esc(old($key,$row[$key]??''),'attr') ?>">
</div>
<?php endforeach ?>
</div>
<p class="text-xs text-slate-400">Estas medidas son una referencia. No modifican las dimensiones reales ya registradas en cada lote ni habilitan casas automáticamente.</p>
<?php endif ?>
<button class="btn">Guardar cambios</button>
</form>
<?= $this->endSection() ?>
