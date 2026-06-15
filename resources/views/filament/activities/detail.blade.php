@php
    /** @var \Spatie\Activitylog\Models\Activity $activity */
    $properties = $activity->properties ?? collect();
    $attributes = $properties->get('attributes', []);
    $old = $properties->get('old', []);
    // For auth/custom logs there's no attributes/old diff — show raw properties instead.
    $extra = collect($properties->toArray())->except(['attributes', 'old']);
@endphp

<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-3 gap-x-4 gap-y-2">
        <dt class="font-medium text-gray-500 dark:text-gray-400">Actor</dt>
        <dd class="col-span-2 text-gray-950 dark:text-white">{{ $activity->causer?->name ?? 'System / Guest' }}</dd>

        <dt class="font-medium text-gray-500 dark:text-gray-400">Event</dt>
        <dd class="col-span-2 text-gray-950 dark:text-white">{{ str($activity->event ?? '—')->headline() }}</dd>

        <dt class="font-medium text-gray-500 dark:text-gray-400">Subject</dt>
        <dd class="col-span-2 text-gray-950 dark:text-white">
            {{ $activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : '—' }}
        </dd>

        <dt class="font-medium text-gray-500 dark:text-gray-400">When</dt>
        <dd class="col-span-2 text-gray-950 dark:text-white">{{ $activity->created_at?->format('M j, Y H:i:s') }}</dd>

        @if ($activity->description)
            <dt class="font-medium text-gray-500 dark:text-gray-400">Description</dt>
            <dd class="col-span-2 text-gray-950 dark:text-white">{{ $activity->description }}</dd>
        @endif
    </dl>

    @if (! empty($attributes))
        <div>
            <p class="mb-2 font-medium text-gray-700 dark:text-gray-300">Changes</p>
            <div class="overflow-hidden rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Field</th>
                            <th class="px-3 py-2">Before</th>
                            <th class="px-3 py-2">After</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($attributes as $field => $newValue)
                            <tr>
                                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">{{ $field }}</td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit((string) ($old[$field] ?? '—'), 80) }}</td>
                                <td class="px-3 py-2 text-gray-950 dark:text-white">{{ \Illuminate\Support\Str::limit((string) ($newValue ?? '—'), 80) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($extra->isNotEmpty())
        <div>
            <p class="mb-2 font-medium text-gray-700 dark:text-gray-300">Properties</p>
            <ul class="space-y-1">
                @foreach ($extra as $key => $value)
                    <li class="text-gray-700 dark:text-gray-300">
                        <span class="font-medium">{{ $key }}:</span> {{ is_scalar($value) ? $value : json_encode($value) }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
