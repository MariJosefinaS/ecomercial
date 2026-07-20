<?php

namespace App\Support;

class Permisos
{
    /**
     * Catálogo de permisos (tareas) agrupado POR SECCIÓN.
     * Cada sección tiene su permiso de "ver" (controla la solapa) + acciones internas.
     */
    public static function grupos(): array
    {
        return [
            'Panel' => [
                'ver_panel' => 'Ver panel',
            ],
            'Stock' => [
                'ver_stock' => 'Ver stock',
                'gestionar_stock' => 'Crear / editar productos',
                'ajustar_precio' => 'Ajustar precios',
            ],
            'Ventas' => [
                'ver_ventas' => 'Ver ventas',
                'crear_venta' => 'Crear venta',
                'aprobar_ventas' => 'Aprobar / rechazar ventas',
                'entregar_venta' => 'Entregar mercadería (cargar códigos de trazabilidad)',
            ],
            'Compras' => [
                'ver_compras' => 'Ver compras',
                'crear_compra' => 'Registrar compra',
                'aprobar_compras' => 'Aprobar / recibir compras',
            ],
            'Recepción' => [
                'ver_recepcion' => 'Ver recepción de mercadería',
                'recepcionar' => 'Recepcionar mercadería (sumar al stock)',
            ],
            'Traspasos' => [
                'ver_traspasos' => 'Ver traspasos entre sucursales',
                'crear_traspaso' => 'Crear traspaso',
                'aprobar_traspasos' => 'Aprobar / rechazar traspasos',
            ],
            'Proveedores' => [
                'ver_proveedores' => 'Ver proveedores',
                'ver_ficha_proveedor' => 'Ver ficha / cuenta del proveedor',
                'gestionar_proveedores' => 'Crear / editar proveedores',
            ],
            'Clientes' => [
                'ver_clientes' => 'Ver clientes (lista)',
                'ver_cuenta_cliente' => 'Ver cuenta / ficha del cliente',
                'ver_riesgo_cliente' => 'Ver análisis de riesgo',
                'gestionar_clientes' => 'Crear / editar clientes',
            ],
            'Cobranza' => [
                'ver_cobranza' => 'Ver mi planilla de cobranza (cobrador)',
                'registrar_cobro' => 'Registrar cobro de cuota',
                'supervisar_cobranza' => 'Supervisar cobranza (tablero de todos: atrasos, agenda, eficacia)',
                'reportar_no_visita' => 'Reportar "no cobré" (queda pendiente de aprobación del supervisor)',
                'gestionar_novedades_cobranza' => 'Aprobar / marcar "el cobrador no pasó" (suspende mora)',
                'auditar_cobranza' => 'Auditar / cerrar planillas de cobranza',
            ],
            'Devoluciones' => [
                'ver_devoluciones' => 'Ver devoluciones',
                'crear_devolucion' => 'Registrar devolución',
                'aprobar_devoluciones' => 'Aprobar / rechazar devoluciones',
            ],
            'Tesorería' => [
                'ver_tesoreria' => 'Ver tesorería',
                'cargar_cheques' => 'Cargar / gestionar cheques',
                'registrar_pago' => 'Registrar pagos (ingresos/egresos)',
            ],
            'Reportes' => [
                'ver_reportes' => 'Ver reportes',
            ],
            'Usuarios' => [
                'ver_usuarios' => 'Ver usuarios',
                'gestionar_usuarios' => 'Crear / editar usuarios',
                'reset_password' => 'Resetear contraseñas',
            ],
            'Configuración' => [
                'ver_config' => 'Ver configuración',
                'gestionar_roles' => 'Gestionar roles y permisos',
                'gestionar_locales' => 'Gestionar sucursales / locales',
                'gestionar_zonas' => 'Gestionar zonas de cobranza y sus cobradores',
            ],
        ];
    }

    /** @return list<string> todos los permisos (claves) */
    public static function todos(): array
    {
        return array_keys(array_merge(...array_values(self::grupos())));
    }

    /**
     * Permiso requerido por cada ruta (enforcement a nivel ruta vía middleware).
     * El orden define la prioridad de la pantalla de inicio (ver inicio()).
     */
    public static function rutaPermiso(): array
    {
        return [
            'dashboard' => 'ver_panel',
            'recepcion' => 'ver_recepcion',
            'stock' => 'ver_stock',
            'stock.reposicion' => 'gestionar_stock',
            'ventas' => 'ver_ventas',
            'compras' => 'ver_compras',
            'traspasos' => 'ver_traspasos',
            'proveedores' => 'ver_proveedores',
            'clientes' => 'ver_clientes',
            'cobranza' => 'supervisar_cobranza',   // tablero de supervisión (vive en Tesorería)
            'cobranza.planilla' => 'ver_cobranza', // planilla del cobrador
            'tesoreria' => 'ver_tesoreria',
            'devoluciones' => 'ver_devoluciones',
            'reportes' => 'ver_reportes',
            'usuarios' => 'ver_usuarios',
            'configuracion' => 'ver_config',
        ];
    }

    /** Ruta de inicio para el rol: la primera sección que puede ver. */
    public static function inicio(?string $rol): string
    {
        foreach (self::rutaPermiso() as $ruta => $perm) {
            if (self::puede($rol, $perm)) {
                return $ruta;
            }
        }
        return 'login';
    }

    /** Caché de la matriz fusionada (defaults + DB) por request. */
    private static ?array $cache = null;

    /** Roles base del sistema (fallback si la tabla `roles` no existe todavía). */
    private const ROLES_FALLBACK = [
        'super_admin' => 'Super Admin',
        'admin_local' => 'Admin de Local',
        'vendedor' => 'Vendedor',
        'empleado' => 'Empleado',
        'deposito' => 'Encargado de Depósito',
    ];

    /** Claves de los roles de sistema (no borrables). */
    private const ROLES_SISTEMA_FALLBACK = ['super_admin', 'admin_local', 'vendedor', 'empleado', 'deposito'];

    /** @return array<string,string> [clave => nombre] de todos los roles (sistema + custom). */
    public static function rolesNombres(): array
    {
        try {
            $roles = \App\Models\Rol::orderByDesc('es_sistema')->orderBy('nombre')->pluck('nombre', 'clave')->toArray();
            if ($roles !== []) {
                return $roles;
            }
        } catch (\Throwable $e) {
            // La tabla aún no existe (antes de migrar): usar fallback.
        }

        return self::ROLES_FALLBACK;
    }

    /** @return list<string> claves de los roles de sistema (no se pueden borrar). */
    public static function rolesSistema(): array
    {
        try {
            $roles = \App\Models\Rol::where('es_sistema', true)->pluck('clave')->toArray();
            if ($roles !== []) {
                return $roles;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return self::ROLES_SISTEMA_FALLBACK;
    }

    /**
     * Permisos por defecto de cada rol del sistema. Son la base cuando un par
     * (rol, permiso) no fue editado/persistido en la tabla `permisos_rol`.
     */
    public static function defaults(): array
    {
        $todos = self::todos();

        $defaults = [
            'super_admin' => $todos, // todo
            // admin_local hace casi todo, pero la APROBACIÓN de compras (factura) queda
            // reservada al super_admin (admin_local crea la orden, super_admin la aprueba).
            'admin_local' => array_values(array_diff($todos, ['ver_config', 'gestionar_roles', 'aprobar_compras'])),
            // El vendedor ve: Stock (consulta), Ventas (las suyas + cargar nota de
            // pedido), Clientes (elegir + riesgo) y Devoluciones. NO ve Panel ni
            // Proveedores por defecto.
            'vendedor' => [
                'ver_stock', 'ver_ventas', 'crear_venta', 'entregar_venta',
                'ver_clientes', 'ver_cuenta_cliente',
                'ver_devoluciones', 'crear_devolucion',
                // Muchos vendedores también cobran en la calle ("Vtas y Cob.").
                'ver_cobranza', 'registrar_cobro', 'reportar_no_visita',
            ],
            // Ejemplo del usuario: el empleado VE la lista de clientes pero NO su cuenta/ficha.
            'empleado' => ['ver_panel', 'ver_stock', 'ver_clientes'],
            // Encargado de depósito: recepciona mercadería; ve compras (órdenes) y stock para ubicar productos.
            'deposito' => ['ver_recepcion', 'recepcionar', 'ver_compras', 'ver_stock', 'ver_traspasos', 'crear_traspaso'],
        ];

        $m = [];
        foreach (array_keys($defaults) as $rol) {
            foreach ($todos as $perm) {
                $m[$rol][$perm] = in_array($perm, $defaults[$rol], true);
            }
        }
        return $m;
    }

    /**
     * Matriz efectiva = defaults + overrides persistidos en `permisos_rol`.
     * Se cachea por request.
     */
    public static function matriz(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $m = self::defaults();

        // Roles custom (no-sistema): no tienen defaults en el helper, arrancan
        // todo en false y se completan con los overrides de `permisos_rol`.
        try {
            $custom = \App\Models\Rol::where('es_sistema', false)->pluck('clave');
            foreach ($custom as $clave) {
                foreach (self::todos() as $perm) {
                    $m[$clave][$perm] ??= false;
                }
            }
        } catch (\Throwable $e) {
            // La tabla `roles` aún no existe: solo defaults de sistema.
        }

        try {
            foreach (\App\Models\PermisoRol::all() as $row) {
                $m[$row->rol][$row->permiso] = (bool) $row->permitido;
            }
        } catch (\Throwable $e) {
            // La tabla aún no existe (antes de migrar): usar defaults.
        }

        return self::$cache = $m;
    }

    /** Limpia la caché tras guardar permisos. */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /** ¿El rol tiene el permiso? Super admin siempre. */
    public static function puede(?string $rol, string $perm): bool
    {
        if ($rol === 'super_admin') {
            return true;
        }
        return (bool) (self::matriz()[$rol][$perm] ?? false);
    }
}
