@if(0 === count($fireflyIIIAccounts['assets']) && 0 === count($fireflyIIIAccounts['liabilities']) && $flow === 'file')
    <div class="row mt-3">
        <div class="col-lg-10 offset-lg-1">
            <div class="card">
                <div class="card-header">
                    Error :(
                </div>
                <div class="card-body">
                    <p>It looks like you have no Firefly III asset accounts yet. The importer will not create these for you. You must create them yourself.</p>
                    <p>Please go to your Firefly III installation and create them, then refresh this page.</p>
                </div>
            </div>
        </div>
    </div>
@endif
