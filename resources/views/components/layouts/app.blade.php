<!DOCTYPE html>
<html lang="es-AR" class="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'E.Comercial — Consola de Administración' }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">

    <x-sidebar />

    {{-- Overlay para el drawer en mobile --}}
    <div x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false"
         class="fixed inset-0 z-40 bg-black/40 lg:hidden"></div>

    <main class="flex min-h-screen flex-col lg:ml-64">
        <x-topbar />

        <div class="flex-1 space-y-6 p-4 sm:p-6">
            {{ $slot }}
        </div>

        <x-footer-bar />
    </main>

    @livewireScripts
</body>
</html>
