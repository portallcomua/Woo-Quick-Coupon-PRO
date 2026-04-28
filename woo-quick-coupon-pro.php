<?php
/**
 * Plugin Name: WooQuick Coupon Pro
 * Version: 1.0
 * Description: Швидке створення купонів з питаннями-відповідями
 * Author: WooQuick
 * GitHub Plugin URI: portallcomua/woo-quick-coupon-pro
 * GitHub Branch: main
 */

if (!defined('ABSPATH')) exit;

define('WQ_VERSION', '1.0');
define('WQ_FREE_LIMIT', 5);
define('WQ_SHOP_URL', 'https://uaserver.pp.ua/product/wooquick-coupon-pro/');

function wq_get_coupons_count() {
    $count = wp_count_posts('shop_coupon');
    return $count->publish;
}

function wq_is_license_active() {
    $license_valid = get_option('wq_license_valid', false);
    $license_domain = get_option('wq_license_domain', '');
    return $license_valid && $license_domain === $_SERVER['HTTP_HOST'];
}

function wq_can_create_coupon() {
    if (wq_is_license_active()) return true;
    return wq_get_coupons_count() < WQ_FREE_LIMIT;
}

function wq_get_remaining_free() {
    return max(0, WQ_FREE_LIMIT - wq_get_coupons_count());
}

add_action('admin_menu', function() {
    add_menu_page('WooQuick Coupon Pro', 'WooQuick Coupon Pro', 'manage_options', 'wq_main', 'wq_render_coupon_page', 'dashicons-tickets-alt', 31);
    add_submenu_page('wq_main', 'Ліцензія Купон', '🔑 Ліцензія', 'manage_options', 'wq_license', 'wq_render_license_page');
});

function wq_render_license_page() {
    ?>
    <div class="wrap" style="max-width:600px; margin:auto; padding:20px;">
        <h2>🔑 WooQuick Coupon Pro - Ліцензія</h2>
        <?php if (wq_is_license_active()): ?>
            <div style="background:#d4edda; padding:15px; border-radius:10px;">✅ <strong>Ліцензія активна!</strong><br>Домен: <?php echo get_option('wq_license_domain', ''); ?></div>
        <?php else: ?>
            <div style="background:#fff3cd; padding:15px; border-radius:10px; margin-bottom:20px;">⚠️ <strong>Безкоштовна версія</strong><br>Ліміт: <?php echo WQ_FREE_LIMIT; ?> купонів.<br>Залишилось: <?php echo wq_get_remaining_free(); ?></div>
            <div style="background:#e8f0fe; padding:20px; border-radius:10px;">
                <h3>💰 Придбати ліцензію - 299 грн / $15 USD</h3>
                <p><a href="<?php echo WQ_SHOP_URL; ?>" target="_blank" style="background:#4CAF50; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;">📦 ПЕРЕЙТИ ДО ОПЛАТИ</a></p>
                <hr>
                <form method="post"><?php wp_nonce_field('wq_activate_action', 'wq_activate_nonce'); ?>
                    <input type="text" name="license_key" placeholder="Ліцензійний ключ" style="width:100%; padding:10px; margin-bottom:10px;">
                    <button type="submit" name="wq_activate_license" style="background:#2196F3; color:#fff; padding:10px 20px;">🔑 Активувати</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_init', function() {
    if (isset($_POST['wq_activate_license']) && isset($_POST['license_key']) && wp_verify_nonce($_POST['wq_activate_nonce'], 'wq_activate_action')) {
        $key = trim($_POST['license_key']);
        if (strlen($key) >= 16) { update_option('wq_license_valid', true); update_option('wq_license_key', $key); update_option('wq_license_domain', $_SERVER['HTTP_HOST']); echo '<div class="notice notice-success"><p>✅ Ліцензію активовано!</p></div>'; }
        else { echo '<div class="notice notice-error"><p>❌ Невірний ключ</p></div>'; }
    }
});

function wq_render_coupon_page() {
    $remaining = wq_get_remaining_free();
    $license_active = wq_is_license_active();
    $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
    ?>
    <div class="wrap" style="max-width:700px; margin:auto; padding:20px;">
        <h1>🎫 WooQuick Coupon Pro v<?php echo WQ_VERSION; ?></h1>
        <div style="background: <?php echo $license_active ? '#d4edda' : ($remaining > 0 ? '#fff3cd' : '#f8d7da'); ?>; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center;">
            <?php if ($license_active): ?>✅ PRO версія - необмежено купонів<?php else: ?>📊 Безкоштовно: <?php echo wq_get_coupons_count(); ?> з <?php echo WQ_FREE_LIMIT; ?> (залишилось <?php echo $remaining; ?>)<?php endif; ?>
        </div>
        
        <div style="background:#fff; padding:30px; border-radius:15px;">
            <h2>🎫 Звичайний купон</h2>
            <input type="text" id="cp_code" placeholder="Код купона" style="width:100%; padding:12px; margin-bottom:10px;"><br>
            <input type="number" id="cp_amount" placeholder="Сума знижки" style="width:48%; padding:12px;"><select id="cp_type" style="width:48%; padding:12px;"><option value="percent">%</option><option value="fixed_cart">грн</option></select><br>
            <button id="make_coupon" class="button button-primary">🚀 Створити</button>
            <hr>
            <h2>❓ Купон з питанням (Квіз)</h2>
            <input type="text" id="quiz_code" placeholder="Код купона" style="width:100%; padding:12px; margin-bottom:10px;">
            <input type="text" id="quiz_question" placeholder="Питання" style="width:100%; padding:12px; margin-bottom:10px;">
            <input type="text" id="quiz_answer" placeholder="Правильна відповідь" style="width:100%; padding:12px; margin-bottom:10px;">
            <textarea id="quiz_description" placeholder="Пояснення (що отримає клієнт)" rows="2" style="width:100%; padding:12px; margin-bottom:10px;"></textarea>
            <input type="number" id="quiz_amount" placeholder="Сума знижки" style="width:48%; padding:12px;"><select id="quiz_type" style="width:48%; padding:12px;"><option value="percent">%</option><option value="fixed_cart">грн</option></select>
            <button id="make_quiz_coupon" class="button button-primary">❓+🚀 Створити квіз</button>
        </div>
        <div id="status" style="margin-top:20px;"></div>
    </div>
    <script>
    function showStatus(msg, isError=false){ let d=document.getElementById('status'); d.innerHTML=msg; d.style.color=isError?'red':'green'; setTimeout(()=>d.innerHTML='',3000); }
    document.getElementById('make_coupon').onclick=async function(){
        let fd=new FormData(); fd.append('action','wq_create_coupon'); fd.append('code',document.getElementById('cp_code').value); fd.append('amount',document.getElementById('cp_amount').value); fd.append('type',document.getElementById('cp_type').value);
        let r=await fetch(ajaxurl,{method:'POST',body:fd}); let res=await r.json(); if(res.success){ showStatus('✅ Купон створено!'); document.getElementById('cp_code').value=''; document.getElementById('cp_amount').value=''; } else { showStatus('❌ '+res.data,true); }
    };
    document.getElementById('make_quiz_coupon').onclick=async function(){
        let fd=new FormData(); fd.append('action','wq_create_quiz_coupon'); fd.append('code',document.getElementById('quiz_code').value); fd.append('question',document.getElementById('quiz_question').value); fd.append('answer',document.getElementById('quiz_answer').value); fd.append('description',document.getElementById('quiz_description').value); fd.append('amount',document.getElementById('quiz_amount').value); fd.append('type',document.getElementById('quiz_type').value);
        let r=await fetch(ajaxurl,{method:'POST',body:fd}); let res=await r.json(); if(res.success){ showStatus('✅ Квіз-купон створено!'); document.getElementById('quiz_code').value=''; document.getElementById('quiz_question').value=''; document.getElementById('quiz_answer').value=''; document.getElementById('quiz_description').value=''; document.getElementById('quiz_amount').value=''; } else { showStatus('❌ '+res.data,true); }
    };
    </script>
    <?php
}

add_action('wp_ajax_wq_create_coupon', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Немає прав');
    if (!wq_can_create_coupon()) wp_send_json_error('Ліміт вичерпано');
    $id = wp_insert_post(['post_title'=>sanitize_text_field($_POST['code']), 'post_status'=>'publish', 'post_type'=>'shop_coupon']);
    if($id){ update_post_meta($id, 'discount_type', sanitize_text_field($_POST['type'])); update_post_meta($id, 'coupon_amount', sanitize_text_field($_POST['amount'])); wp_send_json_success(); }
    else wp_send_json_error('Помилка');
});

add_action('wp_ajax_wq_create_quiz_coupon', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Немає прав');
    if (!wq_can_create_coupon()) wp_send_json_error('Ліміт вичерпано');
    $id = wp_insert_post(['post_title'=>sanitize_text_field($_POST['code']), 'post_status'=>'publish', 'post_type'=>'shop_coupon']);
    if($id){
        update_post_meta($id, 'discount_type', sanitize_text_field($_POST['type'])); update_post_meta($id, 'coupon_amount', sanitize_text_field($_POST['amount']));
        update_post_meta($id, '_wq_quiz_question', sanitize_text_field($_POST['question'])); update_post_meta($id, '_wq_quiz_answer', strtolower(trim(sanitize_text_field($_POST['answer'])))); update_post_meta($id, '_wq_quiz_description', sanitize_textarea_field($_POST['description']));
        wp_send_json_success();
    } else wp_send_json_error('Помилка');
});
?>