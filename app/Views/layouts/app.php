<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>
<?= esc($title ?? 'Villa Verde') ?> · Villa Verde</title>
<link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/interactions.css') ?>">
<script defer src="<?= base_url('assets/interactions.js') ?>">
</script>
</head>
<body>
<div class="min-h-screen lg:grid lg:grid-cols-[232px_1fr]">
<aside data-sidebar class="hidden border-r border-[#e3e9e3] bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col">
<a href="<?= site_url('inventario') ?>" class="block px-7 pb-6 pt-5">
<img src="<?= base_url('assets/villa-verde-logo.jpg') ?>" alt="Residencial Villa Verde" class="mx-auto h-32 w-40 object-contain">
<div class="eyebrow text-center">Gestión administrativa</div>
</a>
<div class="px-5">
<p class="eyebrow mb-3 px-4">Espacio de trabajo</p>
<nav class="space-y-1" aria-label="Navegación principal">
<?php foreach (['inventario'=>['Inventario','▦','inventory.view'],'catalogos'=>['Catálogos','◇','inventory.manage'],'configuracion'=>['Configuración','⚙','settings.manage']] as $url=>[$text,$icon,$permission]): if (!auth()->user()->can($permission)) continue; ?>
<a class="nav-link <?= str_starts_with(uri_string(),$url)?'active':'' ?>" href="<?= site_url($url) ?>">
<span aria-hidden="true" class="text-lg">
<?= $icon ?>
</span>
<?= $text ?>
</a>
<?php endforeach ?>
</nav>
</div>
<div class="mx-6 mt-8 rounded-xl border border-brand-100 bg-brand-50 p-4 lg:mt-auto lg:mb-6">
<p class="text-xs font-semibold text-brand-700">Cada propiedad, en un lugar.</p>
<p class="mt-2 text-xs leading-relaxed text-slate-500">Disponibilidad y datos del proyecto para tomar mejores decisiones.</p>
</div>
</aside>
<div class="min-w-0">
<header class="flex h-20 items-center justify-between border-b border-[#e3e9e3] bg-white/80 px-5 lg:px-10">
<div class="flex items-center gap-3">
<button data-menu class="btn-secondary lg:hidden" aria-label="Abrir navegación" aria-expanded="false">☰</button>
<span class="text-sm font-medium">Residencial Villa Verde</span>
<span class="hidden rounded-full bg-brand-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-700 sm:inline">Administración</span>
</div>
<div class="flex items-center gap-3">
<span class="hidden text-sm text-slate-500 sm:inline">
<?= esc(auth()->user()->username ?? 'Usuario') ?>
</span>
<span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-700" aria-hidden="true">
<?= esc(mb_strtoupper(mb_substr(auth()->user()->username ?? 'U',0,1))) ?>
</span>
<form method="post" action="<?= url_to('logout') ?>">
<?= csrf_field() ?>
<button class="text-xs text-slate-500 hover:text-brand-700">Salir</button>
</form>
</div>
</header>
<main class="mx-auto max-w-[1600px] p-5 lg:p-10">
<?php if(session('error')): ?>
<div class="alert border-red-200 bg-red-50 text-red-800" role="alert">
<?= esc(session('error')) ?>
</div>
<?php endif ?>
<?php if(session('success')): ?>
<div class="alert border-brand-100 bg-brand-50 text-brand-700" role="status">
<?= esc(session('success')) ?>
</div>
<?php endif ?>
<?= $this->renderSection('content') ?>
<footer class="mt-12 flex flex-wrap justify-between gap-3 border-t border-slate-200 pt-5 text-[11px] text-slate-400">
<span>Residencial Villa Verde · Gestión administrativa</span>
<span>Zona horaria: El Salvador</span>
</footer>
</main>
</div>
</div>
</body>
</html>
