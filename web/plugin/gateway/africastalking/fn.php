<?php
defined('_SECURE_') or die('Forbidden');

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

	if ($sms_sender && $sms_to && $sms_msg) {

		$params = array(
				'username' => $plugin_config['africastalking']['api_username'],
				'to'       => $sms_to,
				'message'  => $sms_msg,
		);		

		$requestUrl  = "https://api.africastalking.com/version1/messaging";		
		$requestBody = http_build_query($params, '', '&');
		executePost();

		function executePost ()
		{
			if (function_exists('curl_init')) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Accept: application/json','apikey: ' . $plugin_config['africastalking']['api_password']));
				doExecute($ch);
			} else {
				_log("fail to sendsms due to missing PHP curl functions", 3, "africastalking_hook_sendsms");
			}
		}

		function setCurlOpts (&$curlHandle_)
		{
			curl_setopt($curlHandle_, CURLOPT_TIMEOUT, 60);
			curl_setopt($curlHandle_, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($curlHandle_, CURLOPT_URL, $requestUrl);
			curl_setopt($curlHandle_, CURLOPT_RETURNTRANSFER, true);
		}

		function doExecute (&$curlHandle_)
		{
			try {	   	
				setCurlOpts($curlHandle_);
				$theResponseBody = curl_exec($curlHandle_);
				$responseInfo = curl_getinfo($curlHandle_);			
				$responseBody = $theResponseBody;
				curl_close($curlHandle_);

				_log("sendsms url:[" . $requestUrl . "] callback:[" . $plugin_config['africastalking']['callback_url'], "] smsc:[" . $smsc . "]", 3, "africastalking_hook_sendsms");
				$resp = json_decode($theResponseBody);

				if ($resp->status) {
					if($responseInfo['http_code']==201){
						$c_status = $resp->status;
						$c_message_id = $resp->messageId;					
					} else{
						$c_error_text =	$responseInfo['http_code'];				
					}
					_log("sent smslog_id:" . $smslog_id . " message_id:" . $c_message_id . " status:" . $c_status . " error:" . $c_error_text . " smsc:[" . $smsc . "]", 2, "africastalking_hook_sendsms");
					$db_query = "
						INSERT INTO " . _DB_PREF_ . "_gatewayTwilio (local_smslog_id,remote_smslog_id,status,error_text)
						VALUES ('$smslog_id','$c_message_id','$c_status','$c_error_text')";
					$id = @dba_insert_id($db_query);
					if ($id && ($c_status == 'Sent')) {
						$ok = true;
						$p_status = 0;
					} else {
						$p_status = 2;
					}
					dlr($smslog_id, $uid, $p_status);
				} else {
					// even when the response is not what we expected we still print it out for debug purposes
					$resp = str_replace("\n", " ", $resp);
					$resp = str_replace("\r", " ", $resp);
					_log("failed smslog_id:" . $smslog_id . " resp:" . $resp . " smsc:[" . $smsc . "]", 2, "africastalking_hook_sendsms");
				}	

			}
				
			catch(Exeption $e) {
				curl_close($curlHandle_);
				throw $e;
			}
		}

	}
	if (!$ok) {
		$p_status = 2;
		dlr($smslog_id, $uid, $p_status);
	}
	
	return $ok;
}

 ?>
 