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
                            According to your configuration file, you do not wish to re-configure this import job.
                            You will be redirected in five (5) seconds. You don't need to do anything.
                        </p>
                            <p>
                                <a class="btn btn-primary" href="{{ route('configure-import.index', [$identifier]) }}?do_not_skip=true">
                                    Wait, I do want to configure my import job!
                                </a>
                            </p>
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
                window.location.href = '#?dont_know_where_to_go=true';
            }, 5000);
        };
    </script>
@endsection
