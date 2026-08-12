<?php

namespace App\Livewire\Auth;

use App\Rules\ValidCaptcha;
use App\Services\MathCaptcha;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public string $captchaId = '';

    public string $captchaQuestion = '';

    public string $captchaAnswer = '';

    public function mount(MathCaptcha $captcha)
    {
        $this->regenerateCaptcha($captcha);
    }

    public function regenerateCaptcha(?MathCaptcha $captcha = null)
    {
        $soal = ($captcha ?? app(MathCaptcha::class))->generate();
        $this->captchaId = $soal['id'];
        $this->captchaQuestion = $soal['question'];
        $this->captchaAnswer = '';
    }

    public function login()
    {
        try {
            $cred = $this->validate([
                'email' => 'required|email',
                'password' => 'required',
                'captchaAnswer' => ['required', new ValidCaptcha($this->captchaId)],
            ]);
        } catch (ValidationException $e) {
            $this->regenerateCaptcha(); // token sekali pakai — soal baru buat percobaan berikutnya
            throw $e;
        }

        if (! Auth::attempt(['email' => $cred['email'], 'password' => $cred['password']], $this->remember)) {
            $this->addError('email', 'Email atau password salah.');
            $this->regenerateCaptcha();

            return;
        }

        session()->regenerate();

        $user = Auth::user();

        // Reviewer (dokter senior, gaptek) yang login biasa dari /login — tanpa
        // deep-link — mendarat langsung di halaman satu-layar, bukan dashboard.
        // Deep-link (url.intended) selalu menang: jangan buang konteks orang yang
        // datang lewat link proposal spesifik (mis. notifikasi Telegram).
        if (! session()->has('url.intended')
            && $user->getRoleNames()->count() === 1
            && $user->hasRole('reviewer')) {
            return redirect()->route('reviewer.telaah');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login')->title('Masuk');
    }
}
