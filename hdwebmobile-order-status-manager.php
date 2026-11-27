<?php

/**
 * Plugin Name: HDWebmobile Order Status Manager
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-order-status-manager/
 * Description: Create your own WooCommerce order statuses with colours, bulk actions and optional customer emails.
 * Version: 1.0.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-order-status-manager
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdosm;

if (!defined('ABSPATH')) {
    exit;
}

define('HDOSM_VERSION', '1.0.0');
define('HDOSM_PLUGIN_FILE', __FILE__);
define('HDOSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDOSM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDOSM_PLUGIN_DIR . 'includes/class-hdosm-activator.php';

register_activation_hook(__FILE__, array(HDOSM_Activator::class, 'activate'));

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDOSM_PLUGIN_FILE, true);
    }
});

add_action('plugins_loaded', function () {
    require_once HDOSM_PLUGIN_DIR . 'includes/class-hdosm-core.php';
    HDOSM_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" rel="noopener noreferrer">' . esc_html__('Donate', 'hdwebmobile-order-status-manager') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});
