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
                        <p>In order to import using {{ config('importer.flow_titles' . $flow) }} you must enter the authentication data you received from this provider. You can read how to get the necessary codes in the <a target="_blank" href="https://docs.firefly-iii.org/how-to/data-importer/import/third-party-providers/">documentation</a></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        Form
                    </div>
                    <div class="card-body">
                        @if('' !== ($error ?? ''))
                            <p class="text-danger">{{ $error }}</p>
                        @endif

                        <form method="post" action="{{ route('authenticate-flow.post', [$flow]) }}" accept-charset="UTF-8">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}"/>

                            @foreach($data as $key => $value)
                                <div class="form-group row">
                                    <label for="date" class="col-sm-3 col-form-label">{{ trans(sprintf('import.label_%s_%s', $flow, $key)) }}</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="{{ sprintf('%s_%s', $flow, $key) }}" class="form-control" id="{{ sprintf('%s_%s', $flow, $key) }}"
                                               placeholder="{{ trans(sprintf('import.placeholder_%s_%s', $flow, $key)) }}" value="{{ $value }}" aria-describedby="{{ sprintf('%s_%s', $flow, $key) }}_help">
                                        <small id="{{ sprintf('%s_%s', $flow, $key) }}_help" class="form-text text-muted">
                                            {{ trans(sprintf('import.help_%s_%s', $flow, $key)) }}
                                        </small>
                                    </div>
                                </div>

                            @endforeach
                            <button type="submit" class="float-end btn btn-primary">Authenticate &rarr;</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-body">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('back.start') }}" class="btn btn-secondary"><span
                                    class="fas fa-arrow-left"></span> Go back to index</a>
                            <a href="{{ route('flush') }}" class="btn btn-danger text-white btn-sm"><span
                                    class="fas fa-redo-alt"></span> Start over</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
