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
- **Jubelio** (ERP/warehouse) — stock, HPP (cost price), PO quantities → **`products`** (master
  table, 1 row per physical SKU). A single daily sync `jubelio:sync-inventory` (23:58 WIB) writes
  everything: it upserts stock/PO/labels AND updates `hpp` (guarded: skips HPP=0 so manual bundling
  values are never overwritten) plus does HPP change-detection for the email. The Jubelio inventory
  endpoint returns stock and HPP in the same response, so both come from one `fetchAllInventory()`
  pass (page_size 200) — there is no separate HPP fetch. Because this command is the sole HPP writer
  from sync, the stored value acts as the change-detection baseline (vs the previous run). The
  standalone `hpp:sync` command was removed (folded into `jubelio:sync-inventory` 2026-07-21).
- **TikTok Seller Center** — product/SKU listings per store → `tiktok_listings`,
  `tiktok_listing_skus`. **The external listing importer was deleted 2026-07-16** (repo
  `C:\generate-diskon-tiktok`) — no external process writes ANY table anymore; a future importer
  must target the current schema.
- **Prices are manual** — the Tokopedia price scraper was retired 2026-07-17; `tiktok_listing_prices`
  is owned and written solely by this app's UI (see Domain model).

For `products.hpp`: HPP=0 means "not set" and manual edits are preserved; sync never overwrites
with 0.

Full pre-restructure dumps of the 5 core tables (dev & prod) live in
`C:\db-backup-marketing-flow\*_5core_*.sql`.

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

- **Stores** (`stores` table) — one row per TikTok shop. Listing identity and prices are scoped
  per store; stock/HPP is global (master `products`).
- **Core schema (redesigned 2026-07-18, full FK chain — naming rule: `product_id` columns are
  ALWAYS FK→`products.id`; TikTok's own ids are `tiktok_product_id`/`tiktok_sku_id`):**
  - `products` — MASTER, 1 row per physical SKU: `id`, `sku_code` (unique), `parent_sku` (variant
    grouping derived from Jubelio's item_group_id at sync time — the column itself was dropped),
    `variation_label`, `stok`, `hpp`, `po_qty`, `synced_at`.
  - `tiktok_listings` — `id`, `store_id` FK, `tiktok_product_id` (TikTok's listing id, unique per
    store; the same physical product has a different one on each store, so it can NOT group a
    product across stores).
  - `tiktok_listing_skus` — bridge listing↔product: `listing_id` FK→tiktok_listings (cascade),
    `product_id` FK→products (cascade), `tiktok_sku_id`; unique `(listing_id, tiktok_sku_id)`.
  - `tiktok_listing_prices` — owned entirely by this app (all manual price-edit endpoints in
    `ProductController` upsert here; no external writer): `listing_id` FK, `product_id` FK,
    `retail_price`, `promotion_price`; unique `(listing_id, product_id)`. Per-listing prices exist
    because one SKU can be listed under >1 TikTok listing in the same store (~95 real cases) and
    editing must affect only the listing being viewed. 0 = no price/discount set.
  - `tiktok_listing_price_histories` — audit snapshot, DELIBERATELY denormalized
    (`store_id`, `tiktok_product_id`, `tiktok_sku_id`, `sku_code`, price_type, old/new, changed_at)
    so history stays readable even if the listing/product is deleted. Filled ONLY by DB trigger
    `trg_tiktok_listing_prices_history` (BEFORE UPDATE, only when a value actually changed; it
    SELECTs the identity from listings/products since price rows only carry FKs). The app just
    reads it back for the "Histori" modal (filtered by store+tiktok_product_id+sku_code).
  - **JSON/HTTP contract note:** endpoint payloads and JSON fields still use the names
    `product_id`/`sku_id` for TikTok ids (aliased in SELECTs) — the Blade/JS layer predates the
    rename and was intentionally left unchanged.

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
