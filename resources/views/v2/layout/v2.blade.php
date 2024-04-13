<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="{{ route('index') }}/">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">


    <script type="text/javascript">
        /*!
 * Color mode toggler for Bootstrap's docs (https://getbootstrap.com/)
 * Copyright 2011-2023 The Bootstrap Authors
 * Licensed under the Creative Commons Attribution 3.0 Unported License.
 */

        (() => {
            'use strict'
            // todo store just happens to store in localStorage but if not, this would break.
            const getStoredTheme = () => JSON.parse(localStorage.getItem('darkMode'))

            const getPreferredTheme = () => {
                const storedTheme = getStoredTheme()
                if (storedTheme) {
                    return storedTheme
                }

                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
            }

            const setTheme = theme => {
                if (theme === 'browser' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark')
                    window.theme = 'dark';
                    return;
                }
                if (theme === 'browser' && window.matchMedia('(prefers-color-scheme: light)').matches) {
                    window.theme = 'light';
                    document.documentElement.setAttribute('data-bs-theme', 'light')
                    return;
                }
                document.documentElement.setAttribute('data-bs-theme', theme)
                window.theme = theme;
            }

            setTheme(getPreferredTheme())

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                const storedTheme = getStoredTheme()
                if (storedTheme !== 'light' && storedTheme !== 'dark') {
                    setTheme(getPreferredTheme())
                }
            })
        })()
    </script>
    @yield('styles')
    @vite(['src/sass/app.scss'])



    <title>Firefly III Data Importer // {{ $pageTitle ?? 'No title' }}</title>
</head>
<body>
@if(config('importer.is_external'))
<div class="alert alert-warning" role="alert">
    This Firefly III Data Importer installation is <strong>publicly accessible</strong>. Please read <a
        href="https://docs.firefly-iii.org/references/data-importer/public/" class="alert-link" target="_blank">the considerations</a> (link opens in a new
    window or tab).
</div>
@endif

@yield('content')

<!-- Optional JavaScript -->

@yield('scripts')

@if('' != config('importer.tracker_site_id') and '' != config('importer.tracker_url'))
<!-- Matomo -->
<script>
    var _paq = window._paq = window._paq || [];
    /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    (function () {
        var u = "{{ config('importer.tracker_url') }}";
        _paq.push(['setTrackerUrl', u + 'matomo.php']);
        _paq.push(['setSiteId', '{{ config('importer.tracker_site_id') }}']);
        var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
        g.async = true;
        g.src = u + 'matomo.js';
        s.parentNode.insertBefore(g, s);
    })();
</script>
<noscript><p><img src="{{ config('importer.tracker_url') }}matomo.php?idsite={{ config('importer.tracker_site_id') }}&amp;rec=1" style="border:0;" alt=""/>
    </p></noscript>
<!-- End Matomo Code -->
@endif
</body>
</html>
