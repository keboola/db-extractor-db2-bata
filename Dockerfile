FROM php:5.6-fpm
MAINTAINER Miro Cillik <miro@keboola.com>

# Install dependencies
RUN apt-get update && apt-get install -y \
    apt-utils \
    zip \
    unzip \
    unixodbc-dev \
    curl

# Install PHP odbc extension
RUN set -x \
    && cd /usr/src/php/ext/odbc \
    && phpize \
    && sed -ri 's@^ *test +"\$PHP_.*" *= *"no" *&& *PHP_.*=yes *$@#&@g' configure \
    && ./configure --with-unixODBC=shared,/usr \
    && docker-php-ext-install odbc

RUN docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr
RUN docker-php-ext-install pdo_odbc

# Install IBM iAccessSeries app package
RUN mkdir -p /opt/ibm
WORKDIR /opt/ibm
ADD driver/ibm-iaccess-1.1.0.5-1.0.amd64.deb /opt/ibm/
RUN dpkg -i *.deb
RUN cp /opt/ibm/iSeriesAccess/lib64/* /usr/lib

RUN echo "/opt/ibm/iSeriesAccess/lib64/" >> /etc/ld.so.conf.d/iSeriesAccess.conf
RUN ldconfig
RUN odbcinst -i -d -f /opt/ibm/iSeriesAccess/unixodbcregistration

# Install Composer dependencies
ADD . /code
WORKDIR /code
RUN curl -sS https://getcomposer.org/installer | php
RUN echo "memory_limit = -1" >> /etc/php.ini
RUN php composer.phar install --no-interaction

# Run app
ENTRYPOINT php ./run.php --data=/data
