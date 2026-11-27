<?php

namespace htrxuan\hdosm;

if (!defined('ABSPATH')) {
    exit;
}

class HDOSM_Activator
{

    public static function activate()
    {
        if (!self::is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(HDOSM_PLUGIN_FILE));
            set_transient('hdosm_wc_missing_notice', true, 30);
        }
    }

    public static function is_woocommerce_active()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('woocommerce/woocommerce.php') || class_exists('WooCommerce');
    }
}
