FROM ubuntu:focal

# Fixes some weird terminal issues such as broken clear / CTRL+L
ENV TERM=linux

# Ensure apt doesn't ask questions when installing stuff
ENV DEBIAN_FRONTEND=noninteractive

## Install extensions and utils
RUN apt-get update && apt-get install -yqq  --no-install-recommends \
    build-essential software-properties-common \
    libmcrypt-dev libpq-dev libpng-dev libjpeg-dev libxml2-dev libbz2-dev \
    libreadline-dev libfreetype6-dev libpng-dev \
    git zip unzip curl wget vim libzip-dev apt-utils cron sudo \
    openssl libssl-dev libcurl4-openssl-dev gnupg \
    network-manager-openvpn network-manager-openvpn-gnome network-manager-vpnc \
    openvpn dnsutils net-tools \
    && service network-manager restart

# Install Ondrej repos for Ubuntu focal, PHP7.4, composer and selected extensions - better selection than the distro's packages
RUN echo "deb http://ppa.launchpad.net/ondrej/php/ubuntu focal main" > /etc/apt/sources.list.d/ondrej-php.list \
    && apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C \
    && apt-get update \
    && apt-get -y --no-install-recommends install \
        ca-certificates \
        curl unzip \
        php-apcu php-apcu-bc \
        php7.4-cli \
        php7.4-curl \
        php7.4-json \
        php7.4-mbstring \
        php7.4-opcache \
        php7.4-readline \
        php7.4-xml \
        php7.4-zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer global require hirak/prestissimo \
    && composer clear-cache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* ~/.composer

RUN apt-get clean && apt-get --purge autoremove -y


WORKDIR /var/www

# make `.env` available while building
COPY .env /var/www/.env
COPY scripts/vypr-ovpn-connect.sh /var/www/scripts/vypr-ovpn-connect.sh
RUN ./scripts/vypr-ovpn-connect.sh

#RUN sudo ufw allow from any to any port ${SOCKET_PORT} proto tcp
EXPOSE ${SOCKET_PORT}

RUN echo "Worker (Proxy Server) Ready!"
#CMD ["php", "php/socket-server.php"]

# If you'd like to be able to use this container on a docker-compose environment as a quiescent PHP CLI container
# this will make docker-compose stop slow on such a container:
CMD ["tail", "-f", "/dev/null"]
