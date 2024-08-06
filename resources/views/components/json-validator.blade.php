@php
    $isValidJson = true;
    try {
        json_decode($getState());
        if (json_last_error() !== JSON_ERROR_NONE) {
            $isValidJson = false;
        }
    } catch (Exception $e) {
        $isValidJson = false;
    }
@endphp

<div style="margin-bottom: 8px; display: flex; align-items: center;">
    <span>Valid JSON: </span>
    @if ($isValidJson)
        <x-heroicon-o-check-circle class="h-5 w-5" style="color: green; margin-left: 5px;" />
    @else
        <x-heroicon-o-x-circle class="h-5 w-5" style="color: red; margin-left: 5px;" />
    @endif
</div>