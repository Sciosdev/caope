<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application or one of your emergency recovery codes.') }}
    </div>

    @if ($errors->any())
        <div class="mb-4 font-medium text-sm text-red-600">
            {{ __('There was a problem verifying the provided code.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-6">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Authentication Code')" />
            <x-text-input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                class="mt-1 block w-full"
                autofocus
            />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="recovery_code" :value="__('Recovery Code')" />
            <x-text-input
                id="recovery_code"
                name="recovery_code"
                type="text"
                autocomplete="off"
                class="mt-1 block w-full"
            />
            <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end">
            <x-primary-button>
                {{ __('Verify') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
