@component('mail::layout')

{{-- Custom Header --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<span style="display: inline-flex; align-items: center;">
    <span class="dark-mode-icon" alt="Forms Modernization Logo" style="display: none;">
        <!-- Dark Mode SVG Icon -->
        <svg width="32" height="36" viewBox="0 0 32 36" fill="none" xmlns="http://www.w3.org/2000/svg" class="dark-mode-icon">
            <path d="M9 12H18C18.5304 12 19.0391 12.2107 19.4142 12.5858C19.7893 12.9609 20 13.4696 20 14V30C20 30.5304 19.7893 31.0391 19.4142 31.4142C19.0391 31.7893 18.5304 32 18 32H6C5.46957 32 4.96086 31.7893 4.58579 31.4142C4.21071 31.0391 4 30.5304 4 30V17L9 12Z" fill="#2D2D2D" stroke="#2D2D2D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M10 12V16C10 16.5304 9.78929 17.0391 9.41421 17.4142C9.03914 17.7893 8.53043 18 8 18H4" fill="#FCBA19" />
            <path d="M10 12V16C10 16.5304 9.78929 17.0391 9.41421 17.4142C9.03914 17.7893 8.53043 18 8 18H4" stroke="#2D2D2D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M8 23H9" stroke="#D8EAFD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M12 23H16" stroke="#D8EAFD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M8 27H9" stroke="#D8EAFD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M12 27H16" stroke="#D8EAFD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M24 15.5L24 9.2C24 8.9 23.8 8.6 23.6 8.4C23.4 8.2 23.1 8 22.8 8L16 8" stroke="#D8EAFD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M28 11.5L28 5.2C28 4.9 27.8 4.6 27.6 4.4C27.4 4.2 27.1 4 26.8 4L20 4" stroke="#D8EAFD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>
    <span class="light-mode-icon" alt="Forms Modernization Logo" style="display: inline;">
        <svg width="43" height="48" viewBox="0 0 43 48" fill="none" xmlns="http://www.w3.org/2000/svg">
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
    </span>
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

<style>
    @media (prefers-color-scheme: dark) {
        .light-mode-icon {
            display: none !important;
        }

        .dark-mode-icon {
            display: inline !important;
        }
    }

    @media (prefers-color-scheme: light) {
        .light-mode-icon {
            display: inline !important;
        }

        .dark-mode-icon {
            display: none !important;
        }
    }
</style>