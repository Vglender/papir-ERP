# Papir ERP — Project Instructions

## Architecture: Integration Module System

### Core Principle
Every external service (Nova Poshta, MoySklad, Telegram, banks, etc.) connects to Papir through the **Integrations Hub** (`/modules/integrations/`). This is the single source of truth for:
- API keys and credentials (stored in `integration_settings` / `integration_connections` DB tables)
- Active/inactive state per app
- Exchange rules (CRUD-level control for bidirectional syncs)
- Default settings per app

### App Manifest Pattern
Each integration module declares everything it brings into the system via `app.manifest.php`:

```php
// /modules/{module}/app.manifest.php
return [
    'key'      => 'module_key',
    'routes'   => [...],        // registered only if app is active
    'nav'      => [...],        // menu items, shown only if active
    'cron'     => [...],        // cron jobs, skip if inactive
    'webhooks' => [...],        // incoming webhooks with guard
    'tables'   => [...],        // DB tables owned by this app
];
```

**AppRegistry** (`/modules/integrations/AppRegistry.php`) discovers manifests, checks `is_active`, and provides routes/nav/cron for active apps only.

### How to Add a New Integration
1. Create the module in `/modules/{name}/`
2. Add `app.manifest.php` declaring routes, nav, cron, webhooks, tables
3. Add entry to `/modules/integrations/registry.php` (icon, category, settings schema)
4. Seed `is_active` in `integration_settings`
5. If the app has API keys — store in `integration_settings` (single) or `integration_connections` (multiple accounts)
6. Add guards: `AppRegistry::guard('app_key')` at top of webhooks/crons
7. For CRUD-level control: use `MsExchangeGuard` pattern (per-operation toggles)

### Key Rules
- **Never hardcode API keys** in service classes. Read from `IntegrationSettingsService`.
- **Never hardcode nav items** for manifest-managed modules. They come from `AppRegistry::getNavItems()`.
- **Webhook handlers must always respond 200 OK** even when inactive (to prevent external service retries). Use `AppRegistry::guard()`.
- **Cron scripts for integrations** must check `AppRegistry::guard()` or `AppRegistry::isActive()` before doing work.
- **API base URLs** that never change (e.g. `api.moysklad.ru/api/remap/1.2/`) should be hardcoded, not stored in settings.

### Database Tables
- `integration_settings` — key-value settings per app (credentials, defaults, CRUD toggles, is_active)
- `integration_connections` — API keys/accounts with metadata (multiple per app, e.g. NP has 4 senders)

### File Structure
```
/modules/integrations/
├── AppRegistry.php              — manifest discovery + active state
├── IntegrationSettingsService.php — DB read/write for settings
├── MsExchangeGuard.php          — CRUD-level guard for MoySklad
├── registry.php                  — catalog of all apps (metadata, icons, settings schema)
├── index.php / app.php           — UI entry points
├── views/
│   ├── catalog.php               — tile catalog of all apps
│   ├── app_settings.php          — universal settings page
│   ├── novaposhta_settings.php   — custom NP settings
│   └── moysklad_settings.php     — custom MS settings with CRUD matrix
├── api/                          — save_settings, save_connection, toggle_app, toggle_ms_webhook
└── migrations/
```

## Code Conventions
- PHP 5.6 compatible (no `??`, no `[]=` short syntax in some contexts)
- `Database::` static methods for all DB access
- Module pattern: `index.php` sets `$title`, `$activeNav`, `$subNav`, requires `layout.php` → `views/` → `layout_end.php`
- API endpoints return JSON with `{ok: true/false, ...}`