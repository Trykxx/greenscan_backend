# Utilise l'image officielle PHP 8.2 avec Apache préinstallé
FROM php:8.2-apache

# Définit le répertoire de travail dans le conteneur (où sera copié le code Laravel)
WORKDIR /var/www/html

# Active le module "rewrite" d'Apache (nécessaire pour les URLs propres de Laravel)
RUN a2enmod rewrite

# Met à jour les paquets et installe les dépendances système nécessaires :
RUN apt-get update -y && apt-get install -y \
    libicu-dev \
    libmariadb-dev \
    unzip zip \
    zlib1g-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libjpeg62-turbo-dev

# Copie un fichier de configuration Apache personnalisé (pour adapter le serveur web à Laravel)
# Remplace la configuration par défaut d'Apache
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Copie l'exécutable "composer" depuis l'image officielle Composer
# Permet d'utiliser Composer directement dans le conteneur
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Installe les extensions PHP nécessaires :
RUN docker-php-ext-install gettext intl pdo_mysql gd

# Configure et installe l'extension GD avec des options spécifiques :
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd