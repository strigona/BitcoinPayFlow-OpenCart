<?php

class bitcoinPayFlow {
    //API Configuration
    const API_URL       = 'https://bitcoinpayflow.com/';
    const RESULT_FORMAT = 'array'; //default is 'json'
    const EXT           = '.php';
    
    private $apiMethods    = array('orders', 'tokens');
      
        
    public function __construct(){
        
    }
            
    public function __call($method, $params = array()) {
        
        $this->_validateMethod($method);        
                            
        $url = $this->_buildUrl($method);

        print_r($url);
        
        $options = $this->_buildParams($method , $params);
                
        // @REMOVE Output POST variables
        //pre_print($options, 'POST:' . $method);
                              
        $result = $this->_connect($url, $options);
        
        $result = $this->_formatResults($result);
        
        return $result;
    }
    
    private function _buildParams($method, $params = array()){
        
        
            foreach ($params[0] as $k => $v){
                $options[$k] = $v;
            }
        
                    
        
        return $options;
    }
    
        
    private function _buildUrl($method){
                       
        $url = self::API_URL . $method;
        
        return $url;
    }
            
    private function _validateMethod($method){
                           
        if(in_array($method, $this->apiMethods)){
                        return TRUE; 
        } else {
            die('FAILURE: Unknown Method'); 
        }
    }
    
    private function _formatResults($results){
        
        if(self::RESULT_FORMAT == strtolower('array')){
        $results = json_decode($results, true);
        }
        
        return $results;
    }
        
    private function _connect($url, $params = NULL){
        
        //open connection
        $ch = curl_init();
                        
        //set the url
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_HEADER, TRUE);
        
        //add POST fields
        if ($params != NULL){
                        
            //url encode params array before POST
            $postData = http_build_query($params, '', '&');
                        
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
                               
        //curl_setopt($ch,CURLOPT_HTTPHEADER,array());        
        
        //MUST BE REMOVED BEFORE PRODUCTION (USE for SSL)
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0); 
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; BTChash; '
            .php_uname('s').'; PHP/'.phpversion().')');
	
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        
        //curl_setopt($ch, CURLOPT_VERBOSE, 1);
        
        //execute CURL connection
        $returnData = curl_exec($ch);
                
        //$code = $this->returnCode($returnData);        
        
        if( $returnData === false)
        {
            die('<br />Connection error:' . curl_error($ch));
        }
        else
        {
            //Log successful CURL connection
        }
        
        //close CURL connection
        curl_close($ch);
        
                        
        return $returnData;
    }
   
}
