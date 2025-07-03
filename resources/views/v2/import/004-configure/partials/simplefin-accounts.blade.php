<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                SimpleFIN account configuration
            </div>
            <div class="card-body">
                <p>Map your SimpleFIN accounts to Firefly III accounts. You can link to existing accounts or create new ones during import.</p>

                @if(count($importerAccounts) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless">
                            <thead>
                            <tr>
                                <th style="width:45%">SimpleFIN Account</th>
                                <th style="width:10%"></th>
                                <th style="width:45%">Firefly III Account</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($importerAccounts as $information)
                                <x-importer-account :account="$information" :configuration="$configuration" :currencies="$currencies" :flow="$flow"/>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning">
                        <strong>No SimpleFIN accounts found.</strong> Please ensure your SimpleFIN token is valid and try again.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
