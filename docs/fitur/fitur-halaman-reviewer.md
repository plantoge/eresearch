# PRD — Halaman Reviewer Proposal eResearch

## 1. Informasi Produk

**Nama Produk:** eResearch Proposal
**Modul:** Reviewer Proposal
**Institusi:** RSPI Prof. Dr. Sulianti Saroso
**Target Pengguna:** Dokter / Reviewer Proposal, khususnya pengguna senior yang tidak terbiasa menggunakan aplikasi digital.

---

# 2. Latar Belakang

Reviewer proposal bertugas melakukan pemeriksaan terhadap proposal penelitian yang masuk.

Karakteristik pengguna utama pada modul ini adalah dokter senior yang:

* Tidak terbiasa menggunakan aplikasi kompleks.
* Tidak nyaman berpindah-pindah halaman.
* Membutuhkan tampilan yang sederhana dan jelas.
* Lebih membutuhkan akses langsung terhadap proposal daripada dashboard dengan banyak menu.
* Membutuhkan proses membaca dokumen dan memberikan keputusan review dalam satu alur.

Oleh karena itu, modul Reviewer Proposal tidak menggunakan pola aplikasi administratif yang kompleks.

Konsep utama:

> **Login → Cari Proposal → Pilih Proposal → Baca Dokumen → Berikan Review → Simpan**

Seluruh proses utama dilakukan pada **satu halaman**.

---

# 3. Tujuan

## 3.1 Tujuan Utama

Membuat halaman reviewer yang memungkinkan dokter melakukan seluruh proses review proposal tanpa harus berpindah halaman.

## 3.2 Tujuan Khusus

Sistem harus memungkinkan reviewer untuk:

1. Login dengan sederhana.
2. Melihat jumlah proposal yang menjadi tanggung jawabnya.
3. Mencari proposal berdasarkan nama peneliti atau judul.
4. Melihat daftar proposal.
5. Memilih proposal.
6. Melihat seluruh dokumen yang tersedia.
7. Membuka dan membaca dokumen.
8. Memberikan komentar.
9. Menentukan hasil review.
10. Menyimpan hasil review.
11. Melihat status proposal setelah review.

---

# 4. Prinsip UX

Modul ini harus mengikuti prinsip:

### 4.1 Single Page

Reviewer tidak perlu berpindah halaman untuk melakukan review.

### 4.2 Simple

Tidak menampilkan fitur yang tidak diperlukan reviewer.

### 4.3 Large UI

Menggunakan ukuran font, tombol, input, dan area klik yang cukup besar.

### 4.4 Clear Language

Gunakan bahasa yang mudah dipahami.

Contoh:

**Gunakan:**

> Belum Diperiksa

**Hindari:**

> Pending Review

---

### 4.5 Minimal Navigation

Tidak menggunakan sidebar dengan banyak menu.

Navigasi utama cukup:

* Logo / Nama aplikasi
* Identitas reviewer
* Keluar

---

# 5. User Flow

```text
LOGIN
  │
  ▼
HALAMAN REVIEWER
  │
  ├── Melihat total proposal
  │
  ├── Mencari proposal
  │
  ▼
MEMILIH PROPOSAL
  │
  ▼
DETAIL PROPOSAL MUNCUL
  │
  ├── Informasi penelitian
  │
  ├── Daftar dokumen
  │
  ├── Membaca dokumen
  │
  ▼
FORM REVIEW
  │
  ├── Perlu Revisi
  │
  └── Disetujui
  │
  ▼
KOMENTAR REVIEWER
  │
  ▼
SIMPAN HASIL REVIEW
  │
  ▼
STATUS PROPOSAL DIPERBARUI
```

---

# 6. Halaman Login

## 6.1 Tujuan

Menyediakan login yang sederhana untuk reviewer.

## 6.2 Komponen

Halaman hanya membutuhkan:

* Logo RSPI Prof. Dr. Sulianti Saroso
* Nama aplikasi
* Input username/email
* Input password
* Tombol Login

Contoh:

```text
┌──────────────────────────────────────┐
│                                      │
│        eResearch Proposal            │
│        RSPI Prof. Dr. Sulianti       │
│        Saroso                        │
│                                      │
│  Username                            │
│  ┌────────────────────────────────┐  │
│  │                                │  │
│  └────────────────────────────────┘  │
│                                      │
│  Password                            │
│  ┌────────────────────────────────┐  │
│  │                                │  │
│  └────────────────────────────────┘  │
│                                      │
│  ┌────────────────────────────────┐  │
│  │            MASUK               │  │
│  └────────────────────────────────┘  │
│                                      │
└──────────────────────────────────────┘
```

Tidak perlu:

* Register
* Social login
* Dashboard setelah login
* Menu tambahan

Setelah berhasil login, reviewer langsung diarahkan ke halaman review.

---

# 7. Halaman Utama Reviewer

Halaman utama merupakan halaman kerja utama reviewer.

## 7.1 Header

Menampilkan:

```text
eResearch Proposal

Review Proposal

dr. Nama Reviewer                         Keluar
```

Tidak menggunakan sidebar.

---

# 8. Ringkasan Proposal

Di bagian atas halaman terdapat ringkasan:

```text
TOTAL PROPOSAL
12

PERLU DIPERIKSA
5

SUDAH DIPERIKSA
7
```

## 8.1 Total Proposal

Jumlah seluruh proposal yang ditugaskan kepada reviewer.

## 8.2 Perlu Diperiksa

Jumlah proposal yang belum selesai direview.

Angka ini menjadi informasi paling penting.

## 8.3 Sudah Diperiksa

Jumlah proposal yang sudah mendapatkan hasil review.

---

# 9. Search Proposal

Search menjadi fitur utama halaman.

## 9.1 Input

Placeholder:

> Cari nama peneliti atau judul penelitian...

Contoh:

```text
┌──────────────────────────────────────────────────────────┐
│ 🔍  Cari nama peneliti atau judul penelitian...          │
└──────────────────────────────────────────────────────────┘
```

## 9.2 Behavior

Search dilakukan secara otomatis ketika reviewer mengetik.

Tidak perlu tombol "Cari".

## 9.3 Search berdasarkan

* Nama peneliti
* Judul penelitian
* Nomor proposal jika tersedia

---

# 10. Daftar Proposal

Proposal ditampilkan dalam bentuk row/card sederhana.

Contoh:

```text
┌──────────────────────────────────────────────────────────┐
│ dr. Andi                                                  │
│ Hubungan Pola Tidur dengan Kualitas Hidup Pasien          │
│                                                          │
│ 🟠 Belum Diperiksa                                      │
└──────────────────────────────────────────────────────────┘
```

Setiap proposal dapat diklik.

---

# 11. Informasi Proposal

Setelah reviewer memilih proposal, detail ditampilkan pada halaman yang sama.

Informasi minimal:

* Nomor proposal
* Nama peneliti
* Judul penelitian
* Institusi/unit
* Tanggal pengajuan
* Status proposal

Contoh:

```text
DETAIL PROPOSAL

Peneliti
dr. Andi

Judul
Hubungan Pola Tidur dengan Kualitas Hidup Pasien

Nomor Proposal
ER-2026-00125

Tanggal Pengajuan
12 Agustus 2026

Status
Belum Diperiksa
```

---

# 12. Daftar Dokumen

Setelah detail proposal, sistem menampilkan seluruh dokumen yang tersedia dan dapat dibaca reviewer.

Contoh:

```text
DOKUMEN PENELITIAN

┌──────────────────────────────────────────────────────────┐
│ 📄 Proposal Penelitian                                   │
│    Dokumen utama proposal                                │
│                                      [ BACA DOKUMEN ]    │
├──────────────────────────────────────────────────────────┤
│ 📄 Informed Consent                                      │
│    Lembar persetujuan penelitian                         │
│                                      [ BACA DOKUMEN ]    │
├──────────────────────────────────────────────────────────┤
│ 📄 Instrumen Penelitian                                  │
│    Instrumen / kuesioner penelitian                      │
│                                      [ BACA DOKUMEN ]    │
├──────────────────────────────────────────────────────────┤
│ 📄 CV Peneliti                                           │
│                                      [ BACA DOKUMEN ]    │
└──────────────────────────────────────────────────────────┘
```

---

# 13. PDF Viewer

Ketika reviewer menekan:

> BACA DOKUMEN

dokumen dibuka pada halaman yang sama.

Tidak membuka tab browser baru.

PDF viewer minimal memiliki:

* Nomor halaman
* Next page
* Previous page
* Zoom in
* Zoom out
* Fullscreen jika diperlukan

Tujuannya agar reviewer dapat membaca dokumen tanpa keluar dari workflow.

---

# 14. Form Hasil Review

Setelah dokumen, tampilkan bagian:

```text
HASIL REVIEW
```

## 14.1 Keputusan

Gunakan dua pilihan utama:

```text
┌─────────────────────────┐
│ ✎ PERLU REVISI          │
└─────────────────────────┘

┌─────────────────────────┐
│ ✓ DISETUJUI             │
└─────────────────────────┘
```

Hindari dropdown.

---

# 15. Komentar Reviewer

Reviewer dapat memberikan komentar.

Label:

> Catatan / Komentar Reviewer

Textarea:

```text
┌──────────────────────────────────────────────────────────┐
│                                                          │
│ Tuliskan catatan atau masukan untuk peneliti...          │
│                                                          │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

## Behavior

Jika reviewer memilih:

### Perlu Revisi

Komentar wajib diisi.

### Disetujui

Komentar bersifat opsional.

---

# 16. Tombol Simpan

Tombol utama:

```text
┌──────────────────────────────────────────────────────────┐
│                  SIMPAN HASIL REVIEW                      │
└──────────────────────────────────────────────────────────┘
```

Tombol harus besar dan mudah ditemukan.

---

# 17. Konfirmasi

Sebelum menyimpan keputusan final, sistem dapat menampilkan konfirmasi sederhana.

Contoh:

```text
Apakah Anda yakin dengan hasil review ini?

Hasil:
PERLU REVISI

[ KEMBALI ]        [ YA, SIMPAN ]
```

Konfirmasi hanya digunakan untuk mencegah kesalahan klik.

---

# 18. Setelah Review Berhasil

Tampilkan notifikasi:

```text
✓ Review berhasil disimpan.

Proposal telah selesai diperiksa.
```

Kemudian proposal mendapatkan status:

> ✓ Sudah Diperiksa

Daftar proposal kembali dapat digunakan untuk memilih proposal berikutnya.

---

# 19. Status Proposal

Status yang digunakan:

| Status          | Keterangan                                 |
| --------------- | ------------------------------------------ |
| Belum Diperiksa | Proposal belum direview oleh reviewer      |
| Sedang Direview | Reviewer sedang membuka proposal           |
| Perlu Revisi    | Reviewer meminta peneliti melakukan revisi |
| Disetujui       | Reviewer menyetujui proposal               |
| Selesai         | Proses review reviewer telah selesai       |

Status harus ditampilkan menggunakan teks yang jelas.

---

# 20. Empty State

Jika tidak ada proposal:

```text
Belum ada proposal

Saat ini tidak ada proposal yang perlu Anda periksa.
```

Jika search tidak menemukan data:

```text
Proposal tidak ditemukan

Coba gunakan nama peneliti atau judul penelitian yang berbeda.
```

---

# 21. Error Handling

## Gagal membuka dokumen

```text
Dokumen tidak dapat dibuka.

Silakan coba lagi atau hubungi administrator.
```

## Gagal menyimpan review

```text
Review belum berhasil disimpan.

Silakan coba kembali.
```

Data yang sudah ditulis reviewer sebaiknya tidak langsung hilang ketika terjadi error.

---

# 22. Accessibility

Karena target pengguna adalah dokter senior, accessibility merupakan requirement utama.

## Font

Minimum:

* Body: 16–18px
* Heading: 24px+
* Tombol: 16–18px

## Button

Minimum tinggi:

> 48px

Disarankan:

> 52–56px

## Input

Minimum tinggi:

> 52px

## Spacing

Gunakan whitespace yang cukup.

Hindari komponen yang terlalu rapat.

## Contrast

Teks harus memiliki kontras tinggi dengan background.

## Icon

Icon tidak boleh menjadi satu-satunya indikator.

Contoh:

❌

> 🟢

Lebih baik:

> 🟢 Disetujui

---

# 23. Responsive

Halaman harus dapat digunakan pada:

* Desktop
* Laptop
* Tablet

Prioritas utama:

1. Desktop
2. Laptop
3. Tablet

Mobile bukan prioritas utama karena reviewer kemungkinan besar menggunakan komputer saat membaca dokumen.

---

# 24. Requirement Fungsional

### FR-001 — Login

Sistem harus memungkinkan reviewer login menggunakan akun yang telah diberikan.

### FR-002 — Dashboard Reviewer

Sistem harus menampilkan halaman kerja reviewer setelah login.

### FR-003 — Statistik

Sistem harus menampilkan:

* Total proposal
* Proposal belum diperiksa
* Proposal sudah diperiksa

### FR-004 — Search

Reviewer dapat mencari proposal berdasarkan:

* Nama peneliti
* Judul penelitian
* Nomor proposal

### FR-005 — Detail Proposal

Reviewer dapat memilih proposal dan melihat detailnya tanpa berpindah halaman.

### FR-006 — Dokumen

Sistem harus menampilkan seluruh dokumen yang tersedia untuk proposal.

### FR-007 — PDF Viewer

Reviewer dapat membaca dokumen melalui PDF viewer.

### FR-008 — Review

Reviewer dapat memberikan keputusan:

* Perlu Revisi
* Disetujui

### FR-009 — Komentar

Reviewer dapat memasukkan komentar.

### FR-010 — Validasi

Komentar wajib diisi ketika reviewer memilih "Perlu Revisi".

### FR-011 — Simpan

Reviewer dapat menyimpan hasil review.

### FR-012 — Status

Sistem memperbarui status proposal setelah review berhasil disimpan.

### FR-013 — Audit

Sistem menyimpan:

* Reviewer
* Proposal
* Keputusan
* Komentar
* Waktu review

---

# 25. Non-Functional Requirements

## NFR-001 — Usability

Reviewer harus dapat memahami cara menggunakan halaman tanpa pelatihan khusus.

## NFR-002 — Performance

Search proposal harus memberikan response dengan cepat.

## NFR-003 — Security

Dokumen proposal hanya dapat diakses oleh reviewer yang memiliki hak akses.

## NFR-004 — Authorization

Reviewer hanya dapat melihat proposal yang memang ditugaskan kepadanya.

## NFR-005 — Audit Trail

Setiap keputusan review harus tercatat.

## NFR-006 — Data Integrity

Review yang telah disimpan tidak boleh hilang akibat refresh halaman.

---

# 26. Struktur Data Minimal

## Reviewer

```text
reviewers
- id
- user_id
- name
- email
- status
```

## Proposal

```text
proposals
- id
- proposal_number
- researcher_id
- title
- submitted_at
- status
```

## Proposal Reviewer

```text
proposal_reviewers
- id
- proposal_id
- reviewer_id
- status
- assigned_at
```

## Documents

```text
proposal_documents
- id
- proposal_id
- document_type
- file_name
- file_path
- uploaded_at
```

## Reviews

```text
proposal_reviews
- id
- proposal_id
- reviewer_id
- decision
- comment
- reviewed_at
```

---

# 27. Decision Values

Gunakan value internal yang konsisten.

```text
revision
approved
```

Display:

```text
revision  → Perlu Revisi
approved  → Disetujui
```

---

# 28. Layout Final

Struktur halaman secara keseluruhan:

```text
┌───────────────────────────────────────────────────────────────┐
│ eResearch Proposal                         dr. Reviewer  Keluar│
├───────────────────────────────────────────────────────────────┤
│                                                               │
│ Review Proposal                                                │
│ Selamat datang, dr. Reviewer                                  │
│                                                               │
│ ┌────────────┐ ┌────────────┐ ┌────────────┐                 │
│ │     12     │ │      5     │ │      7     │                 │
│ │   TOTAL    │ │   PERLU    │ │   SELESAI  │                 │
│ └────────────┘ └────────────┘ └────────────┘                 │
│                                                               │
│ ┌───────────────────────────────────────────────────────────┐ │
│ │ 🔍 Cari nama peneliti atau judul penelitian...            │ │
│ └───────────────────────────────────────────────────────────┘ │
│                                                               │
│ DAFTAR PROPOSAL                                               │
│                                                               │
│ ┌───────────────────────────────────────────────────────────┐ │
│ │ dr. Andi                                                   │ │
│ │ Hubungan Pola Tidur dengan Kualitas Hidup Pasien           │ │
│ │ 🟠 Belum Diperiksa                                         │ │
│ └───────────────────────────────────────────────────────────┘ │
│                                                               │
│ ───────────────────────────────────────────────────────────── │
│                                                               │
│ DETAIL PROPOSAL                                               │
│                                                               │
│ Peneliti: dr. Andi                                           │
│ Judul: Hubungan Pola Tidur dengan Kualitas Hidup Pasien       │
│                                                               │
│ DOKUMEN                                                       │
│                                                               │
│ 📄 Proposal Penelitian                    [ BACA DOKUMEN ]    │
│ 📄 Informed Consent                        [ BACA DOKUMEN ]    │
│ 📄 Instrumen Penelitian                    [ BACA DOKUMEN ]    │
│ 📄 CV Peneliti                             [ BACA DOKUMEN ]    │
│                                                               │
│ PDF VIEWER                                                     │
│ ┌───────────────────────────────────────────────────────────┐ │
│ │                                                           │ │
│ │                     PDF DOCUMENT                          │ │
│ │                                                           │ │
│ └───────────────────────────────────────────────────────────┘ │
│                                                               │
│ HASIL REVIEW                                                   │
│                                                               │
│ ┌─────────────────────────┐ ┌──────────────────────────────┐ │
│ │ ✎ PERLU REVISI          │ │ ✓ DISETUJUI                  │ │
│ └─────────────────────────┘ └──────────────────────────────┘ │
│                                                               │
│ CATATAN / KOMENTAR REVIEWER                                   │
│ ┌───────────────────────────────────────────────────────────┐ │
│ │                                                           │ │
│ │                                                           │ │
│ └───────────────────────────────────────────────────────────┘ │
│                                                               │
│             [ SIMPAN HASIL REVIEW ]                           │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```

---

# 29. Acceptance Criteria

Fitur dianggap selesai apabila:

* [ ] Reviewer dapat login.
* [ ] Setelah login reviewer langsung masuk ke halaman review.
* [ ] Tidak ada kebutuhan berpindah halaman untuk melakukan review.
* [ ] Total proposal dapat terlihat.
* [ ] Jumlah proposal yang perlu diperiksa dapat terlihat.
* [ ] Reviewer dapat mencari proposal.
* [ ] Reviewer dapat memilih proposal.
* [ ] Detail proposal muncul pada halaman yang sama.
* [ ] Semua dokumen proposal dapat ditampilkan.
* [ ] Dokumen dapat dibaca.
* [ ] Reviewer dapat memilih "Perlu Revisi".
* [ ] Reviewer dapat memilih "Disetujui".
* [ ] Komentar dapat diberikan.
* [ ] Komentar wajib ketika "Perlu Revisi".
* [ ] Hasil review dapat disimpan.
* [ ] Status proposal berubah setelah review disimpan.
* [ ] Reviewer tidak dapat mengakses proposal yang bukan tanggung jawabnya.
* [ ] Riwayat review tersimpan.
* [ ] UI nyaman digunakan oleh pengguna senior.
* [ ] Tombol dan input cukup besar.
* [ ] Tidak terdapat navigasi yang tidak diperlukan.

---

# 30. Prinsip Desain yang Harus Dipertahankan

> **Jangan membuat dokter belajar menggunakan aplikasi. Buat aplikasi yang mengikuti cara dokter bekerja.**

Prioritas UX:

```text
1. Cari proposal
2. Buka proposal
3. Baca dokumen
4. Berikan keputusan
5. Tulis komentar
6. Simpan
```

Semua fitur lain harus berada di belakang layar dan tidak mengganggu workflow tersebut.

---

# 31. Out of Scope

Untuk versi pertama, modul reviewer tidak perlu memiliki:

* Dashboard statistik kompleks
* Grafik
* Sidebar dengan banyak menu
* Chat
* Notifikasi kompleks
* Manajemen user
* Pengaturan sistem
* Export laporan
* Filter proposal yang kompleks
* Multi-level navigation

Fitur tersebut dapat dibuat pada modul administrator/CRU, bukan pada halaman kerja reviewer.

---

# 32. Kesimpulan

Modul Reviewer Proposal dirancang sebagai **single-page workspace** yang ditujukan khusus untuk reviewer senior.

Reviewer tidak perlu memahami struktur aplikasi.

Mereka cukup:

> **Masuk → Cari → Pilih → Baca → Review → Simpan.**

Desain harus mengutamakan **kesederhanaan, ukuran UI yang besar, bahasa yang jelas, minim navigasi, dan pencegahan kesalahan**.

Keberhasilan modul bukan diukur dari banyaknya fitur, tetapi dari seberapa mudah seorang dokter senior dapat menyelesaikan review proposal tanpa membutuhkan bantuan operator.
