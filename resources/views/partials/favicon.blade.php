{{-- The system's icon: browser tabs, bookmarks, and a phone's home screen.

     One partial so every portal's <head> carries the same set, and so the logo
     only has to be replaced in one place. The icons are generated from the
     e-Clearance badge that the Android app uses, at
     mobile/student-android/app/src/main/res/drawable-nodpi/mcc_eclearance_logo.png,
     so the website and the app are recognisably the same product. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#00328c">
