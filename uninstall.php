<?php

/**
 * Removes the plugin's stored status definitions. Orders are never changed here: the
 * admin screen asks merchants to delete their custom statuses (which moves those orders
 * to a standard status) before uninstalling.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('hdosm_statuses');
