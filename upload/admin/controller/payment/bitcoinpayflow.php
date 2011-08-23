<?php
class ControllerPaymentBitcoinPayFlow extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('payment/bitcoinpayflow');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('bitcoinpayflow', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
		}

		$this->data['heading_title'] = $this->language->get('heading_title');

		$this->data['text_enabled'] = $this->language->get('text_enabled');
		$this->data['text_disabled'] = $this->language->get('text_disabled');
		$this->data['text_all_zones'] = $this->language->get('text_all_zones');
		$this->data['text_yes'] = $this->language->get('text_yes');
		$this->data['text_no'] = $this->language->get('text_no');
		$this->data['text_authorization'] = $this->language->get('text_authorization');
		$this->data['text_sale'] = $this->language->get('text_sale');

		$this->data['entry_auth_token'] = $this->language->get('entry_auth_token');
		$this->data['entry_paid_status'] = $this->language->get('entry_paid_status');
		$this->data['entry_pending_status'] = $this->language->get('entry_pending_status');
		$this->data['entry_processed_status'] = $this->language->get('entry_processed_status');
		$this->data['entry_status'] = $this->language->get('entry_status');
		$this->data['entry_sort_order'] = $this->language->get('entry_sort_order');

		$this->data['button_save'] = $this->language->get('button_save');
		$this->data['button_cancel'] = $this->language->get('button_cancel');

 		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		if (isset($this->error['auth_token'])) {
                        $this->data['error_auth_token'] = $this->error['auth_token'];
                } else {
                        $this->data['error_auth_token'] = '';
                }

		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),      		
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_payment'),
			'href'      => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('payment/bitcoinpayflow', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);

		$this->data['action'] = $this->url->link('payment/bitcoinpayflow', 'token=' . $this->session->data['token'], 'SSL');

		$this->data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');


		if (isset($this->request->post['bitcoinpayflow_auth_token'])) {
			$this->data['bitcoinpayflow_auth_token'] = $this->request->post['bitcoinpayflow_auth_token'];
		} else {
			$this->data['bitcoinpayflow_auth_token'] = $this->config->get('bitcoinpayflow_auth_token');
		}

		if (isset($this->request->post['bitcoinpayflow_paid_status_id'])) {
			$this->data['bitcoinpayflow_paid_status_id'] = $this->request->post['bitcoinpayflow_paid_status_id'];
		} else {
			$this->data['bitcoinpayflow_paid_status_id'] = $this->config->get('bitcoinpayflow_paid_status_id');
		}	
		
		if (isset($this->request->post['bitcoinpayflow_pending_status_id'])) {
			$this->data['bitcoinpayflow_pending_status_id'] = $this->request->post['bitcoinpayflow_pending_status_id'];
		} else {
			$this->data['bitcoinpayflow_pending_status_id'] = $this->config->get('bitcoinpayflow_pending_status_id');
		}
									
		if (isset($this->request->post['bitcoinpayflow_processed_status_id'])) {
			$this->data['bitcoinpayflow_processed_status_id'] = $this->request->post['bitcoinpayflow_processed_status_id'];
		} else {
			$this->data['bitcoinpayflow_processed_status_id'] = $this->config->get('bitcoinpayflow_processed_status_id');
		}

		$this->load->model('localisation/order_status');

		$this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['bitcoinpayflow_status'])) {
			$this->data['bitcoinpayflow_status'] = $this->request->post['bitcoinpayflow_status'];
		} else {
			$this->data['bitcoinpayflow_status'] = $this->config->get('bitcoinpayflow_status');
		}
		
		if (isset($this->request->post['bitcoinpayflow_sort_order'])) {
			$this->data['bitcoinpayflow_sort_order'] = $this->request->post['bitcoinpayflow_sort_order'];
		} else {
			$this->data['bitcoinpayflow_sort_order'] = $this->config->get('bitcoinpayflow_sort_order');
		}

		$this->template = 'payment/bitcoinpayflow.tpl';
		$this->children = array(
			'common/header',
			'common/footer'
		);

		$this->response->setOutput($this->render());
	}

	private function validate() {
		if (!$this->user->hasPermission('modify', 'payment/bitcoinpayflow')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

                if (!$this->request->post['bitcoinpayflow_auth_token']) {
                        $this->error['auth_token'] = $this->language->get('error_auth_token');
                }

		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}
}
?>
