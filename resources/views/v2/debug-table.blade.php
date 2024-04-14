<table>
    <tr>
        <th colspan="2">System information</th>
    </tr>
    <tr>
        <th>Item</th>
        <th>Value</th>
    </tr>
    <tr>
        <td>Version</td>
        <td>{{ config('importer.version') }}</td>
    </tr>
    @if($system['is_docker'])
        <tr>
            <td>Build</td>
            <td><span>#</span>{{ $system['build'] }}, base <span>#</span>{{ $system['base_build'] }}</td>
        </tr>
    @endif
    <tr>
        <td>System</td>
        <td>PHP {{ $system['php_version'] }}, {{ $system['php_os'] }}, {{ $system['interface'] }}</td>
    </tr>
</table>

<table>
    <tr>
        <th colspan="2">App information</th>
    </tr>
    <tr>
        <th>Item</th>
        <th>Value</th>
    </tr>
    <tr>
        <td>Timezone</td>
        <td>{{ config('app.timezone') }}, [BrowserTZ]</td>
    </tr>
    <tr>
        <td>Environment</td>
        <td>{{ config('app.env') }}</td>
    </tr>
    <tr>
        <td>Debug mode</td>
        <td>{{ $app['debug'] }}, cache '{{ config('cache.default') }}'</td>
    </tr>
    <tr>
        <td>Log level</td>
        <td>{{ config('logging.level') }}, {{ config('logging.default') }}</td>
    </tr>
    <tr>
        <td>Display errors</td>
        <td>{{ $app['display_errors'] }}, {{ $app['reporting'] }}</td>
    </tr>
    <tr>
        <td>BCscale</td>
        <td>{{ $app['bcscale'] }}</td>
    </tr>
    <tr>
        <td>Trusted proxies</td>
        <td>{{ config('importer.trusted_proxies') }}</td>
    </tr>
</table>
<table>
    <tr>
        <th colspan="2">User information</th>
    </tr>
    <tr>
        <th>Item</th>
        <th>Value</th>
    </tr>
    <tr>
        <td>
            User agent
        </td>
        <td>
            {{ $user['user_agent'] }}
        </td>
    </tr>
</table>
