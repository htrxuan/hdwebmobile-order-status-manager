<?php

namespace htrxuan\hdosm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugs the stored statuses into WooCommerce: registers them, adds them to the status
 * list, the "paid" list and the order-list bulk actions (classic and HPOS screens), colours
 * their badges, and sends the optional customer email when an order enters one.
 *
 * Bulk changes themselves are handled by WooCommerce's own list tables, which already
 * process any `mark_<status>` action for a registered status -- this class only adds the
 * menu entries.
 */
final class HDOSM_Register
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
        add_action('init', array($this, 'register_post_statuses'), 9);
        add_filter('wc_order_statuses', array($this, 'add_order_statuses'));
        add_filter('woocommerce_order_is_paid_statuses', array($this, 'add_paid_statuses'));
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_actions'), 20);
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'add_bulk_actions'), 20);
        add_action('admin_head', array($this, 'print_badge_colours'));
        add_action('woocommerce_order_status_changed', array($this, 'maybe_send_email'), 20, 4);
    }

    public function register_post_statuses()
    {
        foreach (HDOSM_Statuses::all() as $slug => $status) {
            $label = $status['label'];
            register_post_status('wc-' . $slug, array(
                'label'                     => $label,
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // The label is admin-entered text: escaped here, not run through gettext.
                'label_count'               => array(
                    'singular' => esc_html($label) . ' <span class="count">(%s)</span>',
                    'plural'   => esc_html($label) . ' <span class="count">(%s)</span>',
                    'context'  => '',
                    'domain'   => null,
                ),
            ));
        }
    }

    public function add_order_statuses($statuses)
    {
        foreach (HDOSM_Statuses::all() as $slug => $status) {
            $statuses['wc-' . $slug] = $status['label'];
        }
        return $statuses;
    }

    public function add_paid_statuses($statuses)
    {
        foreach (HDOSM_Statuses::all() as $slug => $status) {
            if ($status['paid']) {
                $statuses[] = $slug;
            }
        }
        return array_values(array_unique($statuses));
    }

    public function add_bulk_actions($actions)
    {
        foreach (HDOSM_Statuses::all() as $slug => $status) {
            if ($status['bulk']) {
                /* translators: %s: status name */
                $actions['mark_' . $slug] = sprintf(__('Change status to %s', 'hdwebmobile-order-status-manager'), $status['label']);
            }
        }
        return $actions;
    }

    /**
     * Badge colours on the order list, order preview and edit screen. Only printed on
     * WooCommerce order screens; every value is a validated hex colour.
     */
    public function print_badge_colours()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, array('edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders'), true)) {
            return;
        }
        $statuses = HDOSM_Statuses::all();
        if (!$statuses) {
            return;
        }
        $css = '';
        foreach ($statuses as $slug => $status) {
            $bg = sanitize_hex_color($status['color']);
            if (!$bg) {
                continue;
            }
            $css .= sprintf(
                '.order-status.status-%1$s{background:%2$s;color:%3$s;}',
                sanitize_html_class($slug),
                $bg,
                self::text_colour_for($bg)
            );
        }
        if ($css) {
            echo '<style id="hdosm-status-colours">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built only from sanitize_html_class() slugs and sanitize_hex_color() values above.
        }
    }

    /**
     * Black or white text, whichever reads better on the badge colour.
     */
    public static function text_colour_for($hex)
    {
        $hex = ltrim($hex, '#');
        if (3 === strlen($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.6 ? '#1d2327' : '#ffffff';
    }

    /**
     * @param int       $order_id
     * @param string    $from
     * @param string    $to
     * @param \WC_Order $order
     */
    public function maybe_send_email($order_id, $from, $to, $order)
    {
        $status = HDOSM_Statuses::get($to);
        if (!$status || !$status['email'] || !$order instanceof \WC_Order) {
            return;
        }
        $recipient = $order->get_billing_email();
        if (!is_email($recipient)) {
            return;
        }
        if (HDOSM_Email::send($order, $status)) {
            $order->add_order_note(sprintf(
                /* translators: %s: status name */
                __('"%s" status email sent to the customer.', 'hdwebmobile-order-status-manager'),
                $status['label']
            ));
        }
    }
}
