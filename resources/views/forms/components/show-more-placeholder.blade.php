<div>
    @if($getLabel())
    <label class="block text-sm font-medium text-gray-700 mb-1">
        {!! $getLabel() !!}
    </label>
    @endif

    <div x-data="{ 
        isExpanded: false, 
        maxLength: @js($getMaxLength() ?? 300), 
        originalContent: @js($getShowMoreContent() ?? ''),
        truncatedContent: '',
        needsTruncation: false
    }"
        x-init="
        const textContent = originalContent.replace(/<[^>]*>/g, '');
        if (textContent.length > maxLength) {
            truncatedContent = textContent.slice(0, maxLength) + '...';
            needsTruncation = true;
        } else {
            truncatedContent = textContent;
            needsTruncation = false;
        }
    ">
        <div class="prose max-w-none">
            <span x-text="isExpanded ? originalContent.replace(/<[^>]*>/g, '') : truncatedContent"></span>

            <button
                type="button"
                class="text-primary-600 hover:text-primary-500 underline text-sm mt-1 ml-1"
                @click="isExpanded = !isExpanded"
                x-show="needsTruncation"
                x-text="isExpanded ? 'Show less' : 'Show more'"></button>
        </div>
    </div>
</div>