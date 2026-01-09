<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                {{ config('importer.providers.' . $flow . '.title') }} account configuration
            </div>
            <div class="card-body">
                <p>Map your {{ config('importer.providers.' . $flow . '.title') }} accounts to Firefly III accounts. You can link to existing accounts or create new ones during import.</p>

                @if(count($accounts) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless">
                            <thead>
                            <tr>
                                <th style="width:45%">{{ config('importer.providers.' . $flow . '.title') }} Account</th>
                                <th style="width:10%"></th>
                                <th style="width:45%">Firefly III Account</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($accounts as $information)
                                <x-importer-account :account="$information" :configuration="$configuration" :currencies="$currencies" :flow="$flow"/>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning">
                        <strong>No {{ config('importer.providers.' . $flow . '.title') }} accounts found.</strong> Please ensure your settings are valid and try again.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
