# ShopBot BD Laravel 12 Backend

REST API backend for ShopBot BD, built from the uploaded product documentation.

## Included

- Laravel 12 source structure
- Laravel Sanctum token authentication
- Multi-tenant business data isolation by `business_id`
- RBAC roles and permissions: owner, manager, staff, delivery staff, viewer
- Product, category, stock movement and low-stock APIs
- Customer database and customer order history
- Order, order items, payment, delivery, status history APIs
- Invoice snapshot + HTML invoice generation ready for PDF service
- WhatsApp prefilled message link endpoint
- Reports: dashboard, sales, product, customer, payment, delivery, low stock
- AI caption/reply endpoints with external AI API fallback and generation logs
- Rule-based AI insights: best seller, low stock, slow-moving, repeat customer, anomaly
- Subscription plans and payment history
- Super admin business monitoring endpoints
- Seed data: plans, roles, permissions, message templates, demo business

## Requirements

- PHP 8.2+
- Composer
- MySQL 8+ or MariaDB 10+

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Default seeded login:

```text
login: owner@shopbotbd.test
password: password
```

## Frontend connection

Base URL:

```text
http://localhost:8000/api
```

Use `Authorization: Bearer <token>` after login/register. For business-specific requests, pass one of:

```text
X-Business-ID: 1
```

or omit it to auto-select the first active business for the logged-in user.

## Core endpoint map

```text
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
GET    /api/business
PUT    /api/business
GET    /api/staff
POST   /api/staff
GET    /api/categories
POST   /api/categories
GET    /api/products
POST   /api/products
GET    /api/products/low-stock
GET    /api/customers
POST   /api/customers
GET    /api/orders
POST   /api/orders
PUT    /api/orders/{id}/status
PUT    /api/orders/{id}/payment-status
PUT    /api/orders/{id}/delivery-status
POST   /api/orders/{id}/payments
GET    /api/orders/{id}/invoice
POST   /api/orders/{id}/invoice/generate
GET    /api/orders/{id}/whatsapp-message
GET    /api/reports/dashboard
GET    /api/reports/sales
POST   /api/ai/caption
POST   /api/ai/reply
GET    /api/ai/insights
POST   /api/ai/insights/generate
GET    /api/plans
GET    /api/subscription
POST   /api/subscription/change
```

## Example create order payload

```json
{
  "customer": {"name": "Rahim", "phone": "01711111111", "address": "Dhaka"},
  "order_source": "facebook",
  "discount_amount": 0,
  "delivery_charge": 60,
  "paid_amount": 0,
  "items": [
    {"product_id": 1, "quantity": 2}
  ]
}
```

## Notes

- Stock is deducted when an order becomes `confirmed`, `processing`, `packed`, `shipped`, or `delivered`.
- Stock is returned when an order becomes `cancelled` or `returned`.
- Invoice snapshot is stored so future product price changes do not alter old invoices.
- If `AI_API_KEY` and `AI_ENDPOINT` are empty, the AI endpoints return safe fallback text and still log usage.
