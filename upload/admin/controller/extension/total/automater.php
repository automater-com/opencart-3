<?php
class ControllerExtensionTotalAutomater extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->model('setting/setting');
		$this->load->model('localisation/order_status');
		$this->load->model('extension/total/automater');
        $this->load->language('extension/total/automater');

        $this->document->setTitle($this->language->get('heading_title'));

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {

            if (isSet($this->request->post['synchro'])) {
                $this->model_extension_total_automater->stockSynchronize();
            } else {
                $this->model_setting_setting->editSetting('total_automater', $this->request->post);

                $this->session->data['success'] = $this->language->get('text_success');
            }

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total', true));
        }

        $data = array();

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/total/automater', 'user_token=' . $this->session->data['user_token'], true)
        );

        $module_setting = $this->model_setting_setting->getSetting('total_automater');

		$data['total_automater_status'] =
            $this->config->get('total_automater_status')
            	? $this->config->get('total_automater_status')
                : 0;

        $data['total_automater_api_key'] =
            $this->config->get('total_automater_api_key')
                ? $this->config->get('total_automater_api_key')
                : '';

        $data['total_automater_api_secret'] =
        	$this->config->get('total_automater_api_secret')
                ? $this->config->get('total_automater_api_secret')
                : '';

		$data['total_automater_order_status'] =
			$this->config->get('total_automater_order_status')
				? $this->config->get('total_automater_order_status')
				: '';

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['action']['cancel'] = $this->url->link('marketplace/extension', 'user_token='.$this->session->data['user_token'].'&type=total');
        $data['action']['save'] = "";

        $data['error'] = $this->error;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/total/automater', $data));
    }

    public function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/total/automater')) {
            $this->error['permission'] = true;
            return false;
        }

        if (!utf8_strlen($this->request->post['total_automater_api_key'])) {
            $this->error['api_secret'] = true;
        }

        if (!utf8_strlen($this->request->post['total_automater_api_secret'])) {
            $this->error['api_secret'] = true;
        }

        return empty($this->error);
    }


    public function install()
    {
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD `automater_cart_id` VARCHAR(255) NULL");
        $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD `automater_payment` int(1) NULL");
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "product` ADD `automater_product_id` VARCHAR(255) NULL");

        $this->load->model('setting/event');
        $this->model_setting_event->addEvent('automater_create_transaction', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/total/automater/eventCreateTransaction');
    }

    public function uninstall()
    {
		$this->db->query("ALTER TABLE `" . DB_PREFIX . "order` DROP COLUMN `automater_cart_id`");
        $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` DROP COLUMN `automater_payment`");
        $this->db->query("ALTER TABLE `" . DB_PREFIX . "product` DROP COLUMN `automater_product_id`");

        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('automater_create_transaction');
    }
}
