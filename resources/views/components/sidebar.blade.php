@php
    use App\Support\Permisos;
    $rol = auth()->user()?->rol;

    // Cada sección principal expone sus subsecciones (el submenú).
    // 'default' = subsección que se muestra al entrar (sin ?sub= en la URL).
    // Una subsección puede tener 'perm' propio (se oculta si el rol no lo tiene).
    $nav = [
        ['label' => 'Panel', 'icon' => 'grid_view', 'route' => 'dashboard', 'perm' => 'ver_panel', 'default' => 'resumen', 'children' => [
            ['sub' => 'resumen',      'label' => 'Resumen',            'icon' => 'dashboard'],
            ['sub' => 'alertas',      'label' => 'Alertas de precio',  'icon' => 'price_change'],
            ['sub' => 'actividad',    'label' => 'Actividad reciente', 'icon' => 'history'],
            ['sub' => 'aprobaciones', 'label' => 'Aprobaciones',       'icon' => 'fact_check'],
            ['sub' => 'ranking',      'label' => 'Ranking',            'icon' => 'leaderboard'],
        ]],
        ['label' => 'Stock', 'icon' => 'inventory_2', 'route' => 'stock', 'perm' => 'ver_stock', 'default' => 'consulta', 'children' => [
            ['sub' => 'consulta', 'label' => 'Consulta de stock', 'icon' => 'search'],
            ['sub' => 'catalogo', 'label' => 'Catálogo',          'icon' => 'inventory_2', 'perm' => 'gestionar_stock'],
            ['route' => 'stock.reposicion', 'label' => 'Reposición (EOQ)', 'icon' => 'all_inbox', 'perm' => 'gestionar_stock'],
            ['route' => 'stock.valorizado', 'label' => 'Stock valorizado', 'icon' => 'paid', 'perm' => 'gestionar_stock'],
        ]],
        ['label' => 'Ventas', 'icon' => 'point_of_sale', 'route' => 'ventas', 'perm' => 'ver_ventas', 'default' => 'mis', 'children' => [
            ['sub' => 'mis',   'label' => 'Mis ventas',        'icon' => 'receipt_long'],
            ['sub' => 'todas', 'label' => 'Todas las ventas',  'icon' => 'list', 'perm' => 'aprobar_ventas'],
            ['route' => 'ventas.nueva', 'label' => 'Nota de pedido', 'icon' => 'add_shopping_cart', 'perm' => 'crear_venta'],
        ]],
        ['label' => 'Compras', 'icon' => 'shopping_cart', 'route' => 'compras', 'perm' => 'ver_compras', 'default' => 'listado', 'children' => [
            ['sub' => 'listado', 'label' => 'Listado', 'icon' => 'receipt_long'],
            ['route' => 'recepcion', 'label' => 'Recepción', 'icon' => 'move_to_inbox', 'perm' => 'ver_recepcion'],
        ]],
        ['label' => 'Traspasos', 'icon' => 'swap_horiz', 'route' => 'traspasos', 'perm' => 'ver_traspasos', 'default' => 'listado', 'children' => [
            ['sub' => 'listado', 'label' => 'Listado', 'icon' => 'list'],
        ]],
        ['label' => 'Proveedores', 'icon' => 'local_shipping', 'route' => 'proveedores', 'perm' => 'ver_proveedores', 'default' => 'listado', 'children' => [
            ['sub' => 'listado', 'label' => 'Listado', 'icon' => 'list'],
        ]],
        ['label' => 'Clientes', 'icon' => 'groups', 'route' => 'clientes', 'perm' => 'ver_clientes', 'default' => 'listado', 'children' => [
            ['sub' => 'listado', 'label' => 'Listado', 'icon' => 'list'],
        ]],
        ['label' => 'Cobranza', 'icon' => 'request_quote', 'route' => 'cobranza.planilla', 'perm' => 'ver_cobranza', 'default' => null, 'children' => [
            ['route' => 'cobranza.planilla', 'label' => 'Mi planilla', 'icon' => 'receipt_long'],
        ]],
        ['label' => 'Devoluciones', 'icon' => 'assignment_return', 'route' => 'devoluciones', 'perm' => 'ver_devoluciones', 'default' => 'listado', 'children' => [
            ['sub' => 'listado', 'label' => 'Listado', 'icon' => 'list'],
        ]],
        ['label' => 'Tesorería', 'icon' => 'account_balance', 'route' => 'tesoreria', 'perm' => 'ver_tesoreria', 'default' => 'resumen', 'children' => [
            ['sub' => 'resumen',    'label' => 'Resumen',             'icon' => 'dashboard'],
            ['route' => 'cobranza', 'label' => 'Cobranza (supervisión)', 'icon' => 'request_quote', 'perm' => 'supervisar_cobranza'],
            ['route' => 'tesoreria.cobros', 'label' => 'Cobros y rendición', 'icon' => 'receipt_long', 'perm' => 'registrar_pago'],
            ['route' => 'tesoreria.empleados', 'label' => 'Pago a empleados', 'icon' => 'badge', 'perm' => 'pagar_empleados'],
            ['route' => 'tesoreria.autorizaciones', 'label' => 'Autorización de pagos', 'icon' => 'approval', 'perm' => 'ver_tesoreria'],
            ['sub' => 'caja',       'label' => 'Movimientos de caja', 'icon' => 'payments'],
            ['route' => 'tesoreria.cheques', 'label' => 'Cheques (cartera)', 'icon' => 'account_balance_wallet', 'perm' => 'ver_tesoreria'],
            ['sub' => 'proyeccion', 'label' => 'Proyección',          'icon' => 'show_chart'],
        ]],
        ['label' => 'Comprobantes', 'icon' => 'receipt_long', 'route' => 'comprobantes', 'perm' => 'ver_comprobantes', 'default' => 'todos', 'children' => [
            ['sub' => 'todos',        'label' => 'Todos',             'icon' => 'list'],
            ['sub' => 'factura',      'label' => 'Facturas',          'icon' => 'description'],
            ['sub' => 'nota_credito', 'label' => 'Notas de crédito',  'icon' => 'undo'],
            ['sub' => 'recibo',       'label' => 'Recibos',           'icon' => 'receipt'],
            ['sub' => 'orden_pago',   'label' => 'Órdenes de pago',   'icon' => 'payments'],
        ]],
        ['label' => 'Reportes', 'icon' => 'bar_chart', 'route' => 'reportes', 'perm' => 'ver_reportes', 'default' => 'ranking', 'children' => [
            ['sub' => 'ranking',    'label' => 'Ranking de vendedores', 'icon' => 'leaderboard'],
            ['sub' => 'locales',    'label' => 'Ventas por local',      'icon' => 'store'],
            ['sub' => 'tendencia',  'label' => 'Tendencia',             'icon' => 'show_chart'],
            ['sub' => 'productos',  'label' => 'Más vendidos',          'icon' => 'emoji_events'],
        ]],
        ['label' => 'Usuarios', 'icon' => 'group', 'route' => 'usuarios', 'perm' => 'ver_usuarios', 'default' => 'listado', 'children' => [
            ['sub' => 'listado', 'label' => 'Listado', 'icon' => 'list'],
        ]],
        ['label' => 'Configuración', 'icon' => 'settings', 'route' => 'configuracion', 'perm' => 'ver_config', 'default' => 'roles', 'children' => [
            ['sub' => 'roles',      'label' => 'Roles',               'icon' => 'badge'],
            ['sub' => 'permisos',   'label' => 'Permisos por rol',    'icon' => 'lock'],
            ['sub' => 'parametros', 'label' => 'Parámetros',          'icon' => 'tune'],
            ['sub' => 'sucursales', 'label' => 'Sucursales',          'icon' => 'store',    'perm' => 'gestionar_locales'],
            ['sub' => 'conceptos',  'label' => 'Conceptos de precio', 'icon' => 'percent'],
            ['sub' => 'creditos',   'label' => 'Productos de crédito', 'icon' => 'credit_score'],
            ['sub' => 'comisiones', 'label' => 'Comisiones de cobrador', 'icon' => 'paid', 'solo_super' => true],
            ['sub' => 'zonas',      'label' => 'Zonas de cobranza',   'icon' => 'pin_drop', 'perm' => 'gestionar_zonas'],
            ['sub' => 'categorias', 'label' => 'Categorías',          'icon' => 'category', 'perm' => 'gestionar_stock'],
        ]],
    ];

    $nav = array_filter($nav, fn ($i) => Permisos::puede($rol, $i['perm']));
    $curSub = request('sub');
@endphp

{{-- h-dvh (no h-screen): en mobile 100vh queda detrás de la barra del navegador y el
     pie con "Cerrar sesión" cae fuera de la pantalla; el viewport dinámico lo evita. --}}
<aside class="fixed left-0 top-0 z-50 flex h-dvh w-64 transform flex-col bg-anthracite text-gray-300 transition-transform duration-200 lg:!translate-x-0"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    {{-- Logo (recreación vectorial del Manual de Marca) --}}
    <div class="px-6 py-6">
        <x-logo :on-dark="true" />
        <p class="mt-2 text-[11px] font-medium uppercase tracking-wide text-gray-400">Consola de Administración</p>
    </div>

    <nav class="mt-2 flex-1 space-y-1 overflow-y-auto px-3 pb-4">
        @foreach ($nav as $item)
            @php
                // Subsecciones visibles según permiso propio (si lo tienen).
                $children = array_filter($item['children'], fn ($c) => (! isset($c['perm']) || Permisos::puede($rol, $c['perm'])) && (! ($c['solo_super'] ?? false) || $rol === 'super_admin'));
                // La sección está activa si estás en su ruta o en la de algún hijo (otra ruta).
                $active = request()->routeIs($item['route'])
                    || collect($children)->contains(fn ($c) => isset($c['route']) && request()->routeIs($c['route']));
            @endphp

            <details class="group" @if ($active) open @endif>
                <summary class="flex cursor-pointer list-none items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition-colors
                                {{ $active ? 'bg-brand text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' }}">
                    <span class="material-symbols-outlined text-[22px]">{{ $item['icon'] }}</span>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    <span class="material-symbols-outlined text-[20px] opacity-70 transition-transform group-open:rotate-180">expand_more</span>
                </summary>

                <div class="mt-1 space-y-0.5 pl-4">
                    @foreach ($children as $child)
                        @php
                            $hijoRuta = $child['route'] ?? null;
                            $subActive = $hijoRuta
                                ? request()->routeIs($hijoRuta)
                                : (request()->routeIs($item['route']) && ($curSub === $child['sub'] || ($curSub === null && $child['sub'] === $item['default'])));
                            $href = $hijoRuta ? route($hijoRuta) : route($item['route'], ['sub' => $child['sub']]);
                        @endphp
                        <a href="{{ $href }}" wire:navigate
                           @if ($subActive) aria-current="page" @endif
                           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors
                                  {{ $subActive ? 'bg-white/10 text-white' : 'text-gray-400 hover:bg-white/5 hover:text-white' }}">
                            <span class="material-symbols-outlined text-[18px] {{ $subActive ? 'text-brand' : '' }}">{{ $child['icon'] }}</span>
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                </div>
            </details>
        @endforeach
    </nav>

    <div class="space-y-1 border-t border-white/10 px-3 py-4">
        <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-gray-400 transition-colors hover:bg-white/5 hover:text-white">
            <span class="material-symbols-outlined text-[22px]">help</span> Soporte
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-gray-400 transition-colors hover:bg-white/5 hover:text-white">
                <span class="material-symbols-outlined text-[22px]">logout</span> Cerrar sesión
            </button>
        </form>
    </div>
</aside>
