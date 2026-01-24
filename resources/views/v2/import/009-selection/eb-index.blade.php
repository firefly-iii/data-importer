@extends('layout.v2')
@section('content')

    <div class="container" x-data="enablebanking">
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
                            Select your country and the bank you would like to import from using Enable Banking.
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
        <form method="post" action="{{ route('eb-select-bank.post', [$identifier]) }}" accept-charset="UTF-8">
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
                                        @foreach($countries as $code => $name)
                                        <option label="{{ $name }}"
                                                @if($code === $selectedCountry)selected="selected"@endif
                                                value="{{ $code }}">{{ $name }}</option>
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

            @if($response !== null)
            <div class="row mt-3 bank-box" x-show="'{{ $selectedCountry }}' === selectedCountry && 'XX' !== selectedCountry">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            Select your bank in {{ $selectedCountry }}
                        </div>
                        <div class="card-body">
                            <div class="form-group row">
                                <label for="bank_{{ $selectedCountry }}" class="col-sm-3 col-form-label">Bank ({{ $selectedCountry }})</label>
                                <div class="col-sm-9">
                                    <select class="form-control bank-selector" name="bank_{{ $selectedCountry }}"
                                            x-model="selectedBank">
                                        <option label="(no bank)" value="XX">(no bank)</option>
                                        @foreach($response->getBanks() as $bank)
                                        <option label="{{ $bank->name }}"
                                                @if($bank->name === $configuration->getEnableBankingBank())selected="selected"@endif
                                                value="{{ $bank->name }}">{{ $bank->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

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

            <div class="row mt-3 bank-selected" x-show="'XX' !== selectedCountry && 'XX' !== selectedBank">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            Number of days
                        </div>
                        <div class="card-body">
                            <div class="form-group row">
                                <label for="days" class="col-sm-3 col-form-label">Number of days</label>
                                <div class="col-sm-9">
                                    <input name="days" class="form-control" step="1" min="1" max="1000" type="number"
                                           value="90"/>
                                    <small class="form-text text-muted">
                                        How many days of transaction history to import. Most banks support up to 90 days.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3 submit-button" x-show="'XX' !== selectedCountry && 'XX' !== selectedBank">
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
                            <a href="{{ route('authenticate-flow.index', ['eb']) }}" class="btn btn-secondary"><span
                                    class="fas fa-arrow-left"></span> Go back to authentication</a>
                            <a href="{{ route('flush') }}" class="btn btn-danger text-white btn-sm"><span
                                    class="fas fa-redo-alt"></span> Start over</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('scripts')
    @vite(['src/pages/selection/enablebanking.js'])
@endsection
