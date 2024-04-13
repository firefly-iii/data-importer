@extends('layout.v2')
@section('content')
    <div class="container" x-data="index">
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <h1>{{ $mainTitle }}</h1>
            </div>
        </div>
        <!-- user has no accounts -->
        <!-- TODO validate me -->
        @if(0 === count($fireflyIIIaccounts) && ('nordigen' === $flow || 'spectre' === $flow))
            <div class="row mt-3">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            Error :(
                        </div>
                        <div class="card-body">
                            <p>It looks like you have no Firefly III asset accounts yet. The importer will not create
                                these for you. You must create them yourself.</p>
                            <p>
                                Please go to your Firefly III installation and create them, then refresh this page.
                            </p>
                            @if('nordigen' === $flow && count($importerAccounts) > 0)
                                <p>
                                    Feel free to use this information collected from GoCardless as inspiration:
                                </p>
                                <ul>
                                    @foreach($importerAccounts as $info)
                                        <li>
                                            Name: {{ $info['import_account']['name'] }}<br>
                                            (Internal) identifier: {{ $info['import_account']['identifier'] }}<br>
                                            Resource identifier: {{ $info['import_account']['resourceId'] }}<br>
                                            BBAN: {{ $info['import_account']['bban'] }}<br>
                                            BIC: {{ $info['import_account']['bic'] }}<br>
                                            IBAN: {{ $info['import_account']['iban'] }}<br>
                                            Owner name: {{ $info['import_account']['ownerName'] }}<br>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

@endsection
@section('scripts')
    @vite(['src/pages/configuration/index.js'])
@endsection
