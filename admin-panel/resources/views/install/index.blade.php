<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 2a1 1 0 01.894.553l1.7 3.444 3.798.552a1 1 0 01.554 1.705l-2.748 2.68.648 3.782a1 1 0 01-1.451 1.054L10 13.915l-3.395 1.785a1 1 0 01-1.451-1.054l.648-3.783-2.748-2.68a1 1 0 01.554-1.704l3.798-.552 1.7-3.444A1 1 0 0110 2z" />
                </svg>
            </div>
        </x-slot>

        <div class="text-center">
            <h2 class="text-2xl font-semibold text-gray-900">FoodFlow Installer</h2>
            <p class="mt-2 text-sm text-gray-500">
                Complete the system checks, verify your license, configure the database, and finish setup.
            </p>
        </div>

        <div class="mt-6 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="mb-6 grid grid-cols-4 gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                <div class="rounded-lg px-3 py-2 {{ $step === 0 ? 'bg-cyan-50 text-cyan-700' : 'bg-gray-50' }}">Requirements</div>
                <div class="rounded-lg px-3 py-2 {{ $step === 1 ? 'bg-cyan-50 text-cyan-700' : 'bg-gray-50' }}">License</div>
                <div class="rounded-lg px-3 py-2 {{ $step === 2 ? 'bg-cyan-50 text-cyan-700' : 'bg-gray-50' }}">Database</div>
                <div class="rounded-lg px-3 py-2 {{ $step === 3 ? 'bg-cyan-50 text-cyan-700' : 'bg-gray-50' }}">Finish</div>
            </div>

            @if ($step === 0)
                <div class="space-y-4">
                    <div class="rounded-2xl border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-900">PHP version</span>
                            <span class="{{ $requirements['php_ok'] ? 'text-green-600' : 'text-red-600' }}">
                                {{ $requirements['php_version'] }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($requirements['extensions'] as $extension)
                            <div class="rounded-2xl border border-gray-200 p-4 flex items-center justify-between">
                                <span class="font-medium text-gray-900">{{ strtoupper($extension['name']) }}</span>
                                <span class="{{ $extension['loaded'] ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $extension['loaded'] ? 'Loaded' : 'Missing' }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-gray-200 p-4 flex items-center justify-between">
                            <span class="font-medium text-gray-900">Storage writable</span>
                            <span class="{{ $requirements['writable_storage'] ? 'text-green-600' : 'text-red-600' }}">
                                {{ $requirements['writable_storage'] ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        <div class="rounded-2xl border border-gray-200 p-4 flex items-center justify-between">
                            <span class="font-medium text-gray-900">Cache writable</span>
                            <span class="{{ $requirements['writable_bootstrap_cache'] ? 'text-green-600' : 'text-red-600' }}">
                                {{ $requirements['writable_bootstrap_cache'] ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <a href="{{ route('install.show', ['step' => 1]) }}" class="inline-flex rounded-xl bg-cyan-600 px-5 py-3 text-sm font-semibold text-white hover:bg-cyan-700">
                            Next
                        </a>
                    </div>
                </div>
            @elseif ($step === 1)
                <form method="POST" action="{{ route('install.license') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-label for="activation_code" value="{{ __('Activation Code') }}" />
                        <x-input id="activation_code" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" type="text" name="activation_code" value="{{ old('activation_code') }}" required autofocus autocomplete="off" />
                        <p class="mt-2 text-sm text-gray-400">Use the activation code <strong>FLYDEAL</strong> if your domain is not under foodflow.in.</p>
                    </div>
                    <div class="flex items-center justify-between">
                        <a href="{{ route('install.show', ['step' => 0]) }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">Back</a>
                        <x-button>{{ __('Verify Activation') }}</x-button>
                    </div>
                </form>
            @elseif ($step === 2)
                <form method="POST" action="{{ route('install.database') }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-label for="app_url" value="{{ __('App URL') }}" />
                            <x-input id="app_url" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" type="url" name="app_url" value="{{ old('app_url', request()->getSchemeAndHttpHost()) }}" required />
                        </div>
                        <div>
                            <x-label for="db_host" value="{{ __('Database Host') }}" />
                            <x-input id="db_host" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" type="text" name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required />
                        </div>
                        <div>
                            <x-label for="db_port" value="{{ __('Database Port') }}" />
                            <x-input id="db_port" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" type="number" name="db_port" value="{{ old('db_port', 3306) }}" required />
                        </div>
                        <div>
                            <x-label for="db_name" value="{{ __('Database Name') }}" />
                            <x-input id="db_name" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" type="text" name="db_name" value="{{ old('db_name') }}" required />
                        </div>
                        <div>
                            <x-label for="db_user" value="{{ __('Database Username') }}" />
                            <x-input id="db_user" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" type="text" name="db_user" value="{{ old('db_user') }}" required />
                        </div>
                        <div>
                            <x-label for="db_pass" value="{{ __('Database Password') }}" />
                            <x-input id="db_pass" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500" type="password" name="db_pass" value="{{ old('db_pass') }}" />
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <a href="{{ route('install.show', ['step' => 1]) }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">Back</a>
                        <x-button>{{ __('Setup Database') }}</x-button>
                    </div>
                </form>
            @else
                <div class="space-y-4 text-center">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-green-50 text-green-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-7.071 7.071a1 1 0 01-1.414 0L3.293 9.85a1 1 0 111.414-1.414l3.515 3.515 6.364-6.364a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-900">Installation complete</h3>
                    <p class="text-sm text-gray-500">FoodFlow is now configured and the default accounts are ready.</p>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-left text-sm text-gray-700">
                        <p><strong>Super Admin:</strong> superadmin@example.com / password</p>
                        <p><strong>Admin:</strong> admin@example.com / password</p>
                        <p><strong>Restaurant Owner:</strong> owner@example.com / password</p>
                        <p><strong>Delivery Driver:</strong> driver@example.com / password</p>
                        <p><strong>Customer:</strong> customer@example.com / password</p>
                    </div>
                    <div class="flex justify-center">
                        <a href="{{ route('login') }}" class="inline-flex rounded-xl bg-cyan-600 px-5 py-3 text-sm font-semibold text-white hover:bg-cyan-700">
                            Continue to Login
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </x-authentication-card>
</x-guest-layout>
