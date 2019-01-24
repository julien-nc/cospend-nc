#!/bin/bash

# We need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

export DEBIAN_FRONTEND=noninteractive
apt-get update -yqq
apt-get install git sudo php-xdebug php7.2-sqlite3 php7.2-curl php7.2-xml php7.2-mbstring php7.2-json php7.2-zip php7.2-gd php7.2-intl unzip curl wget nodejs npm sed -yqq > /dev/null 2>&1
npm install -g jshint > /dev/null 2>&1

curl --location --output /usr/local/bin/phpunit https://phar.phpunit.de/phpunit.phar
chmod +x /usr/local/bin/phpunit

