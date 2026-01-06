FROM php:8.1-apache

# Update sistem dan install tools dasar (opsional tapi bagus)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# --- FIX APACHE MPM (SOLUSI NUKLIR) ---
# 1. Hapus paksa semua symlink MPM yang ada di folder enabled
# 2. Aktifkan HANYA mpm_prefork
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
    && rm -f /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork

# --- CONFIG STANDAR ---
# Install ekstensi MySQL
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Aktifkan mod_rewrite
RUN a2enmod rewrite

# Copy file
COPY . /var/www/html/

# Atur hak akses
RUN chown -R www-data:www-data /var/www/html/