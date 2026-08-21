<?php

namespace App\Support;

/**
 * Tanda tangan yang digambar di kanvas `x-mary-signature`.
 *
 * Nilainya data URL PNG (`data:image/png;base64,...`) yang dikirim komponen apa
 * adanya lewat wire:model. Disimpan sebagai kolom teks, bukan berkas di disk:
 * ia bagian dari isian formulir — bukan lampiran yang punya versi sendiri — dan
 * dompdf bisa merendernya langsung lewat `<img src="data:...">`.
 */
class TandaTangan
{
    /**
     * `required` saja tidak cukup: kanvas yang tidak pernah disentuh tetap bisa
     * mengirim string apa pun dari klien, dan tanpa pemeriksaan bentuk, sampah
     * itu tersimpan lalu muncul sebagai gambar rusak di PDF resmi.
     *
     * Batas panjangnya menjaga kolom teks dari kiriman raksasa; ~2 juta karakter
     * base64 setara gambar 1,5 MB, jauh di atas tanda tangan wajar (10–40 KB).
     */
    public const ATURAN = [
        'required',
        'string',
        'max:2000000',
        'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=\s]+$/',
    ];
}
