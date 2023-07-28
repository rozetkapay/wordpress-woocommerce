<?
// Підключення WordPress
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Підключення файлу з класом платіжного шлюзу
require_once('rozetkapay_gateway_class.php');

// Створення об'єкта платіжного шлюзу
$gateway = new WC_RozetkaPay_Gateway();

$url = 'https://api.rozetkapay.com/api/payments/v1/refund';
$order_id = $_POST['order_id'];
$amount = $_POST['amount'];
$data = array(
	'external_id' => 'order_' . $order_id,
    'amount' => $amount,
    'currency' => 'UAH'
);

$username = $gateway->get_option('username');
$password = $gateway->get_option('password');

$credentials = base64_encode($username . ':' . $password);
$headers = 'Authorization: Basic ' . $credentials;
$headers = array(
    'Content-Type: application/json',
    ''.$headers.''
);

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$datas = json_decode($response, true);

if ($response === false) {
    echo 'Error: ' . curl_error($ch);
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code === 200) {
        // Запит був успішним
        echo $datas['message'];
        // Додайте код для обробки результату повернення коштів
        // Отримати об'єкт замовлення за його ID
        $order = wc_get_order($order_id);

        // Встановити статус "Повернуто" для замовлення
        $order->update_status('refunded', 'Повернення оброблено');

        // Зберегти зміни
        $order->save();
    } else {
        // Запит завершився з помилкою
        echo 'Виникла помилка, код помилки: ' .$datas['message'];
    }
}

curl_close($ch);