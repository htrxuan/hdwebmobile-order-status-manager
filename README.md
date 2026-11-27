# HDWebmobile Order Status Manager

Create your own WooCommerce order statuses with colours, bulk actions and optional customer emails.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-order-status-manager/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

WooCommerce ships with a fixed set of order statuses. Real stores often need more: "Awaiting pickup", "In production", "Ready to ship", "Waiting for stock". HDWebmobile Order Status Manager lets you add those statuses yourself, from one simple screen, without code.

Each status you create:

* appears everywhere WooCommerce lists order statuses -- the order list filters, the order edit screen and the order preview
* gets its own badge colour on the order list, with readable text picked automatically
* can be applied to many orders at once from the order list's **Bulk actions** menu
* can count as "paid", so download access and paid-order logic work for orders in it
* can email the customer when an order enters it, with your own subject and message

Works with both WooCommerce order storage modes (High-Performance Order Storage and the classic posts storage).

### Security by design
* Only users who can manage WooCommerce can create, edit or delete statuses, and every change is protected by a nonce.
* Status names, slugs and colours are validated on save; a slug can never take over a built-in WooCommerce status.
* Customer emails are plain text: your message and the customer's details are escaped before being placed in WooCommerce's own email template.
* Deleting a status never strands orders: you choose another status first, every order is moved to it (with an order note), and only then is the status removed.

## Features

* Unlimited custom order statuses
* Badge colour per status
* Bulk actions on the order list (HPOS and classic screens)
* "Counts as paid" option
* Optional customer email per status, with placeholders: {order_number}, {order_date}, {customer_name}, {status}, {site_title}
* Order counts per status, linking straight to the filtered order list
* Safe deletion that moves orders to the status you choose

## Limitations

* Built-in WooCommerce statuses (Processing, Completed, ...) cannot be renamed or removed.
* A status slug cannot be changed after it is created, because orders store it.
* Before deactivating or deleting the plugin, delete your custom statuses first so their orders are moved to a standard status. Otherwise those orders keep a status WooCommerce no longer recognises and do not show in the "All" order list until the plugin is active again.

## License

GPLv2 or later.
