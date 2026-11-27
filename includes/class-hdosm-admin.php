<?php

namespace htrxuan\hdosm;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The "Order Status Manager" hub tab: list, add, edit and delete custom statuses.
 *
 * The two admin_post handlers below are the only write paths. Each checks
 * manage_woocommerce and a nonce before reading any input; every value is then
 * validated by HDOSM_Statuses.
 */
class HDOSM_Admin
{

    const NONCE_SAVE   = 'hdosm_save_status';
    const NONCE_DELETE = 'hdosm_delete_status';
    const TAB          = 'order-status-manager';

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
        require_once HDOSM_PLUGIN_DIR . 'includes/class-hdosm-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_post_hdosm_save_status', array($this, 'handle_save'));
        add_action('admin_post_hdosm_delete_status', array($this, 'handle_delete'));
    }

    public function register_hub_tabs($tabs)
    {
        $tabs[self::TAB] = array(
            'label'  => __('Order Status Manager', 'hdwebmobile-order-status-manager'),
            'order'  => 165,
            'render' => array($this, 'render_page'),
        );
        return $tabs;
    }

    private static function page_url($args = array())
    {
        return add_query_arg($args, admin_url('admin.php?page=hdwebmobile&tab=' . self::TAB));
    }

    private static function redirect_with_notice($type, $message, $args = array())
    {
        set_transient('hdosm_notice_' . get_current_user_id(), array('type' => $type, 'message' => $message), 60);
        wp_safe_redirect(self::page_url($args));
        exit;
    }

    private static function deny($message)
    {
        wp_die(esc_html($message));
    }

    public function handle_save()
    {
        if (!current_user_can('manage_woocommerce')) {
            self::deny(__('You do not have permission to do this.', 'hdwebmobile-order-status-manager'));
        }
        if (!isset($_POST['hdosm_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hdosm_nonce'])), self::NONCE_SAVE)) {
            self::deny(__('Security check failed. Please try again.', 'hdwebmobile-order-status-manager'));
        }

        $original = isset($_POST['original_slug']) ? sanitize_key(wp_unslash($_POST['original_slug'])) : '';
        $input    = array();
        foreach (array('label', 'slug', 'color', 'email_subject') as $key) {
            $input[$key] = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
        }
        $input['email_body'] = isset($_POST['email_body']) ? sanitize_textarea_field(wp_unslash($_POST['email_body'])) : '';
        foreach (array('paid', 'bulk', 'email') as $key) {
            $input[$key] = !empty($_POST[$key]);
        }

        $result = HDOSM_Statuses::save($input, $original);
        if (is_wp_error($result)) {
            self::redirect_with_notice('error', $result->get_error_message(), '' !== $original ? array('edit' => $original) : array('add' => 1));
        }
        self::redirect_with_notice('success', __('Order status saved.', 'hdwebmobile-order-status-manager'));
    }

    public function handle_delete()
    {
        if (!current_user_can('manage_woocommerce')) {
            self::deny(__('You do not have permission to do this.', 'hdwebmobile-order-status-manager'));
        }
        if (!isset($_POST['hdosm_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hdosm_nonce'])), self::NONCE_DELETE)) {
            self::deny(__('Security check failed. Please try again.', 'hdwebmobile-order-status-manager'));
        }

        $slug     = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
        $fallback = isset($_POST['fallback']) ? sanitize_key(wp_unslash($_POST['fallback'])) : '';
        $fallback = preg_replace('/^wc-/', '', $fallback);

        $result = HDOSM_Statuses::delete($slug, $fallback);
        if (is_wp_error($result)) {
            self::redirect_with_notice('error', $result->get_error_message());
        }
        self::redirect_with_notice('success', sprintf(
            /* translators: %d: number of orders moved */
            _n('Status deleted. %d order was moved to the status you chose.', 'Status deleted. %d orders were moved to the status you chose.', $result, 'hdwebmobile-order-status-manager'),
            $result
        ));
    }

    /* ---------- screen ---------- */

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-order-status-manager'));
        }

        $notice = get_transient('hdosm_notice_' . get_current_user_id());
        if ($notice) {
            delete_transient('hdosm_notice_' . get_current_user_id());
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                'error' === $notice['type'] ? 'error' : 'success',
                esc_html($notice['message'])
            );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen switches; every write goes through a nonce-checked admin_post handler.
        $edit   = isset($_GET['edit']) ? sanitize_key(wp_unslash($_GET['edit'])) : '';
        $delete = isset($_GET['delete']) ? sanitize_key(wp_unslash($_GET['delete'])) : '';
        $add    = !empty($_GET['add']);
        // phpcs:enable

        if ('' !== $delete && HDOSM_Statuses::get($delete)) {
            $this->render_delete_form($delete);
            return;
        }
        if ($add || ('' !== $edit && HDOSM_Statuses::get($edit))) {
            $this->render_edit_form($add ? '' : $edit);
            return;
        }
        $this->render_list();
    }

    private function render_list()
    {
        $statuses = HDOSM_Statuses::all();
        ?>
        <p><?php esc_html_e('Create your own order statuses, such as "Awaiting pickup" or "In production". Each one gets a colour on the order list, can be applied to many orders at once with the Bulk actions menu, and can email the customer when an order enters it.', 'hdwebmobile-order-status-manager'); ?></p>
        <p><a class="button button-primary" href="<?php echo esc_url(self::page_url(array('add' => 1))); ?>"><?php esc_html_e('Add order status', 'hdwebmobile-order-status-manager'); ?></a></p>

        <table class="widefat striped" style="max-width:960px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Status', 'hdwebmobile-order-status-manager'); ?></th>
                    <th><?php esc_html_e('Slug', 'hdwebmobile-order-status-manager'); ?></th>
                    <th><?php esc_html_e('Orders', 'hdwebmobile-order-status-manager'); ?></th>
                    <th><?php esc_html_e('Counts as paid', 'hdwebmobile-order-status-manager'); ?></th>
                    <th><?php esc_html_e('Bulk action', 'hdwebmobile-order-status-manager'); ?></th>
                    <th><?php esc_html_e('Customer email', 'hdwebmobile-order-status-manager'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$statuses) : ?>
                <tr><td colspan="7"><?php esc_html_e('No custom statuses yet.', 'hdwebmobile-order-status-manager'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($statuses as $slug => $status) : ?>
                <tr>
                    <td>
                        <span style="display:inline-block;padding:2px 10px;border-radius:4px;background:<?php echo esc_attr($status['color']); ?>;color:<?php echo esc_attr(HDOSM_Register::text_colour_for($status['color'])); ?>;"><?php echo esc_html($status['label']); ?></span>
                    </td>
                    <td><code>wc-<?php echo esc_html($slug); ?></code></td>
                    <td>
                        <?php $count = HDOSM_Statuses::order_count($slug); ?>
                        <?php if ($count) : ?>
                            <a href="<?php echo esc_url($this->orders_url($slug)); ?>"><?php echo esc_html(number_format_i18n($count)); ?></a>
                        <?php else : ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td><?php echo $status['paid'] ? esc_html__('Yes', 'hdwebmobile-order-status-manager') : esc_html__('No', 'hdwebmobile-order-status-manager'); ?></td>
                    <td><?php echo $status['bulk'] ? esc_html__('Yes', 'hdwebmobile-order-status-manager') : esc_html__('No', 'hdwebmobile-order-status-manager'); ?></td>
                    <td><?php echo $status['email'] ? esc_html__('On', 'hdwebmobile-order-status-manager') : esc_html__('Off', 'hdwebmobile-order-status-manager'); ?></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <a class="button button-small" href="<?php echo esc_url(self::page_url(array('edit' => $slug))); ?>"><?php esc_html_e('Edit', 'hdwebmobile-order-status-manager'); ?></a>
                        <a class="button button-small" href="<?php echo esc_url(self::page_url(array('delete' => $slug))); ?>"><?php esc_html_e('Delete', 'hdwebmobile-order-status-manager'); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description" style="max-width:960px;"><?php esc_html_e('Before deactivating or deleting this plugin, delete your custom statuses here first: deleting a status moves its orders to a standard status, so none are left in a status WooCommerce no longer knows.', 'hdwebmobile-order-status-manager'); ?></p>
        <?php
    }

    private function orders_url($slug)
    {
        $hpos = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        return $hpos
            ? admin_url('admin.php?page=wc-orders&status=wc-' . $slug)
            : admin_url('edit.php?post_type=shop_order&post_status=wc-' . $slug);
    }

    private function render_edit_form($slug)
    {
        $is_new = '' === $slug;
        $status = $is_new ? HDOSM_Statuses::defaults() : HDOSM_Statuses::get($slug);
        if ($is_new) {
            $status['email_subject'] = HDOSM_Email::default_subject();
            $status['email_body']    = HDOSM_Email::default_body();
        }
        ?>
        <h2><?php echo $is_new ? esc_html__('Add order status', 'hdwebmobile-order-status-manager') : esc_html__('Edit order status', 'hdwebmobile-order-status-manager'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="hdosm_save_status" />
            <input type="hidden" name="original_slug" value="<?php echo esc_attr($slug); ?>" />
            <?php wp_nonce_field(self::NONCE_SAVE, 'hdosm_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="hdosm-label"><?php esc_html_e('Status name', 'hdwebmobile-order-status-manager'); ?></label></th>
                    <td><input type="text" id="hdosm-label" name="label" class="regular-text" maxlength="50" required value="<?php echo esc_attr($status['label']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hdosm-slug"><?php esc_html_e('Slug', 'hdwebmobile-order-status-manager'); ?></label></th>
                    <td>
                        <?php if ($is_new) : ?>
                            <input type="text" id="hdosm-slug" name="slug" class="regular-text" maxlength="<?php echo esc_attr(HDOSM_Statuses::MAX_SLUG_LENGTH); ?>" pattern="[a-z0-9]+(-[a-z0-9]+)*" />
                            <p class="description"><?php esc_html_e('Optional. Leave empty to build it from the name. Lowercase letters, numbers and hyphens, up to 17 characters. It cannot be changed later.', 'hdwebmobile-order-status-manager'); ?></p>
                        <?php else : ?>
                            <code>wc-<?php echo esc_html($slug); ?></code>
                            <p class="description"><?php esc_html_e('The slug cannot be changed because orders already use it.', 'hdwebmobile-order-status-manager'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hdosm-color"><?php esc_html_e('Colour', 'hdwebmobile-order-status-manager'); ?></label></th>
                    <td><input type="color" id="hdosm-color" name="color" value="<?php echo esc_attr($status['color']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Options', 'hdwebmobile-order-status-manager'); ?></th>
                    <td>
                        <label><input type="checkbox" name="paid" value="1" <?php checked($status['paid']); ?> /> <?php esc_html_e('Counts as paid (customers get download access and the order is treated as paid)', 'hdwebmobile-order-status-manager'); ?></label><br />
                        <label><input type="checkbox" name="bulk" value="1" <?php checked($status['bulk']); ?> /> <?php esc_html_e('Show in the order list\'s Bulk actions menu', 'hdwebmobile-order-status-manager'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Customer email', 'hdwebmobile-order-status-manager'); ?></th>
                    <td>
                        <label><input type="checkbox" name="email" value="1" <?php checked($status['email']); ?> /> <?php esc_html_e('Email the customer when an order enters this status', 'hdwebmobile-order-status-manager'); ?></label>
                        <p><label for="hdosm-subject"><?php esc_html_e('Subject', 'hdwebmobile-order-status-manager'); ?></label><br />
                            <input type="text" id="hdosm-subject" name="email_subject" class="large-text" maxlength="200" value="<?php echo esc_attr($status['email_subject']); ?>" /></p>
                        <p><label for="hdosm-body"><?php esc_html_e('Message (plain text)', 'hdwebmobile-order-status-manager'); ?></label><br />
                            <textarea id="hdosm-body" name="email_body" class="large-text" rows="7"><?php echo esc_textarea($status['email_body']); ?></textarea></p>
                        <p class="description">
                            <?php esc_html_e('Placeholders:', 'hdwebmobile-order-status-manager'); ?>
                            <code><?php echo esc_html(implode(' ', HDOSM_Email::placeholders())); ?></code>
                        </p>
                    </td>
                </tr>
            </table>
            <p>
                <?php submit_button($is_new ? __('Add status', 'hdwebmobile-order-status-manager') : __('Save status', 'hdwebmobile-order-status-manager'), 'primary', 'submit', false); ?>
                <a class="button" href="<?php echo esc_url(self::page_url()); ?>"><?php esc_html_e('Cancel', 'hdwebmobile-order-status-manager'); ?></a>
            </p>
        </form>
        <?php
    }

    private function render_delete_form($slug)
    {
        $status = HDOSM_Statuses::get($slug);
        $count  = HDOSM_Statuses::order_count($slug);
        $others = wc_get_order_statuses();
        unset($others['wc-' . $slug]);
        ?>
        <h2>
            <?php
            /* translators: %s: status name */
            echo esc_html(sprintf(__('Delete "%s"', 'hdwebmobile-order-status-manager'), $status['label']));
            ?>
        </h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="hdosm_delete_status" />
            <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>" />
            <?php wp_nonce_field(self::NONCE_DELETE, 'hdosm_nonce'); ?>
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: %d: number of orders */
                    _n('%d order is in this status. Choose where to move it:', '%d orders are in this status. Choose where to move them:', $count, 'hdwebmobile-order-status-manager'),
                    $count
                ));
                ?>
            </p>
            <p>
                <select name="fallback">
                    <?php foreach ($others as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'wc-on-hold'); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p class="description"><?php esc_html_e('Moving an order adds a note to it. Status emails for the new status are sent as usual.', 'hdwebmobile-order-status-manager'); ?></p>
            <p>
                <?php submit_button(__('Move orders and delete status', 'hdwebmobile-order-status-manager'), 'delete', 'submit', false); ?>
                <a class="button" href="<?php echo esc_url(self::page_url()); ?>"><?php esc_html_e('Cancel', 'hdwebmobile-order-status-manager'); ?></a>
            </p>
        </form>
        <?php
    }
}
