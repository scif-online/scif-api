# СКИФ API

REST API для интеграции с системой складского и финансового учёта [СКИФ](https://www.webnice.biz/online-scif/).

**[📖 Документация с примерами →](https://www.webnice.biz/swagger/)**

---

## Что такое СКИФ

СКИФ (Склад, Коммерция, Интернет, Финансы) — self-hosted система складского и финансового учёта на PHP+MySQL. Устанавливается на ваш сервер или сайт. Включает CRM, обработку заказов, интеграцию с интернет-магазином и маркетплейсами.

Модуль API позволяет подключать к СКИФ внешние системы: мобильные приложения, чат-боты, ИИ-агентов, сторонние сайты.

- О СКИФ: [webnice.biz/online-scif/](https://www.webnice.biz/online-scif/)
- Модуль API: [webnice.biz/catalog/product/api-scif/](https://www.webnice.biz/catalog/product/api-scif/)
- Демо-база: [demo.webnice.biz/scif/](https://demo.webnice.biz/scif/)

---

## Возможности API

Здесь представлены лишь примеры работы с API. 
Фактический набор функционала определяется индивидуально для каждого клиента по его запросу.

---

## Настройки API

Для активации API на вашем сайте загрузите скрипты на ваш сайт в папку /api/ и 
внесите в файл настроек wn_settings.php следующий код:

```php
// API СКИФ. Можно указать несколько токенов с разными настройками
$api=array('tokens'=>array('задайте ваш токен'=>array(
'price'=>'n.price'.SCIF_CATALOG_PRICE, // продажная цена
'sql_store'=>SQL_STORE, // остаток на складе
'invoice'=>$wn_catalog_invoice // данные для создания заказа
)));
```

---

## Аутентификация

API поддерживает два способа передачи токена:

**Заголовок Authorization:**
```
Authorization: ваш-токен
```

**GET-параметр:**
```
GET /api/noms/stock/?token=ваш-токен
```

---

## Примеры использования

### Получить список товаров в наличии

```bash
curl -X GET "https://ВАШ-ДОМЕН/api/noms/stock/" \
  -H "Authorization: ваш-токен" \
  -H "Content-Type: application/json"
```

Ответ:
```json
{
  "count": 2,
  "items": {
    "101": { "id": 101, "name": "Цемент М400 50кг", "price": 450.0, "stock": 120.0, "barcode": "4607123456781" },
    "102": { "id": 102, "name": "Цемент М500 50кг", "price": 520.0, "stock": 85.0,  "barcode": "" }
  }
}
```

### Создать заказ

```bash
curl -X POST "https://ВАШ-ДОМЕН/api/doc/add/" \
  -H "Authorization: ваш-токен" \
  -H "Content-Type: application/json" \
  -d '{
    "fio_from": "Иван Петров",
    "phone_from": "89991234567",
    "date_delivery": "15.06.2025",
    "address_delivery": "г. Москва, ул. Ленина, д. 1",
    "items": {
      "101": { "quant": 2 },
      "102": { "quant": 5 }
    }
  }'
```

Ответ:
```json
{ "id": 692, "summa": 3650.00, "order_num": 13 }
```

---

## Спецификация OpenAPI

Полная спецификация в формате OpenAPI 3.0 находится в файле [`openapi.yaml`](./openapi.yaml).

---

## Swagger документация

**[→ Swagger документация](https://www.webnice.biz/swagger/)**
