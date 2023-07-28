<?php
/*
Plugin Name: RozetkaPay Gateway
Description: Custom payment gateway for RozetkaPay + return client money from API
Version: 2.0
Author: RozetkaPay
*/

    // Підключення модуля до WooCommerce
    add_action('plugins_loaded', 'init_rozetkapay_gateway');
    function init_rozetkapay_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once 'rozetkapay_gateway_class.php'; // Файл, який містить клас платіжного шлюзу
    add_filter('woocommerce_payment_gateways', 'add_rozetkapay_gateway');
    }

    function add_rozetkapay_gateway($gateways) {
    $gateways[] = 'WC_RozetkaPay_Gateway';
    return $gateways;
    }
    // Додавання кнопки після статусу замовлення на сторінці всіх замовлень
    add_filter('manage_edit-shop_order_columns', 'add_custom_button_to_orders_list_column');
    add_action('manage_shop_order_posts_custom_column', 'add_custom_button_to_orders_list', 10, 2);

    function add_custom_button_to_orders_list_column($columns) {
    global $post_type;
    
    // Перевірка, що поточна сторінка є сторінкою замовлень
    if ($post_type === 'shop_order') {
    $columns['custom_button'] = 'Кошти';
    }
    return $columns;
    }

    function add_custom_button_to_orders_list($column, $post_id) {
    global $post_type;
        $order = wc_get_order($post_id);
        $order_total = $order->get_total();
        $order_id = $order->get_id();
    // Перевірка, що поточна сторінка є сторінкою замовлень
    if ($post_type === 'shop_order') {
    if ($column === 'custom_button') {
        echo '<button type="button" class="button custom-order-button-'.$order_id.'">Повернути кошти</button>';
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.custom-order-button-<?=$order_id;?>').click(function() {
                var orderId = <?php echo $order_id; ?>;
                var amount = <?php echo $order_total; ?>; // Отримати суму замовлення з JSON-даних

                $.ajax({
                    url: '<?php echo plugins_url("refund.php", __FILE__); ?>',
                    type: 'POST',
                    data: {
                        order_id: orderId,
                        amount: amount
                    },
                   success: function(response) {;
                     // Обробка успішного виконання запиту
                                    alert(response);
                                    location.reload(); // Оновлення сторінки

                    },
                    error: function(xhr, status, error) {
                        // Обробка помилок запиту
                        alert('Під час виконання запиту сталася помилка. Будь ласка, спробуйте знову.');
                    }
                });
            });
        });
    </script>
    <?
    }
        }
            }

    add_action('woocommerce_order_item_add_action_buttons', 'wc_order_item_add_action_buttons_callback', 10, 1);
    function wc_order_item_add_action_buttons_callback($order) {
    $label = esc_html__('Повернути кошти', 'woocommerce');
    $slug = 'custom';
    $order = json_decode($order, true);
    ?>
    <button type="button" class="button <?php echo $order['id']; ?>-items refund-button"><?php echo $label; ?></button>

    <script>
        jQuery(document).ready(function($) {
            $('.refund-button').click(function() {
                var orderId = <?php echo $order['id']; ?>;
                var amount = <?php echo $order['total']; ?>; // Отримати суму замовлення з JSON-даних

$.ajax({
                    url: '<?php echo plugins_url("refund.php", __FILE__); ?>',
                    type: 'POST',
                    data: {
                        order_id: orderId,
                        amount: amount
                    },
                    success: function(response) {
                                    alert(response);

                    },
                    error: function(xhr, status, error) {
                        // Обробка помилок запиту
                        alert('Під час виконання запиту сталася помилка. Будь ласка, спробуйте знову.');
                    }
                });
            });
        }); 
    </script>
    <?php
}