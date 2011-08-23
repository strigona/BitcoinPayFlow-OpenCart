<?php
require_once(DIR_SYSTEM . 'payment/bitcoinpayflow.php');
class ControllerPaymentBitcoinPayFlow extends Controller {
	protected function index() {
		$this->language->load('payment/bitcoinpayflow');
		
		$this->data['text_instruction'] = $this->language->get('text_instruction');
		$this->data['text_payment'] = $this->language->get('text_payment');
		
		$this->data['button_confirm'] = $this->language->get('button_confirm');
		
		$this->data['continue'] = $this->url->link('checkout/success');
		
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/bitcoinpayflow.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/payment/bitcoinpayflow.tpl';
		} else {
			$this->template = 'default/template/payment/bitcoinpayflow.tpl';
		}	
		
		$this->render(); 
	}

	public function callback() {
		$this->language->load('payment/bitcoinpayflow');

		$this->load->model('checkout/order');

		// Check if required data exists
		if (array_key_exists('Payment_Notification', $this->request->post) && (array_key_exists('amount', $this->request->post['Payment_Notification']) && 
			array_key_exists('bitcoin_address', $this->request->post['Payment_Notification']) && array_key_exists('category', $this->request->post['Payment_Notification']) && 
			array_key_exists('foreign_order_id', $this->request->post['Payment_Notification']) && array_key_exists('number_of_confirmations', $this->request->post['Payment_Notification']) && 
			array_key_exists('order_status', $this->request->post['Payment_Notification']) && array_key_exists('transaction_fee', $this->request->post['Payment_Notification']) && 
			array_key_exists('transaction_timestamp', $this->request->post['Payment_Notification']) && array_key_exists('signature', $this->request->post['Payment_Notification']))) {

			// Create parameter string
			$secureString = 'amount=' . $this->request->post['Payment_Notification']['amount'] . 'bitcoin_address=' . $this->request->post['Payment_Notification']['bitcoin_address']
				. 'category=' . $this->request->post['Payment_Notification']['category'] . 'foreign_order_id=' . $this->request->post['Payment_Notification']['foreign_order_id']
				. 'number_of_confirmations=' . $this->request->post['Payment_Notification']['number_of_confirmations'] . 'order_status='
	       			. $this->request->post['Payment_Notification']['order_status'] . 'transaction_fee=' . $this->request->post['Payment_Notification']['transaction_fee']
				. 'transaction_timestamp=' . $this->request->post['Payment_Notification']['transaction_timestamp'];

			// Hash the auth_token + parameter string
			$secureString = hash('sha256',$this->config->get('bitcoinpayflow_auth_token') . $secureString);

			// Check signature
			if ($secureString == $this->request->post['Payment_Notification']['signature']){
				// Success: A payment has arrived
				$order_id = $this->request->post['Payment_Notification']['foreign_order_id'];
				$amount = $this->request->post['Payment_Notification']['amount'];
				$order_status_id = $this->config->get('bitcoinpayflow_pending_status_id');

				// TODO: Remove these lines (testing purposes)
				$order_id = 49;
				$this->request->post['Payment_Notification']['order_status'] = "satisfied";
				// END TEST

				
				// Update total_paid
				$this->db->query("UPDATE `" . DB_PREFIX . "order` SET total_paid = (total_paid + " . (double)$amount . ") WHERE order_id = '" . (int)$order_id . "'");
				$order_info = $this->model_checkout_order->getOrder($order_id);
				


				if (!$order_info['order_status_id'] && $order_info['total'] <= $order_info['total_paid']) {
					// New order has been fully paid for
					// Confirm + mark PAID
					
                                        $order_status_id = $this->config->get('bitcoinpayflow_paid_status_id');
					$comment = $this->language->get('text_payment_confirm_complete');
					$this->model_checkout_order->confirm($order_id, $order_status_id, $comment);

				} else if ($order_info['total'] <= $order_info['total_paid']) {
					// Confirmed order has been fully paid for
					// Update + mark PAID + Email store
					
                                        $order_status_id = $this->config->get('bitcoinpayflow_paid_status_id');
                                        $comment = $this->language->get('text_payment_partial_complete');
                                        $this->model_checkout_order->update($order_id, $order_status_id, $comment, true);
					

					// Notify the store of payment completion

                                        // Load required language and order data
                                        $this->language->load('mail/order');
                                        $order_info = $this->model_checkout_order->getOrder($order_id);

                                        $order_status_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE order_status_id = '" .
                                                (int)$order_status_id . "' AND language_id = '" . (int)$order_info['language_id'] . "'");

                                        if ($order_status_query->num_rows) {
                                                $order_status = $order_status_query->row['name'];
                                        } else {
                                                $order_status = '';
                                        }

                                        $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");
                                        $order_total_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$order_id . "' ORDER BY sort_order ASC");
                                        $order_download_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_download WHERE order_id = '" . (int)$order_id . "'");


                                        $subject = sprintf($this->language->get('text_update_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'), $order_id);

                                        // Text
                                        $text  = $this->language->get('text_payment_complete_mail') . "\n\n";
                                        $text .= $this->language->get('text_new_order_id') . ' ' . $order_id . "\n";
                                        $text .= $this->language->get('text_new_date_added') . ' ' . date($this->language->get('date_format_short'), strtotime($order_info['date_added'])) . "\n";
                                        $text .= $this->language->get('text_new_order_status') . ' ' . $order_status . "\n\n";
                                        $text .= $this->language->get('text_new_products') . "\n";

                                        foreach ($order_product_query->rows as $result) {
                                                $text .= $result['quantity'] . 'x ' . $result['name'] . ' (' . $result['model'] . ') ' . html_entity_decode($this->currency->format($result['total'],
                                                        $order_info['currency_code'], $order_info['currency_value']), ENT_NOQUOTES, 'UTF-8') . "\n";

                                                $order_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '"
                                                        . $result['order_product_id'] . "'");

                                                foreach ($order_option_query->rows as $option) {
                                                        $text .= chr(9) . '-' . $option['name'] .' '. (strlen($option['value']) > 20 ? substr($option['value'], 0, 20) . '..' : $option['value']) . "\n";
                                                }
                                        }

                                        $text .= "\n";

                                        $text.= $this->language->get('text_new_order_total') . "\n";

                                        foreach ($order_total_query->rows as $result) {
                                                $text .= $result['title'] . ' ' . html_entity_decode($result['text'], ENT_NOQUOTES, 'UTF-8') . "\n";
                                        }

                                        $text .= "\n";

                                        $text .= $this->language->get('text_new_shipping_method');
                                        $text .= $order_info['shipping_method'] . "\n\n";

                                        // Add shipping address
                                        if ($order_info['shipping_address_format']) {
                                                $format = $order_info['shipping_address_format'];
                                        } else {
                                                $format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' .
                                                        "\n" . '{zone}' . "\n" . '{country}';
                                        }

                                        $find = array(
                                                '{firstname}',
                                                '{lastname}',
                                                '{company}',
                                                '{address_1}',
                                                '{address_2}',
                                                '{city}',
                                                '{postcode}',
                                                '{zone}',
                                                '{zone_code}',
                                                '{country}'
                                        );

                                        $replace = array(
                                                'firstname' => $order_info['shipping_firstname'],
                                                'lastname'  => $order_info['shipping_lastname'],
                                                'company'   => $order_info['shipping_company'],
                                                'address_1' => $order_info['shipping_address_1'],
                                                'address_2' => $order_info['shipping_address_2'],
                                                'city'      => $order_info['shipping_city'],
                                                'postcode'  => $order_info['shipping_postcode'],
                                                'zone'      => $order_info['shipping_zone'],
                                                'zone_code' => $order_info['shipping_zone_code'],
                                                'country'   => $order_info['shipping_country']
                                        );

                                        $shipping_address = preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), "\n",trim(str_replace($find, $replace, $format)));
                                        if ($shipping_address) {
                                                $text .= $this->language->get('text_new_shipping_address'). ":\n";
                                                $text .= $shipping_address;
                                        }

                                        $comment = '';
                                        if ($order_info['comment'] != '') {
                                                $comment = $order_info['comment'];
                                        }

                                        if ($comment) {
                                                $text .= $this->language->get('text_new_comment') . "\n\n";
                                                $text .= $comment . "\n\n";
                                        }



                                        $mail = new Mail();
                                        $mail->protocol = $this->config->get('config_mail_protocol');
                                        $mail->parameter = $this->config->get('config_mail_parameter');
                                        $mail->hostname = $this->config->get('config_smtp_host');
                                        $mail->username = $this->config->get('config_smtp_username');
                                        $mail->password = $this->config->get('config_smtp_password');
                                        $mail->port = $this->config->get('config_smtp_port');
                                        $mail->timeout = $this->config->get('config_smtp_timeout');
                                        $mail->setTo($this->config->get('config_email'));
                                        $mail->setFrom($this->config->get('config_email'));
                                        $mail->setSender($order_info['store_name']);
                                        $mail->setSubject($subject);
                                        $mail->setText($text);
                                        $mail->send();

                                        // Send to additional alert emails
                                        $emails = explode(',', $this->config->get('config_alert_emails'));

                                        foreach ($emails as $email) {
                                                if ($email && preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/i', $email)) {
                                                        $mail->setTo($email);
                                                        $mail->send();
                                                }
                                        }
					
				} else {
					// New order has been partially paid for
					// Confirm + mark PENDING
					
					$remaining = $order_info['total'] - $order_info['total_paid'];
					$order_status_id = $this->config->get('bitcoinpayflow_pending_status_id');
                                        $comment = sprintf($this->language->get('text_payment_confirm_partial'), (double) $amount, $remaining);
                                        $this->model_checkout_order->confirm($order_id, $order_status_id, $comment);
				}



                                // Save all of the payment info (hidden from user)
                                $comment = $this->language->get('text_payment_confirmation') . "\n";
                                $comment .= "POST:\n" . http_build_query($this->request->post, '', "\n") . "\n";
                                $this->model_checkout_order->update($order_id, $order_status_id, $comment, false);
				


				/* End New Code */

				/*
				// Save all of the payment info (hidden from user)
				$comment = $this->language->get('text_payment_confirmation') . "\n";
				$comment .= "POST:\n" . http_build_query($this->request->post, '', "\n") . "\n";
				$this->model_checkout_order->update($order_id, $order_status_id, $comment, false);
				
				if ($this->request->post['Payment_Notification']['order_status'] == "satisfied") {
					// Notify user about their payment being complete
					$order_status_id = $this->config->get('bitcoinpayflow_completed_status_id');

					$comment = $this->language->get('text_payment_complete');
					$this->model_checkout_order->update($order_id, $order_status_id, $comment, true);

					
					// Notify the store of payment completion
					
					// Load required language and order data
			 		$this->language->load('mail/order');
					$order_info = $this->model_checkout_order->getOrder($order_id);
					
					$order_status_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_status WHERE order_status_id = '" . 
						(int)$order_status_id . "' AND language_id = '" . (int)$order_info['language_id'] . "'");
			
					if ($order_status_query->num_rows) {
						$order_status = $order_status_query->row['name'];       
					} else {
						$order_status = '';
					}
									
					$order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");
					$order_total_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_total WHERE order_id = '" . (int)$order_id . "' ORDER BY sort_order ASC");
					$order_download_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_download WHERE order_id = '" . (int)$order_id . "'");


					$subject = sprintf($this->language->get('text_update_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'), $order_id);
					
					// Text 
					$text  = $this->language->get('text_payment_complete_mail') . "\n\n";
					$text .= $this->language->get('text_new_order_id') . ' ' . $order_id . "\n";
					$text .= $this->language->get('text_new_date_added') . ' ' . date($this->language->get('date_format_short'), strtotime($order_info['date_added'])) . "\n";
					$text .= $this->language->get('text_new_order_status') . ' ' . $order_status . "\n\n";
					$text .= $this->language->get('text_new_products') . "\n";
					
					foreach ($order_product_query->rows as $result) {
						$text .= $result['quantity'] . 'x ' . $result['name'] . ' (' . $result['model'] . ') ' . html_entity_decode($this->currency->format($result['total'], 
							$order_info['currency_code'], $order_info['currency_value']), ENT_NOQUOTES, 'UTF-8') . "\n";
					
						$order_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" 
							. $result['order_product_id'] . "'");
					
						foreach ($order_option_query->rows as $option) {
							$text .= chr(9) . '-' . $option['name'] .' '. (strlen($option['value']) > 20 ? substr($option['value'], 0, 20) . '..' : $option['value']) . "\n";
						}
					}
				
					$text .= "\n";
	
					$text.= $this->language->get('text_new_order_total') . "\n";
					
					foreach ($order_total_query->rows as $result) {
						$text .= $result['title'] . ' ' . html_entity_decode($result['text'], ENT_NOQUOTES, 'UTF-8') . "\n";
					}		       
					
					$text .= "\n";
					
					
					$text .= $this->language->get('text_new_shipping_method');
					$text .= $order_info['shipping_method'] . "\n\n";

					// Add shipping address
					if ($order_info['shipping_address_format']) {
						$format = $order_info['shipping_address_format'];
					} else {
						$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . 
							"\n" . '{zone}' . "\n" . '{country}';
					}

					$find = array(
						'{firstname}',
						'{lastname}',
						'{company}',
						'{address_1}',
						'{address_2}',
						'{city}',
						'{postcode}',
						'{zone}',
						'{zone_code}',
						'{country}'
					);
				
					$replace = array(
						'firstname' => $order_info['shipping_firstname'],
						'lastname'  => $order_info['shipping_lastname'],
						'company'   => $order_info['shipping_company'],
						'address_1' => $order_info['shipping_address_1'],
						'address_2' => $order_info['shipping_address_2'],
						'city'      => $order_info['shipping_city'],
						'postcode'  => $order_info['shipping_postcode'],
						'zone'      => $order_info['shipping_zone'],
						'zone_code' => $order_info['shipping_zone_code'],
						'country'   => $order_info['shipping_country']  
					);
					
					$shipping_address = preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), "\n",trim(str_replace($find, $replace, $format)));
					if ($shipping_address) {
						$text .= $this->language->get('text_new_shipping_address'). ":\n";
						$text .= $shipping_address;
					}

					$comment = '';
					if ($order_info['comment'] != '') {
						$comment = $order_info['comment'];
					}
					
					if ($comment) {
						$text .= $this->language->get('text_new_comment') . "\n\n";
						$text .= $comment . "\n\n";
					}

					
					
					$mail = new Mail(); 
					$mail->protocol = $this->config->get('config_mail_protocol');
					$mail->parameter = $this->config->get('config_mail_parameter');
					$mail->hostname = $this->config->get('config_smtp_host');
					$mail->username = $this->config->get('config_smtp_username');
					$mail->password = $this->config->get('config_smtp_password');
					$mail->port = $this->config->get('config_smtp_port');
					$mail->timeout = $this->config->get('config_smtp_timeout');
					$mail->setTo($this->config->get('config_email'));
					$mail->setFrom($this->config->get('config_email'));
					$mail->setSender($order_info['store_name']);
					$mail->setSubject($subject);
					$mail->setText($text);
					$mail->send();
					
					// Send to additional alert emails
					$emails = explode(',', $this->config->get('config_alert_emails'));
					
					foreach ($emails as $email) {
						if ($email && preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/i', $email)) {
							$mail->setTo($email);
							$mail->send();
						}
					}
					
				} else {
					// Notify user about receiving their payment
					$comment = sprintf($this->language->get('text_payment_received'), $this->request->post['Payment_Notification']['amount']);
					$this->model_checkout_order->update($order_id, $order_status_id, $comment, true);
				}
				*/
			} else {
				require_once(DIR_SYSTEM . 'payment/bitcoinpayflow.php');
		 		$log = new Log("bitcoinpayflow.txt");
				$message = "Invalid signature";
				$message .= "POST:\n" . http_build_query($this->request->post, '', "\n") . "\n";
				$message .= "GET:\n" . http_build_query($this->request->get, '', "\n") . "\n\n";
				$log->write($message);
			}
		} else {
			require_once(DIR_SYSTEM . 'payment/bitcoinpayflow.php');
			$log = new Log("bitcoinpayflow.txt");
			$message = "Invalid POST (missing variables)\n";
       			$message .= "Remote IP " . $this->request->server['REMOTE_ADDR'] ."\n";
			$message .= "POST:\n" . http_build_query($this->request->post, '', "\n") . "\n";
			$message .= "GET:\n" . http_build_query($this->request->get, '', "\n") . "\n\n";
			$log->write($message);
		}
		
	}
	
	public function confirm() {
		$this->language->load('payment/bitcoinpayflow');

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);


		$payflow = new bitcoinPayFlow();
		$orders = $payflow->orders(array(
				'foreign_order_id' => $this->session->data['order_id'],
				'total_amount' => $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false),
				'custom_field' => 'encrypted order id',
				'auth_token' => $this->config->get('bitcoinpayflow_auth_token')
			));
		// REMOVE ME
		echo $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
		print_r($orders);
		$comment = $this->language->get('text_address') . "\n";
		$comment .= $orders['order']['bitcoin_address'] . "\n\n";
		$comment .= $this->language->get('text_payment') . "\n";
		
		$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('bitcoinpayflow_pending_status_id'), $comment);
	}
}
?>
