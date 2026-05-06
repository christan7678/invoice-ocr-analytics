<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-emerald-700">Free Rule-Based Summary</p>
            <h2 class="text-2xl font-semibold text-gray-900">Explainable Sales Insights</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="rounded-lg bg-emerald-50 p-5">
                    <p class="text-sm font-semibold uppercase text-emerald-700">Generated Insight</p>
                    <p class="mt-3 text-lg leading-8 text-emerald-950">{{ $summary }}</p>
                </div>

                <h3 class="mt-8 text-base font-semibold text-gray-900">Supporting Evidence</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($evidence as $label => $value)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <dt class="text-sm text-gray-500">{{ $label }}</dt>
                            <dd class="mt-1 text-base font-semibold text-gray-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
                    This version does not use a paid AI API. It creates a readable summary from verified invoice data and shows the evidence used to generate the statement.
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
