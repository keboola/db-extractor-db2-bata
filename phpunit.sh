#!/usr/bin/env bash

# install dependencies
composer install -n;

# load data to database

# run test suite
export ROOT_PATH="/code";
./vendor/bin/phpunit;
