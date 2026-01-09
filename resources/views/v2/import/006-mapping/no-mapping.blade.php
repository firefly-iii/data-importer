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
                            There is no data in your import that can be mapped to Firefly III data, so the data importer will now redirect you
                            to the next import step. Please wait to be redirected.
                        </p>
                        <noscript>
                            <p>
                                <a href="{{ $redirect }}">Please follow this link to continue</a>.
                            </p>
                        </noscript>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script type="text/javascript">
        window.onload = function () {
            setTimeout(function () {
                window.location.href = '{{ $redirect }}';
            }, 3000);
        };
    </script>
@endsection
