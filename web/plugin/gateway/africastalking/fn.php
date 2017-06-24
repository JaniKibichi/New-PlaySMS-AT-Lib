<?php

/**
 * This file is part of playSMS.
 *
 * playSMS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * playSMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with playSMS. If not, see <http://www.gnu.org/licenses/>.
 */

defined('_SECURE_') or die('Forbidden');
class AfricasTalkingGatewayException extends Exception  {}

class AfricasTalkingGateway
{
  protected $_username;
  protected $_apiKey;
  
  protected $_requestBody;
  protected $_requestUrl;
  
  protected $_responseBody;
  protected $_responseInfo;
    
  //Turn this on if you run into problems. It will print the raw HTTP response from our server
  const Debug             = false;
  
  const HTTP_CODE_OK      = 200;
  const HTTP_CODE_CREATED = 201;
  
  public function __construct($username_, $apiKey_, $environment_ = "production")
  {
    $this->_username     = $username_;
    $this->_apiKey       = $apiKey_;

    $this->_environment  = $environment_;
    
    $this->_requestBody  = null;
    $this->_requestUrl   = null;
    
    $this->_responseBody = null;
    $this->_responseInfo = null;    
  }
  
  
  //Messaging methods
  public function sendMessage($to_, $message_, $from_ = null, $bulkSMSMode_ = 1, Array $options_ = array())
  {
    if ( strlen($to_) == 0 || strlen($message_) == 0 ) {
      throw new AfricasTalkingGatewayException('Please supply both to and message parameters');
    }
    
    $params = array(
		    'username' => $this->_username,
		    'to'       => $to_,
		    'message'  => $message_,
		    );
    
    if ( $from_ !== null ) {
      $params['from']        = $from_;
      $params['bulkSMSMode'] = $bulkSMSMode_;
    }
    
    //This contains a list of parameters that can be passed in $options_ parameter
    if ( count($options_) > 0 ) {
      $allowedKeys = array (
			    'enqueue',
			    'keyword',
			    'linkId',
			    'retryDurationInHours'
			    );
			    
      //Check whether data has been passed in options_ parameter
      foreach ( $options_ as $key => $value ) {
	if ( in_array($key, $allowedKeys) && strlen($value) > 0 ) {
	  $params[$key] = $value;
	} else {
	  throw new AfricasTalkingGatewayException("Invalid key in options array: [$key]");
	}
      }
    }
    
    $this->_requestUrl  = $this->getSendSmsUrl();
    $this->_requestBody = http_build_query($params, '', '&');
    
    $this->executePOST();
    
    if ( $this->_responseInfo['http_code'] == self::HTTP_CODE_CREATED ) {
      $responseObject = json_decode($this->_responseBody);
      if(count($responseObject->SMSMessageData->Recipients) > 0)
	return $responseObject->SMSMessageData->Recipients;
	  
      throw new AfricasTalkingGatewayException($responseObject->SMSMessageData->Message);
    }
    
    throw new AfricasTalkingGatewayException($this->_responseBody);
  }
  

  public function fetchMessages($lastReceivedId_)
  {
    $username = $this->_username;
    $this->_requestUrl = $this->getSendSmsUrl().'?username='.$username.'&lastReceivedId='. intval($lastReceivedId_);
    
    $this->executeGet();
         
    if ( $this->_responseInfo['http_code'] == self::HTTP_CODE_OK ) {
      $responseObject = json_decode($this->_responseBody);
      return $responseObject->SMSMessageData->Messages;
    }
    
    throw new AfricasTalkingGatewayException($this->_responseBody);    
  }
  
  
  //Subscription methods
  public function createSubscription($phoneNumber_, $shortCode_, $keyword_, $checkoutToken_)
  {
  	
    if ( strlen($phoneNumber_) == 0 || strlen($shortCode_) == 0 || strlen($keyword_) == 0 || strlen($checkoutToken_) == 0 ) {
      throw new AfricasTalkingGatewayException('Please supply phoneNumber, shortCode, keyword and checkoutToken');
    }
    
    $params = array(
		    'username'      => $this->_username,
		    'phoneNumber'   => $phoneNumber_,
		    'shortCode'     => $shortCode_,
		    'keyword'       => $keyword_,
        'checkoutToken' => $checkoutToken_,
		    );
    
    $this->_requestUrl  = $this->getSubscriptionUrl("/create");
    $this->_requestBody = http_build_query($params, '', '&');
    
    $this->executePOST();
    
    if ( $this->_responseInfo['http_code'] != self::HTTP_CODE_CREATED )
      throw new AfricasTalkingGatewayException($this->_responseBody);
     
    return json_decode($this->_responseBody);
  }

  public function deleteSubscription($phoneNumber_, $shortCode_, $keyword_)
  {
    if ( strlen($phoneNumber_) == 0 || strlen($shortCode_) == 0 || strlen($keyword_) == 0 ) {
      throw new AfricasTalkingGatewayException('Please supply phoneNumber, shortCode and keyword');
    }
    
    $params = array(
		    'username'    => $this->_username,
		    'phoneNumber' => $phoneNumber_,
		    'shortCode'   => $shortCode_,
		    'keyword'     => $keyword_
		    );
    
    $this->_requestUrl  = $this->getSubscriptionUrl("/delete");
    $this->_requestBody = http_build_query($params, '', '&');
    
    $this->executePOST();
    
    if ( $this->_responseInfo['http_code'] != self::HTTP_CODE_CREATED )
      throw new AfricasTalkingGatewayException($this->_responseBody);
     
    return json_decode($this->_responseBody);
     
  }
  
  public function fetchPremiumSubscriptions($shortCode_, $keyword_, $lastReceivedId_ = 0)
  {
    $params  = '?username='.$this->_username.'&shortCode='.$shortCode_;
    $params .= '&keyword='.$keyword_.'&lastReceivedId='.intval($lastReceivedId_);
    $this->_requestUrl  = $this->getSubscriptionUrl($params);
    
    $this->executeGet();
    
    if ( $this->_responseInfo['http_code'] == self::HTTP_CODE_OK ) {
      $responseObject = json_decode($this->_responseBody);
      return $responseObject->responses;
    }
    
    throw new AfricasTalkingGatewayException($this->_responseBody);
  }
  
  
  //Call methods
  public function call($from_, $to_)
  {
    if ( strlen($from_) == 0 || strlen($to_) == 0 ) {
      throw new AfricasTalkingGatewayException('Please supply both from and to parameters');
    }
    
    $params = array(
		    'username' => $this->_username,
		    'from'     => $from_,
		    'to'       => $to_
		    );
    
    $this->_requestUrl  = $this->getVoiceUrl() . "/call";
    $this->_requestBody = http_build_query($params, '', '&');
    
    $this->executePOST();
     
    if(($responseObject = json_decode($this->_responseBody)) !== null) {
      if(strtoupper(trim($responseObject->errorMessage)) == "NONE") {
	return $responseObject->entries;
      }
      throw new AfricasTalkingGatewayException($responseObject->errorMessage);
    }
    else
      throw new AfricasTalkingGatewayException($this->_responseBody);
  }
  
  public function getNumQueuedCalls($phoneNumber_, $queueName = null) 
  {  	
    $this->_requestUrl = $this->getVoiceUrl() . "/queueStatus";
    $params = array(
		    "username"     => $this->_username, 
		    "phoneNumbers" => $phoneNumber_
		    );
    if($queueName !== null)
      $params['queueName'] = $queueName;
    $this->_requestBody   = http_build_query($params, '', '&');
    $this->executePOST();
  	
    if(($responseObject = json_decode($this->_responseBody)) !== null) {
      if(strtoupper(trim($responseObject->errorMessage)) == "NONE")
	return $responseObject->entries;
      throw new AfricasTalkingGatewayException($responseObject->ErrorMessage);
    }
  		
    throw new AfricasTalkingGatewayException($this->_responseBody);
  }

		
  public function uploadMediaFile($url_) 
  {
    $params = array(
		    "username" => $this->_username, 
		    "url"      => $url_
		    );
  	             
    $this->_requestBody = http_build_query($params, '', '&');
    $this->_requestUrl  = $this->getVoiceUrl() . "/mediaUpload";
  	
    $this->executePOST();
  	
    if(($responseObject = json_decode($this->_responseBody)) !== null) {
      if(strtoupper(trim($responseObject->errorMessage)) != "NONE")
	throw new AfricasTalkingGatewayException($responseObject->errorMessage);
    }
    else
      throw new AfricasTalkingGatewayException($this->_responseBody);
  }
  
  
  //Airtime method
  public function sendAirtime($recipients) 
  {
    $params = array(
		    "username"    => $this->_username, 
		    "recipients"  => $recipients
		    );
    $this->_requestUrl  = $this->getAirtimeUrl("/send");
    $this->_requestBody = http_build_query($params, '', '&');
  	
    $this->executePOST();
  	
    if($this->_responseInfo['http_code'] == self::HTTP_CODE_CREATED) {
      $responseObject = json_decode($this->_responseBody);
      if(count($responseObject->responses) > 0)
	return $responseObject->responses;
  			
      throw new AfricasTalkingGatewayException($responseObject->errorMessage);
    }
  	
    throw new AfricasTalkingGatewayException($this->_responseBody);
  }

  // Payments
  public function initiateMobilePaymentCheckout($productName_,
						$phoneNumber_,
						$currencyCode_,
						$amount_,
						$metadata_) {
    $this->_requestBody = json_encode(array("username"     => $this->_username,
					    "productName"  => $productName_,
					    "phoneNumber"  => $phoneNumber_,
					    "currencyCode" => $currencyCode_,
					    "amount"       => $amount_,
					    "metadata"     => $metadata_));
    $this->_requestUrl  = $this->getMobilePaymentCheckoutUrl();
    
    $this->executeJsonPOST();
    if($this->_responseInfo['http_code'] == self::HTTP_CODE_CREATED) {
      $response = json_decode($this->_responseBody);
      if ( $response->status == "PendingConfirmation") return $response->transactionId;
      else throw new AfricasTalkingGatewayException($response->description);
    }
    throw new AfricasTalkingGatewayException($this->_responseBody);
  }

  public function mobilePaymentB2CRequest($productName_,
					  $recipients_) {
    $this->_requestBody = json_encode(array("username"     => $this->_username,
					    "productName"  => $productName_,
					    "recipients"   => $recipients_));
    $this->_requestUrl  = $this->getMobilePaymentB2CUrl();
    
    $this->executeJsonPOST();
    if($this->_responseInfo['http_code'] == self::HTTP_CODE_CREATED) {
      $response = json_decode($this->_responseBody);
      $entries  = $response->entries;
      if (count($entries) > 0) return  $entries;      
      else throw new AfricasTalkingGatewayException($response->errorMessage);
    }
    throw new AfricasTalkingGatewayException($this->_responseBody);
  }

  public function mobilePaymentB2BRequest($productName_, $providerData_, $currencyCode_, $amount_, $metadata_) {
		if(!isset($providerData_['provider']) || strlen($providerData_['provider']) == 0)
			throw new AfricasTalkingGatewayException("Missing field provider");
		
		if(!isset($providerData_['destinationChannel']) || strlen($providerData_['destinationChannel']) == 0)
			throw new AfricasTalkingGatewayException("Missing field destinationChannel");

    if(!isset($providerData_['destinationAccount']) || strlen($providerData_['destinationAccount']) == 0)
      throw new AfricasTalkingGatewayException("Missing field destinationAccount");
		
		if(!isset($providerData_['transferType']) || strlen($providerData_['transferType']) == 0)
			throw new AfricasTalkingGatewayException("Missing field transferType");
		
		$params = array("username" => $this->_username,
										"productName"  => $productName_,
										"currencyCode" => $currencyCode_,
										"amount"=>$amount_,
										'provider' => $providerData_['provider'],
										'destinationChannel' => $providerData_['destinationChannel'],
                    'destinationAccount' => $providerData_['destinationAccount'],
										'transferType' => $providerData_['transferType'],
										'metadata' => $metadata_);
		
    $this->_requestBody = json_encode($params);
    $this->_requestUrl  = $this->getMobilePaymentB2BUrl();
    
    $this->executeJsonPOST();
    if($this->_responseInfo['http_code'] == self::HTTP_CODE_CREATED) {
      $response = json_decode($this->_responseBody);
      return $response;
    }
    throw new AfricasTalkingGatewayException($this->_responseBody);
  }
  
  //User info method
  public function getUserData()
  {
    $username = $this->_username;
    $this->_requestUrl = $this->getUserDataUrl('?username='.$username);
    $this->executeGet();
    
    if ( $this->_responseInfo['http_code'] == self::HTTP_CODE_OK ) {
      $responseObject = json_decode($this->_responseBody);
      return $responseObject->UserData;
    }
    	
    throw new AfricasTalkingGatewayException($this->_responseBody);
  }
  
  private function executeGet ()
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Accept: application/json',
							 'apikey: ' . $this->_apiKey));
    $this->doExecute($ch);
  }
  
  private function executePost ()
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_requestBody);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Accept: application/json',
							 'apikey: ' . $this->_apiKey));
    
    $this->doExecute($ch);
  }
  
  private function executeJsonPost ()
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_requestBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json',
					       'Content-Length: ' . strlen($this->_requestBody),
					       'apikey: ' . $this->_apiKey));
    $this->doExecute($ch);
  }
  
  private function doExecute (&$curlHandle_)
  {
    try {
	   	
      $this->setCurlOpts($curlHandle_);
      $responseBody = curl_exec($curlHandle_);
			    
      if ( self::Debug ) {
	echo "Full response: ". print_r($responseBody, true)."\n";
      }
			    
      $this->_responseInfo = curl_getinfo($curlHandle_);
			    
      $this->_responseBody = $responseBody;
      curl_close($curlHandle_);
    }
	   
    catch(Exeption $e) {
      curl_close($curlHandle_);
      throw $e;
    }
  }
  
  private function setCurlOpts (&$curlHandle_)
  {
    curl_setopt($curlHandle_, CURLOPT_TIMEOUT, 60);
    curl_setopt($curlHandle_, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curlHandle_, CURLOPT_URL, $this->_requestUrl);
    curl_setopt($curlHandle_, CURLOPT_RETURNTRANSFER, true);
  }

  private function getApiHost() {
    return ($this->_environment == 'sandbox') ? 'https://api.sandbox.africastalking.com' : 'https://api.africastalking.com';
  }

  private function getPaymentHost() {
    return ($this->_environment == 'sandbox') ? 'https://payments.sandbox.africastalking.com' : 'https://payments.africastalking.com';
  }

  private function getVoiceHost() {
    return ($this->_environment == 'sandbox') ? 'https://voice.sandbox.africastalking.com' : 'https://voice.africastalking.com';
  }
  
  private function getSendSmsUrl($extension_ = "") {
    return $this->getApiHost().'/version1/messaging'.$extension_;
  }
    
  private function getVoiceUrl() {
    return $this->getVoiceHost();
  }
  
  private function getUserDataUrl($extension_) {
    return $this->getApiHost().'/version1/user'.$extension_;
  }
  
  private function getSubscriptionUrl($extension_) {
    return $this->getApiHost().'/version1/subscription'.$extension_;
  }
  
  private function getAirtimeUrl($extension_) {
    return $this->getApiHost().'/version1/airtime'.$extension_;
  }

  private function getMobilePaymentCheckoutUrl() {
    return $this->getPaymentHost().'/mobile/checkout/request';
  }

  private function getMobilePaymentB2CUrl() {
    return $this->getPaymentHost().'/mobile/b2c/request';
  }

  private function getMobilePaymentB2BUrl() {
    return $this->getPaymentHost().'/mobile/b2b/request';
  }
}
// hook_sendsms
// called by main sms sender
// return true for success delivery
// $smsc : smsc
// $sms_sender : sender mobile number
// $sms_footer : sender sms footer or sms sender ID
// $sms_to : destination sms number
// $sms_msg : sms message to be delivered
// $gpid : group phonebook id (optional)
// $uid : sender User ID
// $smslog_id : sms ID
function africastalking_hook_sendsms($smsc, $sms_sender, $sms_footer, $sms_to, $sms_msg, $uid = '', $gpid = 0, $smslog_id = 254, $sms_type = 'text', $unicode = 0) {
	global $plugin_config;
	_log("enter smsc:" . $smsc . " smslog_id:" . $smslog_id . " uid:" . $uid . " to:" . $sms_to, 3, "africastalking_hook_sendsms");
	
	// override plugin gateway configuration by smsc configuration
	$plugin_config = gateway_apply_smsc_config($smsc, $plugin_config);
	_log("override plugin:" . $plugin_config, 3, "override_plugin_gateway_at_africastalking_hook_sendsms");
	
	$sms_sender = stripslashes($sms_sender);
	if ($plugin_config['africastalking']['module_sender']) {
		$sms_sender = $plugin_config['africastalking']['module_sender'];
	}
	
	$sms_footer = stripslashes($sms_footer);
	$sms_msg = stripslashes($sms_msg);
	$ok = false;

	_log("sendsms start", 3, "africastalking_hook_sendsms");

	if ($sms_footer) {
		$sms_msg = $sms_msg . $sms_footer;
	}

	// grab login credentials
	$username= $plugin_config['africastalking']['api_username'];
	$apikey= $plugin_config['africastalking']['api_password'];

	//initiate new gateway instance
	$gateway    = new AfricasTalkingGateway($username, $apikey);
	_log("gateway initiated", 3, "africastalking_gateway_class");	
	
	if ($sms_sender && $sms_to && $sms_msg) {
		_log("sendsms start", 3, "talking_to_africastalking_gateway");
		//sending params
		$from = $sms_sender;
		$recipients = $sms_to;
		$message =  $sms_msg;
		// send it
		try 
		{ 

		$results = $gateway->sendMessage($recipients, $message, $from);
		foreach($results as $result) {
		if($result){
			$db_query = "
				INSERT INTO " . _DB_PREF_ . "_gatewayAfricastalking_log (local_smslog_id, remote_smslog_id)
				VALUES ('$smslog_id', '$result->messageId')";
			$id = @dba_insert_id($db_query);
			if ($id) {
				$ok = true;
				$p_status = 1;
				dlr($smslog_id, $uid, $p_status);
			} else {
				$ok = true;
				$p_status = 0;
				dlr($smslog_id, $uid, $p_status);
			}
		}
		_log("Success" . " Number: " .$result->number." Status: " .$result->status." MessageId: " .$result->messageId , 3, "talking_to_africastalking_gateway");
		}
		}
		catch ( AfricasTalkingGatewayException $e )
		{
		_log("Encountered an error while sending: ".$e->getMessage(), 3,"africastalking_gateway_class_TRY_CATCH");
		}

	} else {
		_log("sendsms start ABORTED", 3, "sms_sender_&&_sms_to_&&_sms_msg_not_supplied");
	}
	if (!$ok) {
		$p_status = 2;
		dlr($smslog_id, $uid, $p_status);
	}
	
	return $ok;
}

 ?>
 