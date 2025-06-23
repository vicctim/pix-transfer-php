FROM php:8.2-apache

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libgd-dev \
    libmagickwand-dev \
    imagemagick \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mysqli \
    zip \
    gd \
    mbstring \
    xml \
    fileinfo

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar Apache
RUN a2enmod rewrite
RUN a2enmod headers

# Configurar PHP para uploads grandes
RUN echo "upload_max_filesize = 10G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 10G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 3600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 3600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 2G" >> /usr/local/etc/php/conf.d/uploads.ini

# Criar diretórios necessários
RUN mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/logs

# Configurar Apache para uploads grandes
RUN echo "LimitRequestBody 10737418240" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html 