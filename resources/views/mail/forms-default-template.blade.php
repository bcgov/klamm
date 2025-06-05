@component('mail::layout')

{{-- Custom Header --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<span style="display: inline-flex; align-items: center;">
    <img src="{{ asset('images/forms-modernization-logo.png') }}" alt="Forms Modernization Logo" style="height: 40px; margin-right: 10px;">
    <span style="font-size: 20px; font-weight: bold; color: #000;">Forms Modernization</span>
</span>
@endcomponent
@endslot

{{-- Main Content --}}
{{ $slot }}

{{-- Custom Footer --}}
@slot('footer')
@component('mail::footer')
<span style="display: inline-flex; align-items: center;">
    <img src="{{ asset('images/forms-modernization-logo.png') }}" alt="Forms Modernization Logo" style="height: 40px; margin-right: 10px;">
    <span style="font-size: 16px; font-weight: bold; color: #000;">Forms Modernization</span>
</span>
<p style="margin: 0; color: #333; font-size: 14px;">
    Forms Modernization Project is focused on rebuilding and improving Web and PDF forms for the social sector.
</p>
@endcomponent
@endslot
@endcomponent
