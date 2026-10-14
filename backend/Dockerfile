FROM php:8.3-apache

# Habilitar mod_rewrite para el enrutamiento de la API
RUN a2enmod rewrite

# Instalar dependencias del sistema requeridas
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    msmtp \
    && rm -rf /var/lib/apt/lists/*

# Usar el script instalador oficial de la comunidad (más estable que PECL directamente)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions mysqli pdo pdo_mysql redis

# Configurar msmtp para redirigir los correos a Mailpit (puerto 1025)
RUN echo "account default" > /etc/msmtprc \
    && echo "host mailpit" >> /etc/msmtprc \
    && echo "port 1025" >> /etc/msmtprc \
    && echo "auto_tls off" >> /etc/msmtprc \
    && echo "tls off" >> /etc/msmtprc \
    && echo "from no-reply@laboratorio.local" >> /etc/msmtprc

# Configurar PHP para usar msmtp como agente de envío de correo
RUN echo "sendmail_path = /usr/bin/msmtp -t" > /usr/local/etc/php/conf.d/mail.ini

# Copiar el código fuente de la aplicación
COPY . /var/www/html/

# Ajustar permisos para Apache
RUN chown -R www-data:www-data /var/www/html
