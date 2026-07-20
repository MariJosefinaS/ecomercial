<div>
    {{-- Logo --}}
    <div class="mb-6 flex flex-col items-center">
        <x-logo :on-dark="true" size="md" class="scale-125" />
        <p class="mt-3 text-[11px] font-medium uppercase tracking-wide text-gray-400">Consola de Administración</p>
    </div>

    {{-- Card --}}
    <form wire:submit="login" class="rounded-2xl bg-white p-6 shadow-2xl">
        <h1 class="mb-1 text-xl font-extrabold text-ink">Ingresar</h1>
        <p class="mb-5 text-sm text-muted">Accedé con tu cuenta de empleado.</p>

        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">Email</label>
        <div class="relative mb-3">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-muted">mail</span>
            <input type="email" wire:model="email" autofocus autocomplete="username"
                   class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-4 text-sm font-medium outline-none transition focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20" />
        </div>
        @error('email') <p class="mb-3 -mt-2 text-xs font-semibold text-danger">{{ $message }}</p> @enderror

        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">Contraseña</label>
        <div class="relative mb-3" x-data="{ show: false }">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[20px] text-muted">lock</span>
            <input :type="show ? 'text' : 'password'" wire:model="password" autocomplete="current-password"
                   class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-10 text-sm font-medium outline-none transition focus:border-brand focus:bg-white focus:ring-2 focus:ring-brand/20" />
            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-brand">
                <span class="material-symbols-outlined text-[20px]" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
            </button>
        </div>
        @error('password') <p class="mb-3 -mt-2 text-xs font-semibold text-danger">{{ $message }}</p> @enderror

        <label class="mb-5 flex cursor-pointer items-center gap-2 text-sm font-medium text-graphite">
            <input type="checkbox" wire:model="remember" class="rounded border-gray-300 text-brand focus:ring-brand/30" /> Recordarme
        </label>

        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand py-3 text-sm font-bold text-white transition hover:bg-brand-dark">
            <span wire:loading.remove wire:target="login">Ingresar</span>
            <span wire:loading wire:target="login" class="flex items-center gap-2"><span class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span> Ingresando...</span>
        </button>
    </form>

    <p class="mt-4 text-center text-[11px] text-gray-500">E.Comercial · Equipamiento para tu empresa y hogar</p>
</div>
