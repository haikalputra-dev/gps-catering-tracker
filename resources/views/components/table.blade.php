@props([
    'headers' => [],
])

<div {{ $attributes->merge(['class' => 'overflow-x-auto -mx-6 sm:mx-0 sm:rounded-lg sm:border sm:border-slate-200']) }}>
    <table class="min-w-full divide-y divide-slate-200">
        @if(count($headers) > 0)
            <thead class="bg-slate-50">
                <tr>
                    @foreach($headers as $header)
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
            {{ $slot }}
        </tbody>
    </table>
</div>
