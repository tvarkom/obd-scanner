=== On-board Diagnostics Scanner for WooCommerce ===
Contributors: tvarkom
Tags: woocommerce, performance, speed, health, dashboard
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A free health dashboard for your WooCommerce store: server, cart and checkout speed, page weight, database and email records, scored 0–100.

== Description ==

On-board Diagnostics adds a **WooCommerce → Store Scanner** page that shows how healthy your store is, right in wp-admin. Click once, and tvarkom's free scanner checks your store; scan it again once a week to follow your score.

The dashboard shows:

* **A store performance score** from 0 to 100, with its risk level (80–100 low, 50–79 moderate, 0–49 high)
* **Where your store lost points**, and what was measured for each
* **Server response** of your homepage, cart and checkout, against the 0.3-second reference
* **Homepage weight** and number of files
* **Autoloaded options**, PHP version and persistent object cache
* **HTTPS certificate** days left, and your domain's **SPF** and **DMARC** email records
* **Your score over time**, scan by scan
* **The full analysis**: what your score means, your sales and checkout in business terms with the modelled impact of slow pages, your catalog, operations and marketing, every finding with why it matters, the measurements against their references, a profile of your checkout (plugins, hooks, queries, outside services), your largest autoloaded options, leftover database tables, PHP settings, failed and late background tasks, and the developer reference

tvarkom's scanner measures your store from outside, the way a shopper's browser loads it, and adds a few figures the plugin reads inside WordPress. The plugin changes nothing on your store.

= How it works =

1. Install and activate the plugin. Nothing is sent yet.
2. Open **WooCommerce → Store Scanner** and click **Connect and scan**, right under the list of what is sent.
3. The first scan takes a few minutes; the page updates itself when the results arrive.
4. After that, click **Scan now** whenever you want fresh results, once a week at most. Until then the button shows the date of your next scan, and the dashboard shows the latest results and the score history.

== External services ==

This plugin uses the tvarkom audit service (https://tvarkom.com), run by MB TVARKOM SOFTWARE (Lithuania), to scan your store and show the results. It contacts tvarkom only after an administrator clicks **Connect and scan** on the plugin's page, right under a list of what it sends and the line "By clicking “Connect and scan”, you agree to send this data to tvarkom now and each time you click Scan now, and accept tvarkom's Terms of Use and Privacy Policy". Before that click, nothing is sent. The plugin never scans on its own: each scan is an administrator's click.

**Connecting** (once, and again if the store's key stops working): your store's home URL, the site's admin email (the account's contact address; no reports or marketing are sent to it) and the URL of the plugin's verification endpoint are sent to `https://api.tvarkom.com/v1/register`. tvarkom then calls `https://<your store>/wp-json/tvarkom-obd/v1/verify` to confirm the request came from your store, and issues a key for your domain (`https://api.tvarkom.com/v1/register/verify`).

**Each scan** (the first with **Connect and scan**, then each **Scan now** click, at most once a week): your store's home URL, cart and checkout URLs and the admin email are sent to `https://api.tvarkom.com/v1/audits`, with:

* PHP, WordPress and WooCommerce versions
* Database and options table sizes, autoloaded options size, and the names and sizes of the 20 largest autoloaded options (never their contents)
* Names, sizes and estimated row counts of database tables no active plugin or theme matches, each with the name of the switched-off or not-installed plugin or theme it likely belongs to
* Number of active plugins, object cache type, `memory_limit`, `WP_MEMORY_LIMIT`, `max_execution_time` and whether OPcache is on
* Action Scheduler task counts (pending, overdue, failed), how late WP-Cron is, failed tasks grouped by hook with their count and last error message (with emails, links, IP addresses and long numbers removed), and the hook names and times of tasks more than 15 minutes late (never their arguments)
* Orders in the last 30 days as a range (for example 50–200)
* Your store's business context: country and currency; the number of paid and on-hold orders in the last 30 days, and of failed, pending and cancelled orders; gross sales in the last 30 days and the average order value, in your store's currency; how many customers in the last 30 days were new and how many returning (two counts, no customer is identified); the number of published products and variations and whether stock management is on; the IDs of the enabled payment gateways and shipping methods (for example `stripe`, `flat_rate`), never their settings or keys; whether checkout uses the Checkout block or the classic shortcode, and whether High-Performance Order Storage is on; the database and web server product; how many of your theme's WooCommerce template overrides are outdated; how many plugins have an update waiting; the name of an active backup plugin, if any; the domain your store's emails come from (never the address), for its SPF and DMARC records; and how many orders in the last 30 days came from each source type and from the 10 most common campaign sources, from WooCommerce Order Attribution
* Shop activity: how many products were added to the cart, checkout pages opened and orders placed over the last 30 days. The plugin counts these on your server in a small table of daily totals (its name ends in `tvarkom_obd_funnel`): no cookies, no browser script and nothing about the shopper. Logged-in administrators and shop managers aren't counted. Days older than 35 are deleted, and uninstalling the plugin removes the table
* A checkout profile: the plugin loads your checkout page once from your own server, with one product in a temporary cart that is never saved (nothing is ordered), and sends plugin, theme and hook names with their timings and call counts, database query shapes with every value replaced by `?`, the host names (not the full addresses) of outside services called, object cache hit and miss counts, peak memory and the page's server time. Analytics or marketing plugins that record checkout visits on the server may count that page load as one visit

Option, table, task and host names have email addresses, long numbers and identifiers replaced before they're sent, and campaign names that look like one count as "other". tvarkom's scanner then loads your homepage, cart and checkout pages like a visitor, and checks your domain's HTTPS certificate and its SPF and DMARC records in public DNS.

**The dashboard**: each time an administrator opens the plugin's page, it asks `https://api.tvarkom.com/v1/results` for your store's results, sending only the store's key.

The plugin never reads customers' names, emails or addresses or order contents for this, and never sends option contents or task arguments.

**What tvarkom keeps**: each scan's results and analysis linked to your store, for 12 months after the scan: the score and risk level, which checks cost points and how many, the measurements, and the full analysis this plugin shows you (the store data, business context and sales figures above, and what tvarkom found in them), never the fix plan. They're used to show you this dashboard and your score history, to answer you when you contact tvarkom, and, across stores, to improve the scanner. tvarkom also keeps the latest business context of your store (without sales amounts), replaced by each scan, for totals across stores and support. Each scan request (store, page addresses, time) is kept for 24 months, with the admin email erased from it after 90 days; the store's contact email is deleted after 12 months without a scan.

Uninstalling the plugin deletes its settings, key and table of daily totals from your store; records on tvarkom's side follow the periods above. To have them deleted sooner, email privacy@tvarkom.com.

* Privacy Policy: https://tvarkom.com/privacy/ (also shipped with the plugin, `legal/privacy-policy.txt`)
* Terms of Use: shipped with the plugin, `legal/terms-of-use.txt`

== Frequently Asked Questions ==

= Is it free? =

Yes. The scan and the dashboard are free, with no account to create.

= Does it slow down my store? =

No. Apart from adding one to a daily total when a shopper adds to cart, opens the checkout or orders, the plugin runs only on its own admin page: when you click a scan button it collects its figures, loads your checkout once to profile it, and sends one request. Nothing runs in the background. The scanner runs on tvarkom's servers and loads your public pages like one visitor would.

= Why does my store need to be public? =

tvarkom's scanner measures your store from the internet, like a shopper. Stores on localhost, behind a password or in maintenance mode can't be scanned, and the domain check needs the WordPress REST API to be reachable.

= How often can I scan my store? =

Once a week, when you click **Scan now**. A scan that fails doesn't count, so you can try again right away (at most 3 times a day).

= Does it send anything on its own? =

No. Nothing is sent until you click **Connect and scan**, and after that only when you click **Scan now** or open the plugin's page (which asks tvarkom for your results). To remove its settings and key, delete the plugin.

= Who is tvarkom? =

tvarkom (MB TVARKOM SOFTWARE, Lithuania) builds diagnostic tools for WooCommerce stores. Contact: support@tvarkom.com.

== Changelog ==

= 0.2.0 =
* The full analysis on the dashboard: what your score means, sales and checkout in business terms with the modelled impact of slow pages, catalog, operations and marketing, every finding with why it matters, measurements, a checkout profile, autoloaded options, leftover tables, PHP settings, background tasks and the developer reference.
* Each scan now sends the same store data as tvarkom's Checkout Speed Audit, listed under External services; the plugin keeps a small table of daily shop totals.

= 0.1.0 =
* First release: scan on demand (once a week), and a dashboard with the score, the points lost, server response, homepage weight, database and server figures, HTTPS and email records, and the score over time.
