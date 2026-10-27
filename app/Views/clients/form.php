<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<a class="text-sm text-slate-500" href="<?= site_url('clientes') ?>">← Clientes</a>
<h1 class="mb-2 mt-5 text-3xl font-semibold">
<?= esc($title) ?>
</h1>
<p class="mb-7 text-sm text-slate-500">Registra los datos confirmados. La identificación puede completarse después.</p>
<form method="post" action="<?= site_url('clientes'.(isset($client['id'])?'/'.$client['id']:'')) ?>" class="card max-w-4xl space-y-6 p-7">
<?= csrf_field() ?>
<input type="hidden" name="lock_version" value="<?= $client['lock_version']??0 ?>">
<input type="hidden" name="return_lot" value="<?= $returnLot ?>">
<div class="grid gap-5 sm:grid-cols-2">
<?php foreach(['full_name'=>['Nombre completo *',180],'code'=>['Código de cliente · opcional',30],'email'=>['Correo electrónico',180],'phone'=>['Teléfono',40]] as $key=>[$label,$limit]): ?>
<div>
<label for="<?= $key ?>">
<?= $label ?>
</label>
<input id="<?= $key ?>" name="<?= $key ?>" type="<?= $key==='email'?'email':'text' ?>" maxlength="<?= $limit ?>" <?= $key==='full_name'?'required':'' ?> value="<?= esc(old($key,$client[$key]??''),'attr') ?>">
</div>
<?php endforeach ?>
</div>
<p class="text-xs text-slate-400">Si dejas el código vacío se asignará uno automáticamente. Los nombres iguales se permiten; un documento ya registrado no se duplica.</p>
<fieldset class="rounded-xl border border-slate-200 p-5">
<legend class="px-2 text-sm font-semibold">Identificación · opcional</legend>
<div class="grid gap-4 sm:grid-cols-3">
<div>
<label for="document_type">Tipo</label>
<select id="document_type" name="document_type">
<option value="">Sin identificar</option>
<?php foreach(['dui'=>'DUI','passport'=>'Pasaporte','nit'=>'NIT','other'=>'Otro'] as $value=>$label): ?>
<option value="<?= $value ?>" <?= old('document_type',$client['document_type']??'')===$value?'selected':'' ?>>
<?= $label ?>
</option>
<?php endforeach ?>
</select>
</div>
<div>
<label for="document_country">País de emisión · 2 letras</label>
<input id="document_country" name="document_country" maxlength="2" placeholder="Ejemplo: SV" value="<?= esc(old('document_country',$client['document_country']??''),'attr') ?>">
</div>
<div>
<label for="document_number">Número</label>
<input id="document_number" name="document_number" maxlength="40" value="<?= esc(old('document_number',$client['document_number']??''),'attr') ?>">
</div>
</div>
</fieldset>
<div>
<label for="address">Dirección</label>
<textarea id="address" name="address" rows="2" maxlength="500">
<?= esc(old('address',$client['address']??'')) ?>
</textarea>
</div>
<div>
<label for="notes">Observaciones</label>
<textarea id="notes" name="notes" rows="3" maxlength="2000">
<?= esc(old('notes',$client['notes']??'')) ?>
</textarea>
</div>
<div>
<label for="is_active">Estado del cliente</label>
<select name="is_active" id="is_active">
<option value="1" <?= old('is_active',$client['is_active']??1)?'selected':'' ?>>Activo</option>
<option value="0" <?= !old('is_active',$client['is_active']??1)?'selected':'' ?>>Inactivo</option>
</select>
</div>
<div class="flex gap-3">
<button class="btn">Guardar cliente</button>
<a class="btn-secondary" href="<?= site_url('clientes') ?>">Cancelar</a>
</div>
</form>
<?= $this->endSection() ?>
