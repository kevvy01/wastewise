FROM php:8.1-apache

# --- BAGIAN PERBAIKAN (HARD DELETE) ---
# Kita hapus manual file konfigurasi mpm_event dan mpm_worker
# agar Apache TIDAK MUNGKIN bisa memuatnya.
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
    && rm -f /etc/apache2/mods-enabled/mpm_event.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.load \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.conf

# Aktifkan mpm_prefork (satu-satunya engine yang kita mau)
RUN a2enmod mpm_prefork

# --- BAGIAN STANDAR ---
# Install ekstensi agar bisa konek database
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Aktifkan mod_rewrite
RUN a2enmod rewrite

# Copy file website kamu
COPY . /var/www/html/

# Atur hak akses agar bisa upload file
RUN chown -R www-data:www-data /var/www/html/