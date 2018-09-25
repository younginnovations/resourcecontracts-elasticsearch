FROM php:7.1.22-apache-jessie
MAINTAINER Anjesh Tuladhar <anjesh@yipl.com.np>

RUN apt-get update && apt-get install -y \
    curl \
    git \
    wget \
    zip \
    unzip \
    gcc \
    make \
    autoconf \
    libc-dev \
    pkg-config \
    libmcrypt-dev \
    supervisor \
    gettext \
 && rm -rf /var/lib/apt/lists/* \
 && curl -O -L https://github.com/papertrail/remote_syslog2/releases/download/v0.19/remote_syslog_linux_amd64.tar.gz \
 && tar -zxf remote_syslog_linux_amd64.tar.gz \
 && cp remote_syslog/remote_syslog /usr/local/bin \
 && rm -r remote_syslog_linux_amd64.tar.gz \
 && rm -r remote_syslog

COPY conf/rc-index.conf /etc/apache2/sites-available/rc-index.conf
RUN ln -s /etc/apache2/sites-available/rc-index.conf /etc/apache2/sites-enabled/rc-index.conf \
 && rm -f /etc/apache2/sites-enabled/000-default.conf

RUN a2enmod rewrite \
 && mkdir -p /var/container_init \
 && mkdir -p /var/www/rc-index \
 && mkdir -p /var/log/supervisor \
 && mkdir -p /var/www/rc-index/logs

# Configure PHP
#RUN sed -i "s/^post_max_size =.*/post_max_size = 5120M/" /etc/php5/apache2/php.ini \
# && sed -i "s/^upload_max_filesize =.*/upload_max_filesize = 5120M/" /etc/php5/apache2/php.ini \
# && sed -i "s/^memory_limit =.*/memory_limit = 256M/" /etc/php5/apache2/php.ini

WORKDIR /var/www/rc-index

COPY conf/init.sh /var/container_init/init.sh
COPY conf/env.template /var/container_init/env.template
COPY conf/log_files.yml.template /var/container_init/log_files.yml.template
COPY composer.json /var/www/rc-index
COPY composer.lock /var/www/rc-index
COPY conf/supervisord.conf /etc/supervisord.conf

RUN curl -s http://getcomposer.org/installer | php \
 && php composer.phar install --prefer-dist --no-scripts --no-autoloader

COPY . /var/www/rc-index

RUN php composer.phar dump-autoload --optimize \
 && chown -R www-data: /var/www/rc-index

EXPOSE 80
CMD cd /var/container_init && ./init.sh && /usr/bin/supervisord -c /etc/supervisord.conf && /usr/sbin/apache2ctl -D FOREGROUND
