<?php
// Создание и редактирование документа
if (empty($api_settings['invoice'])) {
  throw new Exception('Не настроен API для работы с заказами (параметр invoice)');
 }
$time_x=time();
$contr=(!empty($input['client_id'])?intval($input['client_id']):0);

function get_contr() {
 global $db, $userdata, $contr;
 if ($contr>0) {
  $userdata=$db->sql_fetch_assoc($db->sql_query('SELECT balance FROM '.WN_CABINET_USERS.' WHERE id="'.$contr.'"'));
  if (!$userdata) {
   throw new Exception('Не найден контрагент с кодом '.$contr);
  }
 }
}

function get_items() {
 global $db, $summa, $invoice_sql, $input, $api_settings, $time_x;
 if (empty($input['items']) or !is_array($input['items'])) {
  throw new Exception('Не переданы товары в заказе (массив items).');
 }
 $items=$input['items'];
 $result=$db->sql_query('SELECT n.name, n.id AS code, '.$api_settings['price'].' AS price, n.is_service, '.$api_settings['sql_store'].' AS stock
  FROM '.SCIF_PREFIX.'spr_noms n
  WHERE n.id IN ("'.implode('","',array_keys($items)).'")');
 if (!$result) {
  throw new Exception('Ошибка получения товаров в заказе: '.$db->sql_error());
 }
 if (!$db->sql_num_rows($result)) {
  throw new Exception('Не найдено товаров по переданным кодам в массиве items.');
 }
 $summa=0;
 while ($row=$db->sql_fetch_assoc($result)) {
  $quant=$input['items'][$row['code']]['quant'];
   if ($quant<=0 or (!$row['is_service'] and $quant>$row['stock'])) {
    throw new Exception('Товар ('.$row['code'].') '.$row['name'].' недоступен для заказа в указанном количестве.'
    .' Доступно: '.(float)$row['stock'].', вы указали: '.$quant.'.');
   }
   /* проверим кратность
   if ($row['packing']>1 and $quant%$row['packing']) {
    throw new Exception('Товар ('.$row['code'].') '.$row['name'].' в упаковке по '.$row['packing'].'шт,'
    .' заказ необходимо делать кратно упаковкам. Вы указали количество '.$quant.'.');
   }
   */
  $summa+=round($quant*$row['price'],2);
  $invoice_sql.='("###","'.$row['code'].'","'.$quant.'","'.$row['price'].'",'.$time_x.',"'.$api_settings['invoice']['user_insert'].'"),';
  unset($items[$row['code']]);
 }
 // проверим, что найдены все переданные товары
 if (count($items)) {
  throw new Exception('Не найдены следующие коды товаров, переданные в массиве items: '.implode(', ',array_keys($items)));
 }
return true;
}

switch ($method) {
 case 'add': // создание заказа
/*
  if (!isset($input['type_delivery']) or !in_array($input['type_delivery'],array(1,2))) {
   throw new Exception('Не передано поле Способ доставки: 1-Самовывоз, 2-Доставка');
  }
  $type_delivery=intval($input['type_delivery']);
   if ($type_delivery==2) { // доставка
    $scif_order_fields['fio_to']['required']=$scif_order_fields['phone_to']['required']=$scif_order_fields['address_delivery']['required']=true;
   }
*/
  $fields=$err=$message=$invoice_message='';
  foreach ($scif_order_fields AS $key=>$val) {
   if (!empty($val['client_title'])) {
    if (!empty($input[$key]) OR empty($val['required'])) {
     if (!empty($val['type']) and $val['type']=='date_sql' and !preg_match('#\d{1,2}\.\d{1,2}\.\d{2,4}#',$input[$key])) {
      throw new Exception('Некорректный формат даты для поля '.$val['title'].'! Используйте формат dd.mm.yyyy');
     } else {
      $field=(!empty($input[$key])?urldecode(trim($input[$key])):''); // проверка для чекбокса, может быть пустым, но не быть обязательным
      $fields.='`'.$key.'`="'.process_field($key, $val, $field).'",'; // для записи в базу
      if (empty($val['not_save_note'])) {
       $invoice_message.=($field?$val['client_title'].':'.htmlclean($field).'; ':''); // примечания к счету
      }
     }
    } else {
     throw new Exception('Не заполнено обязательное поле '.$val['client_title']);
    }
   }
  }

  // данные клиента. До get_items, т.к. может иметь индивидуальные цены
  get_contr();
  if ($contr<=0) {
   $contr=$api_settings['invoice']['contr'];
   // поищем или создадим клиента, если не передан client_id и передан телефон
   if (!empty($api_settings['scif_contr_group']) and !empty($input['phone_from'])) {
    $phone=substr(preg_replace('#\D#', '', $input['phone_from']), -10);
    $check_contr=$db->sql_fetch_assoc($db->sql_query('SELECT id FROM '.SCIF_PREFIX.'spr_contrs 
   WHERE RIGHT('.sql_replace('phone', 'digit').', 10) LIKE "'.$phone.'"'));
    if ($check_contr) {
     $api_settings['invoice']['contr']=$check_contr['id'];
    } else { // клиента в СКИФ нет, создаем
     $sql='INSERT INTO '.SCIF_PREFIX.'spr_contrs SET 
    `name`="'.htmlclean($input['fio_from']).' '.$phone.'", 
    `parent`="'.$api_settings['scif_contr_group'].'",
    `phone`="'.$phone.'",
    `desc`="",
    `address`="'.(!empty($input['address_delivery'])?htmlclean($input['address_delivery']):'').'",
    `user_insert`="'.$api_settings['invoice']['user_insert'].'", 
    `date_insert`="'.$time_x.'"';
     if ($db->sql_query($sql)) {
      $api_settings['invoice']['contr']=$db->sql_insert_id();
     } else {
      file_put_contents(WN_PATH.'api_error.inc', $sql.PHP_EOL.$db->sql_error());
     }
    }
   }
  }

  // состав заказа
  get_items();

  // добавить type в $scif_order_fields['type']
  // в STRICT_MODE нельзя вставить данные в таблицу без указания значений, поэтому, используем здесь IGNORE
  $order_sql='INSERT IGNORE INTO '.WN_CATALOG_ORDERS.' SET
  `date_insert`='.$time_x.',
  `ip`="'.$_SERVER['REMOTE_ADDR'].'",
  `result`="",
  `note`="",
  '.$fields.'
  `amount`="'.$summa.'",
  `items`=""';
   $db->sql_query($order_sql);
   $order_num=$db->sql_insert_id();

  // пишем заказ в СКИФ '.SCIF_CATALOG_PREFIX.'doc
   $service_notify_msg='';
   if ($invoice_sql) {
    $sql_doc='INSERT INTO '.SCIF_PREFIX.'doc SET
    `price_type`="'.SCIF_CATALOG_PRICE.'", 
    `date_insert`='.$time_x.', `doc_date`="'.date('Y-m-d H:i:s',$time_x).'",
    `summa`="'.$summa.'", `note`="'.$invoice_message.'"';
    // тип создаваемого в СКИФ документа: 11 - Заказ покупателя, 2 - Продажа
    if (empty($api_settings['invoice']['type'])) { $api_settings['invoice']['type']=11; }
    foreach ($api_settings['invoice'] AS $key=>$val) {
     $sql_doc.=', `'.$key.'`="'.$val.'"';
    }
    // если адрес доставки сохраняем в документе
    if (isset($docs_cat['store']['userfields']['my_delivery_address']) and !empty($input['address_delivery'])) {
     $sql_doc.=', `my_delivery_address`="'.htmlclean($input['address_delivery']).'"';
    }
    $db->sql_query($sql_doc);
    $invoice_id=($db->sql_error()?0:$db->sql_insert_id());
     if (!$invoice_id) {
      throw new Exception($service_notify_msg);
     }
    // детали документа
    $invoice_sql=str_replace('###',$invoice_id,$invoice_sql); // подставляем номер документа
    $db->sql_query('INSERT INTO '.SCIF_PREFIX.'doc_det (doc_id,nom_id,quant,price,date_insert,user_insert) VALUES '.mb_substr($invoice_sql,0,-1));
    $db->sql_query('UPDATE '.WN_CATALOG_ORDERS.' SET article="'.$invoice_id.'" WHERE id="'.$order_num.'"');
    if ($api_settings['invoice']['type']==2) { // документ Продажа, списываем остатки товаров на складе и увеличиваем задолженность клиента
     recalc_remains('after',2,$invoice_id,$api_settings['invoice']['store'],0,$api_settings['invoice']['contr'],$summa);
    } elseif (function_exists('my_reserve_recalc')) {   // Заказ покупателя, пересчет резервов
     my_reserve_recalc($invoice_id);
    }

    // сохраним в лог уведомлений
    $service_notify_msg='Новый заказ с сайта <a href="?act=doclist&f_id='.$invoice_id.'" target="_blank">№'.$invoice_id.'</a>';
    service_notify(array('group'=>'Новый заказ','type'=>'Заказы','msg'=>$service_notify_msg));

    // пользовательская функция после отправки заказа, например, отправка уведомлений в Viber https://www.webnice.biz/catalog/product/email-notify/
    if (function_exists('after_order_send')) {
     $data=array('invoice_id'=>$invoice_id,'summa'=>$summa);
     after_order_send($data);
    }

   }
  $resp=array('id'=>$invoice_id,'summa'=>$summa,'order_num'=>$order_num);
 break;

 case 'edit': // =================== редактирование заказа =====================
  if ($id<=0) {
   throw new Exception('Не передан ID заказа');
  }
  $fields=array(
  'client_id'=>array('name'=>'ID контрагента'),
  'items'=>array('name'=>'Товары в заказе'),
  );
  foreach ($fields AS $key=>$val) {
   if (!isset($input[$key]))  {
    throw new Exception('Не передано поле '.$key.':'.$val['name']);
   }
  }
  // данные клиента
  get_contr();
  // данные документа
  $docdata=$db->sql_fetch_assoc($db->sql_query('SELECT type, price_type, contr, summa, finitem
  FROM '.SCIF_PREFIX.'doc
  WHERE id="'.$id.'"'));
  if (!$docdata) {
   throw new Exception('Не найден заказ с номером '.$id);
  }
  if ($docdata['contr']!=$contr) {
   throw new Exception('Документ с номером '.$id.' принадлежит другому контрагенту (ID '.$docdata['contr'].')');
  }
  if ($docdata['type']!=11) {
   throw new Exception('Документ с номером '.$id.' имеет недопустимый для редактирования тип, возможно, уже отгружен');
  }
  if ($docdata['finitem']) {
   throw new Exception('Заказ с номером '.$id.' имеет недопустимый для редактирования статус, он уже принят в обработку');
  }
  // состав заказа
  get_items();
  // детали документа
  $invoice_sql=str_replace('###',$id,$invoice_sql); // подставляем номер документа
  $db->sql_query('INSERT INTO '.SCIF_PREFIX.'doc_det (doc_id,nom_id,quant,price,date_insert,user_insert) VALUES '.mb_substr($invoice_sql,0,-1));
  // обновим заказ
  $new_summa=round($docdata['summa']+$summa,2);
  if (!$db->sql_query('UPDATE '.SCIF_PREFIX.'doc SET
   `date_update`='.$time_x.', `user_update`="'.$api_settings['invoice']['user_insert'].'",
   `summa`="'.$new_summa.'"
   WHERE id="'.$id.'"')) {
   throw new Exception('Ошибка обновления документа '.$id.': '.$db->sql_error());
  }
  // Заказ покупателя, пересчет резервов
  if (function_exists('my_reserve_recalc')) {
   my_reserve_recalc($id);
  }
  $resp=array('id'=>$id,'summa'=>$new_summa);
 break;

 case 'list': // =========== просмотр списка заказов ============
   if ($contr<=0) {
    throw new Exception('Не передан ID контрагента');
   }
  $status=(isset($input['status'])?intval($input['status']):-1); // 0-Новый,-1 в работе
  $res=$db->sql_query('SELECT d.id, d.doc_date AS `date`, d.summa, d.type, d.finitem AS status, f.name AS status_name
  FROM '.SCIF_PREFIX.'doc d
  LEFT JOIN '.SCIF_PREFIX.'spr_finitems f ON d.finitem=f.id
  WHERE d.contr="'.$contr.'" AND d.type IN (2,11)'.($status>=0?' AND d.finitem="'.$status.'"':'')
  .' ORDER BY d.id
  LIMIT 100');
  $resp['count']=$db->sql_num_rows($res);
   if (!$resp['count']) {
   throw new Exception('Не найдено заказов в работе контрагента с ID '.$contr);
   }
   while ($row=$db->sql_fetch_assoc($res)) {
    if (!$row['status']) {
     $row['status_name']='Новый';
    }
    $resp['docs'][$row['id']]=$row;
   }
 break;

 case 'get': // получение заказа по ID
  if ($id<=0) {
   throw new Exception('Не передан ID заказа');
  }
  if ($contr<=0) {
   throw new Exception('Не передан ID контрагента');
  }
  $row=$db->sql_fetch_assoc($db->sql_query('SELECT d.id, d.doc_date AS `date`, d.summa, d.type, d.finitem AS status, f.name AS status_name
  FROM '.SCIF_PREFIX.'doc d
  LEFT JOIN '.SCIF_PREFIX.'spr_finitems f ON d.finitem=f.id
  WHERE d.id="'.$id.'" AND d.type IN (2,11) AND d.contr="'.$contr.'"'));
   if (!$row) {
    throw new Exception('Не найден заказ с номером '.$id);
   }
   if (!$row['status']) {
    $row['status_name']='Новый';
   }
   $resp=$row;
   // детали документа
   $res_det=$db->sql_query('SELECT nom_id AS code, quant, price FROM '.SCIF_PREFIX.'doc_det WHERE doc_id="'.$id.'"');
   while ($row_det=$db->sql_fetch_assoc($res_det)) {
    $arr=array('id'=>(int)$row_det['code'],'quant'=>(float)$row_det['quant'],'price'=>(float)$row_det['price']);
    $resp['items'][]=$arr;
   }
 break;

 default:
  throw new Exception('Неизвестный метод '.$method);
 break;
}