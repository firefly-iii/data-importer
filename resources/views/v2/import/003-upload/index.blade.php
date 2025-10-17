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
                        @if('file' === $flow)
                            <p>
                                The first step of your data import is that you upload your data file.
                            </p>
                        @endif
                        @if('file' !== $flow)
                            <p>
                            The first (optional) step of your data import is that you upload a configuration file
                            from a previous run. If this is the first time ever you import data, this is obviously not possible and
                            you can skip this step. In a next step, you will be offered a configuration file that you can use here to make it easier for yourself.
                            </p>
                        @endif
                        <p>
                            Use the form elements below to upload your data.
                            If you need support, <a target="_blank"
                                                    href="https://docs.firefly-iii.org/how-to/">check
                                out the documentation</a>.
                        </p>
                        <p>
                            A configuration file is entirely <strong>optional</strong>. You can use it to pre-configure
                            the import options. In a later stage you may even use it for automation.
                            It will be generated for you by the data importer so you can download it.
                        </p>
                        @if('simplefin' === $flow)
                        <p>
                            If your configuration already contains an encrypted SimpleFIN access URL, you do not need to fill in the "SimpleFIN token" field. If you are unsure,
                            using the SimpleFIN token field will overrule whatever (if any) access URL is in your configuration file.
                        </p>
                        <p>
                            <strong>Demo Mode:</strong> You can use demo mode to test the import process with sample data before connecting your real financial accounts.
                            Simply check the "Use demo mode" option below.
                        </p>
                        @endif
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
                        <form method="post" action="{{ route('003-upload.upload') }}" accept-charset="UTF-8" id="store"
                              enctype="multipart/form-data">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}"/>

                            <!-- SimpleFIN options -->
                            @if('simplefin' === $flow)
                                @include('import.003-upload.partials.simplefin')
                            @endif

                            <!-- Importable FILE -->
                            @if('file' === $flow)
                                @include('import.003-upload.partials.file')
                            @endif

                            <!-- Configuration file (for all flows)  -->
                            @include('import.003-upload.partials.config')

                            <!-- Pre-made configuration file(s) -->
                            @include('import.003-upload.partials.premade-config')

                            <div class="row">
                                <div class="col-lg-12">
                                    <button type="submit" class="float-end btn btn-primary">Next &rarr;</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('back.start') }}" class="btn btn-secondary"><span
                                    class="fas fa-arrow-left"></span> Go back to
                                index</a>
                            <a href="{{ route('flush') }}" class="btn btn-danger text-white"><span class="fas fa-redo-alt"></span>
                                Start over entirely</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
