# Image unique, volontairement minimale : le serveur de développement PHP
# intégré (php -S, déjà utilisé par `composer start` hors Docker) sert de
# serveur HTTP, sans Nginx/PHP-FPM supplémentaire à configurer. Cohérent
# avec l'objectif "simplicité et commodité" du starter — voir DOCKER.md
# pour les limites de ce choix (ne pas utiliser tel quel en production).
FROM php:8.3-cli-alpine

# - mariadb-connector-c-dev : en-têtes nécessaires à la compilation de
#   l'extension pdo_mysql (Doctrine DBAL en a besoin pour parler à MariaDB).
# - unzip, git : nécessaires à Composer pour installer les dépendances
#   (image php-alpine ne fournit ni l'extension zip, ni ces binaires).
RUN apk add --no-cache unzip git mariadb-connector-c-dev \
    && docker-php-ext-install pdo_mysql

# Composer est copié depuis son image officielle plutôt qu'installé via un
# script shell : plus rapide, plus simple à maintenir à jour (changer le tag).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --no-progress --optimize-autoloader

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
