# Spec — Pemisahan Struktur Database CRU & KEPK

**Status:** rancangan, **belum terpasang**. Ditulis 7 Agustus 2026.

**Umur file ini terbatas.** Begitu migration dijalankan dan strukturnya jadi kenyataan,
`docs/skema.md` ditulis ulang sebagai kondisi terpasang dan file ini dibuang. Sengaja
diletakkan di luar `docs/` supaya `rules.md §1` (docs tepat enam file) tetap utuh dan tidak
ada dokumen basi yang tertinggal.

---

## 1. Masalah

Tabel `proposal` menampung data milik dua unit yang berbeda dalam satu baris: identitas
pengajuan (lintas unit), jadwal presentasi (milik CRU), dan penanda survey (milik Tahap 4).
Kosakata unit — `penelitian` / `kaji_etik` / `reviewer` — hidup sebagai **nilai kolom**, bukan
sebagai struktur. Empat akibatnya:

1. **Kolom terus bertambah.** Tiap permintaan field baru dari CRU atau KEPK melebarkan satu
   tabel yang sama, dan kolomnya `NULL` bagi unit yang lain.
2. **Kerahasiaan bergantung pada ingatan.** Telaah reviewer dan file `tanggapan_reviewer`
   duduk di tabel yang sama dengan berkas peneliti; yang memisahkan cuma `if` di
   `DocumentDownloadController` dan filter di tiap query. Lupa satu kali = bocor.
3. **Laporan per unit sulit.** Lama tahan di KEPK vs di CRU, jumlah tolak etik, SLA per unit —
   semua harus ditebak dari status dan `proposal_status_history`.
4. **KEPK tidak punya tempat untuk datanya sendiri.** Nomor protokol etik, jenis telaah,
   tanggal sidang, dan masa berlaku ethical clearance belum ada kolomnya sama sekali.

---

## 2. Keputusan yang Terkunci

| # | Isu | Keputusan | Alasan singkat |
|---|---|---|---|
| K1 | Bentuk pemisahan | **Satu pengajuan, dua berkas kerja.** Satu `proposal`, satu nomor `RSPISS-YYYY-###`; CRU dan KEPK punya tabel kerja masing-masing | Alur bisnis tetap sequential; tidak ada layanan KEPK berdiri sendiri |
| K2 | Model status | **Tetap satu rantai.** `proposal.status` tetap satu-satunya sumber kebenaran alur | `ProposalWorkflow`, `ProposalStatus`, `Unit`, dan semua UI antrian tidak berubah — risiko terkecil |
| K3 | Schema | **Dua schema: `public` dan `rspi`.** Semua tabel domain di `rspi` | Pilihan pemilik sistem. Batas CRU/KEPK karena itu **tidak** ditegakkan `GRANT` per schema |
| K4 | Penamaan | **Prefiks kelompok:** `cru_*` dan `kepk_*`. Kernel & konfigurasi tanpa prefiks | Karena schema tidak lagi menyatakan kelompoknya, nama tabel yang menggantikan peran itu |
| K5 | Garis potong | **Per pemilik data.** `proposal_documents` **tidak** dipecah | Struktur dokumen seragam; `jenis` sudah membedakan pemilik. Memecahnya menggandakan service upload & controller unduh |
| K6 | Data existing | **Belum ada data nyata** — isinya seeder demo + `ProposalBulkSeeder` | Tidak perlu backfill, tidak perlu masa transisi |
| K7 | Migration | **8 migration domain ditulis ulang** (4 migration bawaan Laravel/spatie tidak disentuh), lalu `migrate:fresh` + seed oleh pemilik sistem | Struktur akhir terbaca apa adanya, bukan lapisan tambal-menambal. `rules.md §5` melarang mengedit migration yang sudah jalan — larangan itu untuk melindungi data produksi, yang di sini tidak ada. **Konsekuensi: 1,2 juta baris data uji hilang** (menutup keputusan yang menggantung di `task.md`) |
| K8 | Tes | **Pindah ke PostgreSQL** (`eprotocol_test`) | SQLite tidak mengenal schema; `"rspi"."proposal"` di sana dibaca sebagai *attached database* → 40 tes gagal semua |

---

## 3. Peta Schema

Aturannya satu kalimat: **`public` untuk yang dipakai lintas unit dan yang diharapkan ada di
sana oleh vendor, `rspi` untuk seluruh domain.**

### `public` — 14 tabel

`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`,
`failed_jobs`, `migrations`, `permissions`, `roles`, `model_has_permissions`, `model_has_roles`,
`role_has_permissions`.

`users` tetap di `public` walau punya kolom domain (`username`, `phone`, `jk`, `institusi_asal`,
`kategori_pendidikan`) — dia target morph spatie dan dipakai auth bawaan; memindahkannya
menambah risiko tanpa imbalan.

### `rspi` — 17 tabel

| Kelompok | Tabel |
|---|---|
| Kernel (lintas unit) | `proposal`, `proposal_status_history`, `proposal_documents` |
| CRU | `cru_berkas_penelitian`, `cru_pembayaran`, `cru_izin_penelitian` |
| CRU — survey kepuasan | `master_aspek`, `master_pertanyaan`, `master_skala`, `respon`, `jawaban` |
| KEPK | `kepk_protokol_etik`, `kepk_penugasan_reviewer`, `kepk_telaah_reviewer`, `kepk_dokumen_telaah` |
| Konfigurasi | `menus`, `informasi_kontak` |

**26 tabel → 31.** Lima benar-benar baru: `cru_berkas_penelitian`, `cru_pembayaran`,
`cru_izin_penelitian`, `kepk_protokol_etik`, `kepk_dokumen_telaah`. Dua di-rename
(`proposal_reviewers` → `kepk_penugasan_reviewer`, `proposal_reviews` → `kepk_telaah_reviewer`).

`proposal_status_history` sengaja **tidak** dipecah per unit: jejak audit harus utuh melintasi
unit, dan memecahnya justru merusak hal yang mau dilindungi.

---

## 4. Definisi Tabel Target

Semua mengikuti `docs/skema.md §1`: `uuid` v7 PK, `timestamps()`, `softDeletes()`,
`auditColumns()`, kolom `*_id` ter-index **tanpa** constraint FK (integritas di layer aplikasi).

Partial unique memakai idiom yang sudah dipakai di repo — nama index **tidak** dikualifikasi
schema (index mewarisi schema tabelnya), nama tabelnya iya:

```php
DB::statement('create unique index cru_pembayaran_unique on rspi.cru_pembayaran (proposal_id, tujuan) where deleted_at is null');
```

### 4.1 `rspi.proposal` — menyusut 4 kolom

Hilang: `tanggal_presentasi`, `kategori_presentasi`, `media_presentasi` (→ `cru_berkas_penelitian`)
dan `isi_survey_kepuasan` (dihapus, lihat §6).

Tetap: `tahun`, `nomor`, `kode`, `peneliti_utama`, `tim_peneliti`, `judul_penelitian`,
`institusi_asal`, `email`, `phone`, `user_id`, `status`, `unit_sekarang`.
Seluruh index tidak berubah: `unique(kode)`, `unique(tahun, nomor)`, index `status`,
`unit_sekarang`, `user_id`.

### 4.2 `rspi.cru_berkas_penelitian` — baru, 1:1

| Kolom | Tipe | Keterangan |
|---|---|---|
| `proposal_id` | uuid | partial unique `where deleted_at is null` → 1:1 ditegakkan database |
| `tanggal_presentasi` | timestamp null | pindahan dari `proposal` |
| `kategori_presentasi` | varchar null | pindahan |
| `media_presentasi` | varchar null | pindahan |
| `catatan_verifikasi` | text null | catatan CRU atas berkas Tahap 1 |
| `diverifikasi_oleh` | uuid null | |
| `diverifikasi_pada` | timestamp null | |

### 4.3 `rspi.cru_pembayaran` — baru, 2 baris per proposal

| Kolom | Tipe | Keterangan |
|---|---|---|
| `proposal_id` | uuid | |
| `tujuan` | varchar | enum `TujuanPembayaran` = `cru` \| `kepk` |
| `nominal` | bigint null | rupiah sebagai **integer**, bukan desimal |
| `status` | varchar | enum `StatusPembayaran` = `menunggu` \| `terverifikasi` \| `ditolak`, default `menunggu` |
| `dokumen_id` | uuid null | menunjuk baris bukti bayar di `proposal_documents` |
| `diverifikasi_oleh` | uuid null | |
| `diverifikasi_pada` | timestamp null | |
| `catatan` | text null | alasan bila ditolak |

Partial unique `(proposal_id, tujuan)`; index `(proposal_id, status)`.

`DocumentType` **tetap** punya dua nilai `bukti_bayar_cru` dan `bukti_bayar_kepk`, tidak
digabung jadi satu `bukti_bayar`: `versi` naik per pasangan `(proposal, jenis)`, jadi kalau
digabung bukti bayar CRU dan KEPK saling menaikkan versi satu sama lain dan riwayat revisinya
kacau.

### 4.4 `rspi.cru_izin_penelitian` — baru, 1:1

`proposal_id` (partial unique), `nomor_izin` (varchar null), `tanggal_terbit_draft` (timestamp
null), `tanggal_terbit_final` (timestamp null), `berlaku_sampai` (date null),
`diterbitkan_oleh` (uuid null).

### 4.5 `rspi.kepk_protokol_etik` — baru, 1:1

| Kolom | Tipe | Keterangan |
|---|---|---|
| `proposal_id` | uuid | partial unique |
| `nomor_protokol` | varchar null | nomor registrasi KEPK, **terpisah** dari `RSPISS-YYYY-###`. Partial unique |
| `jenis_telaah` | varchar null | enum `JenisTelaah` = `exempted` \| `expedited` \| `full_board` |
| `tanggal_sidang` | timestamp null | |
| `keputusan` | varchar null | enum `KeputusanEtik` = `layak` \| `tidak_layak` |
| `nomor_ec` | varchar null | nomor ethical clearance yang diterbitkan |
| `tanggal_terbit_ec` | timestamp null | |
| `berlaku_sampai` | date null | masa berlaku ethical clearance |

### 4.6 `rspi.kepk_penugasan_reviewer` — eks `proposal_reviewers`

Kolom dan index **tidak berubah**: `proposal_id`, `reviewer_id`, `status`
(`menunggu` \| `acc` \| `revisi`), index `(reviewer_id, status)`, partial unique
`(proposal_id, reviewer_id)`.

### 4.7 `rspi.kepk_telaah_reviewer` — eks `proposal_reviews`

Kolom sama **kecuali `tahap` dibuang.** Terverifikasi konstan: di-hardcode `2` di
`ProposalWorkflow.php:143` dan `Show.php:385`, tidak pernah dibaca sebagai variabel.

Sisa kolom: `proposal_id`, `unit`, `reviewer_id`, `keputusan` (`approve` \| `revise` \|
`reject`), `komentar`, `ronde`. Index `(proposal_id, ronde)`. Kolom `unit` tetap ada — dialah
yang membedakan telaah yang ditulis reviewer dari penolakan etik yang ditulis KEPK.

### 4.8 `rspi.kepk_dokumen_telaah` — baru

`proposal_id`, `telaah_id` (uuid null → `kepk_telaah_reviewer`), `path`, `nama_asli`, `versi`,
`uploaded_by`. Index `(proposal_id)`.

Nilai `tanggapan_reviewer` **dikeluarkan** dari enum `DocumentType` (17 → 16 nilai).

### 4.9 Tidak berubah isinya

`proposal_documents`, `proposal_status_history`, kelima tabel survey, `menus`,
`informasi_kontak` — hanya pindah schema ke `rspi`.

---

## 5. Enum

**Empat enum baru** di `app/Enums/`, semua disimpan sebagai string di kolom `varchar` dan
di-cast lewat `$casts` model — sama seperti enum yang sudah ada:

| Enum | Nilai |
|---|---|
| `TujuanPembayaran` | `cru`, `kepk` |
| `StatusPembayaran` | `menunggu`, `terverifikasi`, `ditolak` |
| `JenisTelaah` | `exempted`, `expedited`, `full_board` |
| `KeputusanEtik` | `layak`, `tidak_layak` |

**`ProposalStatus` dan `Unit` tidak disentuh sama sekali** — 18 status, peta `allowedNext()`,
`tahapan()`, `unit()`, `isTerminal()`, `warna()`, `bolaDiPeneliti()` semuanya tetap.

`DocumentType` kehilangan satu case (`tanggapan_reviewer`) → 16 nilai; `milikAdmin()`
menyesuaikan.

---

## 6. Dampak ke Kode

Semua titik di bawah sudah diperiksa langsung di file, bukan diperkirakan.

### 6.1 Buang `isi_survey_kepuasan`

| File | Baris |
|---|---|
| `app/Services/ProposalWorkflow.php` | 63–65 (blok `if ($ke === Selesai)`) |
| `app/Models/Proposal.php` | 27 (`$fillable`), 34 (`$casts`) |
| `resources/views/livewire/proposal/show.blade.php` | 40 (tooltip tombol unduh) |
| `database/seeders/ProposalBulkSeeder.php` | 118 |
| `tests/Feature/ProposalWorkflowTest.php` | 88 |

**Kenapa dibuang, bukan dipindah.** Ini sumber kebenaran kedua untuk pertanyaan yang sudah
punya jawaban: `DocumentDownloadController.php:36` sudah memakai `$proposal->respon()->exists()`,
sementara view memakai boolean-nya. Boolean itu hanya diset saat status jadi `Selesai` — kalau
baris `respon` dihapus, boolean tetap `true` dan tampilan tetap menganggap izin final terbuka.

Diganti satu accessor di `Proposal`:

```php
/** Gate survey: satu-satunya cara menjawab "sudah isi survey?" */
public function sudahIsiSurvey(): bool
{
    return $this->respon()->exists();
}
```

`DocumentDownloadController` dan view sama-sama memanggilnya.

### 6.2 Tiga kolom presentasi → `cru_berkas_penelitian`

| File | Baris |
|---|---|
| `app/Models/Proposal.php` | 26 (`$fillable`), 33 (`$casts`) |
| `app/Livewire/Proposal/Show.php` | 28, 30, 32 (public property), 231–233 (validasi), 237–239 (update) |
| `resources/views/livewire/proposal/show.blade.php` | 24–26 (tampilan), 131–133 (form jadwalkan presentasi) |
| `database/seeders/ProposalBulkSeeder.php` | 115–117 |

Model baru `App\Models\BerkasPenelitian`; `Proposal::berkasPenelitian()` sebagai `hasOne`.
`Show.php` menulis lewat `updateOrCreate` pada relasi itu, bukan ke `$proposal`.

### 6.3 `tanggapan_reviewer` → `kepk_dokumen_telaah`

| File | Perubahan |
|---|---|
| `app/Enums/DocumentType.php` | buang case `TanggapanReviewer` + entrinya di `label()`, `aturanValidasi()`, `milikAdmin()` |
| `app/Services/ProposalWorkflow.php` | baris 152 — `simpanDokumen(...)` diganti `simpanDokumenTelaah(...)` yang menulis ke tabel baru dan mengaitkan `telaah_id` |
| `app/Http/Controllers/DocumentDownloadController.php` | **baris 29–32 dihapus** |
| baru | `DokumenTelaahDownloadController` + route, hanya untuk pemegang `antrian-cru.read` / `kaji-etik.read` / `antrian-reviewer.read` |

**Ini keuntungan struktural, bukan pemindahan file.** Selama berkas rahasia duduk di tabel yang
sama dengan berkas peneliti, kerahasiaannya bergantung pada sebuah `if` yang harus diingat di
setiap query baru. Setelah pindah, peneliti tidak punya route yang bisa menjangkaunya —
lupa memfilter jadi tidak mungkin, bukan sekadar belum pernah terjadi.

### 6.4 Rename model

| Lama | Baru | Tabel |
|---|---|---|
| `ProposalReview` | `TelaahReviewer` | `rspi.kepk_telaah_reviewer` |
| `ProposalReviewerAssignment` | `PenugasanReviewer` | `rspi.kepk_penugasan_reviewer` |

Terdampak: `ProposalWorkflow` (import + 4 pemakaian), `Proposal::reviews()` →
`telaahReviewer()`, `Proposal::reviewerAssignments()` → `penugasanReviewer()`,
`Proposal::semuaReviewerAcc()`, `Show.php`, dan tes terkait.

Model `ProposalReview` yang menunjuk tabel `kepk_telaah_reviewer` akan membingungkan siapa pun
yang membacanya setahun lagi — karena itu di-rename sekalian, bukan dibiarkan.

### 6.5 Model baru

`BerkasPenelitian`, `Pembayaran`, `IzinPenelitian`, `ProtokolEtik`, `DokumenTelaah` — semua
memakai `HasUuidAndAudit` + `SoftDeletes`, `$incrementing = false`, `$keyType = 'string'`,
dan `$table` berkualifikasi (`'rspi.cru_pembayaran'` dst.).

### 6.6 Titik tulis baru di UI

| Tabel | Kapan diisi |
|---|---|
| `cru_berkas_penelitian` | CRU menjadwalkan presentasi / menyimpan catatan verifikasi Tahap 1 |
| `cru_pembayaran` | peneliti unggah bukti bayar (baris dibuat), CRU verifikasi/tolak (status diubah) |
| `cru_izin_penelitian` | CRU terbitkan izin draft, lalu izin final |
| `kepk_protokol_etik` | KEPK menerima berkas etik (nomor protokol, jenis telaah), lalu saat keputusan & penerbitan EC |

Baris satelit dibuat **saat unit pertama kali menyentuh proposal**, bukan di `ajukan()` —
supaya keberadaan baris berarti sesuatu dan tidak ada baris kosong untuk proposal yang ditolak
di Tahap 1.

---

## 7. Migration

`config/database.php:98` — `'search_path' => 'public'` menjadi `'public,rspi'`. **`public`
tetap di depan**: migration bawaan Laravel dan spatie menulis tanpa kualifikasi dan harus
mendarat di `public`. Tabel domain tidak bergantung urutan itu karena namanya dikualifikasi
eksplisit.

Urutan berkas di `database/migrations/`:

1. **Baru, paling awal** — `create schema if not exists rspi` lewat `DB::statement`.
   Dijaga cek driver `pgsql` supaya `down()` dan driver lain tidak meledak.
2. `0001_01_01_*` dan `2026_07_09_054030_create_permission_tables` — **tidak disentuh**,
   tetap menulis ke `public`.
3. Delapan migration domain (`create_proposal_table`, `..._documents`, `..._status_history`,
   `..._reviews`, `..._reviewers`, `create_menus_table`, `create_informasi_kontak_table`,
   `create_survey_tables`) — ditulis ulang: nama tabel jadi `rspi.<nama>`, kolom yang pindah
   dihapus dari `proposal`, `tahap` dibuang dari telaah, dua tabel di-rename.
4. **Baru** — lima tabel: `cru_berkas_penelitian`, `cru_pembayaran`, `cru_izin_penelitian`,
   `kepk_protokol_etik`, `kepk_dokumen_telaah`.

Dijalankan **oleh pemilik sistem**, bukan dari sesi kerja (`rules.md §5`):

```bash
PHP='/c/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe'
"$PHP" artisan migrate:fresh --seed
```

**Data uji 1,2 juta baris hilang di sini.** Itu memang konsekuensi yang diterima di K7 dan
sekaligus menutup keputusan yang menggantung di `docs/task.md`. `ProposalBulkSeeder` tetap ada
untuk membangkitkannya ulang bila benchmark diperlukan.

---

## 8. Pengujian

`phpunit.xml:26-27` berubah:

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="eprotocol_test"/>
```

DB `eprotocol_test` dibuat pemilik sistem — **terpisah dari `eprotocol`**, tidak menyentuhnya.
User DB butuh `USAGE` + `CREATE` di schema `rspi`.

Efek sampingan yang bagus: `pg_advisory_xact_lock()` di `generateKode()` akhirnya teruji.
Selama ini dilewati karena dijaga `if (DB::connection()->getDriverName() === 'pgsql')`, jadi
jalur anti-race pada penomoran proposal tidak pernah dijalankan satu tes pun.

**Tes yang harus lulus tanpa diubah** (bukti K2 dipegang): seluruh
`tests/Feature/ProposalWorkflowTest.php` selain baris 88, plus tes penugasan reviewer, captcha,
verifikasi email, dan sinkronisasi menu→permission.

**Tes baru:**

1. Partial unique menegakkan 1:1 pada `cru_berkas_penelitian`, `cru_izin_penelitian`,
   `kepk_protokol_etik` — baris kedua ditolak, tapi baris soft-deleted tidak menghalangi.
2. `cru_pembayaran` unik per `(proposal, tujuan)`; dua tujuan berbeda boleh berdampingan.
3. Peneliti mendapat 403 saat mencoba route unduh dokumen telaah; petugas berhasil.
4. Gate `izin_final` lewat `sudahIsiSurvey()`: terkunci sebelum ada `respon`, terbuka sesudahnya,
   **dan terkunci lagi** bila baris `respon` dihapus (perilaku yang sekarang salah).

---

## 9. Konsekuensi Operasional

| Hal | Sesudah |
|---|---|
| Periksa tabel | `artisan db:table rspi.proposal` — nama harus dikualifikasi |
| Hak DB | user butuh `USAGE` + `CREATE` di `rspi`; lupa = `permission denied for schema rspi` saat migrate |
| Backup | `pg_dump` penuh sudah mencakup kedua schema; restore selektif per schema jadi mungkin (`-n rspi`) |
| Tidak terpengaruh | spatie/permission, route model binding `/proposal/{proposal}`, disk `dokumen`, struktur path file |

**Yang tidak didapat dari K3.** Karena CRU dan KEPK berbagi satu schema `rspi`, batas antar
keduanya **tidak** bisa ditegakkan `GRANT` per schema. Yang melindungi kerahasiaan tetap
pemisahan tabel + guard aplikasi. Peningkatannya nyata (§6.3) tapi bukan lapis database.

---

## 10. Yang Sengaja Tidak Diubah

- **`ProposalStatus`, `Unit`, dan `ProposalWorkflow::transition()`** — K2. Kalau rancangan ini
  menyentuh salah satunya, berarti ada yang melenceng dari kesepakatan.
- **`proposal_documents` tidak dipecah** — K5.
- **`proposal_status_history` tidak dipecah per unit** — audit harus utuh.
- **Foreign key tetap ditunda** — konsisten dengan `skema.md §1`; bukan bagian dari pekerjaan ini.
- **Tidak ada tabel baru untuk fitur yang belum ada** — dataset request, payment gateway, dan
  monitoring penelitian tetap di `task.md`, tidak dibuatkan tabel "supaya siap".

---

## 11. Yang Boleh Diganti Tanpa Migration

Nilai `JenisTelaah` (`exempted` / `expedited` / `full_board`) dan `KeputusanEtik`
(`layak` / `tidak_layak`) adalah **default yang dipilih tanpa konfirmasi ke pihak KEPK RSPI**.
Istilahnya standar WHO/CIOMS dan lazim dipakai komite etik di Indonesia, tapi belum tentu sama
dengan kosakata di SOP KEPK setempat.

Karena disimpan sebagai string di kolom `varchar` dan di-cast lewat enum PHP, menggantinya
cukup mengubah satu file enum — **tidak butuh migration**, selama belum ada data produksi.
Konfirmasikan sebelum aplikasi dipakai sungguhan.
