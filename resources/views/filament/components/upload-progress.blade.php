<div
    x-data="{ isUploading: false, progress: 0 }"
    x-on:livewire-upload-start="isUploading = true; progress = 0"
    x-on:livewire-upload-finish="isUploading = false; progress = 100"
    x-on:livewire-upload-error="isUploading = false"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    class="space-y-2"
>
    <div x-show="isUploading" x-cloak>
        <div class="flex items-center justify-between text-sm">
            <span>جاري رفع الملف...</span>
            <span x-text="`${progress}%`"></span>
        </div>

        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div
                class="h-2 rounded-full bg-primary-600 transition-all duration-200"
                :style="`width: ${progress}%`"
            ></div>
        </div>
    </div>
</div>
