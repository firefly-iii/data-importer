<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow, noarchive, noodp, NoImageIndex, noydir">
    <title>Firefly III Data Importer Maintenance Mode</title>
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

</head>
<body class="container">
<div class="row mt-3">
    <div class="col-lg-10 col-lg-offset-1 col-md-12 col-sm-12 col-xs-12">
        <h1><strong>Firefly III</strong> Data Importer</h1>
    </div>
</div>
<div class="row">
    <div class="col-lg-10 col-lg-offset-1 col-md-12 col-sm-12 col-xs-12">
        <h3 class="text-info">The Firefly III Data Importer is in maintenance mode.</h3>
    </div>
</div>
<div class="row">
    <div class="col-lg-10 col-lg-offset-1 col-md-12 col-sm-12 col-xs-12">
        <p>
            This should only take a few moments. If you had an import running, you may have to start over. My apologies.
        </p>
        <p>
            Please check back in a second!
        </p>
    </div>
</div>
</body>
</html>
