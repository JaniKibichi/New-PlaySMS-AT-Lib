<?php
defined('_SECURE_') or die('Forbidden');

if (!auth_isadmin()) {
	auth_block();
}

include $core_config['apps_path']['plug'] . "/gateway/africastalking/config.php";

switch (_OP_){
    case "manage":
		$tpl = array(
			'name' => 'africastalking',
			'vars' => array(
				'DIALOG_DISPLAY' => _dialog(),
				'Manage africastalking' => _('Manage africastalking'),
				'Gateway name' => _('Gateway name'),
				'Africastalking send SMS URL' => _mandatory(_('Africastalking send SMS URL')),
				'Callback URL' => _('Callback URL'),
				'Callback URL authcode' => _('Callback URL authcode'),
				'API username' => _('API username'),
				'API password' => _('API password'),
				'Module sender ID' => _('Module sender ID'),
				'Module timezone' => _('Module timezone'),
				'Save' => _('Save'),
				'Notes' => _('Notes'),
				'HINT_CALLBACK_URL' => _hint(_('Empty callback URL to set default')),
				'HINT_CALLBACK_URL_AUTHCODE' => _hint(_('Fill authentication code to secure callback URL')),
				'HINT_FILL_PASSWORD' => _hint(_('Fill to change the API password')),
				'HINT_MODULE_SENDER' => _hint(_('Max. 16 numeric or 11 alphanumeric char. empty to disable')),
				'HINT_TIMEZONE' => _hint(_('Eg: +0300 for Nairobi/Daresalaam timezone')),
				'CALLBACK_URL_IS' => _('Your current callback URL is'),
				'CALLBACK_URL_AUTHCODE_IS' => _('Your current callback URL authcode is'),
				'CALLBACK_URL_ACCESSIBLE' => _('Your callback URL should be accessible from remote gateway'),
				'AFRICASTALKING_PUSH_DLR' => _('Africastalking gateway will push DLR and incoming SMS to your callback URL'),
				'BUTTON_BACK' => _back('index.php?app=main&inc=core_gateway&op=gateway_list'),
				'status_active' => $status_active,
				'africastalking_param_url' => $plugin_config['africastalking']['url'],
				'africastalking_param_callback_url' => $plugin_config['africastalking']['callback_url'],
				'africastalking_param_callback_url_authcode' => $plugin_config['africastalking']['callback_url_authcode'],
				'africastalking_param_api_username' => $plugin_config['africastalking']['api_username'],
				'africastalking_param_module_sender' => $plugin_config['africastalking']['module_sender'],
				'africastalking_param_datetime_timezone' => $plugin_config['africastalking']['datetime_timezone'] 
			) 
		);
		_p(tpl_apply($tpl));
		break;

    case "manage_save":
		$up_url = ($_REQUEST['up_url'] ? $_REQUEST['up_url'] : $plugin_config['africastalking']['default_url']);
		$up_callback_url = ($_REQUEST['up_callback_url'] ? $_REQUEST['up_callback_url'] : $plugin_config['africastalking']['default_callback_url']);
		$up_callback_url_authcode = ($_REQUEST['up_callback_url_authcode'] ? $_REQUEST['up_callback_url_authcode'] : sha1(_PID_));
		$up_api_username = $_REQUEST['up_api_username'];
		$up_api_password = $_REQUEST['up_api_password'];
		$up_module_sender = $_REQUEST['up_module_sender'];
		$up_datetime_timezone = $_REQUEST['up_datetime_timezone'];
		if ($up_url) {
			if ($up_module_sender) {
				$up_module_sender2 = $up_module_sender;
			}	
			if ($up_callback_url) {
				$up_callback_url2 = $up_callback_url;
			}						
			if ($up_url) {
				$up_url2 = $up_url;
			}			
			if ($up_api_username) {
				$up_api_username2 = $up_api_username;
			}			
			if ($up_api_password) {
				$up_api_password2 = $up_api_password;
			}
			$db_query = "
				UPDATE " . _DB_PREF_ . "_gatewayAfricastalking_config
				SET c_timestamp='" . time() . "',
				cfg_module_sender='$up_module_sender2',
				cfg_send_url='$up_url2',			
				cfg_callback_url='$up_callback_url2',						
				cfg_username='$up_api_username2',
				cfg_password='$up_api_password2',
				cfg_datetime_timezone='$up_datetime_timezone '";
			if (@dba_affected_rows($db_query)) {
				$_SESSION['dialog']['info'][] = _('Gateway module configurations has been saved');
			} else {
				$_SESSION['dialog']['danger'][] = _('Fail to save gateway module configurations');
			}
			
		} else {
			$_SESSION['dialog']['danger'][] = _('All mandatory fields must be filled');
		}
		header("Location: " . _u('index.php?app=main&inc=gateway_africastalking&op=manage'));
		exit();
		break;    
}

?>