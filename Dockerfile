FROM php:8.2-apache

# Install PHP MySQL extension & Python 3
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Create virtual environment in the exact path PHP expects
WORKDIR /var/www/html
COPY . /var/www/html/

RUN python3 -m venv /var/www/html/venv && \
    /var/www/html/venv/bin/pip install --no-cache-dir qrcode[pil] pandas reportlab mysql-connector-python

# Ensure Apache can write generated QR codes and PDF/CSV reports
RUN mkdir -p assets/qr_codes assets/reports && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 80