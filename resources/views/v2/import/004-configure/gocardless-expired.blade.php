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
                            It looks like the connection to your bank as expired. It has been removed from your configuration, and this page will reload momentarily.
                        </p>
                        <p>
                            You will then get the opportunity to reconnect to your bank.
                        </p>
                        <noscript>
                            <p>
                                <a href="{{$redirect }}">Please follow this link to continue</a>.
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
                window.location.href = '{{ $redirect  }}';
            }, 2000);
        };
    </script>
@endsection
