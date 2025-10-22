<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('auth.ui.name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('auth.ui.email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Carrera -->
        <div class="mt-4">
            <x-input-label for="carrera" :value="__('auth.ui.career')" />
            <select
                id="carrera"
                name="carrera"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
            >
                <option value="">{{ __('auth.ui.select_career') }}</option>
                @foreach ($carreras as $carrera)
                    <option value="{{ $carrera }}" @selected(old('carrera') === $carrera)>
                        {{ $carrera }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('carrera')" class="mt-2" />
        </div>

        <!-- Turno -->
        <div class="mt-4">
            <x-input-label for="turno" :value="__('auth.ui.shift')" />
            <select
                id="turno"
                name="turno"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
            >
                <option value="">{{ __('auth.ui.select_shift') }}</option>
                @foreach ($turnos as $turno)
                    <option value="{{ $turno }}" @selected(old('turno') === $turno)>
                        {{ $turno }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('turno')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('auth.ui.password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('auth.ui.confirm_password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('auth.ui.already_registered') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('auth.ui.register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
