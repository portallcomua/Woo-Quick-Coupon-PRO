<?php
/**
 * Plugin Name: WooQuick Coupon Pro
 * Description: Швидке створення купонів з голосовим введенням та питаннями-відповідями
 * Version: 1.0
 * Author: WooQuick
 */

if (!defined('ABSPATH')) exit;

define('WQ_COUPON_VERSION', '1.0');
define('WQ_COUPON_FREE_LIMIT', 5);
define('WQ_COUPON_SLUG', 'woo_quick_coupon');

// Функції ліцензії та ліміту
function wq_is_local_server() {
    $local_domains = ['localhost', '127.0.0.1', '::1', '.local', '.test', '.dev'];
    $host = $_SERVER['HTTP_HOST'] ?? '';
    foreach ($local_domains as $domain) {
        if (strpos($host, $domain) !== false) return true;
    }
    return false;
}

function wq_get_coupons_count() {
    $count = wp_count_posts('shop_coupon');
    return $count->publish;
}

function wq_is_license_active() {
    if (wq_is_local_server()) return true; // Локальний сервер - без обмежень
    
    $license_valid = get_option('wq_coupon_license_valid', false);
    $license_domain = get_option('wq_coupon_license_domain', '');
    $current_domain = $_SERVER['HTTP_HOST'];
    
    if ($license_valid && $license_domain !== $current_domain) {
        update_option('wq_coupon_license_valid', false);
        return false;
    }
    return $license_valid;
}

function wq_can_create_coupon() {
    if (wq_is_license_active()) return true;
    return wq_get_coupons_count() < WQ_COUPON_FREE_LIMIT;
}

function wq_get_remaining_free() {
    return max(0, WQ_COUPON_FREE_LIMIT - wq_get_coupons_count());
}

// Адмін меню
add_action('admin_menu', function() {
    add_menu_page('Швидкий Купон Pro', 'Швидкий Купон Pro', 'manage_options', WQ_COUPON_SLUG, 'wq_render_coupon_page', 'dashicons-tickets-alt', 31);
    add_submenu_page(WQ_COUPON_SLUG, 'Ліцензія Купон', '🔑 Ліцензія', 'manage_options', WQ_COUPON_SLUG . '_license', 'wq_render_license_page');
});

// Сторінка ліцензії
function wq_render_license_page() {
    ?>
    <div class="wrap" style="max-width:600px; margin:auto; padding:20px;">
        <h2>🔑 WooQuick Coupon Pro - Ліцензія</h2>
        
        <?php if (wq_is_local_server()): ?>
            <div style="background:#d4edda; padding:15px; border-radius:10px; margin-bottom:20px;">
                🛠️ <strong>Режим розробника (локальний сервер)</strong><br>
                Обмеження знято, всі функції доступні.
            </div>
        <?php elseif (wq_is_license_active()): ?>
            <div style="background:#d4edda; padding:15px; border-radius:10px;">
                ✅ <strong>Ліцензія активна!</strong><br>
                Домен: <?php echo get_option('wq_coupon_license_domain', ''); ?>
            </div>
        <?php else: ?>
            <div style="background:#fff3cd; padding:15px; border-radius:10px; margin-bottom:20px;">
                ⚠️ <strong>Безкоштовна версія</strong><br>
                Ліміт: <?php echo WQ_COUPON_FREE_LIMIT; ?> купонів.<br>
                Залишилось: <?php echo wq_get_remaining_free(); ?>
            </div>
            <div style="background:#e8f0fe; padding:20px; border-radius:10px;">
                <h3>💰 Придбати ліцензію - 299 грн / $15 USD</h3>
                <form method="post">
                    <?php wp_nonce_field('wq_activate_action', 'wq_activate_nonce'); ?>
                    <input type="text" name="license_key" placeholder="Ліцензійний ключ" style="width:100%; padding:10px; margin-bottom:10px;">
                    <button type="submit" name="wq_activate_license" style="background:#4CAF50; color:#fff; padding:10px 20px;">🔑 Активувати</button>
                </form>
                <hr>
                <form method="post">
                    <?php wp_nonce_field('wq_request_action', 'wq_request_nonce'); ?>
                    <input type="email" name="buyer_email" placeholder="Ваш email" style="width:100%; padding:10px; margin-bottom:10px;">
                    <button type="submit" name="wq_request_payment" style="background:#2196F3; color:#fff; padding:10px 20px;">📩 Запит на оплату</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Обробка ліцензії
add_action('admin_init', function() {
    if (isset($_POST['wq_activate_license']) && isset($_POST['license_key']) && wp_verify_nonce($_POST['wq_activate_nonce'], 'wq_activate_action')) {
        $key = trim($_POST['license_key']);
        if (strlen($key) >= 16) {
            update_option('wq_coupon_license_valid', true);
            update_option('wq_coupon_license_key', $key);
            update_option('wq_coupon_license_domain', $_SERVER['HTTP_HOST']);
            echo '<div class="notice notice-success"><p>✅ Ліцензію активовано!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Невірний ключ</p></div>';
        }
    }
    
    if (isset($_POST['wq_request_payment']) && isset($_POST['buyer_email']) && wp_verify_nonce($_POST['wq_request_nonce'], 'wq_request_action')) {
        $buyer_email = sanitize_email($_POST['buyer_email']);
        wp_mail(get_option('admin_email'), 'Запит на ліцензію WooQuick Coupon', "Email: $buyer_email");
        wp_mail($buyer_email, 'Інструкція з оплати WooQuick Coupon Pro', "Оплатіть 299 грн / $15 USD\nПісля оплати надішліть чек - отримаєте ключ.");
        echo '<div class="notice notice-success"><p>✅ Запит надіслано!</p></div>';
    }
});

// Головна сторінка плагіна
function wq_render_coupon_page() {
    $remaining = wq_get_remaining_free();
    $license_active = wq_is_license_active();
    $is_local = wq_is_local_server();
    ?>
    <div class="wrap" style="max-width: 700px; margin: auto; font-family: sans-serif; padding: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 2px solid #2271b1; margin-bottom: 20px;">
            <h1 style="color:#2271b1; margin:0;">🎫 WooQuick Coupon Pro</h1>
            <span style="background:#2271b1; color:white; padding:4px 12px; border-radius:20px;">v<?php echo WQ_COUPON_VERSION; ?></span>
        </div>
        
        <!-- Лічильник ліміту -->
        <div style="background: <?php echo $license_active || $is_local ? '#d4edda' : ($remaining > 0 ? '#fff3cd' : '#f8d7da'); ?>; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
            <?php if ($is_local): ?>
                🛠️ <strong>Режим розробника</strong> - локальний сервер, обмежень немає
            <?php elseif ($license_active): ?>
                ✅ <strong>PRO ВЕРСІЯ</strong> - необмежено купонів<br>
                <span style="font-size:12px;">Створено купонів: <?php echo wq_get_coupons_count(); ?></span>
            <?php elseif ($remaining > 0): ?>
                📊 <strong>Безкоштовна версія</strong><br>
                Створено: <?php echo wq_get_coupons_count(); ?> з <?php echo WQ_COUPON_FREE_LIMIT; ?> купонів<br>
                Залишилось: <strong><?php echo $remaining; ?></strong>
            <?php else: ?>
                🚫 <strong>Ліміт вичерпано!</strong><br>
                <a href="<?php echo admin_url('admin.php?page=' . WQ_COUPON_SLUG . '_license'); ?>" style="color:#d9534f;">Придбати ліцензію →</a>
            <?php endif; ?>
        </div>
        
        <div style="background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
            
            <!-- Звичайний купон -->
            <div style="margin-bottom: 30px;">
                <h2>🎫 Звичайний купон</h2>
                
                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Код купона:</label>
                    <input type="text" id="cp_code" placeholder="Напр: SALE2026" style="width:100%; padding:12px; border:2px solid #ddd; border-radius:10px;">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom: 15px;">
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Сума знижки:</label>
                        <input type="number" id="cp_amount" placeholder="0" style="width:100%; padding:12px; border:2px solid #ddd; border-radius:10px;">
                    </div>
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Тип:</label>
                        <select id="cp_type" style="width:100%; padding:12px; border:2px solid #ddd; border-radius:10px;">
                            <option value="percent">Відсоток (%)</option>
                            <option value="fixed_cart">Фіксована сума (грн)</option>
                        </select>
                    </div>
                </div>

                <button id="make_coupon" style="width:100%; height:50px; background:#00a32a; color:#fff; border:none; border-radius:10px; font-size:16px; font-weight:bold; cursor:pointer;">🚀 СТВОРИТИ КУПОН</button>
            </div>
            
            <hr style="margin: 20px 0;">
            
            <!-- Купон з питанням-відповіддю -->
            <div style="margin-bottom: 20px;">
                <h2>❓ Купон з питанням (Квіз)</h2>
                <p style="color:#666; margin-bottom:15px;">Користувач отримає знижку після правильної відповіді</p>
                
                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Код купона:</label>
                    <input type="text" id="quiz_code" placeholder="Напр: QUIZ10" style="width:100%; padding:12px; border:2px solid #ddd; border-radius:10px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Питання:</label>
                    <input type="text" id="quiz_question" placeholder="Напр: Крим це" style="width:100%; padding:12px; border:2px solid #ddd; border-radius:10px;">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Правильна відповідь:</label>
                    <input type="text" id="quiz_answer" placeholder="Напр: Україна" style="width:100%; padding:12px; border:2px solid #ddd; border-radius:10px;">
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom: 15px;">
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Сума знижки:</label>
                        <input type="number" id="quiz_amount" placeholder="10" style="width:100%; padding:12px; border:2px solid #ddd; border-radius:10px;">
                    </div>
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px;">Тип:</label>
                        <select id="quiz_type" style="width:100%; padding:12px; border:2px solid #ddd; border-radius:10px;">
                            <option value="percent">Відсоток (%)</option>
                            <option value="fixed_cart">Фіксована сума (грн)</option>
                        </select>
                    </div>
                </div>

                <button id="make_quiz_coupon" style="width:100%; height:50px; background:#1877F2; color:#fff; border:none; border-radius:10px; font-size:16px; font-weight:bold; cursor:pointer;">❓+🚀 СТВОРИТИ КУПОН З ПИТАННЯМ</button>
            </div>
            
            <div id="cp_status" style="margin-top:20px; text-align:center; font-weight:bold; padding:10px; border-radius:10px; display:none;"></div>
        </div>
        
        <div style="margin-top:20px; text-align:center; font-size:12px; color:#888;">
            <a href="<?php echo admin_url('admin.php?page=' . WQ_COUPON_SLUG . '_license'); ?>" style="color:#888;">🔑 Ліцензія</a>
        </div>
    </div>

    <script>
    // Показати статус
    function showStatus(message, isError = false) {
        const div = document.getElementById('cp_status');
        div.innerHTML = message;
        div.style.display = 'block';
        div.style.background = isError ? '#f8d7da' : '#d4edda';
        div.style.color = isError ? '#721c24' : '#155724';
        setTimeout(() => div.style.display = 'none', 3000);
    }

    // Звичайний купон
    document.getElementById('make_coupon').onclick = async function() {
        const code = document.getElementById('cp_code').value.trim();
        const amount = document.getElementById('cp_amount').value;
        const type = document.getElementById('cp_type').value;

        if (!code) return showStatus('Введіть код купона!', true);
        if (!amount) return showStatus('Введіть суму знижки!', true);

        this.disabled = true;
        this.innerText = "⏳ Створюю...";

        const fd = new FormData();
        fd.append('action', 'wq_create_coupon');
        fd.append('code', code);
        fd.append('amount', amount);
        fd.append('type', type);

        try {
            const resp = await fetch(ajaxurl, {method: 'POST', body: fd});
            const r = await resp.json();
            if(r.success) {
                showStatus('✅ Купон ' + code + ' створено!');
                document.getElementById('cp_code').value = '';
                document.getElementById('cp_amount').value = '';
            } else {
                showStatus('❌ ' + r.data, true);
            }
        } catch(e) { showStatus('❌ Помилка з\'єднання', true); }

        this.disabled = false;
        this.innerText = "🚀 СТВОРИТИ КУПОН";
    };

    // Купон з питанням
    document.getElementById('make_quiz_coupon').onclick = async function() {
        const code = document.getElementById('quiz_code').value.trim();
        const question = document.getElementById('quiz_question').value.trim();
        const answer = document.getElementById('quiz_answer').value.trim();
        const amount = document.getElementById('quiz_amount').value;
        const type = document.getElementById('quiz_type').value;

        if (!code) return showStatus('Введіть код купона!', true);
        if (!question) return showStatus('Введіть питання!', true);
        if (!answer) return showStatus('Введіть правильну відповідь!', true);
        if (!amount) return showStatus('Введіть суму знижки!', true);

        this.disabled = true;
        this.innerText = "⏳ Створюю...";

        const fd = new FormData();
        fd.append('action', 'wq_create_quiz_coupon');
        fd.append('code', code);
        fd.append('question', question);
        fd.append('answer', answer);
        fd.append('amount', amount);
        fd.append('type', type);

        try {
            const resp = await fetch(ajaxurl, {method: 'POST', body: fd});
            const r = await resp.json();
            if(r.success) {
                showStatus('✅ Купон-квіз "' + code + '" створено!');
                document.getElementById('quiz_code').value = '';
                document.getElementById('quiz_question').value = '';
                document.getElementById('quiz_answer').value = '';
                document.getElementById('quiz_amount').value = '';
            } else {
                showStatus('❌ ' + r.data, true);
            }
        } catch(e) { showStatus('❌ Помилка з\'єднання', true); }

        this.disabled = false;
        this.innerText = "❓+🚀 СТВОРИТИ КУПОН З ПИТАННЯМ";
    };
    </script>
    <?php
}

// Створення звичайного купона
add_action('wp_ajax_wq_create_coupon', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Немає прав');
    if (!wq_can_create_coupon()) wp_send_json_error('Ліміт безкоштовної версії вичерпано (максимум ' . WQ_COUPON_FREE_LIMIT . ' купонів)');

    $code   = sanitize_text_field($_POST['code']);
    $amount = sanitize_text_field($_POST['amount']);
    $type   = sanitize_text_field($_POST['type']);

    if (empty($code)) wp_send_json_error('Код купона обов\'язковий');
    if (!is_numeric($amount) || $amount <= 0) wp_send_json_error('Сума знижки має бути більше 0');

    $coupon_id = wp_insert_post([
        'post_title'   => $code,
        'post_status'  => 'publish',
        'post_type'    => 'shop_coupon'
    ]);

    if ($coupon_id) {
        update_post_meta($coupon_id, 'discount_type', $type);
        update_post_meta($coupon_id, 'coupon_amount', $amount);
        update_post_meta($coupon_id, 'individual_use', 'yes');
        wp_send_json_success();
    } else {
        wp_send_json_error('Помилка створення');
    }
});

// Створення купона з питанням-відповіддю
add_action('wp_ajax_wq_create_quiz_coupon', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Немає прав');
    if (!wq_can_create_coupon()) wp_send_json_error('Ліміт безкоштовної версії вичерпано');

    $code     = sanitize_text_field($_POST['code']);
    $question = sanitize_text_field($_POST['question']);
    $answer   = sanitize_text_field($_POST['answer']);
    $amount   = sanitize_text_field($_POST['amount']);
    $type     = sanitize_text_field($_POST['type']);

    if (empty($code)) wp_send_json_error('Код купона обов\'язковий');
    if (empty($question)) wp_send_json_error('Питання обов\'язкове');
    if (empty($answer)) wp_send_json_error('Відповідь обов\'язкова');
    if (!is_numeric($amount) || $amount <= 0) wp_send_json_error('Сума знижки має бути більше 0');

    $coupon_id = wp_insert_post([
        'post_title'   => $code,
        'post_status'  => 'publish',
        'post_type'    => 'shop_coupon'
    ]);

    if ($coupon_id) {
        update_post_meta($coupon_id, 'discount_type', $type);
        update_post_meta($coupon_id, 'coupon_amount', $amount);
        update_post_meta($coupon_id, 'individual_use', 'yes');
        
        // Зберігаємо питання та відповідь
        update_post_meta($coupon_id, '_wq_quiz_question', $question);
        update_post_meta($coupon_id, '_wq_quiz_answer', strtolower(trim($answer)));
        
        wp_send_json_success();
    } else {
        wp_send_json_error('Помилка створення');
    }
});

// Вивід питання на сторінці кошика/оформлення замовлення
add_action('woocommerce_before_cart', function() {
    wq_render_quiz_if_needed();
});
add_action('woocommerce_before_checkout_form', function() {
    wq_render_quiz_if_needed();
});

function wq_render_quiz_if_needed() {
    // Перевіряємо чи є активний купон з квізом
    $coupons = WC()->cart->get_applied_coupons();
    foreach ($coupons as $coupon_code) {
        $coupon = new WC_Coupon($coupon_code);
        $coupon_id = $coupon->get_id();
        $question = get_post_meta($coupon_id, '_wq_quiz_question', true);
        
        if ($question && !WC()->session->get('wq_quiz_passed_' . $coupon_code)) {
            ?>
            <div id="wq_quiz_modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); display:flex; justify-content:center; align-items:center; z-index:99999;">
                <div style="background:white; padding:30px; border-radius:15px; max-width:500px; width:90%; text-align:center;">
                    <h2>❓ Підтвердження купона</h2>
                    <p style="font-size:18px;"><strong><?php echo esc_html($question); ?></strong></p>
                    <input type="text" id="wq_quiz_answer" placeholder="Ваша відповідь" style="width:100%; padding:12px; margin:15px 0; border:2px solid #ddd; border-radius:10px;">
                    <button id="wq_submit_quiz" data-coupon="<?php echo esc_attr($coupon_code); ?>" style="background:#1877F2; color:white; padding:12px 30px; border:none; border-radius:10px; cursor:pointer;">Перевірити</button>
                    <p style="margin-top:15px; font-size:12px; color:#888;">Підказка: відповідь не чутлива до регістру</p>
                </div>
            </div>
            <script>
            document.getElementById('wq_submit_quiz').onclick = function() {
                const answer = document.getElementById('wq_quiz_answer').value.trim().toLowerCase();
                const coupon = this.dataset.coupon;
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'wq_check_quiz_answer',
                        coupon: coupon,
                        answer: answer
                    })
                }).then(r => r.json()).then(res => {
                    if(res.success) {
                        location.reload();
                    } else {
                        alert('❌ Відповідь неправильна! Спробуйте ще раз.');
                    }
                });
            };
            </script>
            <?php
            break;
        }
    }
}

// Перевірка відповіді на квіз
add_action('wp_ajax_wq_check_quiz_answer', function() {
    $coupon_code = sanitize_text_field($_POST['coupon']);
    $user_answer = strtolower(trim(sanitize_text_field($_POST['answer'])));
    
    $coupon = new WC_Coupon($coupon_code);
    $coupon_id = $coupon->get_id();
    $correct_answer = strtolower(trim(get_post_meta($coupon_id, '_wq_quiz_answer', true)));
    
    if ($user_answer === $correct_answer) {
        WC()->session->set('wq_quiz_passed_' . $coupon_code, true);
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
});
add_action('wp_ajax_nopriv_wq_check_quiz_answer', function() {
    wq_check_quiz_answer();
});
?>