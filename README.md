
### Prerequisites
- Docker & Docker Compose

### Run the Platform
```bash
cd ecommerce-microservices
docker compose up -d
start php artisan migrate in user,product,order,notification service, docker exec -it xxxx-service bash -> app
#TODO automate this step
import postman env and collection json for testing
```

**That's it!** All services are now running with automatic:
- Database migrations
- RabbitMQ consumers (product sync, order events, payment processing)
- Email notifications
- Mailtrap.io https://mailtrap.io/inboxes/
- Stripe dashboard https://dashboard.stripe.com/xxxxxx/test/payments

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

### Testing Flow - import postman JSON collection and env

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

## 📝 License
MIT