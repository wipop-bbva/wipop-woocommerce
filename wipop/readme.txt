=== Wipop ===
Contributors: wipopbbva
Tags: woocommerce, payment gateway, bbva, wipop, bizum, payments
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.10.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Plataforma de pagos de BBVA en España para pymes y autónomos. Acepta pagos con tarjeta, Bizum y Google Pay en tu tienda WooCommerce.

== Description ==

WooCommerce plugin that integrates the Wipöp payment gateway, allowing your WordPress e-commerce to accept payments easily.

**Features:**

* **Card payments** - Redirect checkout with tokenization, preauthorization support, and refunds
* **Bizum payments** - Dedicated button at checkout for Bizum charges
* **Google Pay** - Initial support (pending)
* **Merchant synchronization** - Central settings page with environment, keys, terminal, and capture mode
* **Order status updates** - Webhook endpoint that syncs orders with Wipöp transactions
* **Recurring payments** - Schedule monthly/yearly charges using stored tokens

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wipop/` or install via WordPress admin
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Wipop to configure your credentials
4. Enable payment methods in WooCommerce > Payments

== Frequently Asked Questions ==

= What are the requirements? =

* PHP 8.1 or higher
* WordPress with WooCommerce installed and active
* Wipöp merchant account with credentials (Merchant ID, Terminal ID, Public Key, Private Key)

= How do I configure webhooks? =

Configure `https://{your-domain}/?wc-api=wipop_bbva` as the webhook URL in the Wipöp/BBVA portal using the credentials shown in WooCommerce > Wipop.

= Does it support recurring payments? =

Yes. Enable the "Recurring payment" option in product settings to schedule automatic monthly or yearly charges.

= Can I use preauthorizations? =

Yes. Select "Reserve the amount to charge it later" in the Preauthorizations setting to authorize cards without immediate capture.

== Screenshots ==

1. Main settings page with credentials and environment configuration
2. Payment methods configuration in WooCommerce
3. Card payment checkout experience
4. Bizum payment button at checkout
5. Order actions for capture and void

== Changelog ==

= 0.10.0 =
* Initial release
* Card payment support with tokenization
* Bizum payment integration
* Google Pay initial support
* Preauthorization and manual capture
* Webhook synchronization
* Recurring payment scheduling
* Full and partial refunds

== Upgrade Notice ==

= 0.10.0 =
Initial release of Wipop payment gateway for WooCommerce.

== Additional Information ==

For more information, visit [Wipöp by BBVA](https://www.wipop.com/)

**Support:** For technical support, please contact Wipöp support team.

**Privacy:** This plugin connects to Wipöp payment gateway to process transactions. Please review Wipöp's privacy policy.
