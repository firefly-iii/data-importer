<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="light dark">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">
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

    <title>500 error :(</title>
</head>
<body>
@if(config('importer.is_external'))
    <div class="alert alert-warning" role="alert">
        This Firefly III Data Importer installation is <strong>publicly accessible</strong>. Please read <a
            href="https://docs.firefly-iii.org/references/data-importer/public/" class="alert-link" target="_blank">the
            considerations</a> (link opens in a new window or tab).
    </div>
@endif
<div class="container">
    <div class="row mt-3">
        <div class="col-lg-10 offset-lg-1">
            <h1>Whoops! 500 :(</h1>
            <p>
                Sorry, the Firefly III Data Importer broke down.
            </p>
            <h2>Error message</h2>
            <p class="text-danger">
                {{ $exception->getMessage() }}
            </p>
            <h2>More information</h2>
            <p>
                The error occurred in <code>{{ $exception->getFile() }}:{{ $exception->getLine() }}</code>.
            </p>
            <p>
                Please collect more information in the <code>storage/logs</code> directory, where you will find log files.
                If you're running Docker, use <code>docker logs -f [container]</code>.
                You can read more about collecting error information <a href="https://docs.firefly-iii.org/how-to/general/debug/"
                                                                        target="_blank">in the FAQ</a>.
            </p>
            <h2>Get help on GitHub</h2>
            <p>
                You're more than welcome to open a new issue <strong><a href="https://github.com/firefly-iii/firefly-iii/issues">on GitHub</a></strong>.
            </p>
            <ol>
                <li>Use the search!</li>
                <li>Include the information <a href="{{ route('debug') }}">from this debug page</a>.</li>
                <li>Tell us more than &quot;it says Whoops!&quot;</li>
                <li>Include error logs (see above).</li>
                <li>Tell us what you were doing.</li>
            </ol>
            <h2>Stacktrace</h2>
            <p>
                A stacktrace can help find the location of the bug.
            </p>
            <pre>{{ $exception->getTraceAsString() }}
            </pre>
        </div>
    </div>
</body>
</html>
