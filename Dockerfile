FROM ubuntu:14.04
MAINTAINER Anjesh Tuladhar <anjesh@yipl.com.np>

RUN apt-get update && apt-get install -y \
    curl \
    git \
    wget \
    apache2 \
    php5 \
    php5-cli \
    php5-curl \
    php5-mcrypt \
    php5-readline \
 && rm -rf /var/lib/apt/lists/*

COPY conf/rc-index.conf /etc/apache2/sites-available/rc-index.conf
RUN ln -s /etc/apache2/sites-available/rc-index.conf /etc/apache2/sites-enabled/rc-index.conf \
 && rm -f /etc/apache2/sites-enabled/000-default.conf

RUN a2enmod rewrite \
 && a2enmod php5 \
 && ln -s /etc/php5/mods-available/mcrypt.ini /etc/php5/apache2/conf.d/20-mcrypt.ini \
 && ln -s /etc/php5/mods-available/mcrypt.ini /etc/php5/cli/conf.d/20-mcrypt.ini


COPY . /var/www/rc-index
WORKDIR /var/www/rc-index

RUN curl -s http://getcomposer.org/installer | php \
 && php composer.phar install --prefer-dist \
 && php composer.phar dump-autoload --optimize \
 && touch /var/www/rc-index/.env

EXPOSE 80
CMD /usr/sbin/apache2ctl -D FOREGROUND

