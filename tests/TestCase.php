<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Nama database yang boleh dipakai tes. RefreshDatabase menjalankan
     * migrate:fresh — drop SEMUA tabel — jadi salah sasaran berarti data kerja
     * hilang permanen.
     */
    protected const DB_UJI = 'cru_test';

    /**
     * Pagar pengaman: pastikan tes menunjuk database uji sebelum
     * RefreshDatabase sempat menyentuh apa pun.
     *
     * Perlu ada karena `php artisan optimize` / `config:cache` menulis
     * bootstrap/cache/config.php, dan begitu file itu ada Laravel BERHENTI
     * membaca env() — seluruh blok <env> di phpunit.xml (DB_DATABASE=cru_test,
     * QUEUE_CONNECTION=sync) diabaikan diam-diam. Tesnya tetap "hijau", tapi
     * jalan di atas database pengembangan dan menghapus isinya.
     *
     * Ini pernah terjadi (12 Agustus 2026): database `eresearch` ter-drop oleh
     * satu kali `artisan test` karena config sedang ter-cache.
     *
     * Dipasang di setUpTraits(), bukan setUp(): di titik ini aplikasi sudah
     * boot (config terbaca) tapi trait RefreshDatabase BELUM berjalan.
     */
    protected function setUpTraits()
    {
        $this->pastikanDatabaseUji();

        return parent::setUpTraits();
    }

    protected function pastikanDatabaseUji(): void
    {
        $koneksi = config('database.default');
        $database = config("database.connections.{$koneksi}.database");

        if ($database === self::DB_UJI) {
            return;
        }

        $sebab = app()->configurationIsCached()
            ? 'Penyebabnya config ter-cache (bootstrap/cache/config.php) — saat file itu ada, blok <env> di phpunit.xml diabaikan.'
            : 'Periksa DB_DATABASE di phpunit.xml dan .env.testing.';

        throw new RuntimeException(
            "TES DIHENTIKAN — database tujuan '{$database}', bukan '".self::DB_UJI."'. ".
            "Melanjutkan berarti migrate:fresh menghapus seluruh isi '{$database}'. ".
            $sebab.' Jalankan: php artisan config:clear'
        );
    }
}
