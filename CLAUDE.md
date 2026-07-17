# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Laravel 12 / PHP 8.2 internal marketing-ops dashboard ("marketing-flow") for managing TikTok Shop
product catalog/pricing and stores across multiple TikTok stores. Server-rendered Blade views (no SPA
framework) with vanilla JS for interactivity (fetch + custom modal overlays — no Bootstrap JS
component, no Alpine). Bootstrap Icons (`bi bi-*`) for icons, Tailwind v4 + a custom Material Design 3
token layer for styling.

Current menu surface (2026-07-17): **Peringatan Stok** (route `/dashboard`, stock alerts only —
GMV/ROAS/chart widgets, store ranking, and ads performance were removed from this page), **Products**,
**Stores**, and **Perubahan HPP** (route `/notifications`, renamed from "Dashboard"/"Notifikasi" —
labels only, routes unchanged). "Perubahan HPP" only ever contains HPP-change entries today because
`HppChangedNotification` is the sole notification persisted to the `notifications` DB table
(`StockAlertNotification` is mail-only, sent via `Notification::route('mail', ...)` — see
`NotifyStockCheck`); if stock alerts are ever also persisted to that table, this menu name will need
revisiting. The Product Ads, Product Ads New, History Penjualan, and ROAS Calculator menus/routes/
controllers/views were deleted (see `git log` around commits `ffeb3f1`/`c58cf04`) — do not reintroduce
routes or links to `product-ads`, `product-ads-new`, `sales-history`, or `roas-calculator` unless
explicitly asked to bring the feature back.

Data mostly originates from **external systems synced by scheduled jobs**, not user input:
- **Jubelio** (ERP/warehouse) — stock, HPP (cost price), PO quantities → `jubelio_inventory`.
- **TikTok Seller Center** — product/SKU listings per store → `tiktok_listings`, `tiktok_listing_skus`
  (imported by an external Python tool, not this app).
- **Tokopedia scrape** (external Python tool) — retail/promo prices per store SKU → `store_sku_prices`.
- **Jubelio Orders API** — incoming orders → `orders` table.
- A separate external ads importer (Python, lives outside this repo) writes to `ads`,
  `ad_weekly_performances`, `ad_logs` — this app only edits the status/testing/notes columns on those
  rows, never re-derives the performance metrics itself.

Because several of these pipelines are external, always check whether a column is "owned" by an
importer (will be silently overwritten on next sync) before writing to it from a controller. Existing
comments in migrations/controllers usually say so explicitly (e.g. HPP=0 means "not set" and manual
edits are preserved; sync never overwrites with 0).

## Commands

```bash
# Local dev (serves app + queue worker + Vite, one command)
composer dev

# Or individually
php artisan serve
npm run dev            # Vite dev server (Tailwind)
npm run build           # Production asset build

# Tests (Pest, but the suite is currently just framework stubs — no real coverage yet)
php artisan test
vendor/bin/pest
vendor/bin/pest tests/Feature/ExampleTest.php   # single file
vendor/bin/pest --filter=testName               # single test

# Lint / format (Laravel Pint, PSR-12-based)
vendor/bin/pint
vendor/bin/pint --test    # check only, no changes

# Migrations
php artisan migrate
php artisan migrate:rollback

# Route/cache introspection
php artisan route:list
php artisan route:list --path=products
```

No JS test runner or JS linter is configured — `package.json` only builds assets (Tailwind via Vite).

## Architecture

### Two-environment topology (important — do not confuse)

- **Dev**: `192.168.3.101`, app on `:8000`, `.env` → `DB_HOST=127.0.0.1` (local DB on that box).
- **Prod**: `192.168.3.251`, separate XAMPP install, app served at **`:3006`** (port `:80` on that
  same host is a *different, unrelated* ERP app — never assume `:80` is this app).
- Deploys to prod run **`deploy.ps1`** *on the prod box itself* (git pull → composer install --no-dev
  → migrate --force → artisan optimize), followed by a **mandatory Apache restart** — prod's php.ini
  has `opcache.validate_timestamps=0`, so new code is invisible until Apache restarts.
  `deploy.ps1` refuses to run if `git status` isn't clean on the server.

### Domain model

- **Stores** (`stores` table) — one row per TikTok shop. Almost everything else is scoped by
  `store_id` because stock/HPP is global but *price and listing identity are per store*.
- **Catalog identity is layered, not a single `product_id`:**
  - `jubelio_inventory.sku_code` (PK) is the global source of truth for stock/HPP/PO — one row per
    physical SKU, independent of any store or TikTok listing.
  - `jubelio_inventory.parent_sku` / `match_sku` group SKU variants that share a base product
    (`match_sku` = `sku_code` with the trailing `-<number>` variant suffix stripped, uppercased).
  - `tiktok_listings.product_id` is TikTok's own listing id — **scoped per store**: the same
    physical product has a *different* `product_id` on each store, so it cannot be used to group a
    product across stores. `tiktok_listing_skus` maps a listing to its N `sku_code`s (via
    `listing_id` + `store_id`).
  - `store_sku_prices` holds retail/promo price per `(store_id, sku_code)`; it is *upserted by an
    external Python scraper*, not by this app's normal write path (manual price edits from the UI
    are a stopgap and will be overwritten by the next scrape/sync).
  - Price changes to `store_sku_prices` are logged automatically by a DB trigger
    (`trg_store_sku_prices_history`) into `store_sku_price_histories` — the app never writes history
    rows itself, just reads them back for the "Histori" UI action.

- **Ads** — the external importer still writes `ads`, `ad_weekly_performances`, `ad_logs`, and the
  legacy `product_ads`/`product_ad_store`/`product_ad_logs` tables (no schema changes were made when
  the Product Ads / Product Ads New menus were removed). This app no longer has any controller/route
  surface for ads management — `ProductAdController`, `ProductAdNewController`, `ProductAdLogController`,
  and `ProductAdService` were deleted. The `ProductAd`, `ProductAdLog`, `ProductAdStore`, and
  `DailyProductAd` **models** were kept because `DashboardController::buildStockAlerts()` and the
  `NotifyStockCheck` command still read `product_ads`/`product_ad_store` to find which parent SKUs are
  actively advertised (and therefore worth a stock alert) and which stores sell them.

- **Orders** (`orders`) — one row per order line item, synced incrementally from Jubelio's `/orders/`
  endpoint keyed on `last_modified` (so only changed orders are re-fetched). `orders:sync` used to run
  daily but is currently **commented out** in `routes/console.php` (disabled intentionally — check
  there before assuming it's live).

### Controllers bypass Eloquent for reporting/listing queries

Most read-heavy endpoints (`ProductController::index/variants`, dashboard stock-alert queries) use
`DB::table(...)`/query builder with manual joins/aggregates rather than Eloquent relations —
this is deliberate for query control over multi-table joins across `tiktok_listings` /
`tiktok_listing_skus` / `jubelio_inventory` / `store_sku_prices`. Eloquent models exist for most tables
(`app/Models/*.php`) but are mainly used for simple writes/relations, not the heavy list queries. Follow
this existing convention (raw query builder for reporting, models for simple CRUD) rather than
introducing Eloquent-only queries into these hot paths.

### Blade + vanilla JS UI pattern

There is no Alpine.js or Bootstrap JS bundle wired in — interactive widgets (expandable rows, edit
modals) are hand-rolled per view:
- Modals are plain `<div>` overlays toggled via `style.display`, opened/closed with a small IIFE in a
  `@push('scripts')` block, not a Bootstrap `Modal` instance.
- Row expansion (e.g. product variant detail in `resources/views/products/index.blade.php`) lazy-loads
  via `fetch()` to a small JSON endpoint (`ProductController::variants`) and builds table rows with a
  JS template-string function, rather than a Blade partial — because the same container needs to be
  rebuilt/patched client-side after inline edits (HPP, promo price) without a full page reload.
  When adding a similar "edit in place" feature, follow the existing `js-hpp-edit` / `js-price-edit`
  pattern (pencil button with `data-*` attributes → shared modal → POST via `fetch` with the CSRF meta
  tag → patch the specific DOM node on success) rather than introducing a new UI library.
- `resources/views/products/partials/table.blade.php` is the server-rendered *outer* listing table
  (one row per `product_id`), separate from the JS-built inner variant-detail table.

### Styling

Tailwind v4 (via `@tailwindcss/vite`) plus a hand-authored Material Design 3 token/component layer.
Prefer the existing `--md-*` CSS variables and `.md-*` component classes (see components under
`resources/views/components/layouts/`) over introducing new ad-hoc styles.
