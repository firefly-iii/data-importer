@extends('layout.v2')
@section('content')
    <div class="container" x-data="index">
        <!-- this is a bit of a hack, but it works well enough to sync AlpineJS and the configuration object -->
        <span id="date-range-helper" data-date-range="{{$configuration->getDateRange()  }}"></span>
        <span id="date-format-helper" data-date-format="{{$configuration->getDate()  }}"></span>
        <span id="detection-method-helper" data-method="{{$configuration->getDuplicateDetectionMethod()}}"></span>
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <h1>{{ $mainTitle }}</h1>
            </div>
        </div>

        <!-- error -->
        @if(!$errors->isEmpty())
            <div class="row mt-3">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            Errors :(
                        </div>
                        <div class="card-body">
                            <p class="text-danger">Some error(s) occurred:</p>
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li class="text-danger">{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <!-- end of error -->


        <!-- user has no accounts -->
        @if(0 === count($fireflyIIIaccounts['assets']) && 0 === count($fireflyIIIaccounts['liabilities']) )
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
        <!-- user has accounts! -->
        @if(count($fireflyIIIaccounts['assets']) > 0 || count($fireflyIIIaccounts['liabilities']) > 0)
            <div class="row mt-3">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            {{ $subTitle }}
                        </div>
                        <div class="card-body">
                            @if('file' === $flow)
                                <p>
                                    @if('camt' === $configuration->getContentType())
                                        Even though camt.053 is a defined standard, you might want to customize. Some of
                                        the most important settings are below.
                                        They apply to all records in the uploaded files. If you would like some support,
                                        you won't find anything at <a
                                            href="https://docs.firefly-iii.org/how-to/data-importer/import/csv/"
                                            target="_blank">
                                            this page.</a> right now.
                                    @endif
                                    @if('csv' === $configuration->getContentType())
                                        Importable files come in many shapes and forms. Some of the most important
                                        settings are below.
                                        They apply to all lines in the file. If you would like some support, <a
                                            href="https://docs.firefly-iii.org/how-to/data-importer/import/csv/"
                                            target="_blank">
                                            check out the documentation for this page.</a>
                                    @endif
                                </p>
                            @endif
                            @if('nordigen' === $flow || 'spectre' === $flow)
                                <p>
                                    Your
                                    @if('nordigen' === $flow)
                                        GoCardless
                                    @endif
                                    @if('spectre' === $flow)
                                        Spectre
                                    @endif
                                    import can be configured and fine-tuned.
                                    <a href="https://docs.firefly-iii.org/how-to/data-importer/import/gocardless/"
                                       target="_blank">Check
                                        out the documentation for this page.</a>
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- start of form -->
            <form method="post" action="{{ route('004-configure.post') }}" accept-charset="UTF-8" id="store">
                <input type="hidden" name="_token" value="{{ csrf_token() }}"/>
                <input type="hidden" name="flow" value="{{ $flow }}"/>
                <input type="hidden" name="content_type" value="{{ $configuration->getContentType() }}"/>

                <!-- these values are used by Spectre + Nordigen and must be preserved -->
                <input type="hidden" name="identifier" value="{{ $configuration->getIdentifier() }}"/>
                <input type="hidden" name="connection" value="{{ $configuration->getConnection() }}"/>
                <input type="hidden" name="nordigen_country" value="{{ $configuration->getNordigenCountry() }}"/>
                <input type="hidden" name="nordigen_max_days" value="{{ $configuration->getNordigenMaxDays() }}"/>
                <input type="hidden" name="nordigen_bank" value="{{ $configuration->getNordigenBank() }}"/>
                <input type="hidden" name="nordigen_requisitions"
                       value="{{ json_encode($configuration->getNordigenRequisitions()) }}"/>
                @if('nordigen' === $flow || 'spectre' === $flow)
                    <input type="hidden" name="ignore_duplicate_transactions" value="1"/>
                @endif

                <!-- Account selection for Gocardless and Spectre -->
                <!-- also date range settings -->
                @if('nordigen' === $flow || 'spectre' === $flow)
                    <!-- start of account selection -->
                    <div class="row mt-3">
                        <div class="col-lg-10 offset-lg-1">
                            <div class="card">
                                <div class="card-header">
                                    Account selection for
                                    @if('nordigen' === $flow)
                                        GoCardless
                                    @endif
                                    @if('spectre' === $flow)
                                        Spectre
                                    @endif
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
                                                        @if('nordigen' === $flow)
                                                            GoCardless
                                                        @endif
                                                        @if('spectre' === $flow)
                                                            Spectre
                                                        @endif
                                                        account
                                                    </th>
                                                    <th>&nbsp;</th>
                                                    <th>Firefly III account</th>
                                                </tr>
                                                </thead>
                                                <tbody>
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
                                                                        :configuration="$configuration"/>
                                                @endforeach
                                                </tbody>
                                                <caption>Select and match the
                                                    @if('nordigen' === $flow)
                                                        GoCardless
                                                    @endif
                                                    @if('spectre' === $flow)
                                                        Spectre
                                                    @endif
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
                    <!-- end of account selection -->
                    <!-- start of date options -->
                    <div class="row mt-3">
                        <div class="col-lg-10 offset-lg-1">
                            <div class="card">
                                <div class="card-header">
                                    Date range import options
                                </div>
                                <div class="card-body">
                    <div class="form-group row mb-3">
                        <label for="default_account" class="col-sm-3 col-form-label">Date range</label>
                        <div class="col-sm-9">
                            <div class="form-check">
                                <input class="form-check-input date-range-radio" id="date_range_all"
                                       type="radio" name="date_range" value="all" x-model="dateRange"
                                       @if('all' === $configuration->getDateRange()) checked @endif
                                aria-describedby="rangeHelp"/>
                                <label class="form-check-label" for="date_range_all">Import
                                    everything</label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input date-range-radio" id="date_range_partial"
                                       type="radio" name="date_range" x-model="dateRange"
                                       value="partial"
                                       @if('partial' === $configuration->getDateRange()) checked @endif
                                aria-describedby="rangeHelp"/>
                                <label class="form-check-label" for="date_range_partial">Go back some
                                    time</label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input date-range-radio" id="date_range_range"
                                       type="radio" name="date_range" value="range" x-model="dateRange"
                                       @if('range' === $configuration->getDateRange()) checked @endif
                                aria-describedby="rangeHelp"/>
                                <label class="form-check-label" for="date_range_range">Import a specific
                                    range</label>
                                <small id="rangeHelp" class="form-text text-muted">
                                    <br>What range to grab from your bank through
                                    @if('nordigen' === $flow)
                                        GoCardless?
                                    @endif
                                    @if('spectre' === $flow)
                                        Spectre?
                                    @endif
                                </small>
                            </div>


                        </div>
                    </div>

                    <div class="form-group row mb-3" id="date_range_partial_settings" x-show="'partial' === dateRange">
                        <div class="col-sm-3">
                            Date range settings
                        </div>
                        <div class="col-sm-3">
                            <input
                                name="date_range_number"
                                id="date_range_number"
                                class="form-control" value="{{ $configuration->getDateRangeNumber() }}"
                                type="number" step="1" min="1" max="365">
                        </div>
                        <div class="col-sm-6">
                            <select class="form-control"
                                    name="date_range_unit"
                                    id="date_range_unit">
                                <option
                                    @if('d' === $configuration->getDateRangeUnit()) selected @endif
                                value="d" label="days">days
                                </option>
                                <option
                                    @if('w' === $configuration->getDateRangeUnit()) selected @endif
                                value="w" label="weeks">weeks
                                </option>
                                <option
                                    @if('m' === $configuration->getDateRangeUnit()) selected @endif
                                value="m" label="months">months
                                </option>
                                <option
                                    @if('y' === $configuration->getDateRangeUnit()) selected @endif
                                value="y" label="years">years
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-3" id="date_range_range_settings" x-show="'range' === dateRange">
                        <div class="col-sm-3">
                            Date range settings (from, to)
                        </div>
                        <div class="col-sm-4">
                            <input type="date" name="date_not_before" class="form-control"
                                   value="{{ $configuration->getDateNotBefore() }}">
                        </div>
                        <div class="col-sm-4">
                            <input type="date" name="date_not_after" class="form-control"
                                   value="{{ $configuration->getDateNotAfter() }}">
                        </div>
                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end of date range options -->

                @endif
                <!-- end of account selection and date range settings -->

                <!-- spectre specific options -->
                @if('spectre' === $flow)
                    <div class="row mt-3">
                        <div class="col-lg-10 offset-lg-1">
                            <div class="card">
                                <div class="card-header">
                                    Spectre import options
                                </div>
                                <div class="card-body">
                                    <div class="form-group row">
                                        <label for="X" class="col-sm-3 col-form-label">Ignore Spectre categories</label>
                                        <div class="col-sm-9">
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                       @if($configuration->isIgnoreSpectreCategories()) checked @endif
                                                       type="checkbox" value="1" id="ignore_spectre_categories"
                                                       name="ignore_spectre_categories"
                                                       aria-describedby="duplicateSpectre">
                                                <label class="form-check-label" for="ignore_spectre_categories">
                                                    Ignore Spectre's categories.
                                                </label>
                                            </div>

                                            <small class="form-text text-muted" id="duplicateSpectre">
                                                Spectre adds categories to each transaction. You can choose to ignore
                                                them.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <!-- end of spectre options -->

                <!-- camt.053 options -->
                @if('file' === $flow && 'camt'  === $configuration->getContentType())
                    <div class="row mt-3">
                        <div class="col-lg-10 offset-lg-1">
                            <div class="card">
                                <div class="card-header">
                                    CAMT.053 import options
                                </div>
                                <div class="card-body">
                                    <div class="form-group row mb-3">
                                        <div class="col-sm-3">How to handle "Level-D" data</div>
                                        <div class="col-sm-9">
                                            <select id="grouped_transaction_handling"
                                                    name="grouped_transaction_handling"
                                                    class="form-control"
                                                    aria-describedby="grouped_transaction_handling_help">
                                                <option
                                                    label="Create multiple single transactions, for each Level-D record"
                                                    @if('single' === $configuration->getGroupedTransactionHandling()) selected
                                                    @endif
                                                    value="single">Create multiple single transactions, for each Level-D
                                                    record
                                                </option>
                                                <option disabled
                                                        label="Create one split transaction with splits for each record"
                                                        @if('split' === $configuration->getGroupedTransactionHandling()) selected
                                                        @endif
                                                        value="split">Create one split transaction with splits for each
                                                    record
                                                </option>
                                                <option
                                                    label='Drop "level-D" data, sum and merge all details in a single transaction'
                                                    @if('group' === $configuration->getGroupedTransactionHandling()) selected
                                                    @endif
                                                    value="group">Drop "level-D" data, sum and merge all details in a
                                                    single transaction
                                                </option>
                                            </select>
                                            <small class="form-text text-muted">
                                                It's not recommended to drop the "level-D" data, you may lose details.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-3">
                                        <div class="col-sm-3">Use the entire address of the opposing part?</div>
                                        <div class="col-sm-9">
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                       @if($configuration->isUseEntireOpposingAddress()) checked @endif
                                                       type="checkbox" id="use_entire_opposing_address"
                                                       name="use_entire_opposing_address" value="1"
                                                       aria-describedby="useEntireOpposingAddressHelp">
                                                <label class="form-check-label" for="use_entire_opposing_address">
                                                    Yes
                                                </label>
                                                <small id="use_entire_opposing_address_help"
                                                       class="form-text text-muted">
                                                    <br>
                                                    The default is to only use the name, and only use the address
                                                    details when no name is available.
                                                    If you select this option, both name and address will always be used
                                                    (when available).
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <!-- end of camt.053 options -->
                <!-- start of CSV options -->
                @if('file' === $flow && 'csv'  === $configuration->getContentType())
                    <div class="row mt-3">
                        <div class="col-lg-10 offset-lg-1">
                            <div class="card">
                                <div class="card-header">
                                    CSV file import options
                                </div>
                                <div class="card-body">
                                    <div class="form-group row mb-3">
                                        <div class="col-sm-3">Headers</div>
                                        <div class="col-sm-9">
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       id="headers" name="headers" value="1"
                                                       aria-describedby="headersHelp"
                                                       @if($configuration->isHeaders()) checked @endif
                                                >
                                                <label class="form-check-label" for="headers">
                                                    Yes
                                                </label><br>
                                                <small id="headersHelp" class="form-text text-muted">
                                                    Select this checkbox when your importable file is a CSV-like file
                                                    and has headers on the first line of the
                                                    file.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group row mb-3">
                                        <div class="col-sm-3">Convert to UTF-8</div>
                                        <div class="col-sm-9">
                                            <div class="form-check">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       @if($configuration->isConversion()) checked @endif
                                                       id="conversion" name="conversion" value="1"
                                                       aria-describedby="conversionHelp">
                                                <label class="form-check-label" for="conversion">
                                                    Yes
                                                </label><br>
                                                <small id="conversionHelp" class="form-text text-muted">
                                                    Try to convert your file to UTF-8. This may lead to weird
                                                    characters.
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group row mb-3">
                                        <label for="delimiter" class="col-sm-3 col-form-label">CSV-file
                                            delimiter</label>
                                        <div class="col-sm-9">
                                            <select id="delimiter" name="delimiter" class="form-control"
                                                    aria-describedby="delimiterHelp">
                                                <option value="comma"
                                                        @if('comma' === $configuration->getDelimiter()) selected @endif
                                                        label="A comma (,)">A comma (,)
                                                </option>
                                                <option value="semicolon"
                                                        @if('semicolon' === $configuration->getDelimiter()) selected
                                                        @endif
                                                        label="A semicolon (;)">A semicolon (;)
                                                </option>
                                                <option value="tab"
                                                        @if('tab' === $configuration->getDelimiter()) selected @endif
                                                        label="A tab (invisible)">A tab (invisible)
                                                </option>
                                            </select>
                                            <small id="delimiterHelp" class="form-text text-muted">
                                                If your file is a CSV file, select the field separator of the file. This
                                                is almost always a comma.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="form-group row mb-3">
                                        <label for="date" class="col-sm-3 col-form-label">Date format</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="date" class="form-control"
                                                   placeholder="Date format" x-model="dateFormat"
                                                   value="{{ $configuration->getDate() ?? 'Y-m-d' }}"
                                                   @change="getParsedDate"
                                                   aria-describedby="dateHelp">
                                            <small id="dateHelp" class="form-text text-muted">
                                                1. Read more about the date format <a
                                                    href="https://www.php.net/manual/en/datetime.format.php">on this
                                                    page</a><br>
                                                2. Make sure this example date's format matches your file:
                                                <strong x-show="!loadingParsedDate" x-text="parsedDateFormat">1984-09-17</strong>
                                                <em x-show="loadingParsedDate" class="fas fa-cog fa-spin"></em>
                                                <br>
                                                3. If your file contains something like "5 mei 2023", prefix with your
                                                country code like so <code>nl:d F Y</code>
                                            </small>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <!-- end of CSV options -->

                <!-- generic import options -->
                <div class="row mt-3">
                    <div class="col-lg-10 offset-lg-1">
                        <div class="card">
                            <div class="card-header">
                                Various import options
                            </div>
                            <div class="card-body">
                                <div class="form-group row mb-3">
                                    <label for="default_account" class="col-sm-3 col-form-label">Default import
                                        account</label>
                                    <div class="col-sm-9">
                                        <select id="default_account" name="default_account" class="form-control"
                                                aria-describedby="defaultAccountHelp">
                                            @foreach($fireflyIIIaccounts as $accountGroup => $accountList)
                                                <optgroup label="{{ $accountGroup }}">
                                                    {% for account in accountList %}
                                                    @foreach($accountList as $account)
                                                    <option
                                                        @if($configuration->getDefaultAccount() === $account->id) selected @endif
                                                        value="{{ $account->id }}"
                                                        label="{{ $account->name }}">{{ $account->name }}</option>
                                                    @endforeach
                                                </optgroup>
                                                @endforeach
                                        </select>
                                        <small id="defaultAccountHelp" class="form-text text-muted">
                                            Select the asset account you want to link transactions to, if your import
                                            doesn't have enough meta data to determine this.
                                        </small>
                                    </div>
                                </div>
                                <div class="form-group row mb-3">
                                    <div class="col-sm-3">Rules</div>
                                    <div class="col-sm-9">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   @if($configuration->isRules()) checked @endif
                                                   type="checkbox" id="rules" name="rules" value="1"
                                                   aria-describedby="rulesHelp">
                                            <label class="form-check-label" for="rules">
                                                Yes
                                            </label>
                                            <small id="rulesHelp" class="form-text text-muted">
                                                <br>Select if you want Firefly III to apply your rules to the import.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row mb-3">
                                    <div class="col-sm-3">Import tag</div>
                                    <div class="col-sm-9">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   @if($configuration->isAddImportTag()) checked @endif
                                                   type="checkbox" id="add_import_tag" name="add_import_tag" value="1"
                                                   aria-describedby="add_import_tagHelp">
                                            <label class="form-check-label" for="add_import_tag">
                                                Yes
                                            </label>
                                            <small id="add_import_tagHelp" class="form-text text-muted">
                                                <br>When selected Firefly III will add a tag to each imported transaction
                                                denoting the import; this groups your import under a tag.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <!-- both camt and csv support custom tag -->
                                <div class="form-group row mb-3">
                                    <label for="date" class="col-sm-3 col-form-label">Custom import tag</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="custom_tag" class="form-control" id="custom_tag"
                                               placeholder="Custom import tag"
                                               value="{{ $configuration->getCustomTag() }}"
                                               aria-describedby="custom_tagHelp">
                                        <small id="custom_tagHelp" class="form-text text-muted">
                                            You can set your own import tag to easily distinguish imports
                                            or just because you don't like the default one.
                                            <a href="https://docs.firefly-iii.org/how-to/data-importer/advanced/custom-import-tag/" target="_blank">Read more in the documentation</a>.
                                        </small>
                                    </div>
                                </div>

                                <div class="form-group row mb-3">
                                    <div class="col-sm-3">Map data</div>
                                    <div class="col-sm-9">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   @if($configuration->isMapAllData()) checked @endif
                                                   type="checkbox"
                                                   id="map_all_data"
                                                   name="map_all_data" value="1" aria-describedby="mapAllDataHelp">
                                            <label class="form-check-label" for="map_all_data">
                                                Yes
                                            </label>
                                            <small id="mapAllDataHelp" class="form-text text-muted">
                                                <br>You get the opportunity to link your data to existing Firefly III
                                                data, for a cleaner import.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- end of generic import options -->

                <!-- duplicate detection options -->
                <div class="row mt-3">
                    <div class="col-lg-10 offset-lg-1">
                        <div class="card">
                            <div class="card-header">
                                Duplicate transaction detection
                            </div>
                            <div class="card-body">
                                <div class="col-sm-9 offset-sm-3">
                                    <p class="text-muted">
                                        Firefly III can automatically detect duplicate transactions. This is pretty
                                        foolproof. In some special cases however,
                                        you want more control over this process. Read more about the options below in <a
                                            href="https://docs.firefly-iii.org/how-to/data-importer/import/csv/"
                                            target="_blank">the documentation</a>.
                                    </p>
                                </div>

                                @if('file' === $flow)
                                <div class="form-group row mb-3">
                                    <label for="X" class="col-sm-3 col-form-label">General detection options</label>
                                    <div class="col-sm-9">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   @if($configuration->isIgnoreDuplicateLines()) checked @endif
                                                   type="checkbox" value="1" id="ignore_duplicate_lines"
                                                   name="ignore_duplicate_lines" aria-describedby="duplicateHelp">
                                            <label class="form-check-label" for="ignore_duplicate_lines">
                                                Do not import duplicate lines or entries in the importable file.
                                            </label>
                                            <br>
                                            <small class="form-text text-muted" id="duplicateHelp">
                                                Whatever method you choose ahead, it's smart to make the importer ignore
                                                any
                                                duplicated lines or entries in your importable file.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <div class="form-group row mb-3">
                                    <label for="duplicate_detection_method" class="col-sm-3 col-form-label">Detection
                                        method</label>
                                    <div class="col-sm-9">
                                        <select id="duplicate_detection_method" name="duplicate_detection_method" x-model="detectionMethod"
                                                class="form-control" aria-describedby="duplicate_detection_method_help">
                                            <option label="No duplicate detection"
                                                    @if('none' === $configuration->getDuplicateDetectionMethod()) selected @endif
                                            value="none">No duplicate detection
                                            </option>
                                            <option label="Content-based"
                                                    @if('classic' === $configuration->getDuplicateDetectionMethod()) selected @endif
                                            value="classic">Content-based detection
                                            </option>
                                            <option label="Identifier-based"
                                                    @if('cell' === $configuration->getDuplicateDetectionMethod()) selected @endif
                                            value="cell">Identifier-based detection
                                            </option>
                                        </select>
                                        <small id="duplicate_detection_method_help" class="form-text text-muted">
                                            For more details on these detection method see <a
                                                href="https://docs.firefly-iii.org/references/data-importer/duplicate-detection/"
                                                target="_blank">the documentation</a>. If you're not sure, select
                                            "content-based" detection.
                                        </small>
                                    </div>
                                </div>
                                @if('file' === $flow)
                                <div class="form-group row mb-3" id="unique_column_index_holder" x-show="'cell' === detectionMethod">
                                    <label for="unique_column_index" class="col-sm-3 col-form-label">Unique column
                                        index</label>
                                    <div class="col-sm-9">
                                        <input type="number" step="1" name="unique_column_index" class="form-control"
                                               id="unique_column_index" placeholder="Column index"
                                               value="{{ $configuration->getUniqueColumnIndex() }}"
                                               aria-describedby="unique_column_index_help">
                                        <small id="unique_column_index_help" class="form-text text-muted">
                                            This field is only relevant for the "identifier-based" detection option.
                                            Indicate which column / field contains the unique identifier. Start counting from
                                            zero!
                                        </small>
                                    </div>
                                </div>
                                @endif

                                <div class="form-group row" id="unique_column_type_holder" x-show="'cell' === detectionMethod">
                                    <label for="unique_column_type" class="col-sm-3 col-form-label">Unique column
                                        type</label>
                                    <div class="col-sm-9">
                                        <select id="unique_column_type" name="unique_column_type" class="form-control"
                                                aria-describedby="unique_column_type_help">
                                            @foreach($uniqueColumns as $columnType => $columnName)
                                            <option label="{{ $columnName }}"
                                                    @if($configuration->getUniqueColumnType() === $columnType) selected @endif
                                                    value="{{ $columnType }}">{{ $columnName }}</option>
                                            @endforeach
                                        </select>

                                        <small id="unique_column_type_help" class="form-text text-muted">
                                            This field is only relevant for the "identifier-based" detection option.
                                            Select
                                            the type of value you expect in
                                            the unique identifier. What must Firefly III search for?
                                        </small>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- end of duplicate detection options -->

                <!-- other options -->
                <div class="row mt-3">
                    <div class="col-lg-10 offset-lg-1">
                        <div class="card">
                            <div class="card-header">
                                Other options
                            </div>
                            <div class="card-body">

                                <h4>Other options</h4>
                                <div class="form-group row mb-3">
                                    <div class="col-sm-3">Skip form</div>
                                    <div class="col-sm-9">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   @if($configuration->isSkipForm()) checked @endif
                                                    type="checkbox"
                                                   id="skip_form" name="skip_form" value="1" aria-describedby="skipHelp">
                                            <label class="form-check-label" for="skip_form">
                                                Yes
                                            </label>
                                            <small id="skipHelp" class="form-text text-muted">
                                                <br>Skip the options the next time you import and go straight to processing.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- end of other options -->

                <!-- start of submit button -->
                <div class="row mt-3">
                    <div class="col-lg-10 offset-lg-1">
                        <div class="card">
                            <div class="card-header">
                                Submit!
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <button type="submit" class="float-end btn btn-primary">Submit &rarr;</button>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- end of submit button -->


                    <!-- next form steps here -->
                <!--
                    <div class="row mt-3">
                        <div class="col-lg-10 offset-lg-1">
                            <div class="card">
                                <div class="card-header">
                                    Box title
                                </div>
                                <div class="card-body">
                                    BOX CONTENT
                                </div>
                            </div>
                        </div>
                    </div>
                    -->
                    <!-- end of form -->
            </form>
        @endif
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-body">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('back.upload') }}" class="btn btn-secondary"><span
                                    class="fas fa-arrow-left"></span> Go back to upload</a>
                            <a href="{{ route('flush') }}" class="btn text-white btn-danger btn-sm"><span
                                    class="fas fa-redo-alt"></span> Start over</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection
@section('scripts')
    @vite(['src/pages/configuration/index.js'])
@endsection
