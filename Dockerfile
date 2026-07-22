FROM php:8.4-apache

# 1. Instalar dependências do sistema e extensões do PHP necessárias
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configurar e instalar extensões do PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache

# 2. Habilitar o mod_rewrite do Apache
RUN a2enmod rewrite

# 3. Configurar a pasta pública do Laravel como raiz do Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Instalar o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Definir diretório de trabalho
WORKDIR /var/www/html

# 6. Copiar os arquivos do projeto
COPY . .

# 7. Executar a instalação das dependências do Laravel
# Nota: --no-scripts é opcional, mas evita erros em builds onde o banco não está disponível ainda
RUN composer install --no-interaction --no-dev --optimize-autoloader

# 8. Dar permissões de escrita para pastas de armazenamento
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 9. Expor a porta 80
EXPOSE 80

# 10. Script de inicialização automática no container
CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan migrate --force && apache2-foreground"]
