<footer class="flex items-center justify-between border-t border-gray-200 px-6 py-3 text-xs font-medium text-muted"
        x-data="{ now: new Date().toLocaleTimeString('es-AR') }"
        x-init="setInterval(() => now = new Date().toLocaleTimeString('es-AR'), 1000)">
    <span class="flex items-center gap-1.5">
        <span class="h-2 w-2 rounded-full bg-success"></span> Última sincronización: hace 1 min
    </span>
    <span class="tabular" x-text="now">--:--:--</span>
</footer>
