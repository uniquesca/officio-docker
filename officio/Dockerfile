FROM php:8.1-fpm

# ENV variables
ARG CLIENT_CONFIG 
ARG GIT_PERSONAL_ACCESS_TOKEN
ARG NPM_PERSONAL_ACCESS_TOKEN
ENV CLIENT_CONFIG ${CLIENT_CONFIG}
ENV GIT_PERSONAL_ACCESS_TOKEN ${GIT_PERSONAL_ACCESS_TOKEN}
ENV NPM_PERSONAL_ACCESS_TOKEN ${NPM_PERSONAL_ACCESS_TOKEN}

# php requirements 
RUN apt update && apt install -y libc-client-dev libkrb5-dev && rm -r /var/lib/apt/
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl && docker-php-ext-install imap
RUN pecl install mailparse && docker-php-ext-enable mailparse
RUN apt-get update && apt-get install -y \
        libldap2-dev \
        libpq-dev \
        libicu-dev \
        libtidy-dev \
        libpng-dev \
        zip \
        cron \
        vim \
        git \
        libzip-dev \
    && docker-php-ext-configure zip\
    && docker-php-ext-install zip \
    && docker-php-ext-install gettext \
    && docker-php-ext-install calendar \
    && docker-php-ext-install gd \
    && docker-php-ext-install tidy \
    && docker-php-ext-install intl \
    && docker-php-ext-install pdo \
    && docker-php-ext-install sockets \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www

# Copy existing application directory contents
COPY app /var/www

RUN chmod 755 /var/www/officio.phar

# Select Officio application type
RUN ./officio.phar select_project ${CLIENT_CONFIG}

COPY config/.npmrc .npmrc

RUN echo '@uniquesca:registry=https://npm.pkg.github.com //npm.pkg.github.com/:_authToken="${GIT_PERSONAL_ACCESS_TOKEN}" always_auth=true' >> .npmrc

RUN echo '{  "github-oauth" : {  "github.com": "'"${GIT_PERSONAL_ACCESS_TOKEN}"'" } }' >> auth.json

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Composer setup
RUN composer config -g github-oauth.github.com -a ${GIT_PERSONAL_ACCESS_TOKEN}

RUN composer install

RUN composer dump-autoload

RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.3/install.sh | bash

# Node installation and vendor folder
RUN curl -sL https://deb.nodesource.com/setup_16.x | bash -

RUN apt-get install -y nodejs

RUN npm install -g npm@latest

RUN npm install -g yarn

RUN corepack enable

RUN npm i -g corepack

RUN yarn install

RUN find . -type d -exec chmod 0755 {} \;

RUN find . -type f -exec chmod 0644 {} \;

# Make directories writable by the webserver and update permissions
RUN chmod -R 775 backup/
RUN chmod -R 775 data/
RUN chmod -R 775 data/pdf 
RUN chmod -R 775 data/xod
RUN chmod -R 775 var/cache/
RUN chmod -R 775 var/log/
RUN chmod -R 775 var/log/payment/
RUN chmod -R 775 var/log/xfdf/
RUN chmod -R 775 var/pdf_tmp/
RUN chmod -R 775 var/tmp/
RUN chmod -R 775 var/tmp/uploads/
RUN chmod -R 775 var/tmp/lock/
RUN chmod -R 775 public/captcha/images/
RUN chmod -R 775 public/website/
RUN chmod -R 775 public/email/data/
RUN chmod -R 775 public/help_files/
RUN chmod -R 775 vendor/bin/phinx
RUN chmod -R 775 vendor/robmorgan/phinx/bin/phinx

# Change owner of project to www-data
RUN chown -R www-data:www-data /var/www

# Cron jobs

# Expose port 9000 and start php-fpm server
EXPOSE 9000

CMD ["php-fpm"]