@extends('layout.v2')
@section('content')
    <div class="container" x-data="index">
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <h1>Firefly III Data Importer, v{{ $version }}</h1>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        Firefly III Data Importer, v{{ $version }}
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            Welcome! This tool will help you import data into Firefly III. You can find instructions in
                            the <a href="https://docs.firefly-iii.org/" target="_blank">documentation</a>. Any links you
                            see to the documentation will open in a <em>new</em> window or tab.
                        </p>
                        <p>
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
                        Configuration / connection error :(
                    </div>
                    <div class="card-body">
                        <p>The importer could not connect to Firefly III.
                            Please remedy the error below first, and check out the <a
                                href="https://docs.firefly-iii.org/references/faq/data-importer/general/"
                                target="_blank">
                                documentation</a> if necessary.</p>
                        <p class="text-danger" x-text="pageProperties.connectionErrorMessage"></p>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('index.post') }}/" method="post">
            <input type="hidden" name="_token" value="{{ csrf_token() }}"/>
            <div class="row" style="margin-top:1em;" id="importers">
                <div class="col-lg-10 offset-lg-1">
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    Import a file
                                </div>
                                <div class="card-body">
                                    <button x-show="loadingFunctions.file" class="btn btn-info disabled" value="file" name="flow" disabled="disabled"
                                            ><span class="fas fa-cog fa-spin"></span></button>
                                    <button x-show="!loadingFunctions.file && importFunctions.file" class="btn btn-info" value="file" name="flow"
                                            >Import file</button>
                                    <button x-show="!loadingFunctions.file && !importFunctions.file" class="btn text-white btn-danger disabled"  value="file" name="flow"
                                            disabled
                                    ><em class="fa-solid fa-face-dizzy"></em></button>

                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    Import from GoCardless
                                </div>
                                <div class="card-body">
                                    <p class="text-danger" x-text="errors.gocardless" x-show="'' !== errors.gocardless"></p>
                                    <button x-show="loadingFunctions.gocardless" class="btn btn-info disabled" value="nordigen" name="flow" disabled="disabled"
                                    ><span class="fas fa-cog fa-spin"></span></button>
                                    <button x-show="!loadingFunctions.gocardless && importFunctions.gocardless" class="btn btn-info" value="nordigen" name="flow"
                                    >Import from GoCardless</button>
                                    <button x-show="!loadingFunctions.gocardless && !importFunctions.gocardless" class="btn text-white btn-danger disabled"  value="nordigen" name="flow"
                                            disabled
                                    ><em class="fa-solid fa-face-dizzy"></em></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    Import from Spectre
                                </div>
                                <div class="card-body">
                                    <p class="text-danger" x-text="errors.spectre" x-show="'' !== errors.spectre"></p>
                                    <button x-show="loadingFunctions.spectre" class="btn btn-info disabled" value="spectre" name="flow" disabled="disabled"
                                    ><span class="fas fa-cog fa-spin"></span></button>
                                    <button x-show="!loadingFunctions.spectre && importFunctions.spectre" class="btn btn-info" value="spectre" name="flow"
                                    >Import from Spectre</button>
                                    <button x-show="!loadingFunctions.spectre && !importFunctions.spectre" class="btn btn-danger text-white disabled"  value="spectre" name="flow"
                                            disabled
                                    ><em class="fa-solid fa-face-dizzy"></em></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="row" style="margin-top:1em;" id="importers">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        Extra information
                    </div>
                    <div class="card-body">
                        <p>
                            If you change your settings, you may need to press <strong>start over</strong> for the
                            settings to be recognized.
                            If you are in doubt if the button works: your session identifier is "{{ $identifier }}" and
                            should change every time you
                            press the @if(!$isDocker)button,@else button or restart the container,@endif
                             but it
                            has to stay the same when you simply refresh the page.
                        </p>
                        <p>
                            <a class="btn btn-danger text-white btn-sm" href="{{ route('flush') }}" data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               title="This button resets your progress">Start over</a>
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
