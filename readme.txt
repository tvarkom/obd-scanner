=== Pirkoo ===
Contributors: tvarkom
Tags: woocommerce, performance, speed, health, dashboard
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

On-board Diagnostics Scanner for WooCommerce.

== Description ==

Pirkoo, the On-board Diagnostics Scanner for WooCommerce, connects your store to **app.pirkoo.com**, where you and your team see how healthy your store is. The plugin has no screen in wp-admin: it adds **Connect to Pirkoo** under its name on the Plugins screen, and answers Pirkoo's scans once the store is connected. Agencies follow all their clients' stores in one place.

The dashboard shows:

* **A store health score** out of 100, with its risk level (80–100 low, 50–79 moderate, 0–49 high) and how many of the scanner's 44 checks it is based on
* **Needs attention**: the problems that cost the most points, each linked to its check
* **Store health trends**: how your score changed since your previous and first scans, scan by scan
* **Your store's profile**: sales and checkout over the last 30 days as counted on your store, products and inventory, payment and shipping methods, and traffic and marketing
* **The scanner pipeline**: all 44 checks, stage by stage (server, store pages, front end, database, background tasks, platform, security and email), coloured red, orange, yellow or green like a CI/CD pipeline. Click a check to see what was measured and against which reference

Every problem a check finds lowers the score by a share of what's left: more for the cart, checkout, HTTPS, search visibility and registration, less for upkeep issues. Checks that couldn't be measured, or don't apply to your site (for example, search engines blocked on a staging site), are left out and counted.

Pirkoo's scanner measures your store from outside, the way a shopper's browser loads it, and adds the figures the plugin reads inside WordPress. The plugin changes nothing on your store.

= How it works =

1. Install and activate the plugin. Nothing is sent yet.
2. On the Plugins screen, click **Connect to Pirkoo** under Pirkoo's name (administrators and shop managers).
3. On app.pirkoo.com, log in with your email (a link is emailed to you, no password) and confirm the connection, right under the list of what is collected.
4. Scan the store from app.pirkoo.com whenever you want fresh results; the dashboard shows the latest results and the score history.
5. To stop, click **Disconnect from Pirkoo** on the Plugins screen, or disconnect the store in the app.

== External services ==

This plugin uses Pirkoo (https://app.pirkoo.com), run by MB TVARKOM SOFTWARE (Lithuania), to scan your store and show the results. It contacts nobody until an administrator clicks **Connect to Pirkoo** on the Plugins screen and confirms on app.pirkoo.com, right under a list of what is collected, which accepts Pirkoo's Privacy Policy. Before that, nothing is sent.

**Connecting**: the click sends your browser to `https://app.pirkoo.com/connect` with your store's address and a one-time code (valid 10 minutes). After you confirm there, app.pirkoo.com calls `https://<your store>/wp-json/pirkoo/v1/connect` with that code, and the plugin answers with a new connection secret. From then on the plugin answers only requests signed with that secret.

**Each scan**, when someone in your Pirkoo account starts one: app.pirkoo.com calls `https://<your store>/wp-json/pirkoo/v1/collect` (signed), and the plugin answers with your store's home URL, cart and checkout URLs, the site's admin email, the store's name, timezone, date and time formats and language, and:

* PHP, WordPress and WooCommerce versions
* Database and options table sizes, autoloaded options size, and the names and sizes of the 20 largest autoloaded options (never their contents)
* Names, sizes and estimated row counts of database tables no active plugin or theme matches, each with the name of the switched-off or not-installed plugin or theme it likely belongs to
* Number of active plugins, object cache type, `memory_limit`, `WP_MEMORY_LIMIT`, `max_execution_time` and whether OPcache is on
* Action Scheduler task counts (pending, overdue, failed), how late WP-Cron is, failed tasks grouped by hook with their count and last error message (with emails, links, IP addresses and long numbers removed), and the hook names and times of tasks more than 15 minutes late (never their arguments)
* Orders in the last 30 days as a range (for example 50–200)
* Your store's business context: country and currency; the number of paid and on-hold orders in the last 30 days, and of failed, pending and cancelled orders; gross sales in the last 30 days and the average order value, in your store's currency; how many customers in the last 30 days were new and how many returning (two counts, no customer is identified); the number of published products and variations and whether stock management is on; the IDs of the enabled payment gateways and shipping methods (for example `stripe`, `flat_rate`), never their settings or keys; whether checkout uses the Checkout block or the classic shortcode, and whether High-Performance Order Storage is on; the database and web server product; how many of your theme's WooCommerce template overrides are outdated; how many plugins have an update waiting; the name of an active backup plugin, if any; the domain your store's emails come from (never the address), for its SPF and DMARC records; and how many orders in the last 30 days came from each source type and from the 10 most common campaign sources, from WooCommerce Order Attribution
* Site checks: whether a WordPress or theme update is waiting (from WordPress's own update check), whether `WP_DEBUG` and `WP_DEBUG_DISPLAY` show errors on your pages, the site's WordPress environment type (production, staging, development or local, so a staging copy isn't marked down for blocking search engines or showing debug output), whether search engines are asked not to index the store, how many of your database tables use the MyISAM engine or a character set other than utf8mb4, how many expired transients are stored, how many administrator accounts exist (a count; no user is read), and whether the dashboard file editor is on; the upload limit, missing PHP extensions, free and total disk space, the server's 1-minute load and CPU core count, and its total and available memory; how many plugins and themes WordPress paused after a fatal error, how many fatal errors your PHP error log shows for the last 7 days (the plugin reads the end of the log on your server and sends only the count), the automatic update settings for core and how many plugins update themselves, and whether permalinks are plain; the database's response time to a trivial query and how many spam comments, auto-drafts and revisions older than 30 days it holds; how many WordPress core files differ from, or are missing against, WordPress.org's official checksums; wp-config.php's file permissions; whether a user named "admin" exists; and whether registration is open and the role new accounts get
* Shop activity: how many products were added to the cart, checkout pages opened and orders placed over the last 30 days. The plugin counts these on your server in a small table of daily totals (its name ends in `pirkoo_funnel`): no cookies, no browser script and nothing about the shopper. Logged-in administrators and shop managers aren't counted. Days older than 35 are deleted, and uninstalling the plugin removes the table
* A checkout profile: the plugin loads your checkout page once from your own server, with one product in a temporary cart that is never saved (nothing is ordered), and sends plugin, theme and hook names with their timings and call counts, database query shapes with every value replaced by `?`, the host names (not the full addresses) of outside services called, object cache hit and miss counts, peak memory and the page's server time. Analytics or marketing plugins that record checkout visits on the server may count that page load as one visit

Option, table, task and host names have email addresses, long numbers and identifiers replaced before they're sent, and campaign names that look like one count as "other". Pirkoo's scanner then loads your homepage, cart and checkout pages like a visitor, checks your domain's HTTPS certificate and its SPF and DMARC records in public DNS, and requests 18 well-known addresses on your store (backups of wp-config.php, .env, wp-content/debug.log, .git/config, database dumps, site archives and phpinfo pages) to see whether any can be downloaded. It reads at most 4 KB of each answer and keeps only how many were found.

**WordPress.org**: for the core files check, the plugin asks `https://api.wordpress.org/core/checksums/1.0/` for the official checksums of your WordPress version and language, the same request WordPress makes for its own updates. It sends the version and language only. WordPress.org's privacy policy: https://wordpress.org/about/privacy/

**Disconnecting**: **Disconnect from Pirkoo** tells `https://app.pirkoo.com/api/stores/disconnect` (signed with the secret) and deletes the secret; app.pirkoo.com can also ask `https://<your store>/wp-json/pirkoo/v1/disconnect` to delete it. After that the plugin answers nothing.

The plugin never reads customers' names, emails or addresses or order contents for this, and never sends option contents or task arguments.

**What Pirkoo keeps**: each scan's results and analysis linked to your store, for 12 months after the scan: the score and risk level, which checks cost points and how many, the measurements, and the full analysis this plugin shows you (the store data, business context and sales figures above, and what Pirkoo found in them), never the fix plan. They're used to show you this dashboard and your score history, to answer you when you contact Pirkoo, and, across stores, to improve the scanner. Pirkoo also keeps the latest business context of your store (without sales amounts), replaced by each scan, for totals across stores and support. Each scan request (store, page addresses, time) is kept for 24 months, with the admin email erased from it after 90 days; the store's contact email is deleted after 12 months without a scan.

Uninstalling the plugin deletes its settings, connection secret and table of daily totals from your store; records on our side follow the periods above. To have them deleted sooner, email privacy@pirkoo.com.

* Privacy Policy: https://pirkoo.com/privacy/ (also shipped with the plugin, `legal/privacy-policy.txt`)
* Terms of Use: shipped with the plugin, `legal/terms-of-use.txt`

== Frequently Asked Questions ==

= Does it slow down my store? =

No. Apart from adding one to a daily total when a shopper adds to cart, opens the checkout or orders, the plugin runs only when Pirkoo scans the store: it collects its figures and loads your checkout once to profile it. Nothing runs in the background. The scanner runs on Pirkoo's servers and loads your public pages like one visitor would.

= Why does my store need to be public? =

Pirkoo's scanner measures your store from the internet, like a shopper. Stores on localhost, behind a password or in maintenance mode can't be scanned, and app.pirkoo.com needs to reach the WordPress REST API at `/wp-json/pirkoo/` (allow it in a security plugin or firewall that blocks it).

= How often can I scan my store? =

From app.pirkoo.com, when you click **Scan now**, within the limits shown there.

= Does it send anything on its own? =

No. Nothing is sent before the store is connected, and after that only when app.pirkoo.com asks the plugin for a scan's data. To stop, disconnect the store; to remove its settings, delete the plugin.

= What if my wp-admin has another address? =

The Connect and Disconnect links are built with WordPress's own admin address (`admin_url()`), which plugins that rename wp-admin (for example to /meu-painel) change too, and app.pirkoo.com only calls `/wp-json/pirkoo/`, never wp-admin.

= Who makes Pirkoo? =

Pirkoo is made by tvarkom (MB TVARKOM SOFTWARE, Lithuania), which builds diagnostic tools for WooCommerce stores. Contact: support@pirkoo.com.

== Changelog ==

= 0.3.0 =
* Pirkoo becomes a connector to app.pirkoo.com: Connect to Pirkoo and Disconnect from Pirkoo on the Plugins screen; scans start in the app, which asks the plugin for the store's data with signed requests.
* The WooCommerce → Pirkoo page, its dashboard and the store's key are removed (an update from 0.2.x deletes the key: connect the store again).
* The store's name, timezone, date and time formats and language are sent with each scan, for the app's pages.

= 0.2.3 =
* Everything the plugin creates on your site is named after Pirkoo: its settings, its table of daily totals, its page's styles and the address the service checks when connecting (`/wp-json/pirkoo/v1/verify`).

= 0.2.2 =
* Pirkoo's own address: the plugin now talks to https://api.pirkoo.com; the Privacy Policy is at https://pirkoo.com/privacy/; help at support@pirkoo.com and privacy requests at privacy@pirkoo.com.

= 0.2.1 =
* The privacy policy shipped with the plugin is the current text, the same as tvarkom.com/privacy/.

= 0.2.0 =
* The plugin's page is WooCommerce → Pirkoo.
* A new dashboard: the store summary, the store health score with how many checks it's based on, what needs attention first, your store's health trends, and your store's profile (sales and checkout as counted, catalog, operations, traffic and marketing).
* Every check now counts towards the score, by how much it matters; earlier scores keep the scoring model they were made with and aren't compared with new ones.
* The scan also sends the site's WordPress environment type, so a staging copy isn't marked down for blocking search engines or showing debug output.
* The store pipeline: every check as a coloured job, with what was measured and its reference.
* New checks: compression, WordPress and theme updates, debug output, search engine visibility, MyISAM tables and character set, expired transients, administrators and the file editor; upload limit, PHP extensions, disk space, server load and memory; paused plugins, fatal errors, automatic updates and permalinks; database latency and clutter; core files against WordPress.org's checksums, readable files, wp-config.php permissions, an "admin" username and open registration.
* Each scan now sends more store data (business context and a profile of one checkout page load), plus the site checks above, listed under External services; the plugin keeps a small table of daily shop totals.

= 0.1.0 =
* First release: scan on demand (once a week), and a dashboard with the score, the points lost, server response, homepage weight, database and server figures, HTTPS and email records, and the score over time.
