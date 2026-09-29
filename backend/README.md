# Presensi Logistik — Backend (Laravel 13)

REST API untuk aplikasi Presensi Logistik. Panduan instalasi, cara menjalankan, dan pengujian ada di [README utama](../README.md).

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve
php artisan test
```
