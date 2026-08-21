<?php

namespace Database\Seeders;

use App\Enums\ProposalStatus as S;
use App\Enums\TipeProposal;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ProposalWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Data sampel proposal untuk demo/uji (mis. infinite scroll).
 * Menjalani alur asli via ProposalWorkflow sehingga history,
 * penugasan reviewer, dan tab Riwayat ikut terisi.
 *
 * Jalankan: php artisan db:seed --class=ProposalSampleSeeder
 */
class ProposalSampleSeeder extends Seeder
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

    public function run(): void
    {
        $peneliti = User::where('email', 'peneliti@eproposal.test')->firstOrFail();
        $cru = User::where('email', 'cru@eproposal.test')->firstOrFail();
        $kepk = User::where('email', 'kepk@eproposal.test')->firstOrFail();
        $reviewers = User::whereIn('email', ['reviewer@eproposal.test', 'reviewer2@eproposal.test'])->get();

        // target status => jumlah
        $distribusi = [
            S::MenungguVerifikasiBerkas->value => 20,
            S::MenungguPresentasi->value => 5,
            S::MenungguKelengkapanBerkasEtik->value => 5,
            S::MenungguPenunjukanReviewer->value => 6,
            S::PerluRevisiBerkasEtik->value => 4,
            S::MenungguReviewReviewer->value => 8,
            S::DisetujuiReviewer->value => 4,
            S::MenungguPembayaran->value => 3,
            S::MenungguVerifikasiPembayaran->value => 3,
            S::PelaksanaanPenelitian->value => 3,
            S::MenungguVerifikasiAkhir->value => 2,
            S::MenungguSurveyKepuasan->value => 2,
            S::Selesai->value => 6,
            S::Ditolak->value => 3,
            S::DitolakKajiEtik->value => 2,
            S::Dibatalkan->value => 2,
        ];

        $wf = app(ProposalWorkflow::class);
        $n = 0;

        foreach ($distribusi as $target => $jumlah) {
            for ($i = 0; $i < $jumlah; $i++) {
                $n++;
                $this->buat($wf, S::from($target), $n, $peneliti, $cru, $kepk, $reviewers);
            }
        }

        Auth::logout();
        $this->command?->info("Selesai: {$n} proposal sampel dibuat.");
    }

    protected function buat(ProposalWorkflow $wf, S $target, int $n, User $peneliti, User $cru, User $kepk, $reviewers): void
    {
        Auth::login($peneliti);

        $p = $wf->ajukan([
            'peneliti_utama' => $peneliti->name,
            'tim_peneliti' => 'Tim Peneliti '.$n,
            'judul_penelitian' => self::TOPIK[$n % count(self::TOPIK)]." (Sampel #{$n})",
            'user_id' => $peneliti->id,
        ], $n % 2 === 0 ? TipeProposal::Internal : TipeProposal::Eksternal);

        // Jalur menuju target — potong sesuai kebutuhan
        Auth::login($cru);

        if ($target === S::Ditolak) {
            $wf->transition($p, S::Ditolak, 'Sampel: berkas tidak sesuai');
        } elseif ($target === S::Dibatalkan) {
            $wf->transition($p, S::Dibatalkan, 'Sampel: dibatalkan');
        } elseif ($target !== S::MenungguVerifikasiBerkas) {
            $wf->transition($p, S::MenungguPresentasi);

            if ($target !== S::MenungguPresentasi) {
                $wf->transition($p, S::MenungguKelengkapanBerkasEtik);

                if ($target === S::DitolakKajiEtik) {
                    Auth::login($kepk);
                    $wf->transition($p, S::DitolakKajiEtik, 'Sampel: tidak layak etik');
                } elseif ($target !== S::MenungguKelengkapanBerkasEtik) {
                    Auth::login($peneliti);
                    $wf->transition($p, S::MenungguPenunjukanReviewer);

                    if ($target === S::PerluRevisiBerkasEtik) {
                        // KEPK mengembalikan berkas sebelum reviewer dilibatkan
                        Auth::login($kepk);
                        $wf->transition($p, S::PerluRevisiBerkasEtik, 'Sampel: PKS belum ditandatangani direktur');
                    } elseif ($target !== S::MenungguPenunjukanReviewer) {
                        Auth::login($kepk);
                        $wf->tugaskanReviewer($p, $reviewers->pluck('id')->all());

                        if ($target !== S::MenungguReviewReviewer) {
                            // Semua reviewer ACC → otomatis Disetujui Reviewer
                            foreach ($reviewers as $r) {
                                Auth::login($r);
                                $wf->reviewerMerespons($p->fresh(), 'approve', 'Sampel: layak dilanjutkan');
                            }

                            $sisa = [
                                S::MenungguPembayaran, S::MenungguVerifikasiPembayaran,
                                S::PelaksanaanPenelitian, S::MenungguVerifikasiAkhir,
                                S::MenungguSurveyKepuasan, S::Selesai,
                            ];

                            Auth::login($cru);
                            foreach ($sisa as $langkah) {
                                if ($p->fresh()->status === $target) {
                                    break;
                                }
                                $wf->transition($p->fresh(), $langkah, 'Sampel');
                            }
                        }
                    }
                }
            }
        }

        // Sebar waktu supaya urutan antrian/riwayat terlihat alami
        $dibuat = now()->subDays(rand(1, 90))->subMinutes(rand(0, 1440));
        DB::table('rspi.proposal')->where('id', $p->id)->update([
            'created_at' => $dibuat,
            'updated_at' => $dibuat->copy()->addHours(rand(1, 72)),
        ]);
    }
}
