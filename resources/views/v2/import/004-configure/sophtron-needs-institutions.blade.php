@extends('layout.v2')
@section('content')
    <div class="container">
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
                        <p>Hi there!</p>
                        <p>
                            It looks like you have not yet connected any institutions to your Sophtron account.
                            Without them, the importer cannot continue.
                        </p>
                        <p>
                            Please visit this <strong><a href="https://sophtron.com/widgets-demo" target="_blank">Sophtron Widget Page</a></strong>
                            and connect to your bank or financial institution.
                        </p>
                        <p>
                            Once you have done so, press the button below and try again.
                        </p>
                        <p>
                            <a class="btn btn-primary" href="{{ route('index') }}">Try again</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')

@endsection
