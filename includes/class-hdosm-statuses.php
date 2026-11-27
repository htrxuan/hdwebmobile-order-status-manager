<?php

namespace htrxuan\hdosm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The custom order statuses: the single place they are stored, validated and removed.
 *
 * Everything lives in one option (`hdosm_statuses`), keyed by the status slug WITHOUT the
 * "wc-" prefix. Every value is validated on the way in (save()) and escaped by whoever
 * prints it -- nothing here ever outputs HTML.
 *
 * Deleting a status never strands orders: delete() first moves every order still in that
 * status to a fallback status of the admin's choosing, and only then removes the status.
 * Without that step those orders would keep a status WooCommerce no longer knows about and
 * silently drop out of the order list.
 */
class HDOSM_Statuses
{

    const OPTION = 'hdosm_statuses';

    /**
     * post_status is varchar(20) and WooCommerce prefixes "wc-", so a slug can be at most 17
     * characters long.
     */
    const MAX_SLUG_LENGTH = 17;

    /** Slugs WooCommerce (or WordPress) already uses -- never allowed for a custom status. */
    const RESERVED = array(
        'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed',
        'checkout-draft', 'draft', 'trash', 'auto-draft', 'publish', 'future', 'private', 'inherit',
    );

    /**
     * @return array<string, array> slug => status data, in the order they were created.
     */
    public static function all()
    {
        $stored = get_option(self::OPTION, array());
        if (!is_array($stored)) {
            return array();
        }
        $out = array();
        foreach ($stored as $slug => $data) {
            if (is_string($slug) && is_array($data) && self::is_valid_slug_shape($slug)) {
                $out[$slug] = self::with_defaults($data);
            }
        }
        return $out;
    }

    public static function get($slug)
    {
        $all = self::all();
        return isset($all[$slug]) ? $all[$slug] : null;
    }

    public static function defaults()
    {
        return array(
            'label'         => '',
            'color'         => '#2271b1',
            'paid'          => false,
            'bulk'          => true,
            'email'         => false,
            'email_subject' => '',
            'email_body'    => '',
        );
    }

    private static function with_defaults($data)
    {
        return array_merge(self::defaults(), array_intersect_key($data, self::defaults()));
    }

    public static function is_valid_slug_shape($slug)
    {
        return is_string($slug)
            && strlen($slug) >= 2
            && strlen($slug) <= self::MAX_SLUG_LENGTH
            && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    /**
     * Builds a slug suggestion from a label, e.g. "Awaiting Pickup" -> "awaiting-pickup".
     */
    public static function slug_from_label($label)
    {
        $slug = sanitize_title(remove_accents((string) $label));
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
        $slug = trim(substr($slug, 0, self::MAX_SLUG_LENGTH), '-');
        return $slug;
    }

    /**
     * Validates and stores one status. $input must already be unslashed. $original_slug is the slug being edited ('' when
     * adding); the slug itself cannot change after creation because orders reference it.
     *
     * @return true|\WP_Error
     */
    public static function save($input, $original_slug = '')
    {
        $all     = self::all();
        $editing = '' !== $original_slug;

        if ($editing && !isset($all[$original_slug])) {
            return new \WP_Error('hdosm_missing', __('That status no longer exists.', 'hdwebmobile-order-status-manager'));
        }

        $label = isset($input['label']) ? sanitize_text_field($input['label']) : '';
        if ('' === $label || mb_strlen($label) > 50) {
            return new \WP_Error('hdosm_label', __('Enter a status name of 1 to 50 characters.', 'hdwebmobile-order-status-manager'));
        }

        if ($editing) {
            $slug = $original_slug;
        } else {
            $slug = isset($input['slug']) ? sanitize_key($input['slug']) : '';
            if ('' === $slug) {
                $slug = self::slug_from_label($label);
            }
            if (!self::is_valid_slug_shape($slug)) {
                return new \WP_Error('hdosm_slug', sprintf(
                    /* translators: %d: maximum slug length */
                    __('The slug must be 2 to %d characters: lowercase letters, numbers and single hyphens.', 'hdwebmobile-order-status-manager'),
                    self::MAX_SLUG_LENGTH
                ));
            }
            if (in_array($slug, self::RESERVED, true) || array_key_exists('wc-' . $slug, wc_get_order_statuses())) {
                return new \WP_Error('hdosm_slug_taken', __('That slug is already used by another order status.', 'hdwebmobile-order-status-manager'));
            }
        }

        $color = isset($input['color']) ? sanitize_hex_color($input['color']) : '';
        if (!$color) {
            $color = self::defaults()['color'];
        }

        $subject = isset($input['email_subject']) ? sanitize_text_field($input['email_subject']) : '';
        $body    = isset($input['email_body']) ? sanitize_textarea_field($input['email_body']) : '';

        $all[$slug] = array(
            'label'         => $label,
            'color'         => $color,
            'paid'          => !empty($input['paid']),
            'bulk'          => !empty($input['bulk']),
            'email'         => !empty($input['email']),
            'email_subject' => mb_substr($subject, 0, 200),
            'email_body'    => mb_substr($body, 0, 5000),
        );

        update_option(self::OPTION, $all, true);
        return true;
    }

    /**
     * Moves every order in $slug to $fallback, then removes $slug.
     *
     * @return int|\WP_Error Number of orders moved.
     */
    public static function delete($slug, $fallback)
    {
        $all = self::all();
        if (!isset($all[$slug])) {
            return new \WP_Error('hdosm_missing', __('That status no longer exists.', 'hdwebmobile-order-status-manager'));
        }

        $valid = wc_get_order_statuses();
        if ($fallback === $slug || !isset($valid['wc-' . $fallback])) {
            return new \WP_Error('hdosm_fallback', __('Choose another status to move its orders to.', 'hdwebmobile-order-status-manager'));
        }

        $moved  = 0;
        $rounds = 0; // hard stop so an order that refuses to change can never loop forever
        do {
            $rounds++;
            $ids = wc_get_orders(array(
                'status' => array('wc-' . $slug),
                'limit'  => 200,
                'return' => 'ids',
                'type'   => 'shop_order',
            ));
            foreach ($ids as $id) {
                $order = wc_get_order($id);
                if ($order) {
                    $order->update_status($fallback, sprintf(
                        /* translators: %s: name of the deleted status */
                        __('Order status "%s" was deleted, so the order was moved.', 'hdwebmobile-order-status-manager'),
                        $all[$slug]['label']
                    ));
                    $moved++;
                }
            }
        } while (!empty($ids) && count($ids) === 200 && $rounds < 500);

        unset($all[$slug]);
        update_option(self::OPTION, $all, true);
        return $moved;
    }

    /**
     * Orders currently in a status (HPOS-aware, via WooCommerce's own counter).
     */
    public static function order_count($slug)
    {
        return (int) wc_orders_count($slug);
    }
}
