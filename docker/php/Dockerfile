FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    curl \
    ffmpeg \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && docker-php-ext-install pdo pdo_mysql mysqli \
  && rm -rf /var/lib/apt/lists/*
