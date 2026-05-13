# Feature Coverage Checklist

## MVP Modules

- [x] Authentication and business setup
- [x] Business dashboard APIs
- [x] Product management
- [x] Category management
- [x] Customer management
- [x] Order management
- [x] Order item management
- [x] Payment status tracking
- [x] Delivery status tracking
- [x] Invoice generation and snapshot
- [x] Stock update and stock movements
- [x] Low stock alert/report
- [x] WhatsApp message templates and prefilled link
- [x] Basic sales reports
- [x] Basic AI caption generator
- [x] Basic AI reply suggestion
- [x] Rule-based AI business insights
- [x] Subscription plan structure
- [x] Role-based access control
- [x] Super admin endpoints

## Security

- [x] Laravel Sanctum token authentication
- [x] Password hashing
- [x] RBAC permission middleware
- [x] Business tenant isolation using `business_id`
- [x] Soft delete on important data
- [x] Audit-log table included
- [x] Input validation on API writes
- [x] CORS config for Next.js frontend

## Business Logic

- [x] subtotal = sum line totals
- [x] total = subtotal - discount + delivery charge
- [x] due = total - paid
- [x] stock deducted after confirmed/processing/packed/shipped/delivered
- [x] stock returned after cancelled/returned
- [x] order status history saved
- [x] invoice snapshot saved
- [x] customer totals updated
- [x] AI generation history saved
