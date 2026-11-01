<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="AI Activity"
        x-bind:title="`Time: {{ number_format($time, 0) }}ms; Run at: ${formatDate('{{ $runAt }}')};`"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.sparkles />
        </x-slot:icon>
        <x-slot:actions>
            <x-pulse::select
                wire:model.live="show"
                id="select-ai-activity-show"
                label="Show"
                :options="[
                    'models' => 'models',
                    'tools' => 'tools',
                ]"
                @change="loading = true"
            />
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        @if ($entries->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>{{ $show === 'tools' ? 'Tool' : 'Model' }}</x-pulse::th>
                        <x-pulse::th class="text-right">{{ $show === 'tools' ? 'Calls' : 'Steps' }}</x-pulse::th>
                        <x-pulse::th class="text-right">Average</x-pulse::th>
                        <x-pulse::th class="text-right">Slowest</x-pulse::th>
                        @if ($show === 'tools')
                            <x-pulse::th class="text-right">Failed</x-pulse::th>
                        @else
                            <x-pulse::th class="text-right">Cache hits</x-pulse::th>
                            <x-pulse::th class="text-right">Failovers</x-pulse::th>
                            <x-pulse::th class="text-right">Credits vs 0.x</x-pulse::th>
                        @endif
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($entries->take(100) as $row)
                        <tr wire:key="{{ $row->key }}-spacer" class="h-2 first:h-0"></tr>
                        <tr wire:key="{{ $row->key }}-row">
                            <x-pulse::td class="max-w-[1px]">
                                <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $row->key }}">{{ $row->key }}</code>
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-bold">{{ number_format($row->count) }}</x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">{{ number_format($row->avg) }} ms</x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">{{ number_format($row->max) }} ms</x-pulse::td>
                            @if ($show === 'tools')
                                <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">{{ number_format($row->failures) }}</x-pulse::td>
                            @else
                                <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">{{ $row->cache_hit_rate === null ? '-' : $row->cache_hit_rate.'%' }}</x-pulse::td>
                                <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">{{ number_format($row->failovers) }}</x-pulse::td>
                                <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">{{ $row->credit_delta > 0 ? '+' : '' }}{{ number_format($row->credit_delta) }}</x-pulse::td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
