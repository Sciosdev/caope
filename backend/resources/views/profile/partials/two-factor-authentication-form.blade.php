@php
    use Laravel\Fortify\Fortify;

    $twoFactorPendingConfirmation = ! is_null($user->two_factor_secret) && is_null($user->two_factor_confirmed_at);
    $secretKey = $user->two_factor_secret ? decrypt($user->two_factor_secret) : null;
    $recoveryCodes = $user->two_factor_recovery_codes
        ? collect(json_decode(decrypt($user->two_factor_recovery_codes), true))
        : collect();
    $status = session('status');
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Two-Factor Authentication') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Add an extra layer of security to your account by requiring a code from an authenticator application.') }}
        </p>
    </header>

    @if ($status === Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED)
        <p class="mt-4 text-sm text-green-600">
            {{ __('Two-factor authentication has been enabled. Complete the verification step to finish setup.') }}
        </p>
    @elseif ($status === Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED)
        <p class="mt-4 text-sm text-green-600">
            {{ __('Two-factor authentication is now active on your account.') }}
        </p>
    @elseif ($status === Fortify::TWO_FACTOR_AUTHENTICATION_DISABLED)
        <p class="mt-4 text-sm text-gray-600">
            {{ __('Two-factor authentication has been disabled for your account.') }}
        </p>
    @elseif ($status === Fortify::RECOVERY_CODES_GENERATED)
        <p class="mt-4 text-sm text-green-600">
            {{ __('New recovery codes have been generated.') }}
        </p>
    @endif

    @if (is_null($user->two_factor_secret))
        <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-6">
            @csrf

            <x-primary-button>
                {{ __('Enable Two-Factor Authentication') }}
            </x-primary-button>
        </form>
    @else
        <div class="mt-6 space-y-6">
            <div>
                <h3 class="text-sm font-semibold text-gray-800">
                    {{ __('Scan the QR code using your authenticator application') }}
                </h3>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('If you cannot scan the QR code, you can manually enter the setup key displayed below.') }}
                </p>

                <div class="mt-4 inline-block bg-white p-4 shadow rounded">
                    {!! $user->twoFactorQrCodeSvg() !!}
                </div>

                @if ($secretKey)
                    <p class="mt-4 text-sm text-gray-700">
                        <span class="font-semibold">{{ __('Setup Key:') }}</span>
                        <span class="font-mono tracking-widest">{{ $secretKey }}</span>
                    </p>
                @endif
            </div>

            @if ($recoveryCodes->isNotEmpty())
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">
                        {{ __('Recovery Codes') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('Store these codes in a safe place. They can be used to access your account if you lose access to your authenticator device.') }}
                    </p>
                    <ul class="mt-3 grid grid-cols-1 gap-2 text-sm text-gray-700 sm:grid-cols-2">
                        @foreach ($recoveryCodes as $code)
                            <li class="font-mono tracking-widest bg-gray-100 px-3 py-2 rounded">{{ $code }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($twoFactorPendingConfirmation)
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">
                        {{ __('Confirm Two-Factor Authentication') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('Enter a code from your authenticator application to finish enabling two-factor authentication.') }}
                    </p>

                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="two_factor_code" :value="__('Authentication Code')" />
                            <x-text-input
                                id="two_factor_code"
                                name="code"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                class="mt-1 block w-full"
                                autofocus
                            />
                            <x-input-error
                                class="mt-2"
                                :messages="$errors->confirmTwoFactorAuthentication?->get('code') ?? []"
                            />
                        </div>

                        <div class="flex items-center gap-3">
                            <x-primary-button>
                                {{ __('Confirm Setup') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="flex flex-col gap-3 sm:flex-row">
                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                    @csrf

                    <x-secondary-button type="submit">
                        {{ __('Regenerate Recovery Codes') }}
                    </x-secondary-button>
                </form>

                <form method="POST" action="{{ route('two-factor.disable') }}">
                    @csrf
                    @method('DELETE')

                    <x-danger-button type="submit">
                        {{ __('Disable Two-Factor Authentication') }}
                    </x-danger-button>
                </form>
            </div>
        </div>
    @endif
</section>
