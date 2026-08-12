<?php

namespace App\Providers;

use App\Models\Menu;
use App\Models\ProposalStatusHistory;
use App\Observers\MenuObserver;
use App\Observers\ProposalStatusHistoryObserver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Mary\View\Components\Badge;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Konvensi prd §8.0 — kolom audit wajib di semua tabel.
        Blueprint::macro('auditColumns', function () {
            /** @var Blueprint $this */
            $this->uuid('created_by')->nullable();
            $this->uuid('updated_by')->nullable();
            $this->uuid('deleted_by')->nullable();
        });

        /*
         * `berkas_ada` — pagar pertama untuk SEMUA unggahan.
         *
         * Berkas sementara Livewire bisa lenyap sebelum form disubmit (masa
         * unggah 5 menit habis, koneksi putus, pembersih 24 jam). Masalahnya
         * `TemporaryUploadedFile::isValid()` di-hardcode `true`, jadi aturan
         * `file` selalu lolos, lalu `mimes`/`max` memanggil getMimeType()/
         * getSize() yang membaca disk — dan Flysystem MELEMPAR
         * `UnableToRetrieveMetadata`, bukan mengembalikan gagal. Pengguna dapat
         * layar 500 alih-alih pesan validasi (kejadian di /proposal/baru,
         * 12 Agustus 2026).
         *
         * `bail` saja tidak cukup: ia melewati aturan setelah ada KEGAGALAN,
         * sedangkan aturan di sini melempar. Karena itu pemeriksaannya harus
         * berada di DEPAN dan memakai exists() — satu-satunya pembacaan yang
         * mengembalikan bool alih-alih melempar.
         *
         * Dipasang otomatis lewat DocumentType::aturanValidasi() dan
         * DokumenTelaah::ATURAN_VALIDASI, sehingga semua form ikut terlindungi
         * tanpa perlu diingat satu per satu.
         */
        Validator::extend(
            'berkas_ada',
            fn ($attribute, $value) => ! $value instanceof TemporaryUploadedFile || $value->exists(),
            'Berkas gagal diunggah atau masa unggahnya sudah habis. Silakan unggah ulang berkasnya.',
        );

        Menu::observe(MenuObserver::class);

        // Titik sisip tunggal notifikasi Telegram (lihat observer-nya).
        ProposalStatusHistory::observe(ProposalStatusHistoryObserver::class);

        // Workaround bug Mary UI 2.9: Tab.php memanggil <x-badge> tanpa prefix,
        // tapi 'badge' tidak masuk daftar alias internal provider Mary — sehingga
        // dengan prefix 'mary-' semua halaman ber-x-mary-tab gagal compile.
        Blade::component('badge', Badge::class);
    }
}
