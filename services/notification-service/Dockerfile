FROM webdevops/php-nginx:8.3-alpine

# Install Supervisor
RUN apk add --no-cache supervisor

WORKDIR /app

# Copy application files
COPY . /app

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Fix storage permissions for Laravel
RUN chown -R application:application /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Copy Supervisor config for RabbitMQ consumer
COPY docker/supervisord-rabbitmq.conf /opt/docker/etc/supervisor.d/rabbitmq-consumer.conf

# Set general permissions
RUN chown -R application:application /app
