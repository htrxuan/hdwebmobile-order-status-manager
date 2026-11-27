<?php

namespace htrxuan\hdosm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The optional "your order is now <status>" customer email.
 *
 * The subject and body are plain text the admin typed. Placeholders are replaced first,
 * then the whole body is escaped and wrapped in WooCommerce's own email template, so
 * neither the admin's text nor customer data (name, order number) can inject markup.
 */
class HDOSM_Email
{

    public static function placeholders()
    {
        return array('{order_number}', '{order_date}', '{customer_name}', '{status}', '{site_title}');
    }

    public static function default_subject()
    {
        return __('Your {site_title} order #{order_number} is now {status}', 'hdwebmobile-order-status-manager');
    }

    public static function default_body()
    {
        return __("Hi {customer_name},\n\nThe status of your order #{order_number} has changed to: {status}.\n\nThank you for shopping with {site_title}.", 'hdwebmobile-order-status-manager');
    }

    /**
     * @param \WC_Order $order
     * @param array     $status Status data from HDOSM_Statuses.
     */
    public static function replace_placeholders($text, $order, $status)
    {
        $date = $order->get_date_created();
        $name = trim($order->get_billing_first_name());
        return strtr($text, array(
            '{order_number}'  => $order->get_order_number(),
            '{order_date}'    => $date ? wc_format_datetime($date) : '',
            '{customer_name}' => '' !== $name ? $name : __('there', 'hdwebmobile-order-status-manager'),
            '{status}'        => $status['label'],
            '{site_title}'    => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        ));
    }

    /**
     * @param \WC_Order $order
     * @param array     $status
     * @return bool
     */
    public static function send($order, $status)
    {
        $subject = '' !== $status['email_subject'] ? $status['email_subject'] : self::default_subject();
        $body    = '' !== $status['email_body'] ? $status['email_body'] : self::default_body();

        $subject = wp_strip_all_tags(self::replace_placeholders($subject, $order, $status));
        $body    = self::replace_placeholders($body, $order, $status);

        $mailer  = WC()->mailer();
        // WooCommerce's email-header template escapes the heading itself (esc_html), so the
        // label goes in raw; the body is escaped here before wpautop() adds paragraphs.
        $message = $mailer->wrap_message(
            $status['label'],
            wpautop(esc_html($body))
        );

        return (bool) $mailer->send($order->get_billing_email(), $subject, $message);
    }
}
