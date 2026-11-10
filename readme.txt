=== Pirkoo ===
Contributors: tvarkom
Tags: woocommerce, performance, speed, health, dashboard
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

On-board Diagnostics Scanner for WooCommerce.

== Description ==

Pirkoo, tvarkom's On-board Diagnostics Scanner for WooCommerce, adds a **WooCommerce → Pirkoo** page that shows how healthy your store is, right in wp-admin. Click once, and tvarkom's free scanner checks your store; scan it again once a week to follow your score.

The dashboard shows:

* **A store health score** out of 100, with its risk level (80–100 low, 50–79 moderate, 0–49 high) and how many of the scanner's 44 checks it is based on
* **Needs attention**: the problems that cost the most points, each linked to its check
* **Store health trends**: how your score changed since your previous and first scans, scan by scan
* **Your store's profile**: sales and checkout over the last 30 days as counted on your store, products and inventory, payment and shipping methods, and traffic and marketing
* **The scanner pipeline**: all 44 checks, stage by stage (server, store pages, front end, database, background tasks, platform, security and email), coloured red, orange, yellow or green like a CI/CD pipeline. Click a check to see what was measured and against which reference

Every problem a check finds lowers the score by a share of what's left: more for the cart, checkout, HTTPS, search visibility and registration, less for upkeep issues. Checks that couldn't be measured, or don't apply to your site (for example, search engines blocked on a staging site), are left out and counted.

tvarkom's scanner measures your store from outside, the way a shopper's browser loads it, and adds a few figures the plugin reads inside WordPress. The plugin changes nothing on your store.

= How it works =

1. Install and activate the plugin. Nothing is sent yet.
2. Open **WooCommerce → Pirkoo** and click **Connect and scan**, right under the list of what is sent.
3. The first scan takes a few minutes; the page updates itself when the results arrive.
4. After that, click **Scan now** whenever you want fresh results, once a week at most. Until then the button shows the date of your next scan, and the dashboard shows the latest results and the score history.

== External services ==

This plugin uses Pirkoo's audit service (https://pirkoo.com), run by MB TVARKOM SOFTWARE (Lithuania), to scan your store and show the results. It contacts the service only after an administrator clicks **Connect and scan** on the plugin's page, right under a list of what it sends and the line "By clicking “Connect and scan”, you agree to send this data to Pirkoo's service, run by tvarkom, now and each time you click Scan now, and accept Pirkoo's Terms of Use and Privacy Policy". Before that click, nothing is sent. The plugin never scans on its own: each scan is an administrator's click.

**Connecting** (once, and again if the store's key stops working): your store's home URL, the site's admin email (the account's contact address; no reports or marketing are sent to it) and the URL of the plugin's verification endpoint are sent to `https://api.pirkoo.com/v1/register`. The service then calls `https://<your store>/wp-json/tvarkom-obd/v1/verify` to confirm the request came from your store, and issues a key for your domain (`https://api.tvarkom.com/v1/register/verify`).

**Each scan** (the first with **Connect and scan**, then each **Scan now** click, at most once a week): your store's home URL, cart and checkout URLs and the admin email are sent to `https://api.pirkoo.com/v1/audits`, with:

* PHP, WordPress and WooCommerce versions
* Database and options table sizes, autoloaded options size, and the names and sizes of the 20 largest autoloaded options (never their contents)
* Names, sizes and estimated row counts of database tables no active plugin or theme matches, each with the name of the switched-off or not-installed plugin or theme it likely belongs to
* Number of active plugins, object cache type, `memory_limit`, `WP_MEMORY_LIMIT`, `max_execution_time` and whether OPcache is on
* Action Scheduler task counts (pending, overdue, failed), how late WP-Cron is, failed tasks grouped by hook with their count and last error message (with emails, links, IP addresses and long numbers removed), and the hook names and times of tasks more than 15 minutes late (never their arguments)
* Orders in the last 30 days as a range (for example 50–200)
* Your store's business context: country and currency; the number of paid and on-hold orders in the last 30 days, and of failed, pending and cancelled orders; gross sales in the last 30 days and the average order value, in your store's currency; how many customers in the last 30 days were new and how many returning (two counts, no customer is identified); the number of published products and variations and whether stock management is on; the IDs of the enabled payment gateways and shipping methods (for example `stripe`, `flat_rate`), never their settings or keys; whether checkout uses the Checkout block or the classic shortcode, and whether High-Performance Order Storage is on; the database and web server product; how many of your theme's WooCommerce template overrides are outdated; how many plugins have an update waiting; the name of an active backup plugin, if any; the domain your store's emails come from (never the address), for its SPF and DMARC records; and how many orders in the last 30 days came from each source type and from the 10 most common campaign sources, from WooCommerce Order Attribution
* Site checks: whether a WordPress or theme update is waiting (from WordPress's own update check), whether `WP_DEBUG` and `WP_DEBUG_DISPLAY` show errors on your pages, the site's WordPress environment type (production, staging, development or local, so a staging copy isn't marked down for blocking search engines or showing debug output), whether search engines are asked not to index the store, how many of your database tables use the MyISAM engine or a character set other than utf8mb4, how many expired transients are stored, how many administrator accounts exist (a count; no user is read), and whether the dashboard file editor is on; the upload limit, missing PHP extensions, free and total disk space, the server's 1-minute load and CPU core count, and its total and available memory; how many plugins and themes WordPress paused after a fatal error, how many fatal errors your PHP error log shows for the last 7 days (the plugin reads the end of the log on your server and sends only the count), the automatic update settings for core and how many plugins update themselves, and whether permalinks are plain; the database's response time to a trivial query and how many spam comments, auto-drafts and revisions older than 30 days it holds; how many WordPress core files differ from, or are missing against, WordPress.org's official checksums; wp-config.php's file permissions; whether a user named "admin" exists; and whether registration is open and the role new accounts get
* Shop activity: how many products were added to the cart, checkout pages opened and orders placed over the last 30 days. The plugin counts these on your server in a small table of daily totals (its name ends in `tvarkom_obd_funnel`): no cookies, no browser script and nothing about the shopper. Logged-in administrators and shop managers aren't counted. Days older than 35 are deleted, and uninstalling the plugin removes the table
* A checkout profile: the plugin loads your checkout page once from your own server, with one product in a temporary cart that is never saved (nothing is ordered), and sends plugin, theme and hook names with their timings and call counts, database query shapes with every value replaced by `?`, the host names (not the full addresses) of outside services called, object cache hit and miss counts, peak memory and the page's server time. Analytics or marketing plugins that record checkout visits on the server may count that page load as one visit

Option, table, task and host names have email addresses, long numbers and identifiers replaced before they're sent, and campaign names that look like one count as "other". tvarkom's scanner then loads your homepage, cart and checkout pages like a visitor, checks your domain's HTTPS certificate and its SPF and DMARC records in public DNS, and requests 18 well-known addresses on your store (backups of wp-config.php, .env, wp-content/debug.log, .git/config, database dumps, site archives and phpinfo pages) to see whether any can be downloaded. It reads at most 4 KB of each answer and keeps only how many were found.

**WordPress.org**: for the core files check, the plugin asks `https://api.wordpress.org/core/checksums/1.0/` for the official checksums of your WordPress version and language, the same request WordPress makes for its own updates. It sends the version and language only. WordPress.org's privacy policy: https://wordpress.org/about/privacy/

**The dashboard**: each time an administrator opens the plugin's page, it asks `https://api.pirkoo.com/v1/results` for your store's results, sending only the store's key.

The plugin never reads customers' names, emails or addresses or order contents for this, and never sends option contents or task arguments.

**What tvarkom keeps**: each scan's results and analysis linked to your store, for 12 months after the scan: the score and risk level, which checks cost points and how many, the measurements, and the full analysis this plugin shows you (the store data, business context and sales figures above, and what tvarkom found in them), never the fix plan. They're used to show you this dashboard and your score history, to answer you when you contact tvarkom, and, across stores, to improve the scanner. tvarkom also keeps the latest business context of your store (without sales amounts), replaced by each scan, for totals across stores and support. Each scan request (store, page addresses, time) is kept for 24 months, with the admin email erased from it after 90 days; the store's contact email is deleted after 12 months without a scan.

Uninstalling the plugin deletes its settings, key and table of daily totals from your store; records on our side follow the periods above. To have them deleted sooner, email privacy@pirkoo.com.

* Privacy Policy: https://pirkoo.com/privacy/ (also shipped with the plugin, `legal/privacy-policy.txt`)
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

tvarkom (MB TVARKOM SOFTWARE, Lithuania) builds diagnostic tools for WooCommerce stores. Contact: support@pirkoo.com.

== Changelog ==

= 0.2.2 =
* Pirkoo's own address: the plugin now talks to https://api.pirkoo.com; the Privacy Policy is at https://pirkoo.com/privacy/; help at support@pirkoo.com and privacy requests at privacy@pirkoo.com.

= 0.2.1 =
* The privacy policy shipped with the plugin is the current text, the same as tvarkom.com/privacy/.

= 0.2.0 =
* The plugin is now called Pirkoo (On-board Diagnostics Scanner for WooCommerce); its page is WooCommerce → Pirkoo.
* A new dashboard: the store summary, the store health score with how many checks it's based on, what needs attention first, your store's health trends, and your store's profile (sales and checkout as counted, catalog, operations, traffic and marketing).
* Every check now counts towards the score, by how much it matters; earlier scores keep the scoring model they were made with and aren't compared with new ones.
* The scan also sends the site's WordPress environment type, so a staging copy isn't marked down for blocking search engines or showing debug output.
* The store pipeline: every check as a coloured job, with what was measured and its reference.
* New checks: compression, WordPress and theme updates, debug output, search engine visibility, MyISAM tables and character set, expired transients, administrators and the file editor; upload limit, PHP extensions, disk space, server load and memory; paused plugins, fatal errors, automatic updates and permalinks; database latency and clutter; core files against WordPress.org's checksums, readable files, wp-config.php permissions, an "admin" username and open registration.
* Each scan now sends more store data (business context and a profile of one checkout page load), plus the site checks above, listed under External services; the plugin keeps a small table of daily shop totals.

= 0.1.0 =
* First release: scan on demand (once a week), and a dashboard with the score, the points lost, server response, homepage weight, database and server figures, HTTPS and email records, and the score over time.
