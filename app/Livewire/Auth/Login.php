<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Ingresar — E.Comercial')]
class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'Las credenciales no coinciden con nuestros registros.');
            return;
        }

        session()->regenerate();

        // Registrar el último ingreso (se muestra en el ABM de Usuarios).
        Auth::user()?->forceFill(['ultimo_acceso' => now()])->save();

        // Cada rol entra a la primera sección que puede ver (vendedor → Nota de pedido).
        return redirect()->intended(route(\App\Support\Permisos::inicio(Auth::user()?->rol)));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
