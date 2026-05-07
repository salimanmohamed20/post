@php
    $failedRows = is_array($record->failed_rows ?? null) ? $record->failed_rows : [];
    $reasonMap = [
        'duplicate_slug_in_import_file' => 'Slug مكرر داخل ملف الاستيراد',
        'invalid_slug' => 'Slug غير صالح',
        'missing_category_mapping' => 'تعذر ربط المقال بتصنيف',
    ];
@endphp

<div class="space-y-4 text-sm">
    @if(empty($failedRows))
        <div class="rounded-lg border border-green-300 bg-green-50 p-4 text-green-700 dark:border-green-800 dark:bg-green-950/30 dark:text-green-300">
            لا توجد أخطاء محفوظة في هذا السجل.
        </div>
    @else
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
            عدد الصفوف الفاشلة: <strong>{{ count($failedRows) }}</strong>
        </div>

        @foreach($failedRows as $index => $row)
            @php
                $rawReason = (string) ($row['reason'] ?? '-');
                $parts = explode(':', $rawReason, 2);
                $reasonKey = trim($parts[0]);
                $reasonText = $reasonMap[$reasonKey] ?? $reasonKey;
                if (isset($parts[1]) && trim($parts[1]) !== '') {
                    $reasonText .= ' - ' . trim($parts[1]);
                }
            @endphp

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/40">
                <div class="mb-2 flex items-center justify-between">
                    <div class="font-bold text-primary-600 dark:text-primary-400">خطأ #{{ $index + 1 }}</div>
                    <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">
                        فشل
                    </span>
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="rounded-md bg-gray-50 p-2 dark:bg-gray-800/60">
                        <div class="text-xs text-gray-500">Old Source ID</div>
                        <div class="font-medium">{{ $row['old_source_id'] ?? '-' }}</div>
                    </div>
                    <div class="rounded-md bg-gray-50 p-2 dark:bg-gray-800/60">
                        <div class="text-xs text-gray-500">Old Slug</div>
                        <div class="font-medium break-all">{{ $row['old_slug'] ?? '-' }}</div>
                    </div>
                </div>

                <div class="mt-3 rounded-md bg-red-50 p-3 dark:bg-red-950/20">
                    <div class="text-xs text-red-600 dark:text-red-300">سبب الخطأ</div>
                    <div class="font-semibold text-red-700 dark:text-red-200 break-words">{{ $reasonText }}</div>
                </div>
            </div>
        @endforeach
    @endif
</div>
