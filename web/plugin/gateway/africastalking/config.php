<?php
defined('_SECURE_') or die('Forbidden');

$db_query = "SELECT * FROM " . _DB_PREF_ . "_gatewayAfricastalking_config";
$db_result = dba_query($db_query);
if ($db_row = dba_fetch_array($db_result)) {
	$plugin_config['africastalking']['name'] = 'africastalking';
	$plugin_config['africastalking']['url'] = ($db_row['cfg_send_url'] ? $db_row['cfg_send_url'] : 'https://api.africastalking.com');
	$plugin_config['africastalking']['callback_url'] = ($db_row['cfg_callback_url'] ? $db_row['cfg_callback_url'] : $core_config['http_path']['base'] . 'plugin/gateway/africastalking/callback.php');
	$plugin_config['africastalking']['api_username'] = $db_row['cfg_username'];
	$plugin_config['africastalking']['api_password'] = $db_row['cfg_password'];
	$plugin_config['africastalking']['module_sender'] = $db_row['cfg_module_sender'];
	$plugin_config['africastalking']['datetime_timezone'] = $db_row['cfg_datetime_timezone'];
}

// smsc configuration
$plugin_config['africastalking']['_smsc_config_'] = array(
	'url' => _('Africastalking send SMS URL'),
	'api_username' => _('API username'),
	'api_password' => _('API password'),
	'module_sender' => _('Module sender ID'),
	'datetime_timezone' => _('Module timezone') 
);
