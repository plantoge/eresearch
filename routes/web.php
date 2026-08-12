<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\DokumenTelaahDownloadController;
use App\Livewire;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'));

// Auth (tanpa 2FA — keputusan terkunci)
Route::middleware('guest')->group(function () {
    Route::get('/login', Livewire\Auth\Login::class)->name('login');
    Route::get('/register', Livewire\Auth\Register::class)->name('register');
    Route::get('/forgot-password', Livewire\Auth\ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', Livewire\Auth\ResetPassword::class)->name('password.reset');
});

Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

// Verifikasi email — sengaja di luar middleware 'verified' (di sinilah
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
// user yang BELUM verified diarahkan; kalau ikut di-gate 'verified' akan loop).
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', Livewire\Auth\VerifyEmailNotice::class)->name('verification.notice');
});

// verified.optional = middleware 'verified' yang hormati toggle EMAIL_VERIFICATION_REQUIRED.
Route::middleware(['auth', 'verified.optional'])->group(function () {
    Route::get('/dashboard', Livewire\Dashboard::class)
        ->middleware('permission:dashboard.read')->name('dashboard');
    Route::get('/profile', Livewire\Profile::class)->name('profile');

    // Peneliti
    Route::get('/proposal', Livewire\Proposal\Index::class)
        ->middleware('permission:proposal.read')->name('proposal.index');
    Route::get('/proposal/baru', Livewire\Proposal\Create::class)
        ->middleware('permission:proposal.create')->name('proposal.create');
    Route::get('/proposal/{proposal}', Livewire\Proposal\Show::class)
        ->name('proposal.show'); // otorisasi kepemilikan/unit di komponen

    // Antrian unit
    Route::get('/antrian/cru', Livewire\Antrian\Cru::class)
        ->middleware('permission:antrian-cru.read')->name('antrian.cru');
    Route::get('/antrian/kaji-etik', Livewire\Antrian\Kepk::class)
        ->middleware('permission:kaji-etik.read')->name('antrian.kepk');
    Route::get('/antrian/reviewer', Livewire\Antrian\Reviewer::class)
        ->middleware('permission:antrian-reviewer.read')->name('antrian.reviewer');

    // Halaman satu-layar untuk reviewer (dokter senior) — tambahan, TIDAK
    // menggantikan "Antrian Reviewer" di atas. Lihat .claude/specs/2026-08-12-halaman-reviewer-sederhana.md
    Route::get('/reviewer/telaah', Livewire\Reviewer\TelaahSederhana::class)
        ->middleware('permission:antrian-reviewer.read')->name('reviewer.telaah');

    // Admin
    Route::get('/admin/users', Livewire\Admin\Users::class)
        ->middleware('permission:users.read')->name('admin.users');
    Route::get('/admin/roles', Livewire\Admin\Roles::class)
        ->middleware('permission:roles.read')->name('admin.roles');
    Route::get('/admin/menus', Livewire\Admin\Menus::class)
        ->middleware('permission:menus.read')->name('admin.menus');
    Route::get('/admin/survey', Livewire\Admin\Survey::class)
        ->middleware('permission:master-survey.read')->name('admin.survey');
    Route::get('/admin/kontak', Livewire\Admin\Kontak::class)
        ->middleware('permission:informasi-kontak.read')->name('admin.kontak');

    // Laporan & audit
    Route::get('/laporan', Livewire\Laporan::class)
        ->middleware('permission:laporan.read')->name('laporan');
    Route::get('/audit-log', Livewire\AuditLog::class)
        ->middleware('permission:audit-log.read')->name('audit-log');

    // Unduhan dokumen ber-gate (survey gate untuk izin_final di controller)
    Route::get('/dokumen/{document}', DocumentDownloadController::class)->name('dokumen.download');

    // Berkas telaah reviewer — route terpisah, tanpa jalur untuk peneliti
    Route::get('/dokumen-telaah/{dokumen}', DokumenTelaahDownloadController::class)
        ->name('dokumen-telaah.download');
});
