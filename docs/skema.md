# Skema Database — eProposal

> Proses bisnis → [prd.md](prd.md) · stack & infra → [arsitektur.md](arsitektur.md) ·
> aturan kerja → [rules.md](rules.md).
>
> Isi file ini **sudah dicocokkan dengan `database/migrations/` dan database nyata**
> (`cru` @ PostgreSQL, dicek read-only lewat `information_schema`).
> Bukan rancangan target — ini kondisi terpasang: 14 tabel di `public`, 17 di `rspi`,
> 7 partial unique index.

---

## 1. Konvensi Wajib

Berlaku untuk **semua** tabel domain (bukan tabel bawaan Laravel/spatie).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | uuid | PK, UUIDv7 (`Str::uuid7()`) |
| `created_at` / `updated_at` | timestamp | `$t->timestamps()` |
| `deleted_at` | timestamp | `$t->softDeletes()` — semua tabel soft delete |
| `created_by` / `updated_by` / `deleted_by` | uuid | pelaku aksi, diisi otomatis |

**Migration** — macro `auditColumns()` didaftarkan sekali di
`AppServiceProvider::boot()`, dipakai di semua migration:

```php
$t->uuid('id')->primary();
$t->timestamps();     // created_at, updated_at
$t->softDeletes();    // deleted_at
$t->auditColumns();   // created_by, updated_by, deleted_by
// ... barulah kolom domain ...
```

**Urutannya mengikat:** blok audit ditulis **tepat setelah PK**, sebelum kolom isi. Dengan
begitu `artisan db:table` dan `\d` di psql selalu membuka dengan enam kolom yang sama di
setiap tabel, dan kolom yang benar-benar berisi data terbaca sebagai satu kelompok
sesudahnya — bukan terpotong oleh kolom audit di ujung.

Daftar kolom per tabel di §3–§5 **tidak mengulang keenam kolom audit itu**; anggap semuanya
ada tepat di bawah `id`.

**Model** — trait `App\Concerns\HasUuidAndAudit` mengisi `id` dan kolom audit lewat event
`creating` / `updating` / `deleting`, plus relasi `createdBy()`, `updatedBy()`, `deletedBy()`.

### Dua schema PostgreSQL

| Schema | Isi |
|---|---|
| `public` | 14 tabel bawaan: Laravel (`users`, `sessions`, `cache`, `jobs`, `migrations`, …) + 5 tabel RBAC spatie |
| `rspi` | 17 tabel domain — seluruh isi aplikasi |

`search_path` = **`public,rspi`** (`config/database.php`). `public` sengaja di depan: migration
bawaan Laravel dan spatie menulis tanpa kualifikasi schema dan harus mendarat di sana. Tabel
domain tidak bergantung urutan itu — namanya **dikualifikasi eksplisit** di migration
(`Schema::create('rspi.proposal')`) maupun model (`protected $table = 'rspi.proposal'`).

`users` tetap di `public` walau punya kolom domain: dia target morph spatie dan dipakai auth
bawaan.

### Penamaan: prefiks kelompok

Karena CRU dan KEPK berbagi satu schema, **nama tabel yang menyatakan kelompoknya**:

| Awalan | Arti |
|---|---|
| *(tanpa awalan)* | kernel & konfigurasi — dipakai lintas unit |
| `cru_` | berkas kerja CRU |
| `kepk_` | berkas kerja KEPK |

**Foreign key sengaja ditunda.** Kolom relasi `*_id` ada dan ter-index, tapi tanpa constraint FK.
Integritas dijaga di layer aplikasi (`ProposalWorkflow`, guard di komponen Livewire).
Yang **tidak** ditunda: hubungan 1:1 ditegakkan database lewat *partial unique index*.

---

## 2. ERD

```mermaid
erDiagram
    users ||--o{ proposal : mengajukan
    users ||--o{ kepk_penugasan_reviewer : ditugaskan
    users ||--o{ kepk_telaah_reviewer : menulis
    users ||--o{ respon : mengisi

    proposal ||--o{ proposal_documents : "berkas"
    proposal ||--o{ proposal_status_history : "jejak status"
    proposal ||--o| cru_berkas_penelitian : "berkas kerja CRU"
    proposal ||--o{ cru_pembayaran : "2 tagihan"
    proposal ||--o| cru_izin_penelitian : "izin"
    proposal ||--o| kepk_protokol_etik : "berkas kerja KEPK"
    proposal ||--o| kepk_form_etik : "formulir etik peneliti"
    proposal ||--o| kepk_informed_consent : "lembar informasi peneliti"
    proposal ||--o{ kepk_telaah_reviewer : "telaah per ronde"
    proposal ||--o{ kepk_penugasan_reviewer : "penugasan reviewer"
    proposal ||--o| respon : "1 survey aktif"

    kepk_telaah_reviewer ||--o{ kepk_dokumen_telaah : "file tanggapan"
    cru_pembayaran ||--o| proposal_documents : "bukti bayar"

    respon ||--o{ jawaban : "jawaban per pertanyaan"
    master_aspek ||--o{ master_pertanyaan : "punya"
    master_pertanyaan ||--o{ jawaban : dijawab
    master_skala ||--o{ jawaban : "nilai"

    menus ||--o{ menus : "submenu (parent_id)"

    proposal {
        uuid id PK
        string tipe_proposal
        smallint tahun
        smallint bulan
        bigint nomor
        string kode UK
        uuid user_id FK
        string status
        string unit_sekarang
    }
    cru_berkas_penelitian {
        uuid proposal_id UK
        timestamp tanggal_presentasi
        string kategori_presentasi
        text catatan_verifikasi
    }
    cru_pembayaran {
        uuid proposal_id FK
        string tujuan
        bigint nominal
        string status
        uuid dokumen_id FK
    }
    cru_izin_penelitian {
        uuid proposal_id UK
        string nomor_izin
        timestamp tanggal_terbit_final
        date berlaku_sampai
    }
    kepk_protokol_etik {
        uuid proposal_id UK
        string nomor_protokol UK
        string jenis_telaah
        timestamp tanggal_sidang
        string keputusan
        string nomor_ec
    }
    kepk_form_etik {
        uuid proposal_id UK
        json kelengkapan
        boolean multisenter
        string kerjasama
        boolean pernah_diajukan
        boolean sampel_ke_luar_negeri
        timestamp dikirim_pada
    }
    kepk_informed_consent {
        uuid proposal_id UK
        boolean merekrut_partisipan
        text alasan_tanpa_consent
        json lembar_informasi
        text tanda_tangan
        timestamp dikirim_pada
    }
    kepk_penugasan_reviewer {
        uuid proposal_id FK
        uuid reviewer_id FK
        string status
    }
    kepk_telaah_reviewer {
        uuid proposal_id FK
        uuid reviewer_id FK
        string unit
        string keputusan
        smallint ronde
    }
    kepk_dokumen_telaah {
        uuid proposal_id FK
        uuid telaah_id FK
        string path
        smallint versi
    }
```

Tidak digambar karena berdiri sendiri: `informasi_kontak` (singleton konfigurasi), `menus`,
tabel RBAC spatie, dan tabel infrastruktur Laravel (§8).

---

## 3. Kernel — dipakai lintas unit

### `rspi.proposal`
Satu baris = satu pengajuan. Tidak ada kolom file (semua di `proposal_documents`), tidak ada
kolom tahapan (turunan status), dan **tidak ada data kerja unit** — itu ada di §4 dan §5.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | uuid | PK |
| `tipe_proposal` | varchar(2) | cast enum `TipeProposal` — `01` internal, `02` eksternal; dipilih peneliti saat mengajukan |
| `tahun` | smallint | tahun pengajuan, **empat digit** (`2026`) |
| `bulan` | smallint | bulan terbitnya nomor, 1–12 |
| `nomor` | bigint | urut per tahun; internal & eksternal berbagi satu deret |
| `kode` | varchar **unique** | 10 digit rapat: tipe(2)+tahun(2)+bulan(2)+urut(4), mis. `0126080001` |
| `peneliti_utama` | varchar | |
| `tim_peneliti` | text null | |
| `judul_penelitian` | text | |
| `institusi_asal` / `email` / `phone` | varchar null | snapshot pengaju saat mengajukan |
| `sponsor` | varchar null | Poin A.6 formulir etik — pemberi grant, boleh kosong |
| `jenis_penelitian` | varchar null | cast enum `JenisPenelitian` — Poin A.7, **wajib** di form pengajuan |
| `lokasi_penelitian` | varchar null | Poin A.8 — **wajib** di form pengajuan |
| `user_id` | uuid | pengaju |
| `status` | varchar | cast enum `ProposalStatus` — **satu-satunya sumber kebenaran alur** |
| `unit_sekarang` | varchar null | cast enum `Unit`; **turunan status**, disimpan agar antrian ter-index |

Index: `unique(kode)` · `unique(tahun, nomor)` · index `status`, `unit_sekarang`, `user_id`.

### `rspi.proposal_documents`
Satu baris = satu file. Menambah jenis dokumen = menambah nilai enum `DocumentType`,
**bukan** menambah kolom.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `proposal_id` | uuid | |
| `jenis` | varchar | enum `DocumentType` |
| `path` | varchar | path **relatif** di disk `dokumen` — `proposal/{id}/{jenis}/{file}` |
| `nama_asli` | varchar null | nama file saat diunggah |
| `versi` | smallint default 1 | naik otomatis per (proposal, jenis) → riwayat revisi |
| `uploaded_by` | uuid null | |

Index: `(proposal_id, jenis)`.

Tabel ini **tidak dipecah per unit**: strukturnya seragam dan `jenis` sudah membedakan pemilik.
Berkas rahasia telaah reviewer bukan pengecualian di sini — ia tidak pernah masuk tabel ini
sama sekali (§5).

### `rspi.proposal_status_history`
Audit tektokan status. Ditulis otomatis oleh `ProposalWorkflow::catatHistory()` pada setiap
transisi — termasuk pengajuan pertama (`from_status = null`).

| Kolom | Keterangan |
|---|---|
| `proposal_id` | |
| `from_status` / `to_status` | nilai enum `ProposalStatus` |
| `unit` | unit tujuan (enum `Unit`) |
| `actor_id` | user yang memindahkan |
| `catatan` | catatan bebas dari pelaku |

Index: `proposal_id`. **Sengaja tidak dipecah per unit** — satu proposal melintasi CRU dan KEPK,
dan riwayat yang terpotong justru merusak hal yang mau dilindungi.

---

## 4. Berkas Kerja CRU

Baris satelit dibuat **saat CRU pertama kali menyentuh proposal**, bukan saat pengajuan —
supaya keberadaan baris berarti sesuatu.

### `rspi.cru_berkas_penelitian` — 1:1

| Kolom | Tipe | Keterangan |
|---|---|---|
| `proposal_id` | uuid | **partial unique** `where deleted_at is null` |
| `tanggal_presentasi` | timestamp null | |
| `kategori_presentasi` / `media_presentasi` | varchar null | |
| `catatan_verifikasi` | text null | catatan CRU atas berkas Tahap 1 |
| `diverifikasi_oleh` / `diverifikasi_pada` | uuid / timestamp null | |

### `rspi.cru_pembayaran` — 2 baris per proposal

| Kolom | Tipe | Keterangan |
|---|---|---|
| `proposal_id` | uuid | |
| `tujuan` | varchar | enum `TujuanPembayaran` = `cru` \| `kepk` |
| `nominal` | bigint null | rupiah sebagai **integer**, bukan desimal |
| `status` | varchar | enum `StatusPembayaran`, default `menunggu` |
| `dokumen_id` | uuid null | menunjuk baris bukti bayar di `proposal_documents` |
| `diverifikasi_oleh` / `diverifikasi_pada` | uuid / timestamp null | |
| `catatan` | text null | alasan bila ditolak |

Partial unique `(proposal_id, tujuan)` · index `(proposal_id, status)`.

Kirim ulang setelah ditolak memakai **baris yang sama**; riwayat buktinya ada di `versi`
dokumen. `DocumentType` tetap punya dua nilai `bukti_bayar_cru` dan `bukti_bayar_kepk` — kalau
digabung jadi satu jenis, kedua bukti saling menaikkan versi dan riwayat revisinya kacau.

### `rspi.cru_izin_penelitian` — 1:1

`proposal_id` (partial unique), `nomor_izin`, `tanggal_terbit_draft`, `tanggal_terbit_final`,
`berlaku_sampai` (date), `diterbitkan_oleh`.

---

## 5. Berkas Kerja KEPK

### `rspi.kepk_protokol_etik` — 1:1

| Kolom | Keterangan |
|---|---|
| `proposal_id` | partial unique |
| `nomor_protokol` | penomoran KEPK sendiri, **terpisah** dari `RSPISS-YYYY-###`. Partial unique (abaikan null) |
| `jenis_telaah` | enum `JenisTelaah` = `exempted` \| `expedited` \| `full_board` |
| `tanggal_sidang` | timestamp null |
| `keputusan` | enum `KeputusanEtik` = `layak` \| `tidak_layak` |
| `nomor_ec` / `tanggal_terbit_ec` / `berlaku_sampai` | ethical clearance yang diterbitkan |

### `rspi.kepk_form_etik` — 1:1

Formulir Pengajuan Etik yang **diisi peneliti** (Poin B & C), menggantikan unggahan PDF
`form_kaji_etik`. Terpisah dari `kepk_protokol_etik` karena tabel itu ditulis KEPK,
sedangkan tabel ini ditulis peneliti. Poin A tidak disalin ke sini — jawabannya sudah ada
di `proposal`.

| Kolom | Keterangan |
|---|---|
| `proposal_id` | partial unique |
| `kelengkapan` | json `{a..j: bool}` — ceklist Poin B, enum `KelengkapanDokumen`. **Deklarasi**, bukan slot unggahan; tidak satu pun butir wajib dicentang |
| `multisenter` | bool. Bila true → `senter_utama` (wajib), `senter_satelit` |
| `kerjasama` | enum `BentukKerjasama` = `bukan` \| `nasional` \| `internasional`. Bila internasional → `jumlah_negara` wajib |
| `peneliti_asing` | bool — kolom sendiri, bukan pilihan `kerjasama`: bisa benar bersamaan dengan nasional maupun internasional |
| `pernah_diajukan` | bool. Bila true → `disetujui_komisi_lain` wajib |
| `sampel_ke_luar_negeri` | bool. Bila true → `negara_tujuan` wajib |
| `registrasi_bpom` | text null — jawaban bebas Poin C.5 |
| `tanda_tangan` | data URL PNG dari kanvas `x-mary-signature` — lihat `App\Support\TandaTangan` |
| `dikirim_pada` | timestamp saat formulir dikirim; **null = masih draf** |

Jawaban lanjutan yang syaratnya gugur **dikosongkan saat disimpan**, supaya tidak ada dua
jawaban yang saling bertentangan. Dibaca lewat modal di kartu Dokumen dan dicetak PDF di
`GET /proposal/{proposal}/formulir-etik.pdf` (otorisasi `Proposal::bolehDilihatOleh()`).

### `rspi.kepk_informed_consent` — 1:1

Lembar Informasi Informed Consent yang **diisi peneliti**, menggantikan unggahan PDF
`informed_consent`.

Lembar Persetujuan (halaman terakhir formulir kertas) **tidak punya kolom sama sekali**: ia
ditandatangani subjek penelitian di lapangan — orang yang tidak punya akun di aplikasi ini —
jadi aplikasi hanya mencetaknya kosong sebagai templat. Yang ditelaah KEPK memang templatnya,
bukan persetujuan yang sudah terkumpul.

| Kolom | Keterangan |
|---|---|
| `proposal_id` | partial unique |
| `merekrut_partisipan` | bool. **false** (mis. rekam medis retrospektif) → seluruh Lembar Informasi dilewati dan `alasan_tanpa_consent` wajib |
| `alasan_tanpa_consent` | text null — alasannya tetap dicatat supaya KEPK menilai, bukan menebak |
| `peran_peneliti` / `maksud_penelitian` | mengisi kalimat pembuka; instansi tidak disalin — sudah ada di `proposal` |
| `lembar_informasi` | json `{bagian: teks}` — 14 bagian naratif, enum `BagianLembarInformasi` |
| `tanda_tangan` | data URL PNG; wajib hanya bila merekrut partisipan |
| `dikirim_pada` | timestamp saat dikirim; **null = masih draf** |

Jawaban yang syaratnya gugur dikosongkan saat disimpan. Dibaca lewat modal di kartu Dokumen
dan dicetak di `GET /proposal/{proposal}/informed-consent.pdf` (otorisasi
`Proposal::bolehDilihatOleh()`); PDF-nya memuat Lembar Informasi + **Lembar Persetujuan kosong**
dengan judul penelitian terisi.

### `rspi.kepk_penugasan_reviewer`
Penugasan reviewer oleh KEPK — bisa lebih dari satu per proposal.

| Kolom | Keterangan |
|---|---|
| `reviewer_id` | user ber-role reviewer |
| `status` | `menunggu` \| `acc` \| `revisi` |

Index `(reviewer_id, status)` + **partial unique** `(proposal_id, reviewer_id) where deleted_at is null`.

Aturan: antrian reviewer = proposal berstatus `Menunggu Review Reviewer` yang penugasannya
`menunggu`. Semua penugasan `acc` → proposal otomatis `Disetujui Reviewer`. Peneliti kirim
revisi → semua penugasan di-reset ke `menunggu` (ronde baru).

### `rspi.kepk_telaah_reviewer`
Komentar & keputusan per ronde — dipakai reviewer (unit `reviewer`) dan KEPK saat menolak
etik (unit `kaji_etik`).

| Kolom | Keterangan |
|---|---|
| `unit` | enum `Unit` — pembeda telaah reviewer vs penolakan KEPK |
| `reviewer_id` | penulis |
| `keputusan` | `approve` \| `revise` \| `reject` |
| `komentar` | teks |
| `ronde` | naik per reviewer tiap kali merespons |

Index: `(proposal_id, ronde)`.

### `rspi.kepk_dokumen_telaah`
File tanggapan reviewer: `proposal_id`, `telaah_id` (null-able), `path`, `nama_asli`, `versi`,
`uploaded_by`. Index `(proposal_id)`. Aturan upload di `DokumenTelaah::ATURAN_VALIDASI`.

**Kenapa tabel sendiri.** Selama berkas ini duduk di `proposal_documents`, kerahasiaannya
bergantung pada sebuah `if` di `DocumentDownloadController` dan filter yang harus diingat di
setiap query baru. Setelah dipisah, peneliti tidak punya satu pun route yang menjangkaunya —
unduhannya lewat `DokumenTelaahDownloadController` yang hanya menerima pemegang permission
unit. Lupa memfilter jadi tidak mungkin, bukan sekadar belum pernah terjadi.

---

## 6. Enum

Semua nilai disimpan sebagai string di kolom `varchar`, di-cast lewat `$casts` model.

### `App\Enums\ProposalStatus` (19 nilai)

Daftar nilai, tahap, dan unit ada di [prd.md §4](prd.md#4-status-tahap-dan-unit). Peta
transisi yang sah (`allowedNext()`):

| Dari | Boleh ke |
|---|---|
| `Menunggu Verifikasi Berkas` | `Perlu Revisi Proposal`, `Menunggu Presentasi`, `Ditolak` |
| `Perlu Revisi Proposal` | `Menunggu Verifikasi Revisi` |
| `Menunggu Verifikasi Revisi` | `Menunggu Kelengkapan Berkas Etik` *(loloskan tanpa presentasi ulang)*, `Perlu Revisi Proposal`, `Menunggu Presentasi`, `Ditolak` |
| `Menunggu Presentasi` | `Menunggu Kelengkapan Berkas Etik`, `Perlu Revisi Proposal`, `Ditolak` |
| `Menunggu Kelengkapan Berkas Etik` | `Menunggu Penunjukan Reviewer`, `Ditolak Kaji Etik` |
| `Menunggu Penunjukan Reviewer` | `Perlu Revisi Berkas Etik` *(berkas kurang)*, `Menunggu Review Reviewer`, `Ditolak Kaji Etik` |
| `Perlu Revisi Berkas Etik` | `Menunggu Penunjukan Reviewer` *(hanya maju)* |
| `Menunggu Review Reviewer` | `Perlu Revisi Reviewer`, `Disetujui Reviewer`, `Ditolak Kaji Etik` |
| `Perlu Revisi Reviewer` | `Menunggu Review Reviewer` |
| `Disetujui Reviewer` | `Menunggu Pembayaran`, `Ditolak Kaji Etik` |
| `Menunggu Pembayaran` | `Menunggu Verifikasi Pembayaran` |
| `Menunggu Verifikasi Pembayaran` | `Pelaksanaan Penelitian`, `Menunggu Pembayaran` *(mundur)* |
| `Pelaksanaan Penelitian` | `Menunggu Verifikasi Akhir` |
| `Menunggu Verifikasi Akhir` | `Menunggu Survey Kepuasan`, `Pelaksanaan Penelitian` *(mundur)* |
| `Menunggu Survey Kepuasan` | `Selesai` |
| `Selesai`, `Ditolak`, `Ditolak Kaji Etik`, `Dibatalkan` | — *(terminal)* |

`Dibatalkan` tidak muncul di tabel: `canGoTo()` mengizinkannya dari **semua** status non-terminal.

Helper lain di enum ini: `tahapan()` (1–4, `null` untuk terminal) · `unit()` (enum `Unit`,
`null` untuk `Selesai`/`Dibatalkan`) · `isTerminal()` · `warna()` (kelas badge daisyUI) ·
`bolaDiPeneliti()`.

### `App\Enums\DocumentType` (16 nilai)

| Kelompok | Nilai |
|---|---|
| Tahap 1 | `surat_pengantar`, `proposal` *(wajib)*; `kaji_etik`, `sertifikat_gcp` *(opsional)* |
| Tahap 2 | `kerahasiaan_data` *(satu-satunya unggahan wajib)*; `form_kaji_etik` dan `informed_consent` **tidak lagi diunggah** — digantikan isian terstruktur `rspi.kepk_form_etik` dan `rspi.kepk_informed_consent`. Kedua nilai enum dipertahankan agar unggahan lama tetap tampil dan terbaca reviewer |
| Tahap 3 | `bukti_bayar_cru`, `bukti_bayar_kepk` *(keduanya wajib)* |
| Tahap 4 | `laporan_penelitian`, `raw_data` |
| Output admin | `izin_draft`, `izin_final`, `surat_penolakan`, `surat_tanggapan` |
| Lepas dari alur | `pks` — diunggah **CRU** kapan saja, termasuk setelah proposal `Selesai` |

Helper: `label()` · `wajibTahap1()` / `opsionalTahap1()` / `wajibTahap2()` ·
`aturanValidasi()` (aturan upload Livewire) · `milikAdmin()` · `hintUnggah()`.

`hintUnggah()` menghasilkan teks seperti `PDF · maks 10 MB` untuk atribut `hint` di setiap
`x-mary-file`, **diturunkan dari `aturanValidasi()` lewat `hintDariAturan()`** — bukan ditulis
ulang di view. Angka di layar karena itu tidak pernah bisa berbeda dari angka yang ditegakkan.

Tidak ada lagi nilai `tanggapan_reviewer` — berkas itu punya tabelnya sendiri (§5).

`pks` **sengaja tidak masuk `wajibTahap2()`.** Penerbitannya lama dan sering baru selesai
setelah penelitiannya rampung; sebagai syarat Tahap 2 ia membekukan proposal karena menunggu
berkas yang belum tentu ada. Yang mengunggah CRU (`milikAdmin()` = true), lewat
`Show::unggahPks()` yang tidak menyentuh status sama sekali.

### `App\Enums\Unit` (3 nilai)
`penelitian` (CRU) · `kaji_etik` (KEPK) · `reviewer`. Kosakata yang sama dipakai di
`proposal.unit_sekarang`, `proposal_status_history.unit`, dan `kepk_telaah_reviewer.unit`.

### Enum berkas kerja

| Enum | Nilai |
|---|---|
| `TipeProposal` | `01` internal, `02` eksternal — plus `label()`, `keterangan()`. **Nilainya tidak boleh diubah** begitu ada proposal di database: ia tercetak di kolom `kode` |
| `TujuanPembayaran` | `cru`, `kepk` — plus `jenisDokumen()` yang memetakan ke `DocumentType` |
| `StatusPembayaran` | `menunggu`, `terverifikasi`, `ditolak` — plus `label()`, `warna()` |
| `JenisTelaah` | `exempted`, `expedited`, `full_board` |
| `KeputusanEtik` | `layak`, `tidak_layak` |

> **`JenisTelaah` dan `KeputusanEtik` belum dikonfirmasi ke KEPK RSPI.** Istilahnya standar
> WHO/CIOMS dan lazim di komite etik Indonesia, tapi belum tentu sama dengan SOP setempat.
> Karena disimpan sebagai string, menggantinya cukup mengubah satu file enum — **tanpa
> migration**, selama belum ada data produksi.

---

## 7. Survey Kepuasan

| Tabel | Isi |
|---|---|
| `rspi.master_aspek` | `nama_aspek`, `deskripsi`, `urutan`, `status_aktif` |
| `rspi.master_pertanyaan` | `master_aspek_id`, `pertanyaan`, `is_required`, `urutan`, `status_aktif` |
| `rspi.master_skala` | `nama_skala`, `nilai` (int), `urutan` |
| `rspi.respon` | `proposal_id`, `responden_id`, `responden` & `jenis_responden` (snapshot), `saran` |
| `rspi.jawaban` | `respon_id`, `master_pertanyaan_id`, `master_skala_id`, + snapshot teks `pertanyaan` & `jawaban` |

**Partial unique** `respon (proposal_id) where deleted_at is null` — satu survey aktif per
proposal. Baris `respon` inilah yang membuka kunci unduhan `izin_final`.

**Tidak ada penanda boolean di tabel `proposal`.** Satu-satunya cara menjawab "sudah isi
survey?" adalah `Proposal::sudahIsiSurvey()` → `respon()->exists()`, dipakai bersama oleh
`DocumentDownloadController` dan view. Kolom `isi_survey_kepuasan` yang dulu ada dibuang karena
jadi sumber kebenaran kedua: ia hanya diset saat status jadi `Selesai`, jadi kalau baris
`respon` dihapus, penandanya tetap `true` dan izin final ikut terbuka.

Snapshot teks pertanyaan & jawaban disimpan di `jawaban` supaya laporan lama tidak berubah
ketika master pertanyaan/skala diedit.

---

## 8. Menu Dinamis, Kontak & Tabel Bawaan

### `rspi.menus`
`nama`, `slug` (unique), `route`, `icon`, `parent_id`, `urutan`, `aktif`.
Index `(parent_id, urutan)`.

`MenuObserver` → `MenuPermissionSync` menjaga permission spatie tetap sinkron:
menu dibuat → `{slug}.read|create|update|delete` dibuat; slug diganti → permission di-rename;
menu dihapus → permission dihapus. Cache permission di-flush tiap perubahan.

### `rspi.informasi_kontak`
Tabel konfigurasi satu-baris: telepon, fax, callcenter, hotline, email, alamat, sosial media,
contact person per layanan (kaji etik, PKS, MTA, kerahasiaan), serta **data rekening
pembayaran** (`pemilik_rekening`, `nomor_rekening`, `nama_bank`, `logo_bank`, `deskripsi_biaya`)
yang ditampilkan ke peneliti pada Tahap 3.

### Tabel di `public`

**RBAC (spatie/laravel-permission v8):** `permissions`, `roles`, `model_has_permissions`,
`model_has_roles`, `role_has_permissions`. Morph key di-set ke **uuid**; `'teams' => false`.

**Laravel:** `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
`job_batches`, `failed_jobs`, `migrations`.

`users` mengikuti konvensi §1 (uuid + softDeletes + auditColumns) dan menambah kolom domain:
`username` (unique, nullable), `phone`, `jk`, `institusi_asal`, `kategori_pendidikan`.

---

## 9. Keputusan Desain yang Terkunci

| # | Isu | Keputusan |
|---|---|---|
| D1 | Tahap untuk status terminal | `tahapan()` mengembalikan `null` |
| D2 | `unit_sekarang` | disimpan + index, di-sync `ProposalWorkflow::transition()` |
| D3 | Kosakata unit | enum `Unit` = `penelitian\|kaji_etik\|reviewer` di semua tabel |
| D4 | Jalan mundur | verifikasi pembayaran → menunggu pembayaran; verifikasi akhir → pelaksanaan; plus `Dibatalkan` dari semua status non-terminal |
| D5 | Survey per proposal | `respon.proposal_id` + partial unique; gate unduh di `DocumentDownloadController` lewat `sudahIsiSurvey()` |
| D6 | Kode proposal | 10 digit `TTYYMMNNNN` (mis. `0126080001`); dirakit satu tempat di `ProposalWorkflow::formatKode()`, deret `nomor` per tahun dengan `unique(tahun, nomor)` dijaga `pg_advisory_xact_lock`. Bulan hanya penanda terbit, **bukan** pembatas deret; internal & eksternal berbagi deret yang sama |
| D7 | Pemisahan CRU/KEPK | **satu pengajuan, dua berkas kerja**: satu `proposal` dengan satu rantai status; data & keputusan tiap unit di tabel `cru_*` / `kepk_*` sendiri |
| D8 | Schema | dua schema — `public` untuk tabel bawaan, `rspi` untuk seluruh domain. Kelompok CRU/KEPK dinyatakan **prefiks nama tabel**, bukan schema, jadi batasnya tidak bisa ditegakkan `GRANT` |
| D9 | Kerahasiaan telaah | berkas & komentar telaah di tabel `kepk_*` terpisah dengan route unduh sendiri — bukan disaring dari tabel bersama |
| D10 | 1:1 | ditegakkan database lewat *partial unique* `where deleted_at is null`, bukan hanya konvensi aplikasi |

---

## Konversi ke PDF

ERD di atas adalah blok `mermaid`, dirender oleh preview Markdown VS Code & GitHub.
Cara ekspor PDF sama seperti [prd.md](prd.md#konversi-ke-pdf): ekstensi *Markdown PDF* /
*Markdown Preview Enhanced* di VS Code, atau `mermaid-cli` + `pandoc` lewat CLI.
