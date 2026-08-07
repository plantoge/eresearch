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
// ... kolom domain ...
$t->timestamps();
$t->softDeletes();
$t->auditColumns();   // created_by, updated_by, deleted_by
```

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
        smallint tahun
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
| `tahun` | smallint | tahun pengajuan |
| `nomor` | bigint | urut per tahun |
| `kode` | varchar **unique** | `RSPISS-YYYY-###` |
| `peneliti_utama` | varchar | |
| `tim_peneliti` | text null | |
| `judul_penelitian` | text | |
| `institusi_asal` / `email` / `phone` | varchar null | snapshot pengaju saat mengajukan |
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

### `App\Enums\ProposalStatus` (18 nilai)

Daftar nilai, tahap, dan unit ada di [prd.md §4](prd.md#4-status-tahap-dan-unit). Peta
transisi yang sah (`allowedNext()`):

| Dari | Boleh ke |
|---|---|
| `Menunggu Verifikasi Berkas` | `Perlu Revisi Proposal`, `Menunggu Presentasi`, `Ditolak` |
| `Perlu Revisi Proposal` | `Menunggu Verifikasi Revisi` |
| `Menunggu Verifikasi Revisi` | `Menunggu Kelengkapan Berkas Etik` *(loloskan tanpa presentasi ulang)*, `Perlu Revisi Proposal`, `Menunggu Presentasi`, `Ditolak` |
| `Menunggu Presentasi` | `Menunggu Kelengkapan Berkas Etik`, `Perlu Revisi Proposal`, `Ditolak` |
| `Menunggu Kelengkapan Berkas Etik` | `Menunggu Penunjukan Reviewer`, `Ditolak Kaji Etik` |
| `Menunggu Penunjukan Reviewer` | `Menunggu Review Reviewer`, `Ditolak Kaji Etik` |
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
| Tahap 2 | `form_kaji_etik`, `informed_consent`, `pks`, `kerahasiaan_data` *(semua wajib)* |
| Tahap 3 | `bukti_bayar_cru`, `bukti_bayar_kepk` *(keduanya wajib)* |
| Tahap 4 | `laporan_penelitian`, `raw_data` |
| Output admin | `izin_draft`, `izin_final`, `surat_penolakan`, `surat_tanggapan` |

Helper: `label()` · `wajibTahap1()` / `opsionalTahap1()` / `wajibTahap2()` ·
`aturanValidasi()` (aturan upload Livewire) · `milikAdmin()`.

Tidak ada lagi nilai `tanggapan_reviewer` — berkas itu punya tabelnya sendiri (§5).

### `App\Enums\Unit` (3 nilai)
`penelitian` (CRU) · `kaji_etik` (KEPK) · `reviewer`. Kosakata yang sama dipakai di
`proposal.unit_sekarang`, `proposal_status_history.unit`, dan `kepk_telaah_reviewer.unit`.

### Enum berkas kerja

| Enum | Nilai |
|---|---|
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
| D6 | Kode proposal | `RSPISS-YYYY-###`; kolom `tahun` + `nomor` dengan `unique(tahun, nomor)`, dijaga `pg_advisory_xact_lock` |
| D7 | Pemisahan CRU/KEPK | **satu pengajuan, dua berkas kerja**: satu `proposal` dengan satu rantai status; data & keputusan tiap unit di tabel `cru_*` / `kepk_*` sendiri |
| D8 | Schema | dua schema — `public` untuk tabel bawaan, `rspi` untuk seluruh domain. Kelompok CRU/KEPK dinyatakan **prefiks nama tabel**, bukan schema, jadi batasnya tidak bisa ditegakkan `GRANT` |
| D9 | Kerahasiaan telaah | berkas & komentar telaah di tabel `kepk_*` terpisah dengan route unduh sendiri — bukan disaring dari tabel bersama |
| D10 | 1:1 | ditegakkan database lewat *partial unique* `where deleted_at is null`, bukan hanya konvensi aplikasi |

---

## Konversi ke PDF

ERD di atas adalah blok `mermaid`, dirender oleh preview Markdown VS Code & GitHub.
Cara ekspor PDF sama seperti [prd.md](prd.md#konversi-ke-pdf): ekstensi *Markdown PDF* /
*Markdown Preview Enhanced* di VS Code, atau `mermaid-cli` + `pandoc` lewat CLI.
