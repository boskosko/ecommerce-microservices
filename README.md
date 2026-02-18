
### Prerequisites
- Docker & Docker Compose

### Run the Platform
```bash
cd ecommerce-microservices
docker compose up -d
import postman env and collection json for testing
```
### Run Database Migrations
```bash
docker exec -it user-service php artisan migrate
docker exec -it product-service php artisan migrate
docker exec -it order-service php artisan migrate
docker exec -it notification-service php artisan migrate
```
> **Note:** TODO:Migration automation

**That's it!** All services are now running with automatic:
- RabbitMQ consumers (product sync, order events, payment processing)
- Email notifications via Mailtrap
- Stripe payment processing

### Access Points
- **User Service**: http://localhost:8001
- **Product Service**: http://localhost:8002
- **Order Service**: http://localhost:8003
- **Payment Service**: http://localhost:8004
- **Notification Service**: http://localhost:8005
- **RabbitMQ Dashboard**: http://localhost:15672 (admin/secret)

## Architecture

### Services
| Service | Tech          | Database | Purpose |
|---------|---------------|----------|---------|
| User | Laravel 12    | MySQL | Authentication, user management |
| Product | Laravel 12    | MongoDB | Product catalog |
| Order | Laravel 12    | PostgreSQL | Order processing |
| Payment | Spring Boot 4 | MySQL | Stripe payment integration |
| Notification | Laravel 11    | - | Email notifications (Mailtrap) |

### Technologies
- **Backend**: Laravel 11,12 (PHP 8.3), Spring Boot 4 (Java 21)
- **Databases**: MySQL, MongoDB, PostgreSQL
- **Message Broker**: RabbitMQ
- **Containerization**: Docker & Docker Compose
- **Payment**: Stripe API
- **Email**: Mailtrap

## Event Flow
```
Create Order → RabbitMQ
    ↓
┌───────┼───────┬────────┐
↓       ↓       ↓        ↓
Product Payment Notification
(stock) (Stripe) (email)
```

## Test the Platform

### Prerequisites
Import Postman collection and environment:
- `postman/E-commerce-Microservices.postman_collection.json`
- `postman/E-commerce-Microservices.postman_environment.json`

### Testing Flow

**1. Create a Product** (Product Service)
```
POST http://localhost:8002/api/products
```
Use Postman collection: `Product Service → Create Product`

**2. Create an Order** (Order Service)
```
POST http://localhost:8003/api/v1/orders
```
Use Postman collection: `Order Service → Create Order`

**What happens automatically:**
1. ✅ Order created in database
2. ✅ Stock automatically decremented (Product Service Consumer)
3. ✅ Payment processed via Stripe API (Payment Service Consumer)
4. ✅ Email notification sent to Mailtrap (Notification Service Consumer)

**3. Verify Results**
- **RabbitMQ Dashboard**: http://localhost:15672 (admin/secret) - see events flowing
- **Stripe Dashboard**: https://dashboard.stripe.com/test/payments - see payment intent
- **Mailtrap Inbox** - see order confirmation email


## Configuration

### Stripe API Key
Add your Stripe test API key in `services/payment-service/src/main/resources/application.properties`:
```properties
stripe.api.key=placeholder
```

### Mailtrap
Add your Mailtrap credentials in `services/notification-service/.env`:
```env
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
```

## 📝 License
MIT