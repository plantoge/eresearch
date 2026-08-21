<?php

namespace Database\Seeders;

use App\Enums\ProposalStatus;
use App\Enums\TipeProposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder beban: insert massal proposal lewat query builder.
 *
 * Beda dari ProposalSampleSeeder yang sengaja menjalankan alur asli lewat
 * ProposalWorkflow (puluhan write per proposal — realistis tapi lambat):
 * seeder ini menulis baris `rspi.proposal` langsung, tanpa history/dokumen/penugasan
 * dan tanpa berkas kerja CRU/KEPK.
 * Tujuannya semata mengisi tabel sampai volume besar untuk uji beban query.
 *
 * Konsekuensi yang harus disadari saat membaca hasil benchmark:
 * - Tab "Riwayat" di antrian (BaseAntrian::riwayatQuery) memakai
 *   whereHas('statusHistory') — data dari seeder ini TIDAK punya history,
 *   jadi tab itu akan tetap kosong dan tidak ikut terukur.
 * - Halaman Show juga akan tampak kosong (tanpa dokumen/review).
 * Yang terukur: Proposal/Index dan tab "Antrian" — dua daftar yang memang
 * jadi sasaran uji.
 *
 * Jalankan:
 *   php artisan db:seed --class=ProposalBulkSeeder
 *   BULK_PROPOSAL_COUNT=1000000 php artisan db:seed --class=ProposalBulkSeeder
 *
 * Pakai getenv(), bukan env(), supaya tetap terbaca walau config sudah di-cache
 * (config:cache membuat env() selalu mengembalikan null).
 */
class ProposalBulkSeeder extends Seeder
{
    protected const TOPIK = [
        'Efektivitas Terapi Antiviral pada Pasien Demam Berdarah',
        'Pola Resistensi Antibiotik di Ruang Rawat Intensif',
        'Faktor Risiko Sepsis Neonatal di Rumah Sakit Rujukan',
        'Evaluasi Program Vaksinasi Hepatitis B pada Tenaga Kesehatan',
        'Karakteristik Klinis Pasien Tuberkulosis Resisten Obat',
        'Hubungan Status Gizi dengan Lama Rawat Pasien Infeksi',
        'Deteksi Dini Infeksi Nosokomial dengan Machine Learning',
        'Kualitas Hidup Pasien HIV dalam Terapi Antiretroviral',
        'Surveilans Vektor Malaria di Wilayah Endemis',
        'Analisis Biaya Perawatan Pasien COVID-19 Berkomorbid',
    ];

    /** Bobot status — mendekati sebaran nyata: mayoritas menumpuk di awal alur. */
    protected const BOBOT = [
        'Menunggu Verifikasi Berkas' => 25,
        'Perlu Revisi Proposal' => 8,
        'Menunggu Verifikasi Revisi' => 5,
        'Menunggu Presentasi' => 6,
        'Menunggu Kelengkapan Berkas Etik' => 6,
        'Menunggu Penunjukan Reviewer' => 5,
        'Menunggu Review Reviewer' => 8,
        'Perlu Revisi Reviewer' => 4,
        'Disetujui Reviewer' => 4,
        'Menunggu Pembayaran' => 4,
        'Menunggu Verifikasi Pembayaran' => 3,
        'Pelaksanaan Penelitian' => 5,
        'Menunggu Verifikasi Akhir' => 3,
        'Menunggu Survey Kepuasan' => 2,
        'Selesai' => 8,
        'Ditolak' => 2,
        'Ditolak Kaji Etik' => 1,
        'Dibatalkan' => 1,
    ];

    public function run(): void
    {
        $target = (int) (getenv('BULK_PROPOSAL_COUNT') ?: 1_000_000);
        $chunk = (int) (getenv('BULK_PROPOSAL_CHUNK') ?: 5_000);
        $tahun = (int) (getenv('BULK_PROPOSAL_TAHUN') ?: now()->year);

        $peneliti = User::where('email', 'peneliti@eproposal.test')->firstOrFail();

        // Lanjut dari nomor terakhir supaya unique(tahun, nomor) & unique(kode)
        // tidak bentrok dengan data yang sudah ada (mis. ProposalSampleSeeder).
        $nomor = (int) DB::table('rspi.proposal')->where('tahun', $tahun)->max('nomor');

        $pool = $this->poolStatus();
        $jumlahPool = count($pool);

        DB::disableQueryLog();

        $this->command?->info("Menulis {$target} proposal (tahun {$tahun}, chunk {$chunk})…");
        $mulai = microtime(true);
        $ditulis = 0;

        while ($ditulis < $target) {
            $batas = min($chunk, $target - $ditulis);
            $baris = [];

            for ($i = 0; $i < $batas; $i++) {
                $nomor++;
                $status = $pool[random_int(0, $jumlahPool - 1)];
                $dibuat = now()->subMinutes(random_int(0, 1_576_800)); // sebar ±3 tahun

                // Selang-seling supaya data beban memuat kedua tipe; bulan
                // diambil dari tanggal buat yang disebar, agar kode tetap
                // konsisten dengan barisnya.
                $tipe = $nomor % 2 === 0 ? TipeProposal::Internal : TipeProposal::Eksternal;
                $bulan = (int) $dibuat->month;

                $baris[] = [
                    'id' => (string) Str::uuid7(),
                    'tipe_proposal' => $tipe->value,
                    'tahun' => $tahun,
                    'bulan' => $bulan,
                    'nomor' => $nomor,
                    'kode' => ProposalWorkflow::formatKode($tipe, $tahun, $bulan, $nomor),
                    'peneliti_utama' => $peneliti->name,
                    'tim_peneliti' => 'Tim Peneliti '.$nomor,
                    'judul_penelitian' => self::TOPIK[$nomor % count(self::TOPIK)]." (Beban #{$nomor})",
                    'institusi_asal' => $peneliti->institusi_asal ?? null,
                    'email' => $peneliti->email,
                    'phone' => $peneliti->phone ?? null,
                    'user_id' => $peneliti->id,
                    'status' => $status->value,
                    'unit_sekarang' => $status->unit()?->value,
                    'created_at' => $dibuat,
                    'updated_at' => $dibuat->copy()->addHours(random_int(1, 72)),
                    'deleted_at' => null,
                    'created_by' => $peneliti->id,
                    'updated_by' => $peneliti->id,
                    'deleted_by' => null,
                ];
            }

            foreach (array_chunk($baris, $this->maksBarisPerInsert($baris[0])) as $bagian) {
                DB::table('rspi.proposal')->insert($bagian);
            }

            $ditulis += $batas;

            $this->command?->getOutput()->write(sprintf(
                "\r  %d/%d (%.1f%%) — %.0f baris/detik",
                $ditulis,
                $target,
                $ditulis / $target * 100,
                $ditulis / max(microtime(true) - $mulai, 0.001),
            ));
        }

        $detik = microtime(true) - $mulai;
        $this->command?->getOutput()->newLine();
        $this->command?->info(sprintf('Selesai: %d proposal dalam %.1f detik.', $ditulis, $detik));
    }

    /**
     * Berapa baris muat dalam satu INSERT sebelum menabrak batas bind parameter
     * driver (Postgres: 65535 per statement — chunk 5.000 × 23 kolom sudah lewat,
     * gagal dengan "number of parameters must be between 0 and 65535").
     * Diturunkan dari jumlah kolom baris nyata supaya ikut menyesuaikan sendiri
     * kalau tabel `proposal` bertambah kolom.
     */
    protected function maksBarisPerInsert(array $contoh): int
    {
        $batasBind = match (DB::connection()->getDriverName()) {
            'sqlite' => 32_766,
            default => 65_535,
        };

        return max(1, intdiv($batasBind, max(count($contoh), 1)));
    }

    /**
     * Bentangkan BOBOT jadi array datar supaya pengambilan acak = O(1)
     * tanpa hitung kumulatif tiap baris.
     *
     * @return ProposalStatus[]
     */
    protected function poolStatus(): array
    {
        $pool = [];

        foreach (self::BOBOT as $nilai => $bobot) {
            $status = ProposalStatus::from($nilai);

            for ($i = 0; $i < $bobot; $i++) {
                $pool[] = $status;
            }
        }

        return $pool;
    }
}
