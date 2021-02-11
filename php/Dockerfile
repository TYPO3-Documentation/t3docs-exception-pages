# @see https://github.com/docker-library/php/tree/20ebff40bb865416f3767540df06ad1df384337d/7.2/buster/fpm
FROM php:7.2.24-fpm-buster

ARG DEBIAN_FRONTEND=noninteractive

LABEL \
   Maintainer="TYPO3 Documentation Team" \
   Description="This image provides a Php FastCGI Process Manager for creating new TYPO3 Exception pages." \
   Vendor="t3docs"

# Install Composer prerequisites
RUN apt-get -q --no-allow-insecure-repositories update \
   && apt-get install --assume-yes --no-install-recommends \
         unzip \
   && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer \
    | php -- --version="1.10.20" --install-dir=/usr/bin --filename=composer

# Smoke test
RUN php --version

WORKDIR /var/www
