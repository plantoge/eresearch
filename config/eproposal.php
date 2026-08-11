<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verifikasi Email Registrasi
    |--------------------------------------------------------------------------
    |
    | Toggle wajib-verifikasi email saat peneliti daftar akun. Matikan
    | (false) kalau domain pengirim belum terverifikasi di Resend — user
    | langsung aktif tanpa nunggu klik link, dan sistem tidak mencoba
    | kirim email sama sekali (jadi tidak akan gagal karena domain).
    |
    | Nyalakan (true) begitu MAIL_FROM_ADDRESS pakai domain yang sudah
    | diverifikasi di resend.com/domains.
    |
    */

    'email_verification_required' => env('EMAIL_VERIFICATION_REQUIRED', false),

    /*
    |--------------------------------------------------------------------------
    | Notifikasi Telegram ke Grup Staf
    |--------------------------------------------------------------------------
    |
    | Tiap aksi peneliti yang memindahkan status proposal dikirim sebagai
    | pesan singkat ke satu grup Telegram berisi staf CRU/KEPK.
    |
    | Default MATI. Selama 'aktif' false — atau token/chat_id kosong —
    | tidak ada satu pun panggilan HTTP, jadi aplikasi jalan persis
    | seperti sebelum fitur ini ada. Isi ketiganya baru nyalakan.
    |
    | Pesannya sengaja cuma kode + status + unit + link: grup Telegram di
    | luar kendali izin aplikasi (siapa pun di grup lihat semua, bisa
    | di-forward keluar) dan servernya di luar jaringan RS. Judul
    | penelitian dan nama peneliti tidak pernah ikut keluar — yang butuh
    | detail klik linknya, di sana izin ditegakkan seperti biasa.
    |
    | Cara dapat nilainya: chat @BotFather → /newbot → salin token; buat
    | grup, masukkan botnya, lalu ambil chat_id grup. Detail langkahnya
    | ada di docs/arsitektur.md §5.
    |
    */

    'telegram' => [
        'aktif' => env('TELEGRAM_NOTIFIKASI_AKTIF', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'timeout' => (int) env('TELEGRAM_TIMEOUT', 5),
    ],

];
