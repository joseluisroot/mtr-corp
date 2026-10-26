<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ingresar · Villa Verde</title>
<link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
</head>
<body>
<main class="grid min-h-screen lg:grid-cols-2">
<section class="relative hidden flex-col justify-between overflow-hidden bg-[#183c2c] p-16 text-white lg:flex">
<div class="text-sm tracking-[0.25em]">RESIDENCIAL VILLA VERDE</div>
<div class="relative z-10">
<p class="mb-6 text-xs uppercase tracking-[0.2em] text-emerald-300">Un proyecto. Una visión compartida.</p>
<h1 class="max-w-lg text-5xl font-light leading-tight">Todo lo que necesitas para<br>
<span class="font-semibold">seguir creciendo.</span>
</h1>
<p class="mt-7 max-w-sm text-base leading-relaxed text-emerald-100/70">El control de tus propiedades, reunido en un espacio claro y organizado.</p>
</div>
<div class="relative z-10 text-xs text-emerald-100/50">Plataforma de gestión administrativa</div>
<div aria-hidden="true" class="absolute -bottom-32 -right-44 h-[600px] w-[600px] rounded-full border border-white/10">
</div>
<div aria-hidden="true" class="absolute -bottom-16 -right-28 h-[420px] w-[420px] rounded-full border border-white/10">
</div>
</section>
<section class="flex items-center justify-center p-8">
<div class="w-full max-w-sm">
<img class="mx-auto mb-8 h-36 w-48 object-contain" src="<?= base_url('assets/villa-verde-logo.jpg') ?>" alt="Residencial Villa Verde">
<p class="eyebrow">Bienvenido a tu espacio</p>
<h2 class="mt-3 text-3xl font-semibold">Iniciar sesión</h2>
<p class="mb-8 mt-3 text-sm text-slate-500">Ingresa con la cuenta asignada por administración.</p>
<?php if(session('error')): ?>
<p class="alert border-red-200 bg-red-50 text-red-700" role="alert">
<?= esc(session('error')) ?>
</p>
<?php endif ?>
<?php if(session('errors')): ?>
<div class="alert border-red-200 bg-red-50 text-red-700" role="alert">
<?php foreach((array)session('errors') as $error): ?>
<p>
<?= esc($error) ?>
</p>
<?php endforeach ?>
</div>
<?php endif ?>
<form method="post" action="<?= url_to('login') ?>" class="space-y-5">
<?= csrf_field() ?>
<div>
<label for="email">Correo electrónico</label>
<input id="email" name="email" type="email" autocomplete="username" required value="<?= esc(old('email'),'attr') ?>">
</div>
<div>
<label for="password">Contraseña</label>
<input id="password" name="password" type="password" autocomplete="current-password" required>
</div>
<button class="btn w-full py-3">Entrar a la plataforma <span aria-hidden="true">→</span>
</button>
</form>
<p class="mt-8 text-center text-xs leading-relaxed text-slate-400">¿Necesitas acceso? Comunícate con el administrador.<br>El registro de cuentas está restringido.</p>
</div>
</section>
</main>
</body>
</html>
