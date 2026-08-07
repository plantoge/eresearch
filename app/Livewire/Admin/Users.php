<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Spatie\Permission\Models\Role;

class Users extends Component
{
    use Toast, WithPagination;

    public string $cari = '';

    public bool $modal = false;

    public ?string $editId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public array $roles = [];

    public function buka(?string $id = null)
    {
        $this->resetErrorBag();
        $this->editId = $id;

        if ($id) {
            $u = User::findOrFail($id);
            $this->name = $u->name;
            $this->email = $u->email;
            $this->roles = $u->getRoleNames()->all();
        } else {
            $this->reset('name', 'email', 'roles');
        }

        $this->password = '';
        $this->modal = true;
    }

    public function simpan()
    {
        $data = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.($this->editId ?? 'NULL').',id',
            'password' => ($this->editId ? 'nullable' : 'required').'|min:8',
            'roles' => 'array',
        ]);

        if ($this->editId) {
            $u = User::findOrFail($this->editId);
            $u->fill(['name' => $data['name'], 'email' => $data['email']]);
            if ($data['password']) {
                $u->password = $data['password'];
            }
            $u->save();
        } else {
            $u = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        }

        $u->syncRoles($this->roles);
        $this->modal = false;
        $this->success('User tersimpan.');
    }

    public function hapus(string $id)
    {
        abort_unless(auth()->user()->can('users.delete'), 403);
        abort_if($id === auth()->id(), 403, 'Tidak bisa menghapus akun sendiri.');

        User::findOrFail($id)->delete();
        $this->success('User dihapus.');
    }

    public function render()
    {
        $users = User::query()
            ->when($this->cari, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$this->cari}%")
                ->orWhere('email', 'ilike', "%{$this->cari}%")))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.users', [
            'users' => $users,
            'semuaRole' => Role::orderBy('name')->pluck('name'),
        ])->title('Users');
    }
}
