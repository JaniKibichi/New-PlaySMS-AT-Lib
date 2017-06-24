<?php
defined('_SECURE_') or die('Forbidden');

$callback_url = '';
if (!$core_config['daemon_process']) {
	$callback_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/plugin/gateway/africastalking/callback.php";
	$callback_url = str_replace("//", "/", $callback_url);
	$callback_url = ($core_config['ishttps'] ? "https://" : "http://") . $callback_url;
}

$data = registry_search(0, 'gateway', 'africastalking');
$plugin_config['africastalking'] = $data['gateway']['africastalking'];
$plugin_config['africastalking']['name'] = 'africastalking';
$plugin_config['africastalking']['default_url'] = 'https://api.africastalking.com/restless/send?username={AFRICASTALKING_API_USERNAME}&Apikey={AFRICASTALKING_API_PASSWORD}&from={AFRICASTALKING_SENDER}&to={AFRICASTALKING_TO}&message={AFRICASTALKING_MESSAGE}';
$plugin_config['africastalking']['default_callback_url'] = $callback_url;
if (!trim($plugin_config['africastalking']['url'])) {
	$plugin_config['africastalking']['url'] = $plugin_config['africastalking']['default_url'];
}
if (!trim($plugin_config['africastalking']['callback_url'])) {
	$plugin_config['africastalking']['callback_url'] = $plugin_config['africastalking']['default_callback_url'];
}
if (!trim($plugin_config['africastalking']['callback_url_authcode'])) {
	$plugin_config['africastalking']['callback_url_authcode'] = sha1(_PID_);
}

// smsc configuration
$plugin_config['africastalking']['_smsc_config_'] = array(
	'url' => _('Africastalking send SMS URL'),
	'api_username' => _('API username'),
	'api_password' => _('API password'),
	'module_sender' => _('Module sender ID'),
	'datetime_timezone' => _('Module timezone') 
);
