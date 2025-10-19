<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                Account selection for

                {{ config('importer.flow_titles.' . $flow) }}
                import
            </div>
            <div class="card-body">
                <div class="form-group row mb-3">
                    <div class="col-sm-3">Accounts to be matched</div>
                    <div class="col-sm-9">
                        @php
                            $errorAccounts = 0;
                            $warningAccounts = 0;
                        @endphp
                        <table class="table table-sm table-bordered table-striped">
                            <thead>
                            <tr>
                                <th>
                                    {{ config('importer.flow_titles.' . $flow) }}
                                    account
                                </th>
                                <th>&nbsp;</th>
                                <th>Firefly III account</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach($importerAccounts as $information)
                                <x-importer-account :account="$information" :configuration="$configuration" :currencies="$currencies" :flow="$flow"/>
                            @endforeach

                            {{--
                            @foreach($importerAccounts as $information)
                                <!-- update variables -->
                                <!-- account is disabled at provider -->
                                @if('disabled' === $information['import_account']->status)
                                    @php
                                        $errorAccounts++;
                                    @endphp
                                @endif
                                <!-- have no info about account at provider -->
                                @if('no-info' === $information['import_account']->status)
                                    @php
                                        $warningAccounts++;
                                    @endphp
                                @endif
                                <!-- have nothing about account at provider -->
                                @if('nothing' === $information['import_account']->status)
                                    @php
                                        $warningAccounts++;
                                    @endphp
                                @endif
                                <!-- have no balance about account at provider -->
                                @if('nothing' === $information['import_account']->status)
                                    @php
                                        $warningAccounts++;
                                    @endphp
                                @endif
                                <!-- end of update variables -->
                                <x-importer-account :account="$information"
                                                    :configuration="$configuration"
                                                    :currencies="$currencies"
                                                    :flow="$flow"/>
                            @endforeach
                            --}}
                            </tbody>
                            <caption>Select and match the
                                {{ config('importer.flow_titles.' . $flow) }}
                                accounts you want to import into your Firefly III installation.
                            </caption>
                        </table>

                        @if($errorAccounts > 0)
                            <div class="alert alert-danger" role="alert">
                                <em class="fas fa-exclamation-triangle"></em>
                                The importer could not download information on some of your
                                accounts. This does not have to be an issue, but it may lead to
                                import errors.
                            </div>
                        @endif
                        @if($warningAccounts > 0)
                            <div class="alert alert-warning" role="alert">
                                <em class="fas fa-exclamation-triangle"></em>
                                The importer could not download information on some of your
                                accounts. This does not have to be an issue, but it may lead to
                                import errors.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
