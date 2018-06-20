#!/bin/bash

if [ $(phpenv version-name) != "hhvm" ]; then
    cp tests/extensions/$(phpenv version-name)/*.so $(php-config --extension-dir)

    phpenv config-add tests/conf/memcached.ini
    phpenv config-add tests/conf/redis.ini

    phpenv config-add tests/conf/timezone.ini
fi

composer install --no-interaction --ignore-platform-reqs
composer update
