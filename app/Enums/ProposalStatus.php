<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case MenungguVerifikasiBerkas = 'Menunggu Verifikasi Berkas';
    case PerluRevisiProposal = 'Perlu Revisi Proposal';
    case MenungguVerifikasiRevisi = 'Menunggu Verifikasi Revisi';
    case MenungguPresentasi = 'Menunggu Presentasi';
    case Ditolak = 'Ditolak';
    case MenungguKelengkapanBerkasEtik = 'Menunggu Kelengkapan Berkas Etik';
    case MenungguPenunjukanReviewer = 'Menunggu Penunjukan Reviewer';
    case PerluRevisiBerkasEtik = 'Perlu Revisi Berkas Etik';
    case MenungguReviewReviewer = 'Menunggu Review Reviewer';
    case PerluRevisiReviewer = 'Perlu Revisi Reviewer';
    case DisetujuiReviewer = 'Disetujui Reviewer';
    case DitolakKajiEtik = 'Ditolak Kaji Etik';
    case MenungguPembayaran = 'Menunggu Pembayaran';
    case MenungguVerifikasiPembayaran = 'Menunggu Verifikasi Pembayaran';
    case PelaksanaanPenelitian = 'Pelaksanaan Penelitian';
    case MenungguVerifikasiAkhir = 'Menunggu Verifikasi Akhir';
    case MenungguSurveyKepuasan = 'Menunggu Survey Kepuasan';
    case Selesai = 'Selesai';
    case Dibatalkan = 'Dibatalkan';

    /** Tahapan diturunkan dari status (null = terminal, keputusan D1). */
    public function tahapan(): ?int
    {
        return match ($this) {
            self::MenungguVerifikasiBerkas, self::PerluRevisiProposal,
            self::MenungguVerifikasiRevisi, self::MenungguPresentasi => 1,
            self::MenungguKelengkapanBerkasEtik, self::MenungguPenunjukanReviewer,
            self::PerluRevisiBerkasEtik, self::MenungguReviewReviewer,
            self::PerluRevisiReviewer, self::DisetujuiReviewer => 2,
            self::MenungguPembayaran, self::MenungguVerifikasiPembayaran => 3,
            self::PelaksanaanPenelitian, self::MenungguVerifikasiAkhir,
            self::MenungguSurveyKepuasan => 4,
            self::Selesai, self::Ditolak, self::DitolakKajiEtik, self::Dibatalkan => null,
        };
    }

    /** Unit pemegang diturunkan dari status (null = selesai/batal). */
    public function unit(): ?Unit
    {
        return match ($this) {
            self::MenungguReviewReviewer => Unit::Reviewer,
            self::MenungguKelengkapanBerkasEtik, self::MenungguPenunjukanReviewer,
            self::PerluRevisiBerkasEtik, self::PerluRevisiReviewer,
            self::DisetujuiReviewer, self::DitolakKajiEtik => Unit::KajiEtik,
            self::Selesai, self::Dibatalkan => null,
            default => Unit::Penelitian,
        };
    }

    /**
     * Status berikutnya yang sah (peta alur — cegah loncat).
     * Termasuk transisi mundur keputusan D4. `Dibatalkan` tidak dicantumkan
     * di sini; ia diizinkan dari semua status non-terminal via canGoTo().
     *
     * @return self[]
     */
    public function allowedNext(): array
    {
        return match ($this) {
            // Tahap 1 (CRU)
            self::MenungguVerifikasiBerkas => [self::PerluRevisiProposal, self::MenungguPresentasi, self::Ditolak],
            self::PerluRevisiProposal => [self::MenungguVerifikasiRevisi],
            // Loloskan langsung ke KEPK: presentasi pada praktiknya cukup sekali, jadi
            // revisi yang sudah memuaskan tidak perlu dijadwalkan presentasi ulang.
            self::MenungguVerifikasiRevisi => [self::MenungguKelengkapanBerkasEtik, self::PerluRevisiProposal, self::MenungguPresentasi, self::Ditolak],
            self::MenungguPresentasi => [self::MenungguKelengkapanBerkasEtik, self::PerluRevisiProposal, self::Ditolak],
            // Tahap 2 (KEPK + Reviewer) — KEPK perantara: tunjuk reviewer,
            // terima jawaban reviewer, teruskan revisi ke peneliti.
            self::MenungguKelengkapanBerkasEtik => [self::MenungguPenunjukanReviewer, self::DitolakKajiEtik],
            // KEPK memverifikasi kelengkapan berkas SEBELUM menunjuk reviewer. Tanpa
            // cabang revisi ini, satu berkas salah memaksa KEPK memilih antara
            // meneruskan berkas cacat ke reviewer atau menolak etik secara permanen.
            self::MenungguPenunjukanReviewer => [self::PerluRevisiBerkasEtik, self::MenungguReviewReviewer, self::DitolakKajiEtik],
            // Hanya bisa maju — sama seperti PerluRevisiReviewer. Penolakan etik
            // diambil setelah berkas perbaikan masuk, bukan saat menunggu peneliti.
            self::PerluRevisiBerkasEtik => [self::MenungguPenunjukanReviewer],
            self::MenungguReviewReviewer => [self::PerluRevisiReviewer, self::DisetujuiReviewer, self::DitolakKajiEtik],
            self::PerluRevisiReviewer => [self::MenungguReviewReviewer],
            self::DisetujuiReviewer => [self::MenungguPembayaran, self::DitolakKajiEtik],
            // Tahap 3 (CRU)
            self::MenungguPembayaran => [self::MenungguVerifikasiPembayaran],
            self::MenungguVerifikasiPembayaran => [self::PelaksanaanPenelitian, self::MenungguPembayaran],
            // Tahap 4 (CRU)
            self::PelaksanaanPenelitian => [self::MenungguVerifikasiAkhir],
            self::MenungguVerifikasiAkhir => [self::MenungguSurveyKepuasan, self::PelaksanaanPenelitian],
            self::MenungguSurveyKepuasan => [self::Selesai],
            // terminal
            self::Selesai, self::Ditolak, self::DitolakKajiEtik, self::Dibatalkan => [],
        };
    }

    public function canGoTo(self $next): bool
    {
        if ($next === self::Dibatalkan) {
            return ! $this->isTerminal();
        }

        return in_array($next, $this->allowedNext(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedNext() === [];
    }

    /** Warna badge daisyUI. */
    public function warna(): string
    {
        return match ($this) {
            self::Selesai => 'badge-success',
            self::Ditolak, self::DitolakKajiEtik, self::Dibatalkan => 'badge-error',
            self::PerluRevisiProposal, self::PerluRevisiBerkasEtik,
            self::PerluRevisiReviewer => 'badge-warning',
            self::PelaksanaanPenelitian, self::DisetujuiReviewer => 'badge-info',
            default => 'badge-neutral',
        };
    }

    /** Status yang bolanya di tangan peneliti (perlu aksi peneliti). */
    public function bolaDiPeneliti(): bool
    {
        return in_array($this, [
            self::PerluRevisiProposal,
            self::MenungguPresentasi,
            self::MenungguKelengkapanBerkasEtik,
            self::PerluRevisiBerkasEtik,
            self::PerluRevisiReviewer,
            self::MenungguPembayaran,
            self::PelaksanaanPenelitian,
            self::MenungguSurveyKepuasan,
        ], true);
    }
}
