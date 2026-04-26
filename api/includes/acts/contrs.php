<?php
// Работа с контрагентами
switch ($method) {
 case 'balance': // баланс контрагента
  if ($id<=0) {
   throw new Exception('Не передан ID контрагента');
  }
  $data=$db->sql_fetch_assoc($db->sql_query('SELECT balance FROM '.WN_CABINET_USERS.' WHERE id="'.$id.'"'));
  if (!$data) {
   throw new Exception('Не найден контрагент с кодом '.$id);
  }
  $resp['balance']=$data['balance'];
 break;

 default:
  throw new Exception('Неизвестный метод '.$method);
 break;
}