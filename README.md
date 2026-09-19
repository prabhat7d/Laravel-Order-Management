# Laravel Order Management API

A production-oriented Order Management API built with Laravel, focusing on clean architecture, reliable order processing, stock management, payment handling, idempotency, and order lifecycle tracking.

## Tech Stack

- Laravel 13
- PHP 8.4
- MySQL 8
- Laravel Sanctum
- PHPUnit
- Redis-ready architecture
- Queue Jobs
- OpenAPI / Swagger UI

## Features

- Sanctum authentication
- Product and order management
- Order creation with stock validation
- Database row locking for stock consistency
- Order status state machine
- Order cancellation with stock restoration
- Payment gateway abstraction
- Mock and failing payment gateways
- Payment attempt/history tracking
- Payment failure handling
- Idempotent order creation
- API rate limiting
- Order lifecycle logs
- Request validation
- Centralized API error handling
- Automated test coverage
- Interactive API documentation

## Architecture

The project follows a layered architecture:

```text
app/
├── Application/
│   └── Orders/
│
├── Domain/
│   ├── Orders/
│   ├── Payments/
│   └── Products/
│
├── Infrastructure/
│   ├── Orders/
│   ├── Payments/
│   └── Products/
│
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
│
├── Jobs/
├── Events/
├── Listeners/
├── Models/
└── Policies/
```
