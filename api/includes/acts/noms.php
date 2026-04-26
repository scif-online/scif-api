<?php
// Работа с товарами
switch ($method) {
 case 'search': // ================ поиск товаров ==================
  $filter_name=(!empty($input['query'])?trim($input['query']):'');
  if ($filter_name) {
   $filter_name=mb_substr(trim(preg_replace("/[^a-zA-Zа-яА-ЯЁёЇїІіЄєҐґ'0-9_]/u", " ", $filter_name)), 0, 30);
  }
   if (!$filter_name) {
    throw new Exception('Не задан текст поискового запроса');
   }
  // проверим поисковые вхождения групп
  $groups_search_words='';
  $res=$db->sql_query('SELECT g1.id, g1.search_words
  FROM '.SCIF_PREFIX.'spr_noms_gr g1
  WHERE g1.search_words!="" AND g1.my_site_opt="1"');
  if ($db->sql_num_rows($res)) {
   while ($row=$db->sql_fetch_assoc($res)) {
    $arr=explode(',',$row['search_words']);
    foreach ($arr AS $word) {
     $word=trim($word);
     if ($word AND mb_stripos($filter_name,$word)!==false) {
      $groups_search_words.=$row['id'].',';
      break;
     }
    }
   }
  }
  $sql='SELECT n.id, n.name, n.price'.SCIF_CATALOG_PRICE.' AS price, n.logo, n.chpu,
  n.is_service, '.$api_settings['sql_store'].' AS store 
  FROM '.SCIF_PREFIX.'spr_noms n
  LEFT JOIN '.SCIF_PREFIX.'spr_noms_gr g1 ON n.parent=g1.id
  WHERE (n.name LIKE "%'.str_replace(' ','%',$filter_name).'%" 
  OR g1.name LIKE "%'.str_replace(' ','%',$filter_name).'%"'
  .($groups_search_words?' OR g1.id IN ('.substr($groups_search_words,0,-1).')':'')
  .') AND '.$api_settings['sql_store'].'>0
  ORDER BY n.name LIMIT 100';
  $res=$db->sql_query($sql);
  $resp['count']=$db->sql_num_rows($res);
  if (!$resp['count']) {
   throw new Exception('Не найдено товаров по данному запросу!');
  }
  while ($row=$db->sql_fetch_assoc($res)) {
   // ключ будет строковым, т.к. для JSON это объект
   $items[$row['id']]=array(
   'id'=>(int)$row['id'],
   'name'=>$row['name'],
   'price'=>(float)$row['price'],
   'stock'=>(float)$row['store'],
   'image'=>($row['logo']?WN_HOME.logo_url('sp_noms',$row['id'],true):''),
   'url'=>$home.'catalog/product/'.$row['chpu'].'/'
   );
  }
 $resp['items']=$items;
 break;

 case 'stock': // ====================== все товары в наличии ===================
  $from=(!empty($input['from'])?intval($input['from']):0);
  $limit=((!empty($input['limit']) and intval($input['limit'])>0)?intval($input['limit']):0);
  $sql='SELECT '.($limit?'SQL_CALC_FOUND_ROWS ':'')
  .' n.id, n.name, '.$api_settings['price'].' AS price, '.$api_settings['sql_store'].' AS store, n.barcode
  FROM '.SCIF_PREFIX.'spr_noms n
  WHERE '.$api_settings['sql_store'].'>0
  ORDER BY n.id'
  .($limit?' LIMIT '.($from?$from.',':'').$limit:'');
  $res=$db->sql_query($sql);
   if ($limit) {
    $resp['count']=$db->sql_result($db->sql_query('SELECT FOUND_ROWS()'));
   } else {
    $resp['count']=$db->sql_num_rows($res);
   }
  if (!$resp['count']) {
   throw new Exception('Не найдено товаров по данному запросу!');
  }
  while ($row=$db->sql_fetch_assoc($res)) {
   // ключ будет строковым, т.к. для JSON это объект
   $items[$row['id']]=array(
   'id'=>(int)$row['id'],
   'name'=>$row['name'],
   'barcode'=>$row['barcode'],
   'price'=>(float)$row['price'],
   'stock'=>(float)$row['store']
   );
  }
 $resp['items']=$items;
 break;

 case 'get': // получение товара
  if ($id<=0) {
   throw new Exception('Не передан ID товара');
  }
 break;

 case 'categories': // ======= список категорий ========
  $sql='SELECT g1.id, g1.name, g1.parent
  FROM '.SCIF_PREFIX.'spr_noms_gr g1
  ORDER BY g1.sort, g1.name';
  $res=$db->sql_query($sql);
  $resp['count']=$db->sql_num_rows($res);
  while ($row=$db->sql_fetch_assoc($res)) {
   // ключ будет строковым, т.к. для JSON это объект
   $items[$row['id']]=array(
   'id'=>(int)$row['id'],
   'name'=>$row['name'],
   'parent'=>(int)$row['parent']
   );
  }
  $resp['items']=$items;
  break;

 default:
  throw new Exception('Неизвестный метод '.$method);
 break;
}