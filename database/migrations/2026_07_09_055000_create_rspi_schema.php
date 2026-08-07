<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Schema `rspi` menampung SELURUH tabel domain. `public` disisakan untuk tabel
     * bawaan Laravel & spatie — keduanya menulis tanpa kualifikasi schema, jadi
     * `search_path` di config/database.php menaruh `public` di depan.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Gagal keras di sini, bukan diam-diam. SQLite tidak mengenal schema:
        // "rspi"."proposal" di sana dibaca sebagai attached database bernama rspi,
        // dan errornya baru muncul di migration berikutnya dengan pesan menyesatkan.
        if ($driver !== 'pgsql') {
            throw new RuntimeException(
                "Struktur eProposal memakai schema PostgreSQL; driver terpasang: {$driver}. ".
                'Setel DB_CONNECTION=pgsql (lihat phpunit.xml untuk lingkungan tes).'
            );
        }

        DB::statement('create schema if not exists rspi');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('drop schema if exists rspi cascade');
        }
    }
};
