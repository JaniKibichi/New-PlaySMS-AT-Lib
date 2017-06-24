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
require_once('AfricasTalkingGateway.php');
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
 