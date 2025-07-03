@if(0 === count($fireflyIIIaccounts['assets']) && 0 === count($fireflyIIIaccounts['liabilities']) && $flow !== 'simplefin')
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
                                    Name: <strong>{{ $info['import_account']->name ?? '' }}</strong>
                                    <ul>
                                        <li>(Internal)
                                            identifier: {{ $info['import_account']->identifier ?? '' }}</li>
                                        <li>Resource
                                            identifier: {{ $info['import_account']->resourceId ?? '' }}</li>
                                        <li>BBAN: {{ $info['import_account']->bban ?? '' }}</li>
                                        <li>BIC: {{ $info['import_account']->bic  ?? ''}}</li>
                                        <li>IBAN: {{ $info['import_account']->iban ?? '' }}</li>
                                        <li>Owner name: {{ $info['import_account']->ownerName ?? '' }}</li>
                                    </ul>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
