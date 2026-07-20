# Cómo correr la app interna (E.Comercial)

Proyecto **Laravel 13.8 + Livewire 4.3.1 + Tailwind v3**. Entorno PHP instalado vía **Scoop** (no requiere admin).

## Levantar el servidor

Desde una terminal:

```powershell
# 1) compilar assets (una vez, o `npm run dev` para hot-reload mientras diseñás)
npm run build        # o: npm run dev

# 2) servidor PHP
php artisan serve --host=127.0.0.1 --port=8000
```

Abrir: **http://127.0.0.1:8000/dashboard**

> Si `php` o `composer` no se encuentran, están en `~/scoop/shims`. Composer se invoca:
> `php ~/scoop/apps/composer/current/composer.phar <comando>`

## Estructura clave

```
app/Livewire/Dashboard/        ← secciones (PriceDifferenceAlerts, RecentActivity, PendingApprovals, TopSellers)
resources/views/
├── components/                ← shell (layouts/app, sidebar, topbar, footer-bar) + primitivos (kpi-card, panel, avatar, type-badge, quick-actions)
├── livewire/dashboard/        ← vistas de las secciones
└── dashboard.blade.php        ← página que compone todo
routes/web.php                 ← / → /dashboard
tailwind.config.js             ← tokens de marca (#EC6A19 / #6F6F6E)
public/img/logo-ecomercial.png ← logo del manual
```

## Datos
Hoy las secciones usan datos **mock** en `mount()` (ver `// TODO`). El próximo paso es modelar la base de datos MySQL y reemplazar esos mocks por consultas reales.

## Notas de entorno
- PHP 8.5.7 con `php.ini` en `~/scoop/apps/php/8.5.7/php.ini` (extensiones habilitadas: openssl, pdo_mysql, mbstring, curl, fileinfo, gd, zip, sqlite, intl).
- DB por defecto: SQLite (`database/database.sqlite`) — para producción se migra a MySQL (Hostinger).
