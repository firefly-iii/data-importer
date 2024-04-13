@extends('layout.v2')
@section('content')

    <div class="container" x-data="gocardless">
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <h1>{{ $mainTitle }}</h1>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        {{ $subTitle }}
                    </div>
                    <div class="card-body">
                        <p>
                            Select your country and the bank you would like to import from.
                            If you would like some support, <a href="https://docs.firefly-iii.org/how-to/data-importer/import/gocardless/"
                                                               target="_blank">check out the documentation for this
                                page.</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @if(!$errors->isEmpty())
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        Errors
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
        <form method="post" action="{{ route('009-selection.post') }}" accept-charset="UTF-8">
            <input type="hidden" name="_token" value="{{ csrf_token() }}"/>
            <div class="row mt-3">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            Select country
                        </div>
                        <div class="card-body">
                            <div class="form-group row">
                                <label for="country" class="col-sm-3 col-form-label">Country</label>
                                <div class="col-sm-9">
                                    <select class="form-control"
                                            name="country"
                                            x-model="selectedCountry">
                                        <option label="(no selection)" value="XX">(no selection)</option>
                                        @foreach($response as $country)
                                        <option label="{{ $countries[$country->code] ?? 'Unknown' }}"
                                                @if($country->code == $configuration->getNordigenCountry())selected="selected"@endif
                                                value="{{ $country->code }}">{{ $countries[$country->code] ?? 'Unknown' }}</option>
                                        @endforeach
                                    </select>

                                    <small class="form-text text-muted">
                                        Which country is your bank in?
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @foreach($response as $country)
            <div class="row mt-3 bank-box"  x-show="'{{ $country->code }}' === selectedCountry && 'XX' !== selectedCountry">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            Select your bank in {{ $country->code }}
                        </div>
                        <div class="card-body">
                            <div class="form-group row">
                                <label for="bank_{{ $country->code }}" class="col-sm-3 col-form-label">Bank
                                    ({{ $country->code }})</label>
                                <div class="col-sm-9">
                                    <select class="form-control bank-selector" name="bank_{{ $country->code }}"
                                            x-model="selectedBank">
                                        <option label="(no bank)" value="XX" data-days="0">(no bank)</option>
                                        @foreach($country->banks as $bank)
                                        <option label="{{ $bank->name }}"
                                                @if($bank->id === $configuration->getNordigenBank())selected="selected"@endif
                                                data-days="{{ $bank->transactionTotalDays }}"
                                                value="{{ $bank->id }}">{{ $bank->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <p class="mt-3 text-info bank-selected" style="display: none;">Imports from this bank
                                go no further back than <strong class="days">XX</strong> days.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

            <div class="row mt-3 bank-box" x-show="'XX' === selectedCountry">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            Select a bank
                        </div>
                        <div class="card-body">
                            <small class="form-text text-muted">
                                (Please select a country first)
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3 bank-selected" x-show="'XX' !== selectedCountry && '' !== selectedBank">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            Number of days the connection is valid
                        </div>
                        <div class="card-body">
                            <div class="form-group row">
                                <label for="days" class="col-sm-3 col-form-label">Number of days the connection is valid</label>
                                <div class="col-sm-9">
                                    <input name="days" class="form-control" step="1" min="1" type="number"
                                           value="{{ $configuration->getNordigenMaxDays() }}"/>
                                    <small class="form-text text-muted">
                                        The connection to your bank can be recycled for this number of days. It will be stored
                                        in the import configuration file. Keep in mind most banks don't support more than 90 days.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="row mt-3 submit-button" x-show="'XX' !== selectedCountry && '' !== selectedBank">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">

                        <div class="card-header">
                            Submit!
                        </div>
                        <div class="card-body">
                            <button type="submit" class="float-end btn btn-primary">Submit &rarr;</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-body">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('back.upload') }}" class="btn btn-secondary"><span
                                    class="fas fa-arrow-left"></span> Go back to upload</a>
                            <a href="{{ route('flush') }}" class="btn btn-danger text-white btn-sm"><span
                                    class="fas fa-redo-alt"></span> Start over</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--
    <script type="text/javascript">
        $(document).ready(function () {
            $('#country').change(selectCountry)
            $('.bank-selector').change(showDayCounter);
            selectCountry();
        });

        function showDayCounter() {
            $('.bank-selected').show();
            let val = $('#country').val();

            let maxDays = parseInt($('#bank_' + val + ' option:selected').data('days'));

            $('.days').text(maxDays);
        }

        function selectCountry() {
            $('.bank-selected').hide();
            var val = $('#country').val();
            $('.country-code').text(val);
            $('.bank-box').hide();
            $('.submit-button').show();
            $('#' + val + '-box').show();
        }
    </script>
    -->

@endsection
@section('scripts')
    @vite(['src/pages/selection/gocardless.js'])
@endsection
