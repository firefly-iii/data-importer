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
                            Your configuration file needs some parsing. Please wait and do not refresh this page. It will disappear as soon as
                            you have read it.
                        </p>
                        <noscript>
                            <p>
                                <a href="{{ route('configure-import.index', [$identifier]) }}?parse=true">Please follow
                                    this link to continue</a>.
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
                window.location.href = '{{ route('configure-import.index', [$identifier]) }}?parse=true';
            }, 2000);
        };
    </script>
@endsection
