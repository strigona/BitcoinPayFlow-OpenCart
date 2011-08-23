<?php 
class ModelPaymentBitcoinPayFlow extends Model {
  	public function getMethod($address, $total) {
		$this->load->language('payment/bitcoinpayflow');

                if ($this->config->get('bitcoinpayflow_status')) {
	                $status = true;
                } else {
                        $status = false;
                }

		// Payment method is only active when BTC is selected
                if ($this->currency->getCode() != 'BTC') {
			$status = false;
		}
		
		
		$method_data = array();
	
		if ($status) {  
      			$method_data = array( 
        			'code'       => 'bitcoinpayflow',
        			'title'      => $this->language->get('text_title'),
				'sort_order' => $this->config->get('bitcoinpayflow_sort_order')
      			);
	    	}
   
	    	return $method_data;
  	}
}
?>
