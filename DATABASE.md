# Modelo de datos — E.Comercial (MySQL, normalizado 3NF)

Base relacional para la plataforma unificada. Motor: **MySQL 9.1** (dev local) → **MySQL/MariaDB en Hostinger** (prod).
Definido con migraciones Laravel en `database/migrations/` + modelos Eloquent en `app/Models/`.

## Tablas y relaciones

```
locales (1) ───< stock_locales >─── (1) productos
   │                                      │  ├─ (N) categorias  [belongsTo]
   │                                      │  └─ (N) proveedores [belongsTo]
   ├──< users (rol, local_id)
   ├──< ventas ──< venta_items >── productos
   ├──< compras ──< compra_items >── productos
   │       └──< pagos_proveedor (cuentas por pagar)
   └──< solicitudes_compra >── productos / users(solicitante)
activity_logs >── users / locales
```

### Núcleo
| Tabla | Rol | Claves foráneas |
|-------|-----|-----------------|
| `locales` | Los 2 locales | — |
| `users` | Usuarios + **rol** (super_admin/admin_local/vendedor/empleado) + **local_id** | local_id → locales |
| `proveedores` | Proveedores | — |
| `categorias` | Categorías de producto (con ícono) | — |
| `productos` | Catálogo (datos que NO dependen del local) | categoria_id, proveedor_id |
| `stock_locales` | **Stock y PRECIO por producto×local** (unique producto+local) | producto_id, local_id |

> **Normalización clave:** el **precio vive en `stock_locales`**, no en `productos`. Cada local tiene su precio → comparar el mismo producto entre locales produce las **alertas de diferencia de precio**. Sin datos repetidos, en 3NF.

### Operaciones
| Tabla | Rol | FKs |
|-------|-----|-----|
| `compras` | Ingreso de mercadería (OC, factura, estado) | proveedor_id, local_id, usuario_id |
| `compra_items` | Renglones de compra | compra_id, producto_id |
| `ventas` | Ventas (FAC, estado pendiente/aprobada/rechazada) | local_id, vendedor_id, aprobada_por |
| `venta_items` | Renglones de venta | venta_id, producto_id |
| `solicitudes_compra` | Pedidos de reposición de vendedores | producto_id, local_id, solicitante_id |
| `pagos_proveedor` | **Cuentas por pagar** (deuda, vencimiento, estado) | proveedor_id, compra_id |
| `activity_logs` | Feed de actividad del dashboard | usuario_id, local_id |

> Las **"Aprobaciones Pendientes"** del dashboard se obtienen consultando `ventas`, `compras` y `solicitudes_compra` con `estado='pendiente'` (no hay tabla aparte → evita redundancia).

## Cómo crear y poblar la base

Requiere el servidor MySQL corriendo y las credenciales en `.env` (`DB_DATABASE=ecomercial`, `DB_USERNAME`, `DB_PASSWORD`).

1. Crear la base (en MySQL Workbench o consola):
   ```sql
   CREATE DATABASE ecomercial CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Migrar y sembrar datos de ejemplo:
   ```powershell
   php artisan migrate --seed
   ```

El seeder (`database/seeders/DatabaseSeeder.php`) carga: 2 locales, usuarios por rol (pass: `password`), proveedores, categorías, 5 productos con **precios distintos entre locales**, una venta/compra/solicitud **pendientes** (para Aprobaciones), ranking de vendedores, deuda a proveedor y actividad reciente — exactamente lo que muestra el dashboard.

## Próximo paso
Reemplazar los datos **mock** de los componentes Livewire (`app/Livewire/Dashboard/*`) por consultas a estos modelos (los `// TODO` marcan dónde).
