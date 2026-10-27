<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $editing=$op!==null;$kind=old('purchase_kind',$op['purchase_kind']??''); ?>
<a class="text-sm text-slate-500" href="<?= site_url('inventario/'.$lot['id']) ?>">← Propiedad <?= esc($lot['polygon_code'].'-'.$lot['number']) ?>
</a>
<h1 class="mb-2 mt-5 text-3xl font-semibold">
<?= esc($title) ?>
</h1>
<p class="mb-7 text-sm text-slate-500">
<?= $historical?'Registra el acuerdo que realmente existe. No es necesario crear una negociación nueva para un antecedente histórico.':($editing?'Revisar las condiciones no reinicia el plazo de negociación.':'Registra interesados sin exclusividad o asigna una negociación con plazo.') ?>
</p>
<?php if(!$historical && !$editing): ?>
<form method="get" class="card mb-5 flex items-end gap-3 p-5">
<div class="flex-1">
<label for="q">Buscar cliente existente</label>
<input id="q" name="q" value="<?= esc($q,'attr') ?>" maxlength="100">
</div>
<button class="btn-secondary">Buscar</button>
</form>
<?php endif ?>
<?php if($historical && !$file): ?>
<div class="alert border-orange-200 bg-orange-50 text-orange-900">Vincula primero el cliente del expediente desde la ficha de la propiedad.</div>
<?php else: ?>
<form method="post" enctype="multipart/form-data" data-commercial-form data-confirm="Se guardará la operación con sus condiciones e historial. Los documentos de respaldo se conservarán privados." action="<?= site_url($editing?'operaciones/'.$op['id'].'/editar':'inventario/'.$lot['id'].($historical?'/historico':'/negociar')) ?>" class="card max-w-4xl space-y-6 p-7">
<?= csrf_field() ?>
<?php if($editing): ?>
<input type="hidden" name="lock_version" value="<?= $op['lock_version'] ?>">
<p class="font-semibold">
<?= esc($op['full_name']) ?> · Operación #<?= $op['id'] ?>
</p>
<?php elseif($historical): ?>
<p class="font-semibold">Cliente del expediente: <?= esc($file['full_name']) ?>
</p>
<div class="grid gap-4 sm:grid-cols-2">
<div>
<label for="historical_state">Antecedente verificado</label>
<select id="historical_state" name="state" required>
<option value="">Seleccionar</option>
<option value="reserved">Reserva histórica</option>
<option value="sold">Venta histórica</option>
</select>
</div>
<div>
<label for="operation_date">Fecha real del acuerdo</label>
<input type="date" id="operation_date" name="operation_date" max="<?= date('Y-m-d') ?>" required value="<?= esc(old('operation_date'),'attr') ?>">
</div>
</div>
<?php else: ?>
<div class="grid gap-4 sm:grid-cols-2">
<div>
<label for="intent">Acción</label>
<select id="intent" name="intent" data-intent>
<option value="negotiating" <?= old('intent','negotiating')==='negotiating'?'selected':'' ?>>Asignar negociación con plazo</option>
<option value="interested" <?= old('intent')==='interested'?'selected':'' ?>>Registrar solo como interesado</option>
</select>
</div>
<div>
<label for="client_id">Cliente activo</label>
<select id="client_id" name="client_id" required>
<option value="">Seleccionar cliente</option>
<?php foreach($clients['rows'] as $c): ?>
<option value="<?= $c['id'] ?>" <?= old('client_id')==$c['id']?'selected':'' ?>>
<?= esc($c['code'].' · '.$c['full_name']) ?>
</option>
<?php endforeach ?>
</select>
</div>
</div>
<p class="text-xs text-slate-400">Se muestran hasta 25 clientes por búsqueda. Registra al cliente en su catálogo si todavía no existe.</p>
<?php endif ?>
<?php if(!$editing): ?>
<div>
<label for="seller_id">Responsable de seguimiento</label>
<select id="seller_id" name="seller_id" required>
<?php foreach($sellers as $seller): ?>
<option value="<?= $seller['id'] ?>" <?= old('seller_id',auth()->id())==$seller['id']?'selected':'' ?>>
<?= esc($seller['username']??'Usuario #'.$seller['id']) ?>
</option>
<?php endforeach ?>
</select>
</div>
<?php endif ?>
<fieldset data-terms class="space-y-5 rounded-xl border border-slate-200 p-5">
<legend class="px-2 font-semibold">Condiciones de la propiedad</legend>
<div>
<label for="purchase_kind">Modalidad</label>
<select id="purchase_kind" name="purchase_kind" data-kind required>
<option value="">Seleccionar modalidad</option>
<option value="lot" <?= $kind==='lot'?'selected':'' ?>>Solo lote</option>
<option value="lot_house" <?= $kind==='lot_house'?'selected':'' ?>>Lote más casa</option>
</select>
</div>
<div data-house-field>
<label for="house_model_id">Modelo habilitado para este lote</label>
<select id="house_model_id" name="house_model_id" required>
<option value="">Seleccionar modelo</option>
<?php foreach($lot['models'] as $model): if(!$model['is_enabled'] || !$model['model_active']) continue; ?>
<option value="<?= $model['house_model_id'] ?>" <?= old('house_model_id',$op['house_model_id']??'')==$model['house_model_id']?'selected':'' ?>>
<?= esc($model['name']) ?>
</option>
<?php endforeach ?>
</select>
</div>
<div class="grid gap-4 sm:grid-cols-2">
<div>
<label for="land_amount">Terreno · <?= esc($lot['currency']) ?>
</label>
<input type="number" id="land_amount" name="land_amount" min="0" step="0.01" <?= $historical?'required':'' ?> value="<?= esc(old('land_amount',$op['land_amount']??''),'attr') ?>" placeholder="<?= $historical?'Importe del acuerdo':'Vacío = tarifa vigente' ?>">
</div>
<div data-house-field>
<label for="construction_amount">Construcción · <?= esc($lot['currency']) ?>
</label>
<input type="number" id="construction_amount" name="construction_amount" min="0" step="0.01" <?= $historical?'required':'' ?> value="<?= esc(old('construction_amount',$op['construction_amount']??''),'attr') ?>" placeholder="<?= $historical?'Importe del acuerdo':'Vacío = tarifa vigente' ?>">
</div>
</div>
<div>
<label for="price_reason">
<?= $historical?'Referencia que respalda el precio histórico':'Motivo si el precio difiere del catálogo' ?>
</label>
<textarea id="price_reason" name="price_reason" maxlength="500" rows="2" <?= $historical?'required minlength="5"':'' ?>>
<?= esc(old('price_reason',$op['price_reason']??'')) ?>
</textarea>
</div>
<p class="text-xs text-slate-500">
<?= $historical?'El precio se conservará como antecedente, sin aplicar una tarifa actual ni calcular pagos realizados.':'Una tarifa vigente es necesaria. Un importe distinto requiere permiso de autorización; el catálogo original no se modifica.' ?>
</p>
</fieldset>
<div>
<label for="note">Motivo o detalle del seguimiento</label>
<textarea id="note" name="note" minlength="5" maxlength="2000" rows="3" required>
<?= esc(old('note')) ?>
</textarea>
</div>
<?php if($historical): ?>
<div>
<label for="support">Respaldo del acuerdo verificado · obligatorio</label>
<input id="support" type="file" name="support" accept=".pdf,.jpg,.jpeg,.png" required>
<p class="mt-2 text-xs text-slate-400">PDF, JPG o PNG · Máximo 5 MB · Descarga privada para usuarios autorizados.</p>
</div>
<label class="flex items-start gap-3">
<input type="checkbox" name="verified" value="1" required>
<span>Verifiqué el cliente, el acuerdo y su documento. Confirmo que el estado y los importes corresponden al antecedente real.</span>
</label>
<?php endif ?>
<button class="btn">
<?= $historical?'Guardar antecedente verificado':($editing?'Guardar condiciones':'Guardar seguimiento') ?>
</button>
</form>
<?php endif ?>
<?= $this->endSection() ?>
