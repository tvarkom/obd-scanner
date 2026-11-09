# Development

`readme.txt` is the WordPress.org readme for store owners. This file is for working on the code.

The plugin is procedural WordPress code: a main file and `includes/`, every function, constant
and option prefixed `tvarkom_obd` / `TVARKOM_OBD`. It supports PHP 7.4+ and WordPress 6.5+, and
needs WooCommerce. Text domain: `obd-scanner` (change it everywhere if WordPress.org assigns
another slug).

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
| `obd-scanner.php` | Plugin header, constants, includes and every hook |
| `includes/settings.php` | The option `tvarkom_obd`: key, `connected_at`, last result |
| `includes/api.php` | Gateway requests, registration, `GET /v1/results` and its checks |
| `includes/rest.php` | `GET /wp-json/tvarkom-obd/v1/verify`, the registration's domain check |
| `includes/metrics.php` | The store figures sent with each scan |
| `includes/scan.php` | One scan request and the lock |
| `includes/dashboard.php` | The dashboard's charts and texts |
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

Build the zip from a tagged commit with only the shipped files: `obd-scanner.php`, `uninstall.php`,
`readme.txt`, `includes/`, `assets/`, `legal/`.

`legal/privacy-policy.txt` is tvarkom's one privacy policy, shared with the Checkout Speed Audit
plugin and published at tvarkom.com/privacy/ (website repo `content/privacy-policy.txt`): keep the
copies identical. `legal/terms-of-use.txt` is this plugin's own.
