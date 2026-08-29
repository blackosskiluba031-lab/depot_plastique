FROM php:8.2-apache

# Installer les extensions PDO MySQL nécessaires
RUN docker-php-ext-install pdo pdo_mysql

# Activer le module Apache Rewrite
RUN a2enmod rewrite

# Copier tous les fichiers du projet
COPY . /var/www/html/

# Configurer les permissions
RUN chown -R www-data:www-data /var/www/html

# Port par défaut
ENV PORT=80
EXPOSE 80

CMD ["apache2-foreground"]
