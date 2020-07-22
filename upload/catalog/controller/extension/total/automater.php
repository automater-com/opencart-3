<?php
require_once  DIR_SYSTEM . 'library/automater/autoload.php';

class ControllerExtensionTotalAutomater extends Controller
{
    public function __construct($params)
    {
        parent::__construct($params);

        $this->_automater = new \AutomaterSDK\Client\Client($this->config->get('total_automater_api_key'), $this->config->get('total_automater_api_secret'));
    }

    public function isEnabled()
    {
        $enabled =
            $this->config->get('total_automater_status')
                ? $this->config->get('total_automater_status')
                : 0;

        return $enabled;
    }

    public function getLanguageCode()
    {
        $this->load->model('localisation/language');

        $languageId = $this->config->get('config_language_id');
        $languageCo = $this->model_localisation_language->getLanguage($languageId);

        $languageCode = explode('-', $languageCo['code'])[0];

        return $languageCode;
    }

    public function eventCreateTransaction(&$route, &$args, &$data)
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $order_id = $args[0];

        $this->load->model('checkout/order');
        $this->load->model('catalog/product');

        $order  = $this->model_checkout_order->getOrder($order_id);
        $products = $this->model_checkout_order->getOrderProducts($order_id);

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order WHERE order_id = '" . (int)$order_id . "'");

        if ($query->row['automater_cart_id']) {

            if ($order['order_status_id'] == $this->config->get('total_automater_order_status') && $query->row['automater_payment'] == false) {
                $this->payTransaction($order_id);
            }

            return false;
        }

        $productsArray = [];

        foreach ($products as $product) {
            try {
                $productInsight = $this->model_catalog_product->getProduct($product['product_id']);

                $productId = $product['product_id'];
                $qty = $product['quantity'];

                $productQuery = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product['product_id'] . "'");

                $automaterProductId = $productQuery->row['automater_product_id'];

                if (empty($automaterProductId) || $automaterProductId == 0) {
                    continue;
                } else {
                    if (!isset($productsArray[$automaterProductId])) {
                        $productsArray[$automaterProductId] = [
                                  'quantity' => 0,
                                  'price' => null
                              ];
                    }

                    $productsArray[$automaterProductId]['quantity'] = $productsArray[$automaterProductId]['quantity'] + $qty;
                    $productsArray[$automaterProductId]['price'] = $product['total'];
                }
            } catch (Exception $e) {
            }
        }

        $transactionProducts = [];
        foreach ($productsArray as $automaterProductId => $data) {
            $transactionProduct = new \AutomaterSDK\Request\Entity\TransactionProduct();
            $transactionProduct->setId($automaterProductId);
            $transactionProduct->setQuantity($data['quantity']);
            $transactionProduct->setCurrency($order['currency_code']);
            $transactionProduct->setPrice($data['price']);
            $transactionProducts[] = $transactionProduct;
        }

        $automaterLanguage = $this->getLanguageCode();

        if (sizeof($productsArray)) {
            $transactionRequest = new \AutomaterSDK\Request\TransactionRequest();
            $transactionRequest->setEmail($order['email']);
            $transactionRequest->setLanguage($automaterLanguage);
            $transactionRequest->setSendStatusEmail(1 ? \AutomaterSDK\Request\TransactionRequest::SEND_STATUS_EMAIL_TRUE : \AutomaterSDK\Request\TransactionRequest::SEND_STATUS_EMAIL_FALSE); //TODO automater also
            $transactionRequest->setCustom(sprintf("Order from %s, shop order id: %s.", $this->config->get('config_name'), $order['order_id']));
            $transactionRequest->setProducts($transactionProducts);

            try {
                $transactionResponse = $this->_automater->createTransaction($transactionRequest);
                $automaterCartId = $transactionResponse->getCartId();

                $this->db->query("UPDATE " . DB_PREFIX . "order SET automater_cart_id = '" . $automaterCartId . "' WHERE order_id = '" . (int)$order_id . "'");

                if ($order['order_status_id'] == $this->config->get('total_automater_order_status')) {
                    $this->payTransaction($order_id);
                }

            } catch (\AutomaterSDK\Exception\ApiException $apiException) {
                $logger = new \Log('automater.log');
                $logger->write(sprintf("Automater: problem with creating transaction for order number %s: [%s] %s", $order['order_id'], $apiException->getCode(), $apiException->getMessage()), 3);
            } catch (\Exception $exception) {
                $logger = new \Log('automater.log');
                $logger->write(sprintf("Automater (global exception): problem with creating transaction for order number %s: %s", $order['order_id'], $exception->getMessage()), 3);
            }
        }
    }

    public function payTransaction($order_id)
    {
        $order  = $this->model_checkout_order->getOrder($order_id);

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order WHERE order_id = '" . (int) $order_id . "'");

        $automaterCartId = $query->row['automater_cart_id'];

        if (!$automaterCartId) {
            return false;
        }

        try {
            $totalamount = $order['total'];

            $paymentRequest = new \AutomaterSDK\Request\PaymentRequest();
            $paymentRequest->setPaymentId('OC3-' . $order_id);
            $paymentRequest->setCurrency($order['currency_code']);
            $paymentRequest->setAmount($totalamount);
            $paymentRequest->setDescription('shop payment');

            try {
                $this->_automater->postPayment($automaterCartId, $paymentRequest);

                $this->db->query("UPDATE " . DB_PREFIX . "order SET automater_payment = 1 WHERE order_id = '" . (int)$order_id . "'");

            } catch (\AutomaterSDK\Exception\ApiException $apiException) {
                $logger = new Log('automater.log');
                $logger->write(sprintf("Automater: problem with post payment for transaction for order number %s: [%s] %s", $order_id, $apiException->getCode(), $apiException->getMessage()), 3);
            } catch (\Exception $exception) {
                $logger = new Log('automater.log');
                $logger->write(sprintf("Automater (global exception): problem with post payment for transaction for order number %s: %s", $order_id, $exception->getMessage()), 3);
            }

        } catch (\Exception $e) {
        }
    }
}
