@extends('layout.v2')
@section('content')
    <div class="container" x-data="index">
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <h1>Firefly III Data Importer,
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
                        Firefly III Data Importer,
                        @if(str_starts_with($version, 'develop'))
                            {{ $version }}
                        @else
                            v{{ $version }}
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            Welcome! This tool will help you import data into Firefly III. You can find instructions in
                            the <a href="https://docs.firefly-iii.org/" target="_blank">documentation</a>. Any links you
                            see to the documentation will open in a <em>new</em> window or tab.
                            To import data, you need to authenticate with Firefly III, and optionally with one of the
                            data sources this importer supports.
                        </p>
                        @if($pat)
                            <p id="firefly_expl">
                                You're using a Personal Access Token to <span class="text-info">authenticate</span> to
                                Firefly III.
                            </p>
                        @endif
                        @if($clientIdWithURL)
                            <p id="firefly_expl">
                                You're using a fixed Client ID and a fixed Firefly III URL to <span class="text-info">authenticate</span>
                                to Firefly III.
                            </p>
                        @endif
                        @if($URLonly)
                            <p id="firefly_expl">
                                You're using a Client ID and a fixed Firefly III URL to <span
                                    class="text-info">authenticate</span> to Firefly III.
                            </p>
                        @endif
                        @if($flexible)
                            <p id="firefly_expl">
                                You're using a self-submitted Client ID and Firefly III URL to <span class="text-info">authenticate</span>
                                to Firefly III.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="row" style="margin-top:1em;" x-show="pageProperties.connectionError">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        Configuration or connection error :(
                    </div>
                    <div class="card-body">
                        <p>The importer could not connect to Firefly III. Please remedy the error below first, and check
                            out the <a href="https://docs.firefly-iii.org/references/faq/data-importer/general/"
                                       target="_blank">documentation</a> if necessary.</p>
                        <p class="text-danger" x-text="pageProperties.connectionErrorMessage"></p>
                    </div>
                </div>
            </div>
        </div>
        @if('' !== $warning)
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="alert alert-warning" role="alert">
                    {!! $warning !!}
                </div>
            </div>
        </div>
        @endif
            {{--
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        Existing and historical import jobs
                    </div>
                    <div class="card-body">
                        <p>
                            This table shows you historical and current import jobs.
                            <span class="text-warning">This table does not work yet, stay tuned!</span>
                        </p>
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th>Identifier</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>More details</th>
                            </tr>
                            </thead>
                            <tbody>
                            <template x-if="false === loading && 0 === importJobs.length">
                                <tr>
                                    <td colspan="4"><em>There are no existing import jobs.</em></td>
                                </tr>
                            </template>
                            <template x-if="true === loading && 0 === importJobs.length">
                                <tr>
                                    <td colspan="4"><em>Please wait while the disk is inspected for existing import jobs.</em></td>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        --}}
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        Create a new import job
                    </div>
                    <div class="card-body">
                        <p>
                            To start importing data into Firefly III, select your data source below and press the [Start] button.
                        </p>
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th style="width:33%;">Import data souce</th>
                                <th style="width:33%;">Availability</th>
                                <th>Button!</th>
                            </tr>
                            </thead>
                            <tbody>
                            <template x-for="flow in importFlows">
                                <tr>
                                    <td><span x-text="flow.title"></span>
                                        <span x-show="'' !== flow.explanation">
                                            <span>
                                        <br>
                                        <small class="text-muted" x-text="flow.explanation"></small>
                                                </span>
                                        </span>
                                    </td>
                                    <td>
                                        <span x-show="flow.loading" class="fas fa-cog fa-spin"></span>
                                        <small x-show="!flow.error && !flow.loading && !flow.enabled" class="text-danger">Not available yet.</small>
                                        <span  x-show="!flow.error && !flow.loading &&  flow.enabled && !flow.authenticated" class="text-primary">Needs authentication details</span>
                                        <span  x-show="!flow.error && !flow.loading &&  flow.enabled &&  flow.authenticated" class="text-success">Available</span>
                                        <span  x-show="flow.error" class="text-danger" x-text="flow.errorMessage"></span>
                                    </td>
                                    <td>
                                        <span x-show="flow.loading" class="fas fa-cog fa-spin"></span>
                                        <a x-show="!flow.error && !flow.loading && true === flow.enabled && false === flow.authenticated" :href="'{{ route('authenticate-flow.index', ['']) }}/' + flow.key" class="btn btn-sm btn-primary">Authenticate</a>
                                        <a x-show="!flow.error && !flow.loading && true === flow.enabled && flow.authenticated" :href="'{{ route('new-import.index', ['']) }}/' + flow.key" class="btn btn-sm btn-success">Start</a>
                                    </td>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="row" style="margin-top:1em;" id="importers">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        Extra information
                    </div>
                    <div class="card-body">
                        <p>If you change your settings, you may need to press <strong>start over</strong> for the
                            settings to be recognized. If you are in doubt if the button works: your session identifier
                            is "{{ $identifier }}" and should change every time you
                            press the @if(!$isDocker)
                                button,
                            @else
                                button or restart the container,
                            @endif but it has to stay the same when you simply refresh the page.
                        </p>
                        <p>
                            <a class="btn btn-danger text-white btn-sm" href="{{ route('flush') }}"
                               data-bs-toggle="tooltip" data-bs-placement="top"
                               title="This button resets your progress">Start over</a>
                            <a class="btn btn-secondary btn-sm" onclick="window.location.reload(true)">Only refresh the
                                page</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('scripts')
    @vite(['src/pages/index/index.js'])
@endsection
