<?php
require_once  DIR_SYSTEM . 'library/automater/autoload.php';

class ModelExtensionTotalAutomater extends Model
{
    public function __construct($params)
    {
        parent::__construct($params);

        $this->_automater = new \AutomaterSDK\Client\Client($this->config->get('total_automater_api_key'), $this->config->get('total_automater_api_secret'));
    }

    public function stockSynchronize()
    {
        $products = $this->getAutomaterAll();

        if ($products) {
            foreach ($products as $product) {
                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE automater_product_id = '" . (int) $product->getId() . "'");

                $productid = $query->row['product_id'];

                if ($productid) {
                    $qty = $product->getAvailableCodes();

                    $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = '" . $qty . "' WHERE product_id = '" . (int)$productid . "'");
                }

            }
        }
    }


    public function getAutomaterProducts()
    {
        $products = $this->getAutomaterAll();

        $data = [];
        foreach ($products as $product) {
            $data[] = [
                'value' => $product->getId(),
                'label' => $product->getName()
            ];
        }

        return $data;
    }

    public function getAutomaterAll() {
        $productsRequest = new \AutomaterSDK\Request\ProductsRequest();
        $productsRequest->setType(\AutomaterSDK\Request\ProductsRequest::TYPE_SHOP);
        $productsRequest->setStatus(\AutomaterSDK\Request\ProductsRequest::STATUS_ACTIVE);
        $productsRequest->setPage(1);
        $productsRequest->setLimit(100);

        $currentPage = 1;
        $result = false;
        $data = [];

        while (empty($result) || $result->getCurrentPage() * $result->getCurrentCount() < $result->getRecordsCount()) {
            try {
                $result = $this->_automater->getProducts($productsRequest);
            } catch (\AutomaterSDK\Exception\ApiException $apiException) {
                $logger = new Log('automater.log');
                $logger->write(sprintf("Automater: problem with getting products from API: [%s] %s", $apiException->getCode(), $apiException->getMessage()), 3);
                return false;
            } catch (\Exception $exception) {
                $logger = new Log('automater.log');
                $logger->write(sprintf("Automater (global exception): problem with getting products from API: %s", $exception->getMessage()), 3);
                return false;
            }

            $data = array_merge($data, $result->getData()->toArray());

            $currentPage++;
            $productsRequest->setPage($currentPage);
        }

        return $data;
    }
}
