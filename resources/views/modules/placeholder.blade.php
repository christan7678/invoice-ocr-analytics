<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-emerald-700">{{ $eyebrow }}</p>
            <h2 class="text-2xl font-semibold leading-tight text-gray-900">
                {{ $title }}
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <p class="text-base text-gray-700">{{ $summary }}</p>
                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <p class="text-sm font-semibold text-gray-900">Current Status</p>
                        <p class="mt-2 text-sm text-gray-600">Ready for implementation</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <p class="text-sm font-semibold text-gray-900">Database</p>
                        <p class="mt-2 text-sm text-gray-600">Tables will be added next</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <p class="text-sm font-semibold text-gray-900">Workflow</p>
                        <p class="mt-2 text-sm text-gray-600">Connected to app navigation</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
