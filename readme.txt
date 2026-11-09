=== On-board Diagnostics Scanner for WooCommerce ===
Contributors: tvarkom
Tags: woocommerce, performance, speed, health, dashboard
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A free weekly health dashboard for your WooCommerce store: server, cart and checkout speed, page weight, database and email records, scored 0–100.

== Description ==

On-board Diagnostics adds a **WooCommerce → OBD Scanner** page that shows how healthy your store is, right in wp-admin. Connect once, and tvarkom's free scanner checks your store every week.

The dashboard shows:

* **A store performance score** from 0 to 100, with its risk level (80–100 low, 50–79 moderate, 0–49 high)
* **Where your store lost points**, and what was measured for each
* **Server response** of your homepage, cart and checkout, against the 0.3-second reference
* **Homepage weight** and number of files
* **Autoloaded options**, PHP version and persistent object cache
* **HTTPS certificate** days left, and your domain's **SPF** and **DMARC** email records
* **Your score over time**, week by week

tvarkom's scanner measures your store from outside, the way a shopper's browser loads it, and adds a few figures the plugin reads inside WordPress. The plugin changes nothing on your store.

= How it works =

1. Install and activate the plugin. Nothing is sent yet.
2. Open **WooCommerce → OBD Scanner** and click **Connect and scan**, right under the list of what is sent.
3. The first scan takes a few minutes; the page updates itself when the results arrive.
4. After that, your store is scanned once a week on its own (with WP-Cron), and the dashboard shows the latest results and the score history.

== External services ==

This plugin uses the tvarkom audit service (https://tvarkom.com), run by MB TVARKOM SOFTWARE (Lithuania), to scan your store and show the results. It contacts tvarkom only after an administrator clicks **Connect and scan** on the plugin's page, right under a list of what it sends and the line "By clicking “Connect and scan”, you agree to send this data to tvarkom now and once a week, and accept tvarkom's Terms of Use and Privacy Policy". Before that click, nothing is sent.

**Connecting** (once, and again if the store's key stops working): your store's home URL, the site's admin email (the account's contact address; no reports or marketing are sent to it) and the URL of the plugin's verification endpoint are sent to `https://api.tvarkom.com/v1/register`. tvarkom then calls `https://<your store>/wp-json/tvarkom-obd/v1/verify` to confirm the request came from your store, and issues a key for your domain (`https://api.tvarkom.com/v1/register/verify`).

**Each scan** (the first right after connecting, then once a week): your store's home URL, cart and checkout URLs, the admin email and these figures are sent to `https://api.tvarkom.com/v1/audits`:

* PHP version
* Size of the autoloaded options
* Number of active plugins
* Whether a persistent object cache is on, and its type (Redis, Memcached)
* Counts of pending, overdue and failed WooCommerce background tasks (Action Scheduler), whether WP-Cron is disabled and how late it is

tvarkom's scanner then loads your homepage, cart and checkout pages like a visitor, and checks your domain's HTTPS certificate and its SPF and DMARC records in public DNS.

**The dashboard**: each time an administrator opens the plugin's page, it asks `https://api.tvarkom.com/v1/results` for your store's results, sending only the store's key.

The plugin never sends customer data, orders, sales, products, option contents or task names.

**What tvarkom keeps**: each scan's results linked to your store, for 12 months after the scan: the score and risk level, which checks cost points and how many, the response times, page weight and number of files, the autoloaded options size, the PHP version and object cache type, the days left on the HTTPS certificate, and whether SPF and DMARC records were found. They're used to show you this dashboard and your score history, to answer you when you contact tvarkom, and, across stores, to improve the scanner. Each scan request (store, page addresses, time) is kept for 24 months, with the admin email erased from it after 90 days; the store's contact email is deleted after 12 months without a scan.

Uninstalling the plugin deletes its settings and key from your store; records on tvarkom's side follow the periods above. To have them deleted sooner, email privacy@tvarkom.com.

* Terms of Use: https://tvarkom.com/terms/
* Privacy Policy: https://tvarkom.com/privacy/

== Frequently Asked Questions ==

= Is it free? =

Yes. The scan and the dashboard are free, with no account to create.

= Does it slow down my store? =

No. The plugin runs only on its own admin page and once a week in the background, when it collects a few counts and sends one request. The scanner runs on tvarkom's servers and loads your public pages like one visitor would.

= Why does my store need to be public? =

tvarkom's scanner measures your store from the internet, like a shopper. Stores on localhost, behind a password or in maintenance mode can't be scanned, and the domain check needs the WordPress REST API to be reachable.

= How often is my store scanned? =

Once a week. If a scan fails, the plugin tries again within the hour (at most 3 times a day).

= Can I stop the scans? =

Yes: deactivate the plugin. To also remove its settings, delete it.

= Who is tvarkom? =

tvarkom (MB TVARKOM SOFTWARE, Lithuania) builds diagnostic tools for WooCommerce stores. Contact: support@tvarkom.com.

== Changelog ==

= 0.1.0 =
* First release: connect once, a weekly scan, and a dashboard with the score, the points lost, server response, homepage weight, database and server figures, HTTPS and email records, and the score over time.
