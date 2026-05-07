FROM php:8.2-apache

# Define o fuso horário do container
RUN echo "America/Sao_Paulo" > /etc/timezone

# Copia todo o conteúdo do projeto para o servidor Apache
COPY . /var/www/html/

# Define a pasta pública como raiz do site
WORKDIR /var/www/html/public

# Expõe a porta 80 para acesso HTTP
EXPOSE 80
