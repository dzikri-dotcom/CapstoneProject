<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Frontend React (Vite) berjalan di origin berbeda dari API, jadi origin-nya
    | harus diizinkan di sini. Isi FRONTEND_URL di .env; beberapa origin bisa
    | dipisahkan dengan koma (misal untuk akses dari HP di jaringan lokal).
    |
    | Autentikasi memakai token Bearer (Sanctum), bukan cookie, sehingga
    | supports_credentials tidak perlu diaktifkan.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_map('trim', explode(',', env('FRONTEND_URL', 'http://localhost:5173'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Agar frontend bisa membaca nama file saat mengunduh Excel/PDF.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => false,

];
