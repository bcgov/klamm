<x-mail::layout>
    {{-- Header --}}
    @isset($header)
    <x-slot:header>
        {!! $header !!}
    </x-slot:header>
    @endisset

    {{-- Body --}}
    {!! $slot !!}

    {{-- Subcopy --}}
    @isset($subcopy)
    <x-slot:subcopy>
        <x-mail::subcopy>
            {!! $subcopy !!}
        </x-mail::subcopy>
    </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    @isset($footer)
    <x-slot:footer>
        {!! $footer !!}
    </x-slot:footer>
    @endisset
</x-mail::layout>
