<?php

namespace htrxuan\hdosm;

if (!defined('ABSPATH')) {
    exit;
}

final class HDOSM_Core
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    private function __clone()
    {
    }

    private function includes()
    {
        require_once HDOSM_PLUGIN_DIR . 'includes/class-hdosm-statuses.php';
        require_once HDOSM_PLUGIN_DIR . 'includes/class-hdosm-email.php';
        require_once HDOSM_PLUGIN_DIR . 'includes/class-hdosm-register.php';
        require_once HDOSM_PLUGIN_DIR . 'includes/class-hdosm-admin.php';
    }

    private function init_hooks()
    {
        add_action('admin_notices', array($this, 'render_missing_woocommerce_notice'));

        if (!class_exists('WooCommerce')) {
            return;
        }

        // HDOSM_Admin owns the hdwebmobile_hub_tabs registration used by the shared hub
        // page, so it must load unconditionally (not only when is_admin()).
        HDOSM_Admin::get_instance();
        HDOSM_Register::get_instance();
    }

    public function render_missing_woocommerce_notice()
    {
        $screen = get_current_screen();
        if (!$screen || 'plugins' !== $screen->id) {
            return;
        }

        if (!get_transient('hdosm_wc_missing_notice')) {
            return;
        }
        delete_transient('hdosm_wc_missing_notice');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php esc_html_e('HDWebmobile Order Status Manager requires WooCommerce to be installed and active. The plugin has been deactivated.', 'hdwebmobile-order-status-manager'); ?>
            </p>
        </div>
        <?php
    }
}
