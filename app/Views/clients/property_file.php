<?php if(auth()->user()->can('clients.view')): ?>
<section class="card p-7">
<h2 class="text-lg font-semibold">Cliente del expediente</h2>
<?php if(!$clientModuleInstalled): ?>
<p class="mt-4 text-sm text-slate-500">Aplica la migración 000006 para habilitar el catálogo y la vinculación de clientes.</p>
<?php elseif($clientFile): ?>
<p class="mt-4 text-xl font-semibold">
<a class="text-brand-700 underline" href="<?= site_url('clientes/'.$clientFile['client_id']) ?>">
<?= esc($clientFile['full_name']) ?>
</a>
</p>
<p class="mt-2 text-xs text-slate-400">
<?= esc($clientFile['client_code']) ?> · Vínculo de expediente por verificar</p>
<p class="mt-4 text-sm text-slate-500">
<?= esc($clientFile['note']) ?>
</p>
<?php if(auth()->user()->can('clients.link')): ?>
<details class="mt-5">
<summary class="cursor-pointer text-sm text-slate-500">Desvincular cliente del expediente</summary>
<form class="mt-4 space-y-3" method="post" action="<?= site_url('inventario/'.$lot['id'].'/cliente/desvincular') ?>" data-confirm="Se finalizará este vínculo conservando su historial. No se modifican ventas ni pagos.">
<?= csrf_field() ?>
<input type="hidden" name="link_id" value="<?= $clientFile['id'] ?>">
<label for="unlink_reason">Motivo</label>
<textarea id="unlink_reason" name="reason" required minlength="5" maxlength="500" rows="2">
</textarea>
<button class="btn-secondary">Desvincular</button>
</form>
</details>
<?php endif ?>
<?php else: ?>
<p class="mt-4 text-sm text-slate-500">Todavía no hay un cliente relacionado con este expediente.</p>
<?php if(auth()->user()->can('clients.link')): ?>
<a class="btn-secondary mt-5" href="<?= site_url('inventario/'.$lot['id'].'/cliente') ?>">Buscar y vincular cliente</a>
<?php endif ?>
<?php endif ?>
<p class="mt-5 text-xs leading-relaxed text-slate-400">La relación del expediente no acredita una venta ni cambia el saldo o la disponibilidad.</p>
</section>
<?php endif ?>
