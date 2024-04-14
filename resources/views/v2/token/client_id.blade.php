@extends('layout.v2')
@section('content')
    <div class="container">
        <div class="row mt-4">
            <div class="col-lg-10 offset-lg-1">
                <h1>Firefly III Data Import Tool,
                    @if(str_starts_with($version, 'develop'))
                        {{ $version }}
                    @else
                        v{{ $version }}
                    @endif

                </h1>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        Authenticate with Firefly III
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            Welcome! This tool will help you import data into Firefly III.
                        </p>
                        <p>
                            This tool is sparsely documented, you can find all the details you need
                            in the <a href="https://docs.firefly-iii.org/" target="_blank">
                                documentation</a>. Any links you see to the docs will open in a new window or tab.
                        </p>
                        <p class="card-text">
                            @if('' !== (string)$baseUrl)
                                In order to get access to your Firefly III installation at <a
                                href="{{ $baseUrl }}">{{ $baseUrl }}</a>
                                @if('' !== (string)$vanityUrl)
                                    (<a href="{{ $vanityUrl }}">{{ $vanityUrl }}</a>)
                                @endif
                                , you will need to submit a valid Client ID. This is a number.
                            @else
                                In order to get access to your Firefly III installation, you will need to submit a valid Client ID. This is a number.
                            @endif
                        </p>
                        <p>
                            @if('' !== (string)$baseUrl)
                                If you don't have one, you must create <a href="{{ $baseUrl }}/profile">in your
                                profile</a> under "OAuth". Make sure you
                                <em>remove</em> the checkbox under "Confidential".
                            @else
                                If you don't have one, you must create in your profile under "OAuth". Make sure you
                                <em>remove</em> the checkbox under "Confidential".
                            @endif
                        </p>
                        <p>
                            The callback URL for this installation is<br> <code>{{ route('token.callback') }}</code>
                        </p>
                        @foreach($errors->all() as $error)
                            <p class="text-danger">{{ $error }}</p>
                        @endforeach

                        <form action="{{ route('token.submitClientId') }}" method="POST">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}"/>
                            @if('' === (string)$baseUrl)
                                <div class="form-group mb-3">
                                    <label for="input_base_url">Firefly III URL</label>
                                    <input type="url" placeholder="https://" value="{{ $baseUrl }}" class="form-control" id="input_base_url" autocomplete="off" name="base_url">
                                    @if($errors->has('base_url'))
                                        <span class="text-danger">{{ $errors->first('base_url') }}</span>
                                    @endif
                                    @if(session()->has('secure_url'))
                                        <span class="text-danger">{{ session()->get('secure_url') }}</span>
                                    @endif
                                </div>
                            @endif
                            <div class="form-group mb-3">
                                <label for="input_client_id">Client ID</label>
                                <input type="number" step="1" min="1" class="form-control" id="input_client_id" autocomplete="off" name="client_id" value="{{ $clientId }}">
                                @if($errors->has('client_id'))
                                    <span class="text-danger">{{ $errors->first('client_id') }}</span>
                                @endif
                            </div>
                            <input type="submit" name="submit" value="Submit" class="float-end text-white btn btn-success"/>
                        </form>

                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-body">
                        <p>
                            <a class="btn btn-danger text-white btn-sm" href="{{ route('flush') }}" data-bs-toggle="tooltip"
                               data-bs-placement="top" title="This button resets your progress">Start over</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
