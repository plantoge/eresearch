<?php

namespace App\Enums;

/**
 * Bagian naratif Lembar Informasi pada formulir Informed Consent KEPK.
 *
 * Urutannya mengikuti formulir kertas dan tidak boleh diacak: subjek penelitian
 * membacanya berurutan, dan KEPK menelaahnya berdampingan dengan formulir asli.
 *
 * Seluruh bagian disimpan dalam SATU kolom json, bukan empat belas kolom teks —
 * daftarnya adalah isi formulir, tidak pernah dicari satu per satu lewat query,
 * dan bisa berubah kalau KEPK merevisi formulirnya.
 */
enum BagianLembarInformasi: string
{
    case Tujuan = 'tujuan';
    case SubjekTerpilih = 'subjek_terpilih';
    case Prosedur = 'prosedur';
    case Risiko = 'risiko';
    case ManfaatSubjek = 'manfaat_subjek';
    case ManfaatUmum = 'manfaat_umum';
    case Kerahasiaan = 'kerahasiaan';
    case JumlahSubjek = 'jumlah_subjek';
    case Kesukarelaan = 'kesukarelaan';
    case PeriodeKeikutsertaan = 'periode_keikutsertaan';
    case PengunduranDiri = 'pengunduran_diri';
    case Asuransi = 'asuransi';
    case Insentif = 'insentif';
    case Kontak = 'kontak';

    /** Judul bagian, persis seperti tercetak di formulir. */
    public function label(): string
    {
        return match ($this) {
            self::Tujuan => 'Tujuan Penelitian',
            self::SubjekTerpilih => 'Mengapa Subjek terpilih',
            self::Prosedur => 'Tata Cara/Prosedur',
            self::Risiko => 'Risiko dan ketidaknyamanan',
            self::ManfaatSubjek => 'Manfaat langsung untuk subjek',
            self::ManfaatUmum => 'Manfaat umum',
            self::Kerahasiaan => 'Kerahasiaan data',
            self::JumlahSubjek => 'Perkiraan jumlah subjek yang akan diikutsertakan',
            self::Kesukarelaan => 'Kesukarelaan',
            self::PeriodeKeikutsertaan => 'Periode Keikutsertaan Subjek',
            self::PengunduranDiri => 'Subjek dapat dikeluarkan/mengundurkan diri dari penelitian',
            self::Asuransi => 'Kemungkinan timbulnya pembiayaan dari perusahaan asuransi kesehatan atau peneliti',
            self::Insentif => 'Insentif dan kompensasi',
            self::Kontak => 'Pertanyaan',
        };
    }

    /** Panduan pengisian dari formulir — tampil sebagai hint di form. */
    public function petunjuk(): string
    {
        return match ($this) {
            self::Tujuan => 'Jelaskan tujuan penelitian secara singkat dan jelas kepada calon subjek.',
            self::SubjekTerpilih => 'Jelaskan alasan subjek terpilih; sebutkan kriteria inklusi.',
            self::Prosedur => 'Jelaskan tata cara/prosedur penelitian yang akan dilakukan terhadap subjek.',
            self::Risiko => 'Jelaskan risiko dan ketidaknyamanan yang akan dirasakan subjek (bila ada).',
            self::ManfaatSubjek => 'Manfaat yang langsung dirasakan subjek.',
            self::ManfaatUmum => 'Manfaat bagi masyarakat luas.',
            self::Kerahasiaan => 'Jelaskan bagaimana data subjek dijaga kerahasiaannya.',
            self::JumlahSubjek => 'Jelaskan perkiraan jumlah subjek yang diikutsertakan.',
            self::Kesukarelaan => 'Jelaskan bahwa keikutsertaan bersifat sukarela dan apa artinya bagi subjek.',
            self::PeriodeKeikutsertaan => 'Berapa lama subjek terlibat dalam penelitian ini.',
            self::PengunduranDiri => 'Jelaskan penghentian studi dan cara subjek mengundurkan diri.',
            self::Asuransi => 'Jelaskan asuransi yang diberikan. Bila tidak ada, tulis "tidak ada asuransi yang diberikan kepada subjek dalam penelitian ini".',
            self::Insentif => 'Bentuk dan jenis insentif atau kompensasi yang akan diterima subjek.',
            self::Kontak => 'Tuliskan contact person yang bisa dihubungi subjek.',
        };
    }

    /**
     * Tinggi textarea. Bagian yang jawabannya memang pendek (kontak, periode)
     * tidak perlu kotak sebesar bagian prosedur — kotak kosong yang kebesaran
     * membuat form terlihat jauh lebih berat daripada isian sebenarnya.
     */
    public function baris(): int
    {
        return match ($this) {
            self::Prosedur, self::Risiko, self::Kerahasiaan => 4,
            self::Kontak, self::JumlahSubjek, self::PeriodeKeikutsertaan => 2,
            default => 3,
        };
    }

    /** Bahasa awam: bagian ini dibaca calon subjek, bukan komite. */
    public static function pengantar(): string
    {
        return 'Gunakan bahasa awam yang dipahami calon subjek.';
    }
}
