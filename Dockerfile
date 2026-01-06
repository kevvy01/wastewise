# Gunakan image PHP CLI (tanpa Apache, lebih ringan)
FROM php:8.1-cli

# Update dan install ekstensi MySQL (Wajib buat database)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy semua file tugas kamu ke dalam Docker
COPY . /var/www/html/

# Pindah ke folder kerja
WORKDIR /var/www/html/

# Atur hak akses folder uploads (biar bisa upload gambar)
RUN chown -R www-data:www-data /var/www/html/uploads

# --- PERINTAH START (RAHASIANYA DI SINI) ---
# Kita jalankan server bawaan PHP di Port 80
# Host 0.0.0.0 artinya "bisa diakses dari luar"
CMD ["php", "-S", "0.0.0.0:80"]