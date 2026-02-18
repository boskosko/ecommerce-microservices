FROM webdevops/php-nginx:8.3-alpine

# Install PostgreSQL extension and Supervisor
RUN apk add --no-cache postgresql-dev php83-pgsql php83-pdo_pgsql supervisor

WORKDIR /app

# Copy application files
COPY . /app

# Configure PHP-FPM timeout
RUN echo "request_terminate_timeout = 30" >> /usr/local/etc/php-fpm.d/application.conf

# Fix storage permissions for Laravel
RUN chown -R application:application /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Copy Supervisor config for RabbitMQ consumer
COPY docker/supervisord-rabbitmq.conf /opt/docker/etc/supervisor.d/rabbitmq-consumer.conf

# Set general permissions
RUN chown -R application:application /app
