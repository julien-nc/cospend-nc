#!/bin/bash

# We need to install dependencies only for Docker
[[ ! -e /.dockerenv ]] && exit 0

set -xe

export DEBIAN_FRONTEND=noninteractive
apt-get update -yqq
apt-get install git sudo php-xdebug php7.3-sqlite3 php7.3-curl php7.3-xml php7.3-mbstring php7.3-json php7.3-zip php7.3-gd php7.3-intl unzip curl wget nodejs npm sed -yqq > /dev/null 2>&1
npm install -g jshint > /dev/null 2>&1

curl --location --output /usr/local/bin/phpunit https://phar.phpunit.de/phpunit.phar
chmod +x /usr/local/bin/phpunit

