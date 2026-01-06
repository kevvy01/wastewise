FROM php:8.1-apache

# --- FIX ERROR MPM (Bagian Penting) ---
# Mematikan mpm_event dan mpm_worker agar tidak bentrok dengan mpm_prefork
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork

# --- Install Ekstensi Database ---
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# --- Config Apache ---
RUN a2enmod rewrite

# --- Copy File Project ---
COPY . /var/www/html/

# --- Atur Hak Akses ---
RUN chown -R www-data:www-data /var/www/html/