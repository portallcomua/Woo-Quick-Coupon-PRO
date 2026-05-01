<?php
/**
 * Plugin Name: WooQuick Coupon Pro
 * Version: 1.0
 * Description: Купони з питаннями-відповідями для WooCommerce
 * Author: portallcomua
 * GitHub Plugin URI: https://github.com/portallcomua/woo-quick-coupon-pro
 */

if (!defined('ABSPATH')) exit;

define('WQC_VERSION', '1.0');
define('WQC_FREE_LIMIT', 25);
define('WQC_SHOP_URL', 'https://uaserver.pp.ua/product/wooquick-coupon-pro/');

add_filter('pre_set_site_transient_update_plugins', function($transient) {
    if (empty($transient->checked)) return $transient;
    $plugin_slug = plugin_basename(__FILE__);
    $response = wp_remote_get("https://api.github.com/repos/portallcomua/woo-quick-coupon-pro/releases/latest");
    if (is_wp_error($response)) return $transient;
    $release = json_decode(wp_remote_retrieve_body($response));
    if (isset($release->tag_name)) {
        $latest = ltrim($release->tag_name, 'v');
        if (version_compare(WQC_VERSION, $latest, '<')) {
            $transient->response[$plugin_slug] = (object) [
                'slug' => dirname($plugin_slug),
                'plugin' => $plugin_slug,
                'new_version' => $latest,
                'url' => $release->html_url,
                'package' => $release->zipball_url,
            ];
        }
    }
    return $transient;
});

function wqc_get_count() { return (int) get_option('wqc_operations', 0); }
function wqc_inc() { update_option('wqc_operations', wqc_get_count() + 1); }
function wqc_can() { return get_option('wqc_license') ? true : wqc_get_count() < WQC_FREE_LIMIT; }
function wqc_remaining() { return max(0, WQC_FREE_LIMIT - wqc_get_count()); }

add_action('admin_menu', function() {
    add_menu_page('WooQuick Coupon', 'WooQuick Coupon', 'manage_woocommerce', 'wqc_main', 'wqc_page', 'dashicons-tickets-alt', 31);
    add_submenu_page('wqc_main', 'Ліцензія', '🔑 Ліцензія', 'manage_woocommerce', 'wqc_license', 'wqc_license_page');
});

function wqc_page() { echo '<div class="wrap"><h1>🎫 WooQuick Coupon Pro</h1><p>Створення купонів з квізами. Ліміт: ' . wqc_remaining() . ' / ' . WQC_FREE_LIMIT . '</p></div>'; }

function wqc_license_page() { ?>
    <div class="wrap"><h1>🔑 Ліцензія WooQuick Coupon Pro</h1>
    <?php if (get_option('wqc_license')): ?>
        <div class="notice notice-success"><p>✅ Активна</p></div>
    <?php else: ?>
        <div class="notice notice-warning"><p>⚠️ Безкоштовно: <?php echo wqc_remaining(); ?> / <?php echo WQC_FREE_LIMIT; ?></p>
        <form method="post"><?php wp_nonce_field('wqc_lic'); ?>
            <input name="license_key" placeholder="Ключ"><button type="submit" name="activate_lic">🔑 Активувати</button>
        </form>
        <p><a href="<?php echo WQC_SHOP_URL; ?>" target="_blank">💰 Придбати PRO (299 грн / $15)</a></p>
    <?php endif; ?>
    </div><?php
}

add_action('admin_init', function() {
    if (isset($_POST['activate_lic']) && wp_verify_nonce($_POST['wqc_lic'], 'wqc_lic')) {
        if (strlen(sanitize_text_field($_POST['license_key'])) >= 16) update_option('wqc_license', true);
        else echo '<div class="notice notice-error"><p>❌ Невірний ключ</p></div>';
    }
});
?>