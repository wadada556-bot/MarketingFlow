# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Laravel 12 / PHP 8.2 internal marketing-ops dashboard ("marketing-flow") for managing TikTok Shop
product catalog/pricing and stores across multiple TikTok stores. Server-rendered Blade views (no SPA
framework) with vanilla JS for interactivity (fetch + custom modal overlays — no Bootstrap JS
component, no Alpine). Bootstrap Icons (`bi bi-*`) for icons, Tailwind v4 + a custom Material Design 3
token layer for styling.

Current menu surface (2026-07-18): **Products** (`/products`, also the home page — `/` redirects
here), **Stores**, **Selisih Harga** (`/price-comparison`), and **Perubahan HPP** (`/notifications` —
label only; the route name is historical). Deleted features — do NOT reintroduce routes/links to them
unless explicitly asked: Product Ads / Product Ads New / History Penjualan / ROAS Calculator (removed
~commits `ffeb3f1`/`c58cf04`) and, in the 2026-07-18 big cleanup, **Peringatan Stok** (`/dashboard`,
`DashboardController`, `NotifyStockCheck`, `StockAlertNotification`) and the whole **orders** feature
(`SyncOrders`/`BackfillOrders`/`OrderSyncService`/`DailySalesQueryService`). In that same cleanup the
DB was purged down to 18 tables: all ads tables (`ads`, `ad_weekly_performances`, `ad_logs`,
`ads_old`, `ad_products_old`), all product-ads tables (`product_ads`, `product_ad_store`,
`product_ad_logs`), `daily_*` stats tables, `products`, `orders`, and `sales_sync_state` were dropped
on dev AND prod (user had a full backup; see migration `2026_07_18_100002_drop_unused_tables`).

Data sources today:
- **Jubelio** (ERP/warehouse) — stock, HPP (cost price), PO quantities → `jubelio_inventory`
  (`jubelio:sync-inventory` every 30 min; `hpp:sync` daily 08:30 also emails HPP changes using
  `sku_hpp` as its change-detection baseline — keep `sku_hpp`, it is NOT redundant with
  `jubelio_inventory.hpp` which the 30-min sync overwrites).
- **TikTok Seller Center** — product/SKU listings per store → `tiktok_listings`, `tiktok_listing_skus`
  (imported by an external Python tool, not this app).
- **Prices are manual** — the Tokopedia price scraper was retired 2026-07-17; `tiktok_listing_prices`
  is owned and written solely by this app's UI (see Domain model).

For `jubelio_inventory.hpp`: HPP=0 means "not set" and manual edits are preserved; sync never
overwrites with 0.

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
  - `tiktok_listing_prices` (`store_id`, `product_id`, `sku_id`, `sku_code`, `retail_price`,
    `promotion_price`; unique `(store_id, product_id, sku_code)`) holds the promo/retail price **per
    listing** — one `sku_code` can be listed under >1 TikTok `product_id` in the same store (real,
    ~95 cases in one store), and editing a price from the UI must affect only the specific listing
    the user is looking at. This table replaced both `store_sku_prices` (per store+SKU only, could
    not distinguish listings) and the short-lived `tiktok_listing_price_overrides` in the 2026-07-18
    restructure. It is owned entirely by this app: all manual price-edit endpoints
    (`updatePrice`/`bulkUpdatePrice`/`bulkPriceApply` in `ProductController`) upsert here, and no
    external process writes it. Price semantics follow the old table: 0 = no price/discount set.
  - Price changes are logged automatically by DB trigger `trg_tiktok_listing_prices_history` into
    `tiktok_listing_price_histories` (fires on UPDATE only, and only when the value actually
    changed; includes `product_id`+`sku_id` so history is per listing) — the app never writes
    history rows itself, just reads them back for the "Histori" UI action (filtered by
    store+product_id+sku_code).

### Controllers bypass Eloquent for reporting/listing queries

Most read-heavy endpoints (`ProductController::index/variants`, `PriceComparisonService`) use
`DB::table(...)`/query builder with manual joins/aggregates rather than Eloquent relations —
this is deliberate for query control over multi-table joins across `tiktok_listings` /
`tiktok_listing_skus` / `jubelio_inventory` / `tiktok_listing_prices`. Eloquent models exist for most tables
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
