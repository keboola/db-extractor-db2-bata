FROM php:5.6-fpm
MAINTAINER Miro Cillik <miro@keboola.com>

# Install dependencies
RUN apt-get update && apt-get install -y \
    apt-utils \
    zip \
    unzip \
    unixodbc-dev \
    curl \
    alien

# Install PHP odbc extension
RUN set -x \
    && cd /usr/src/php/ext/odbc \
    && phpize \
    && sed -ri 's@^ *test +"\$PHP_.*" *= *"no" *&& *PHP_.*=yes *$@#&@g' configure \
    && ./configure --with-unixODBC=shared,/usr \
    && docker-php-ext-install odbc

# Install IBM iAccessSeries app package
RUN mkdir -p /opt/ibm
WORKDIR /opt/ibm
ADD driver/iSeriesAccess-6.1.0-1.0.x86_64.rpm /opt/ibm/
RUN alien /opt/ibm/iSeriesAccess-6.1.0-1.0.x86_64.rpm
RUN dpkg -i iseriesaccess_6.1.0-2_amd64.deb
RUN cp /opt/ibm/iSeriesAccess/lib64/* /usr/lib

RUN echo "/opt/ibm/iSeriesAccess/lib64/" >> /etc/ld.so.conf.d/iSeriesAccess.conf
RUN ldconfig
RUN odbcinst -i -d -f /opt/ibm/iSeriesAccess/unixodbcregistration64

# Install Composer dependencies
ADD . /code
WORKDIR /code
RUN curl -sS https://getcomposer.org/installer | php
RUN echo "memory_limit = -1" >> /etc/php.ini
RUN php composer.phar install --no-interaction

# Run app
ENTRYPOINT php ./run.php --data=/data
