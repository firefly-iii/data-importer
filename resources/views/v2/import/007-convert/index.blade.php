@extends('layout.v2')
@section('content')

    <!-- another tiny hack to get data from a to b -->
    <span id="data-helper" data-flow="{{ $flow }}" data-identifier="{{ $identifier }}" data-url="{{ $nextUrl }}"></span>

    <div class="container" x-data="index">
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <h1>{{ $mainTitle }}</h1>
            </div>
        </div>
        <div id="app">
    <div class="row mt-3">
        <div class="col-lg-10 offset-lg-1">
            <div class="card">
                <div class="card-header">
                    Data conversion
                </div>
                <!-- show start of process button -->
                <div x-show="showStartButton()" class="card-body">
                    <p>
                        The first step in the import process is a <strong>conversion</strong>.
                        <span x-show="'file' === flow">The CSV file you uploaded</span>
                        <span x-show="'nordigen' === flow">The transactions downloaded from GoCardless</span>
                        <span x-show="'spectre' === flow">The transactions downloaded from Spectre</span>
                        will be converted to Firefly III compatible transactions. Please press <strong>Start
                            job</strong> to start.
                    </p>
                    <p>
                        <button class="btn btn-success float-end text-white" type="button" @click="startJobButton">Start job
                            &rarr;
                        </button>
                    </p>
                </div>
                <div x-show="showWaitingButton()" class="card-body">
                    <p><span class="fas fa-cog fa-spin"></span> Please wait for the job to start..</p>
                </div>
                <div x-show="showTooManyChecks()" class="card-body">
                    <p>
                        <em class="fa-solid fa-face-dizzy"></em>
                        The data importer has been polling for more than <span x-text="checkCount"></span> seconds. It has stopped, to prevent eternal loops.</p>
                </div>
                <div x-show="showPostError()" class="card-body">
                    <p class="text-danger">
                        The conversion could not be started, or failed due to an error. Please check the log files.
                        Sorry about this :(
                    </p>
                    <p x-show="'' !== post.result" x-text="post.result"></p>
                    <x-conversion-messages />
                </div>

                <div x-show="showWhenRunning()" class="card-body">
                    <p>
                        <span class="fas fa-cog fa-spin"></span> The conversion is running, please wait. Messages may appear below the progress bar.
                    </p>
                    <div class="progress">
                        <div aria-valuemax="100" aria-valuemin="0"
                             aria-valuenow="100" class="progress-bar progress-bar-striped progress-bar-animated"
                             role="progressbar" style="width: 100%"></div>
                    </div>
                    <x-conversion-messages />
                </div>
                <div x-show="showWhenDone()" class="card-body">
                    <p>
                        <span class="fas fa-sync fa-spin"></span> The conversion routine has finished 🎉. Please wait to be redirected!
                    </p>
                    <x-conversion-messages />
                </div>
                <div x-show="showIfError()" class="card-body">
                    <p class="text-danger">
                        The conversion could not be started, or failed due to an error. Please check the log files.
                        Sorry about this :(
                    </p>
                    <x-conversion-messages />
                </div>
            </div>
        </div>
    </div>

        </div>

        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-body">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ $jobBackUrl }}" class="btn btn-secondary"><span class="fas fa-arrow-left"></span>
                                Go back to the previous step</a>
                            <a class="btn btn-danger text-white btn-sm" href="{{ route('flush') }}" data-bs-toggle="tooltip"
                               data-bs-placement="top" title="If the conversion seems stuck, you can reset it."><span
                                    class="fas fa-redo-alt"></span> Start over</a>
                            <a class="btn btn-info text-white btn-sm" href="{{ route('004-configure.download') }}"
                               data-bs-toggle="tooltip" data-bs-placement="top"
                               title="You can download a configuration file of your import, so you can make a quick start the next time you import.">
                                <span class="fas fa-download"></span> Download configuration file
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </div>
@endsection
@section('scripts')
    @vite(['src/pages/conversion/index.js'])
@endsection
