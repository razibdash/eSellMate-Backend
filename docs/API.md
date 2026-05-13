# ShopBot BD REST API Quick Docs

All protected endpoints require:

```http
Authorization: Bearer TOKEN
Accept: application/json
X-Business-ID: 1
```

## Auth

### Register
`POST /api/auth/register`

```json
{
  "name":"Owner",
  "email":"owner@example.com",
  "phone":"01700000000",
  "password":"password",
  "password_confirmation":"password",
  "business_name":"Fresh Achar House"
}
```

### Login
`POST /api/auth/login`

```json
{"login":"owner@example.com","password":"password","device_name":"web"}
```

## Products

`GET /api/products?q=garlic&status=active`

`POST /api/products`

```json
{"name":"Garlic Pickle","sku":"GP-001","price":250,"stock_quantity":20,"low_stock_alert":5,"unit":"jar"}
```

## Customers

`GET /api/customers?q=017`

`POST /api/customers`

```json
{"name":"Rahim","phone":"01711111111","address":"Dhaka"}
```

## Orders

`POST /api/orders`

```json
{
  "customer_id": 1,
  "order_source": "facebook",
  "order_status": "pending",
  "delivery_charge": 60,
  "paid_amount": 0,
  "items": [{"product_id": 1, "quantity": 2}]
}
```

`PUT /api/orders/{id}/status`

```json
{"order_status":"confirmed","note":"Customer confirmed"}
```

`POST /api/orders/{id}/payments`

```json
{"payment_method":"bkash","amount":500,"transaction_id":"TXN123"}
```

## Reports

- `GET /api/reports/dashboard`
- `GET /api/reports/sales?date_from=2026-01-01&date_to=2026-01-31`
- `GET /api/reports/products`
- `GET /api/reports/customers`
- `GET /api/reports/payments`
- `GET /api/reports/delivery`
- `GET /api/reports/low-stock`

## AI

`POST /api/ai/caption`

```json
{"product_name":"Garlic Pickle","price":250,"tone":"friendly","language":"Banglish"}
```

`POST /api/ai/reply`

```json
{"message":"Price koto?","product_info":"Garlic Pickle 250 taka","language":"Banglish"}
```
