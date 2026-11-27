=== HDWebmobile Order Status Manager ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, order status, custom order status, order management, bulk actions
Requires at least: 6.9
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create your own WooCommerce order statuses with colours, bulk actions and optional customer emails.

== Description ==

WooCommerce ships with a fixed set of order statuses. Real stores often need more: "Awaiting pickup", "In production", "Ready to ship", "Waiting for stock". HDWebmobile Order Status Manager lets you add those statuses yourself, from one simple screen, without code.

Each status you create:

* appears everywhere WooCommerce lists order statuses -- the order list filters, the order edit screen and the order preview
* gets its own badge colour on the order list, with readable text picked automatically
* can be applied to many orders at once from the order list's **Bulk actions** menu
* can count as "paid", so download access and paid-order logic work for orders in it
* can email the customer when an order enters it, with your own subject and message

Works with both WooCommerce order storage modes (High-Performance Order Storage and the classic posts storage).

= Security by design =
* Only users who can manage WooCommerce can create, edit or delete statuses, and every change is protected by a nonce.
* Status names, slugs and colours are validated on save; a slug can never take over a built-in WooCommerce status.
* Customer emails are plain text: your message and the customer's details are escaped before being placed in WooCommerce's own email template.
* Deleting a status never strands orders: you choose another status first, every order is moved to it (with an order note), and only then is the status removed.

= Key Features =
* Unlimited custom order statuses
* Badge colour per status
* Bulk actions on the order list (HPOS and classic screens)
* "Counts as paid" option
* Optional customer email per status, with placeholders: {order_number}, {order_date}, {customer_name}, {status}, {site_title}
* Order counts per status, linking straight to the filtered order list
* Safe deletion that moves orders to the status you choose

= Limitations (please read before installing) =
* Built-in WooCommerce statuses (Processing, Completed, ...) cannot be renamed or removed.
* A status slug cannot be changed after it is created, because orders store it.
* Before deactivating or deleting the plugin, delete your custom statuses first so their orders are moved to a standard status. Otherwise those orders keep a status WooCommerce no longer recognises and do not show in the "All" order list until the plugin is active again.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-order-status-manager` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Order Status Manager** to add your first status.

== How to Use ==

= 1. Add a status =
Click **Add order status**, enter a name (for example "Awaiting pickup"), pick a colour and choose the options you want. The slug is built from the name if you leave it empty.

= 2. Use it on orders =
Change a single order's status on its edit screen, or select several orders on the order list and pick **Change status to Awaiting pickup** from the Bulk actions menu.

= 3. Email the customer (optional) =
Tick **Email the customer when an order enters this status** and adjust the subject and message. The email uses your WooCommerce email styling.

= 4. Delete a status =
Click **Delete**, choose which status its orders should move to, and confirm.

== Frequently Asked Questions ==

= Will my custom statuses survive a WooCommerce update? =
Yes. They are stored by this plugin and registered with WooCommerce on every page load.

= Does it work with High-Performance Order Storage (HPOS)? =
Yes. It declares HPOS compatibility and works on both order list screens.

= What happens to orders if I deactivate the plugin? =
Orders keep their custom status in the database, but WooCommerce no longer recognises it, so they are hidden from the "All" order list until you reactivate the plugin. Delete the statuses first (which moves their orders) if you plan to remove the plugin.

== Screenshots ==

1. The Order Status Manager screen listing custom statuses with their colours and order counts.
2. Adding a status with colour, options and the customer email.
3. Custom status badges and the bulk action on the WooCommerce order list.

== Changelog ==

= 1.0.0 =
* Initial release: custom order statuses with colours, bulk actions, "counts as paid", optional customer emails and safe deletion.
