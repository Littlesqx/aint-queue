FROM php:7.4.6-cli

COPY ./ /var/www/

RUN \
    php -r "copy('https://install.phpcomposer.com/installer', 'composer-setup.php');" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/bin/composer && chmod +x "/usr/bin/composer"

RUN \
    apt install apt-transport-https ca-certificates && \
    echo "deb https://mirrors.tuna.tsinghua.edu.cn/debian/ buster main contrib non-free\n \
    deb https://mirrors.tuna.tsinghua.edu.cn/debian/ buster-updates main contrib non-free\n \
    deb https://mirrors.tuna.tsinghua.edu.cn/debian/ buster-backports main contrib non-free\n \
    deb https://mirrors.tuna.tsinghua.edu.cn/debian-security buster/updates main contrib non-free\n" > /etc/apt/sources.list

RUN \
    apt-get update              && \
    apt-get install -y             \
        libssl-dev                 \
        unzip                      \
        zlib1g-dev                 \
        --no-install-recommends && \
    rm -rf /var/lib/apt/lists/* /usr/bin/qemu-*-static

RUN \
    chmod +x /var/www/scripts/*.sh && \
    /var/www/scripts/install-swoole.sh 4.5.2

ENTRYPOINT ["./vendor/bin/aint-queue"]

WORKDIR "/var/www/"