# Development

`readme.txt` is the WordPress.org readme for store owners. This file is for working on the code.

The plugin is procedural WordPress code: a main file and `includes/`, every function, constant,
option, table, CSS class and REST route prefixed `pirkoo` / `PIRKOO` (since 0.2.3; nothing on a
store carries another name). It supports PHP 7.4+ and WordPress 6.5+, and
needs WooCommerce. Text domain: `pirkoo`, the WordPress.org slug
(it must always equal the slug). The plugin is called Pirkoo; "On-board Diagnostics Scanner for
WooCommerce" is its description, never its name. The repo and folder are `pirkoo`.

## How it works

Since 0.3.0 the plugin is a connector to app.pirkoo.com, with no screen in wp-admin (the app's
repo, `docs/PROPOSAL-0.3.0.md` 3.5, has the protocol).

```
Plugins screen: Connect to Pirkoo ──▶ admin-post pirkoo_connect: one-time code (10 min)
        ──▶ browser to app.pirkoo.com/connect?store=…&code=…  ──▶ an Owner or Admin confirms
        ◀── app: POST /wp-json/pirkoo/v1/connect {code} ──▶ {secret} (stored in the option pirkoo)
App "Scan now" ──▶ POST /wp-json/pirkoo/v1/collect (signed) ──▶ the store's data ──▶ gateway
Plugins screen: Disconnect from Pirkoo ──▶ POST app/api/stores/disconnect (signed), secret deleted
App "Disconnect" ──▶ POST /wp-json/pirkoo/v1/disconnect (signed) ──▶ secret deleted
```

- Nothing is collected or sent before the store is connected, and then only when the app asks.
- Signed requests: headers `X-Pirkoo-Timestamp` and `X-Pirkoo-Signature`, the hex HMAC-SHA256 with
  the secret of timestamp, method, URL path (`/wp-json/pirkoo/v1/collect`) and the body's sha256,
  one per line (`pirkoo_signature()`). Refused: more than 5 minutes of clock difference, a
  signature already used (a transient for 10 minutes), a wrong one. `tests/test-connect.php` and
  the app's `internal/storelink` tests share one signature vector: keep them in line.
- One collection at a time (`pirkoo_lock()`): a second gets 409.
- `PIRKOO_APP_URL` (default `https://app.pirkoo.com`) can be overridden in wp-config.php.
- Not supported yet: stores whose REST API isn't at `<origin>/wp-json/` (plain permalinks,
  WordPress in a subfolder).

## Files

| File | What it holds |
|---|---|
| `pirkoo.php` | Plugin header, constants, includes and every hook |
| `includes/settings.php` | The option `pirkoo`: secret, `connected_at`, the connection code in progress; the clean-up after an update from 0.2.x |
| `includes/connect.php` | The Plugins screen links and their handlers, the REST routes (connect, collect, disconnect), signatures, the collection lock, the suggested privacy policy text |
| `includes/metrics.php` | The store metrics sent with each scan (schema 3) |
| `includes/site-health.php` | The site checks sent as `detailed.site_health` (updates, debug output, search visibility, table engines, transients, administrators, file editor), merged with the two files below |
| `includes/server-checks.php` | Upload limit, PHP extensions, disk space, load and memory (null when the host doesn't let PHP read them) |
| `includes/wordpress-checks.php` | Paused plugins and themes, fatal errors (7 days, a count from the log), automatic updates, permalinks, database latency and clutter, core checksums (api.wordpress.org), wp-config.php permissions, an "admin" username, open registration |
| `includes/business.php`, `funnel.php`, `detailed.php`, `profile*.php`, `class-pirkoo-profiler.php` | The collectors: business context, daily shop totals (table `pirkoo_funnel`), detailed diagnostics, the checkout profiler (header `X-Pirkoo-Profile`) |
| `uninstall.php` | Removes the option, lock, tokens and the daily totals table |

## Checks

The CI (`.github/workflows/ci.yml`) runs phpcs (WordPress Coding Standards + PHP 7.4
compatibility), PHPStan level 6, `php -l` and PHPUnit on PHP 7.4 and 8.4 with MySQL. Locally, without PHP installed: `PHP=7.4 npx @php-wasm/cli` runs
`vendor/bin/phpcs` and `vendor/bin/phpstan` (phpcs needs `--runtime-set installed_paths` and
`-d sys_temp_dir`; its exit code is always 0, read the output).

`tests/` uses the WordPress test suite with WooCommerce loaded; every request to
`PIRKOO_APP_URL` (`https://app.test` in the tests) is answered by the fake app in
`Pirkoo_Test_Case`, and any other request fails as if offline. PHPStan through php-wasm needs one
worker (`parallel.maximumNumberOfProcesses: 1` in a local config).

## Release

Build the zip from a tagged commit, its folder named after the slug
(`pirkoo/`), with only the shipped files: `pirkoo.php`, `uninstall.php`,
`readme.txt`, `includes/`, `legal/`.

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
  id (`PIRKOO_PAID_SECTIONS`) anyway.
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

