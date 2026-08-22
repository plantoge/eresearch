<?php

namespace App\Support;

/**
 * Tanda tangan yang digambar di kanvas.
 *
 * Disimpan sebagai **SVG** (`data:image/svg+xml;base64,...`), bukan PNG.
 * Alasannya bukan selera: dompdf memerlukan `imagecreatefrompng()` — dan dengan
 * itu ekstensi GD — untuk setiap gambar raster, sedangkan SVG dirender lewat
 * php-svg-lib tanpa ekstensi gambar apa pun. Server produksi memakai FrankenPHP
 * yang PHP-nya dibangun tanpa GD, jadi tanda tangan raster membuat seluruh
 * pencetakan formulir gagal dengan "The PHP GD extension is required".
 *
 * Tanda tangan memang aslinya rangkaian garis; ia hanya menjadi raster karena
 * `toDataURL()` memotretnya. Menyimpannya sebagai vektor mengembalikannya ke
 * bentuk semula — dan hasil cetaknya lebih tajam.
 */
class TandaTangan
{
    public const AWALAN_SVG = 'data:image/svg+xml;base64,';

    /**
     * PNG masih DITERIMA walau tidak lagi dihasilkan.
     *
     * Formulir yang terlanjur ditandatangani sebelum perubahan ini menyimpan
     * PNG, dan `isiDari()` memuatnya kembali ke form saat peneliti membuka
     * halaman perbaikan. Menolaknya di sini akan mengunci berkas mereka di layar
     * revisi tanpa jalan keluar, padahal tidak ada yang salah dengan isinya.
     *
     * Batas panjangnya menjaga kolom teks dari kiriman raksasa.
     */
    public const ATURAN = [
        'required',
        'string',
        'max:2000000',
        'regex:/^data:image\/(svg\+xml|png);base64,[A-Za-z0-9+\/=\s]+$/',
    ];

    /** Bisa dicetak ke PDF tanpa GD? Hanya SVG yang bisa. */
    public static function adalahSvg(?string $nilai): bool
    {
        return is_string($nilai) && str_starts_with($nilai, self::AWALAN_SVG);
    }
}
