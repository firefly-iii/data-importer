@extends('layout.v2')
@section('content')
    <div class="container">
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <h1>{{ $mainTitle }}</h1>
            </div>
        </div>
        <form method="post" action="{{ route('011-connections.post') }}" accept-charset="UTF-8" id="store">
            <input type="hidden" name="_token" value="{{ csrf_token() }}"/>
            <div class="row mt-3">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            {{ $subTitle }}
                        </div>
                        <div class="card-body">
                            <p>Select the connection to use or make a new connection</p>
                            <p>
                                Spectre creates connections; a representation of the connection to your financial
                                institution.
                                Select below which one the importer must use, or opt to create a new connection if no
                                connections are visible.
                                Please read
                                <a href="https://docs.firefly-iii.org/"
                                   target="_blank"> the documentation for this page</a> if you want to know more.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-lg-10 offset-lg-1">
                    <div class="card">
                        <div class="card-header">
                            Connections
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                <tr>
                                    <th>&nbsp;</th>
                                    <th>Bank</th>
                                    <th>Last used</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($list as $item)
                                <tr>
                                    <td>
                                        <input
                                        id="{{ $item->id }}"
                                        type="radio"
                                        name="spectre_connection_id"
                                        value="{{ $item->id }}"

                                        @if('disabled' === $item->status) disabled @endif
                                        @if($configuration->getConnection() === $item->id) checked @endif
                                        @if(1 === count($list)) checked @endif
                                        >
                                    </td>
                                    <td>
                                        <label for="{{ $item->id }}">
                                            {{ $item->providerName }} ({{ $item->countryCode }})
                                        </label>
                                    </td>
                                    <td>
                                        Last success: {{ $item->lastSuccess->format("Y-m-d H:i:s") }}<br>
                                        Updated at: {{ $item->updatedAt->format("Y-m-d H:i:s") }}<br>
                                    </td>
                                    <td>
                                        {{ $item->status }}
                                    </td>
                                </tr>
                                @endforeach
                                <tr>
                                    <td>
                                        <input id="new_login" type="radio" name="spectre_connection_id" value="00"
                                               @if(0 === $configuration->getConnection()) checked @endif
                                               >
                                    </td>
                                    <td colspan="3">
                                        <label for="new_login"><em>
                                                Create a new connection
                                            </em>
                                        </label>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-primary float-end">Submit &rarr;</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end of selection -->
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
        </form>
    </div>
@endsection
@section('scripts')
@endsection
