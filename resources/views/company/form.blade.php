<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-emerald-700">Company Workspace</p>
            <h2 class="text-2xl font-semibold text-gray-900">
                {{ $company ? 'Edit Company Profile' : 'Set Up Your Company' }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ $company ? route('company.update') : route('company.store') }}" enctype="multipart/form-data" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                @if ($company)
                    @method('PUT')
                @endif

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="company_name" value="Company Name" />
                        <x-text-input id="company_name" name="company_name" class="mt-1 block w-full" value="{{ old('company_name', $company->company_name ?? '') }}" required />
                        <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="registration_no" value="Registration No" />
                        <x-text-input id="registration_no" name="registration_no" class="mt-1 block w-full" value="{{ old('registration_no', $company->registration_no ?? '') }}" />
                    </div>

                    <div>
                        <x-input-label for="default_currency_code" value="Default Currency" />
                        <input id="default_currency_code" name="default_currency_code" list="currency-options" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" value="{{ old('default_currency_code', $company->default_currency_code ?? 'MYR') }}" maxlength="3" required>
                        <datalist id="currency-options">
                            @foreach ($currencies as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </datalist>
                    </div>

                    <div>
                        <x-input-label for="email" value="Company Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $company->email ?? '') }}" />
                    </div>

                    <div>
                        <x-input-label for="phone" value="Company Phone" />
                        <x-text-input id="phone" name="phone" class="mt-1 block w-full" value="{{ old('phone', $company->phone ?? '') }}" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Company Address" />
                        <textarea id="address" name="address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('address', $company->address ?? '') }}</textarea>
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="logo" value="Company Logo" />
                        <input id="logo" name="logo" type="file" accept="image/*" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100">
                        @if ($company?->logo_path)
                            <img src="{{ Storage::url($company->logo_path) }}" alt="Company logo" class="mt-3 h-16 rounded border border-gray-200 bg-white object-contain p-2">
                        @endif
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <x-primary-button>{{ $company ? 'Save Company' : 'Create Company' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
