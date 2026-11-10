# Development

`readme.txt` is the WordPress.org readme for store owners. This file is for working on the code.

The plugin is procedural WordPress code: a main file and `includes/`, every function, constant
and option prefixed `tvarkom_obd` / `TVARKOM_OBD`. It supports PHP 7.4+ and WordPress 6.5+, and
needs WooCommerce. Text domain: `pirkoo`, the WordPress.org slug
(it must always equal the slug; the plugin is called Pirkoo, "On-board Diagnostics Scanner for WooCommerce", since 10 Nov 2026; the repo and folder are `pirkoo`).

## How it works

```
Connect and scan (admin.php) ──▶ register (api.php) ──▶ POST /v1/audits {product: "obd"} (scan.php)
        │                         gateway fetches /wp-json/tvarkom-obd/v1/verify (rest.php)
        ▼
Scan now (admin.php, once a week) ──▶ POST /v1/audits again
        ▼
The page (admin.php) ──▶ GET /v1/results ──▶ dashboard (dashboard.php): gauge, lost points, bars, history
```

- Nothing is sent before an administrator clicks **Connect and scan** (`connected_at` in the
  option `tvarkom_obd`), and every scan is a click: there is no scheduled scan (founder decision of
  9 Nov 2026: automatic weekly scans are for a future paid version).
- The gateway (`api-gateway`) allows one scan a week per store, shared with the Checkout Speed
  Audit plugin. The page disables **Scan now** until `GET /v1/results`' `next_scan_at`; a failed
  scan doesn't count (the gateway caps attempts at 3 a day). Its audits run as plan `obd` in the
  audit tool, which emails nothing.
- The dashboard reads only what the gateway's audit history keeps (`GET /v1/results`): score,
  level, the metric catalog's values and the points each scoring rule cost. The fix plan is never
  sent to the plugin. The references in `dashboard.php` are the scanner's
  (`audit-tool scanner/src/analyzers/riskEngine.ts`): keep them in line.
- Charts are inline SVG and CSS rendered in PHP; `assets/admin.js` only reloads the page while a
  scan runs and marks the button busy.

## Files

| File | What it holds |
|---|---|
| `pirkoo.php` | Plugin header, constants, includes and every hook |
| `includes/settings.php` | The option `tvarkom_obd`: key, `connected_at`, last result |
| `includes/api.php` | Gateway requests, registration, `GET /v1/results` and its checks |
| `includes/rest.php` | `GET /wp-json/tvarkom-obd/v1/verify`, the registration's domain check |
| `includes/metrics.php` | The store metrics sent with each scan (schema 3, as the Checkout Speed Audit) |
| `includes/scan.php` | One scan request and the lock |
| `includes/dashboard.php` | The dashboard's charts and texts |
| `includes/analysis.php` | The analysis (0.2.0): the free sections from `latest.analysis`, checked and escaped; paid ones skipped |
| `includes/charts.php` | The store profile (sales and checkout as counted, catalog, operations) as charts, from the analysis's figures; payment and shipping names, with the store's own titles for identifiers the scanner can't name |
| `includes/overview.php` | The overview above the pipeline: store summary (with what its terms mean), the store health score card (risk bands, checks it's based on), needs attention (every problem by points lost, linked to its panel) and store health trends (only within one scoring model); reads the scan's data, never scores or estimates |
| `includes/pipeline.php` | The store pipeline: every check as a coloured job in a stage, a log-style panel per job (no cause, no fix) |
| `includes/site-health.php` | The site checks sent as `detailed.site_health` for the pipeline (updates, debug output, search visibility, table engines, transients, administrators, file editor), merged with the two files below |
| `includes/server-checks.php` | Upload limit, PHP extensions, disk space, load and memory (null when the host doesn't let PHP read them) |
| `includes/wordpress-checks.php` | Paused plugins and themes, fatal errors (7 days, a count from the log), automatic updates, permalinks, database latency and clutter, core checksums (api.wordpress.org), wp-config.php permissions, an "admin" username, open registration |
| `includes/business.php`, `funnel.php`, `detailed.php`, `profile*.php`, `class-tvarkom-obd-profiler.php` | The Checkout Speed Audit's collectors, ported with the `tvarkom_obd` prefix (0.2.0): business context, daily shop totals (table `tvarkom_obd_funnel`), detailed diagnostics, the checkout profiler (header `X-Tvarkom-Obd-Profile`) |
| `includes/admin.php` | The page, the scan button and its handler, assets, privacy policy text |
| `uninstall.php` | Removes the option, lock and token |

## Checks

The CI (`.github/workflows/ci.yml`) runs phpcs (WordPress Coding Standards + PHP 7.4
compatibility), PHPStan level 6, `php -l` and PHPUnit on PHP 7.4 and 8.4 with MySQL, as in the
Checkout Speed Audit plugin. Locally, without PHP installed: `PHP=7.4 npx @php-wasm/cli` runs
`vendor/bin/phpcs` and `vendor/bin/phpstan` (phpcs needs `--runtime-set installed_paths` and
`-d sys_temp_dir`; its exit code is always 0, read the output).

`tests/` uses the WordPress test suite with WooCommerce loaded; every request to
`TVARKOM_OBD_API_URL` (`https://gateway.test` in the tests) is answered by the fake gateway in
`Tvarkom_OBD_Test_Case`, and any other request fails as if offline.

## Release

Build the zip from a tagged commit, its folder named after the slug
(`pirkoo/`), with only the shipped files: `pirkoo.php`, `uninstall.php`,
`readme.txt`, `includes/`, `assets/`, `legal/`.

`legal/privacy-policy.txt` is Pirkoo's privacy policy (the plugin, the service at
`api.pirkoo.com` and pirkoo.com; since 10 Nov 2026), published at https://pirkoo.com/privacy/: the
pirkoo-website repo (`../../website`) generates that page from a copy of this file (its
`content/privacy-policy.txt`, `go run ./cmd/legalpage`), and its test fails when they differ. tvarkom.com
keeps only a short notice for its own site. `legal/terms-of-use.txt` is this plugin's own. Pirkoo's
addresses: support@pirkoo.com (help), privacy@pirkoo.com (privacy requests), contact@pirkoo.com.

## Paid version (not in the free plugin)

Founder decisions of 9 Nov 2026. Kept out of the free plugin, for a future paid version (tracked in
tvarkom's `BACKLOG.md`, item 11):

- The analysis's **Technical findings**, **Diagnostic details**, **How this scan works** and
  **Developer reference** sections. The scanner marks them `"tier": "paid"`, the gateway keeps them
  and never sends them (`audit.FreeAnalysis`), and `includes/analysis.php` skips them by tier and by
  id (`TVARKOM_OBD_PAID_SECTIONS`) anyway.
- The fix plan, next steps and priorities (never in the dashboard file).
- Automatic weekly scans: the free plugin scans only on a click.

The page, top to bottom (founder decisions of 10 Nov 2026): the store summary; the store health
score, needs attention and store health trends; the store profile in detail; traffic and
marketing, full width; then the pipeline. The free sections are drawn as charts from the
analysis's `figures` (`includes/charts.php`, `includes/overview.php`); traffic and marketing stay
as text. There is no money estimate: the modelled revenue at risk was removed on 10 Nov 2026, and
a stored scan that still carries one doesn't show it.

Scores come from the scanner's score model 2 (audit-tool `scanner/src/analyzers/score.ts`): every
problem lowers the score by a share of what's left, by its check's tier and severity. The analysis's
`scoring` says how many of the 44 checks ran and have a result; the score card says so. Every
history point carries its `score_model` (gateway migration 0021; 1 when the gateway doesn't say):
the trends compare and chart only scores of the latest score's model, and count the others.
Last, the store pipeline (left as designed; the overview only links to its panels) (`includes/pipeline.php`, built by the scanner's
`reporters/pipeline.ts`): red is a critical problem, orange a warning, yellow a minor (upkeep)
issue, green passes, grey not measured or not applicable (a staging site's blocked search engines). A job's panel shows
what was measured against which reference and the points lost, never the cause (plugin, hook,
query, option, task) or the fix: those are the paid version's.

