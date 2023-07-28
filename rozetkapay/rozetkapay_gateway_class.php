<?
    class WC_RozetkaPay_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'rozetkapay_payment';
        $this->method_title = 'RozetkaPay';
        $this->method_description = 'Оплата через RozetkaPay';
        $this->supports = array(
            'products',
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->logo = $this->get_option('logo');


        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        // Додати хук для перевірки статусу платежу
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'rozetkapay_payment_status_check'));
     }


    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Включити/виключити',
                'type' => 'checkbox',
                'label' => 'Включити RozetkaPay',
                'default' => 'yes',
            ),
            'title' => array(
                'title' => 'Назва',
                'type' => 'text',
                'description' => 'Назва способу оплати, яку побачить користувач.',
                'default' => 'RozetkaPay',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Опис',
                'type' => 'textarea',
                'description' => 'Опис способу оплати, який побачить користувач.',
                'default' => 'Оплата через RozetkaPay',
                'desc_tip' => true,
            ),
            'username' => array(
                'title' => 'Логін',
                'type' => 'text',
                'description' => 'Введіть ваш логін для авторизації в RozetkaPay.',
                'default' => '',
                'desc_tip' => true,
            ),
            'password' => array(
                'title' => 'Пароль',
                'type' => 'text',
                'description' => 'Введіть ваш пароль для авторизації в RozetkaPay.',
                'default' => '',
                'desc_tip' => true,
            ),
            'logo' => array(
                'title' => 'Логотип',
                'type' => 'text',
                'description' => 'URL логотипу платіжної системи.',
                'default' => '',
                'desc_tip' => true,
            ),
            'return_link' => array(
                'title' => 'URL повернення',
                'type' => 'text',
                'description' => 'URL повернення після успішної оплати.',
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Отримуємо суму замовлення
        $amount = $order->get_total();

        // Авторизаційні дані
        $username = $this->get_option('username');
        $password = $this->get_option('password');
        $return_link = $this->get_option('return_link');

        // URL платіжної системи
        $url = "https://api.rozetkapay.com/api/payments/v1/new";

        // Отримуємо URL логотипу
        $logo = $this->get_option('logo');

        // Створюємо запит до платіжної системи
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $auth = $this->generate_basic_auth_header($username, $password);
        $headers = array(
            $auth,
            'Content-Type: application/json',
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // Отримуємо деталі корзини
        $items = $order->get_items();
        $cart = array();
        foreach ($items as $item) {
            $product = $item->get_product();
            $cart[] = array(
                'name' => $product->get_name(),
                'quantity' => $item->get_quantity(),
                'var_amount' => $product->get_price(),
            );
        }

        $order_url = $this->get_return_url($order); // Отримати URL-адресу сторінки замовлення

        $data = array(
            'amount' => $amount,
            'currency' => 'UAH',
            'external_id' => 'order_' . $order_id,
            'mode' => 'hosted',
            'confirm' => true,
            'description' => 'Номер замовлення:' . $order_id,
            'result_url' => $order_url,
        );

        $data = json_encode($data);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        // Вимкнути перевірку SSL (тільки для налаштування розробки)
        // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        // Виконуємо запит до платіжної системи
        $resp = curl_exec($curl);
        curl_close($curl);

        // Розшифровуємо отримані дані
        $decoded_data = json_decode($resp, true);

        if ($decoded_data === null) {
            // Обробка помилки розшифрування JSON
            wc_add_notice('Помилка розшифрування JSON', 'error');
            return;
        }

        // Отримуємо URL переадресації
        $redirect_url = $decoded_data['action']['value'];

        // Оновлюємо статус замовлення
        $order->update_status('success', 'Оплачено через RozetkaPay');

        // Перенаправляємо користувача на сторінку оплати
        return array(
            'result' => 'success',
            'redirect' => $redirect_url,
        );
    }

    private function generate_basic_auth_header($username, $password) {
        $credentials = base64_encode($username . ':' . $password);
        $header = 'Authorization: Basic ' . $credentials;
        return $header;
    }

    public function rozetkapay_payment_status_check($order_id) {
        // Отримуємо дані замовлення
        $order = wc_get_order($order_id);
        $external_id = 'order_' . $order_id;

        // Авторизаційні дані
        $username = $this->get_option('username');
        $password = $this->get_option('password');

        // URL платіжної системи для перевірки статусу платежу
        $url = "https://api.rozetkapay.com/api/payments/v1/info?external_id=$external_id";
        // Створюємо запит до платіжної системи
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $auth = $this->generate_basic_auth_header($username, $password);
        $headers = array(
            $auth,
            'Content-Type: application/json',
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // Виконуємо запит до платіжної системи
        $resp = curl_exec($curl);
        curl_close($curl);
        // Розшифровуємо отримані дані
        $decoded_data = json_decode($resp, true);

        if ($decoded_data === null) {
            // Обробка помилки розшифрування JSON
            wc_add_notice('Помилка розшифрування JSON', 'error');
            return;
        }

        // Отримуємо статус платежу
        $status = $decoded_data['purchase_details'][0]['status'];

        // Оновлюємо статус замовлення в залежності від статусу платежу
        if ($status === 'success') {
            $order->update_status('processing', 'Оплачено через RozetkaPay');
        } elseif ($status === 'failure') {
            $order->update_status('failed', 'Платіж не пройшов');
        } elseif ($status === 'pending') {
            $order->update_status('on-hold', 'Очікується оплата через RozetkaPay');
        }

    }
}
    
