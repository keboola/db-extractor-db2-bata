#!/usr/bin/env bash

# install dependencies
composer install -n;

# run test suite
export ROOT_PATH="/code";
./vendor/bin/phpunit;
