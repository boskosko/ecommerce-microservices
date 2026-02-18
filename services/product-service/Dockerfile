FROM webdevops/php-nginx:8.3-alpine

# Install MongoDB PHP extension and Supervisor
RUN apk add --no-cache php83-pecl-mongodb supervisor

WORKDIR /app

# Copy application files
COPY . /app

# Fix storage permissions for Laravel
RUN chown -R application:application /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Copy Supervisor config for RabbitMQ consumer
COPY docker/supervisord-rabbitmq.conf /opt/docker/etc/supervisor.d/rabbitmq-order-consumer.conf

# Set general permissions
RUN chown -R application:application /app
