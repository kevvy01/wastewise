# Menggunakan base image PHP dengan Apache
FROM php:8.1-apache

# Install ekstensi MySQLi (wajib, karena PHP native butuh ini buat connect DB)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Aktifkan mod_rewrite (opsional, tapi berguna jika nanti pakai .htaccess)
RUN a2enmod rewrite

# Copy semua file project kamu ke dalam container
COPY . /var/www/html/

# Ubah hak akses folder uploads agar bisa upload gambar (PENTING!)
RUN chown -R www-data:www-data /var/www/html/uploads