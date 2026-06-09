@php
    /** @var \App\Models\Holiday $holiday */
@endphp

@if (filled($holiday->description))
    <div class="prose prose-sm max-w-none text-gray-700 dark:prose-invert dark:text-gray-300 [&_a]:font-medium [&_a]:text-primary-600 [&_a]:underline [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5 dark:[&_a]:text-primary-400">
        {!! $holiday->description !!}
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">No additional details for this holiday.</p>
@endif
