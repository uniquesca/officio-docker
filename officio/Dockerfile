FROM php:8.1-fpm

RUN docker-php-ext-install pdo

RUN docker-php-ext-install pdo_mysql

# Set working directory
WORKDIR /var/www

# Copy existing application directory contents
COPY app /var/www

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]