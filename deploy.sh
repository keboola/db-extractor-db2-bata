#!/bin/bash
docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/db-extractor-db2-bata quay.io/keboola/db-extractor-db2-bata:$TRAVIS_TAG
docker images
docker push quay.io/keboola/db-extractor-db2-bata:$TRAVIS_TAG
