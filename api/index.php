<?php
/* API для СКИФ */
if ($_SERVER['SERVER_NAME']=='demo.webnice.biz') { // Demo база, заголовки для Swagger
 header('Access-Control-Allow-Origin: *');
 header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
 header('Access-Control-Allow-Headers: Authorization, Content-Type');
 // Браузер сначала шлёт OPTIONS-запрос (preflight) — нужно ответить и выйти
 if ($_SERVER['REQUEST_METHOD']==='OPTIONS') {
  http_response_code(200);
  exit;
 }
}

define('WN_PATH',dirname(__DIR__).'/');
define('WN_PATH_INCLUDES', __DIR__ . '/includes/');

$resp=array();
$err=$sql='';
$cron_log_file = WN_PATH.'cache/cron_index.inc';

try {

 $input=array();
$raw_input=file_get_contents('php://input');
 if (!empty($raw_input)) {
  $input=json_decode($raw_input, true);
  if (json_last_error()!==JSON_ERROR_NONE) {
   throw new Exception('Некорректный JSON в запросе: '.json_last_error_msg());
  }
 }

 if (!empty($input['test'])) {  // DEBUG. Если не задана, в cron_start.php будет определена как SCIF_CATALOG_BASE
  define('SCIF_BASE','scif4');
 }

 require WN_PATH.'includes/cron_start.php';

if (empty($api['tokens'])) {
 throw new Exception('Не задан токен API в файле настроек',401);
}
// токен в заголовке
if (!empty($_GET['token'])) {
 $token=$_GET['token'];
 if (!isset($api['tokens'][$token])) {
  throw new Exception('Некорректный API-токен в параметре token',401);
 }
} else { // поищем токен в заголовках
  // Приводим все ключи заголовков к нижнему регистру
  $headers = array_change_key_case(getallheaders(), CASE_LOWER);
  if (empty($headers['authorization'])) {
   throw new Exception('Не передан заголовок Authorization',401);
  }
 $token=$headers['authorization'];
  if (!isset($api['tokens'][$headers['authorization']])) {
   throw new Exception('Некорректный API-токен в заголовке Authorization',401);
  }
 }
$api_settings=$api['tokens'][$token];
$chpu=((!empty($_GET['chpu']) AND preg_match('/^[a-z\d_\-\/]+$/',$_GET['chpu']))?$_GET['chpu']:'');
 if (!$chpu) {
  throw new Exception('Не передан адрес запроса к API',422);
 }
$chpu_arr=explode('/',$chpu);
if (empty($chpu_arr[0])) {
 throw new Exception('Не передана группа метода API',422);
}
$act=$chpu_arr[0];
 if (!file_exists(WN_PATH_INCLUDES.'acts/'.$act.'.php')) {
  throw new Exception('Некорректная группа методов '.$act,422);
 }
 if (empty($chpu_arr[1])) {
  throw new Exception('Не передан метод API',422);
 }

$method=$chpu_arr[1];
$id=(!empty($chpu_arr[2])?intval($chpu_arr[2]):0);

$home=(!empty($api['home'])?$api['home']:WN_HOME);
require WN_PATH_INCLUDES.'acts/'.$act.'.php';

} catch (Exception $e) {
$err=$e->getMessage();
http_response_code($e->getCode()?$e->getCode():500);
}

if ($err) {
 $resp=array('error'=>$err);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($resp,JSON_UNESCAPED_UNICODE);

$log_data=print_r($input,true).PHP_EOL
.print_r($_REQUEST,true).PHP_EOL
.print_r($resp,true).PHP_EOL
.$sql;
file_put_contents($cron_log_file,$log_data);

// запросы для создания заказов сохраним в отдельный лог
if (!empty($act) and $act=='doc' and in_array($method,array('add','edit'))) {
 file_put_contents(WN_PATH.'cache/api_doc_'.$method
 .(!empty($resp['id'])?'_'.$resp['id']:'').'.inc',
 $log_data
 .(isset($my_kind)?PHP_EOL.print_r($my_kind,true):''));
}