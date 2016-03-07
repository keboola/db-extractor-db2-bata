#!/usr/bin/env bash

# install dependencies
php composer.phar install -n;

# run test suite
export ROOT_PATH="/code";
./vendor/bin/phpunit;
