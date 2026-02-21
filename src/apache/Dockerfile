FROM httpd:2.4-trixie

# Install packages and enable Apache modules
RUN apt-get update && apt-get dist-upgrade -y && apt-get -y install vim iputils-ping && apt-get clean && \
    sed -i '/LoadModule rewrite_module/s/^#//g' /usr/local/apache2/conf/httpd.conf && \
    sed -i '/LoadModule headers_module/s/^#//g' /usr/local/apache2/conf/httpd.conf && \
    sed -i '/LoadModule proxy_module/s/^#//g' /usr/local/apache2/conf/httpd.conf && \
    sed -i '/LoadModule proxy_fcgi_module/s/^#//g' /usr/local/apache2/conf/httpd.conf && \
    sed -i '/LoadModule setenvif_module/s/^#//g' /usr/local/apache2/conf/httpd.conf


COPY --chmod=755 ./src/apache/entrypoint.sh /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["httpd-foreground"]


#RUN mkdir -p /var/www && chown -R www-data: /var/www/data


