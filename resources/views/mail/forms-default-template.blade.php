@component('mail::layout')
{{-- Custom Header --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<div style="background-color: #f3f6f9; padding: 20px; text-align: left; margin: -25px -30px 0; width: calc(100% + 60px);">
    <div style="display: flex; align-items: center;">
        <svg width="43" height="48" viewBox="0 0 43 48" fill="none" xmlns="http://www.w3.org/2000/svg" alt="Forms Modernization Logo" style="height: 40px; margin-right: 10px;">
            <path d="M12 16H24C24.7072 16 25.3855 16.281 25.8856 16.781C26.3857 17.2811 26.6666 17.9594 26.6666 18.6667V40C26.6666 40.7072 26.3857 41.3855 25.8856 41.8856C25.3855 42.3857 24.7072 42.6667 24 42.6667H7.99996C7.29272 42.6667 6.61444 42.3857 6.11434 41.8856C5.61424 41.3855 5.33329 40.7072 5.33329 40V22.6667L12 16Z" fill="#D8EAFD" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M13.3334 16V21.3333C13.3334 22.0406 13.0524 22.7189 12.5523 23.219C12.0522 23.719 11.374 24 10.6667 24H5.33337" fill="#FCBA19" />
            <path d="M13.3334 16V21.3333C13.3334 22.0406 13.0524 22.7189 12.5523 23.219C12.0522 23.719 11.374 24 10.6667 24H5.33337" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M10.6666 30.668H12" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M16 30.668H21.3333" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M10.6666 36H12" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M16 36H21.3333" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M32 20.668L32 12.268C32 11.868 31.7333 11.468 31.4667 11.2013C31.2 10.9346 30.8 10.668 30.4 10.668L21.3333 10.668" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M37.3334 15.332L37.3334 6.93203C37.3334 6.53203 37.0667 6.13203 36.8 5.86536C36.5334 5.5987 36.1334 5.33203 35.7334 5.33203L26.6667 5.33203" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span style="font-size: 20px; font-weight: bold; color: #000;">Forms Modernization</span>
    </div>
</div>
@endcomponent
@endslot

{{-- Main Content --}}
{{ $slot }}

{{-- Custom Footer --}}
@slot('footer')
@component('mail::footer')
<div style="background-color: #f3f6f9; padding: 20px; text-align: left; margin: 0 -30px -25px; width: calc(100% + 60px);">
    <div style="display: flex; align-items: center; margin-bottom: 10px;">
        <svg width="43" height="48" viewBox="0 0 43 48" fill="none" xmlns="http://www.w3.org/2000/svg" alt="Forms Modernization Logo" style="height: 30px; margin-right: 10px;">
            <path d="M12 16H24C24.7072 16 25.3855 16.281 25.8856 16.781C26.3857 17.2811 26.6666 17.9594 26.6666 18.6667V40C26.6666 40.7072 26.3857 41.3855 25.8856 41.8856C25.3855 42.3857 24.7072 42.6667 24 42.6667H7.99996C7.29272 42.6667 6.61444 42.3857 6.11434 41.8856C5.61424 41.3855 5.33329 40.7072 5.33329 40V22.6667L12 16Z" fill="#D8EAFD" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M13.3334 16V21.3333C13.3334 22.0406 13.0524 22.7189 12.5523 23.219C12.0522 23.719 11.374 24 10.6667 24H5.33337" fill="#FCBA19" />
            <path d="M13.3334 16V21.3333C13.3334 22.0406 13.0524 22.7189 12.5523 23.219C12.0522 23.719 11.374 24 10.6667 24H5.33337" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M10.6666 30.668H12" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M16 30.668H21.3333" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M10.6666 36H12" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M16 36H21.3333" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M32 20.668L32 12.268C32 11.868 31.7333 11.468 31.4667 11.2013C31.2 10.9346 30.8 10.668 30.4 10.668L21.3333 10.668" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M37.3334 15.332L37.3334 6.93203C37.3334 6.53203 37.0667 6.13203 36.8 5.86536C36.5334 5.5987 36.1334 5.33203 35.7334 5.33203L26.6667 5.33203" stroke="black" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span style="font-size: 16px; font-weight: bold; color: #000;">Forms Modernization</span>
    </div>
    <p style="margin: 0; color: #333; font-size: 14px;">
        Forms Modernization Project is focused on rebuilding and improving Web and PDF forms for the social sector.
    </p>
</div>
@endcomponent
@endslot
@endcomponent
