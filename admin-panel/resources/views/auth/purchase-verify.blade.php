<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-r from-indigo-500 to-cyan-500 text-white shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                </svg>
            </div>
        </x-slot>

        <div class="text-center">
            <h2 class="text-2xl font-semibold text-gray-900">Activation Code</h2>
            <p class="mt-2 text-sm text-gray-500">
                Enter the activation code to unlock the admin panel. Trusted domains under foodflow.in are allowed automatically.
            </p>
        </div>

        <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
            <x-validation-errors class="mb-4" />

            @if (session('success'))
                <div class="mb-4 rounded-xl bg-green-50 border border-green-200 p-4 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('purchase.verify.submit') }}">
                @csrf

                <div class="space-y-4">
                    <div>
                        <x-label for="activation_code" value="{{ __('Activation Code') }}" />
                        <x-input id="activation_code" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" type="text" name="activation_code" value="{{ old('activation_code') }}" required autofocus autocomplete="off" />
                        <p class="mt-2 text-sm text-gray-400">Use the activation code <strong>FLYDEAL</strong> if your domain is not under foodflow.in.</p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500">Trusted domains on foodflow.in work without activation code.</p>
                        <x-button class="w-full sm:w-auto">
                            {{ __('Verify Activation') }}
                        </x-button>
                    </div>
                </div>
            </form>
        </div>
    </x-authentication-card>
</x-guest-layout>
