<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\Menu;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginRedirectReviewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Menu::create(['nama' => 'Dashboard', 'slug' => 'dashboard', 'route' => 'dashboard']);
        Menu::create(['nama' => 'Antrian Reviewer', 'slug' => 'antrian-reviewer', 'route' => 'antrian.reviewer']);
        Role::findByName('peneliti')->givePermissionTo('dashboard.read');
        Role::findByName('reviewer')->givePermissionTo(['dashboard.read', 'antrian-reviewer.read']);
        Role::findByName('kepk')->givePermissionTo(['dashboard.read', 'antrian-reviewer.read']);
    }

    protected function loginSebagai(User $user, string $password = 'password123'): Testable
    {
        $test = Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', $password);

        $id = $test->get('captchaId');
        $jawaban = session('captcha_'.$id);

        return $test->set('captchaAnswer', (string) $jawaban)->call('login');
    }

    public function test_login_reviewer_tunggal_diarahkan_ke_halaman_sederhana(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $user->assignRole('reviewer');

        $this->loginSebagai($user)->assertRedirect(route('reviewer.telaah'));
    }

    public function test_login_reviewer_dengan_peran_lain_tetap_ke_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $user->assignRole('reviewer');
        $user->assignRole('kepk');

        $this->loginSebagai($user)->assertRedirect(route('dashboard'));
    }

    public function test_login_dengan_intended_url_tidak_dipaksa_ke_halaman_sederhana(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $user->assignRole('reviewer');

        // Simulasikan deep-link: akses halaman yang butuh auth dulu supaya
        // 'url.intended' tersimpan di session, baru login.
        $this->get(route('antrian.reviewer'));

        $this->loginSebagai($user)->assertRedirect(route('antrian.reviewer'));
    }
}
