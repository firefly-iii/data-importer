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

                            <!-- SimpleFIN fields -->
                            @if('simplefin' === $flow)
                                <!-- Demo Mode Toggle -->
                                <div class="form-group row mb-3">
                                    <label for="use_demo" class="col-sm-4 col-form-label">Demo Mode</label>
                                    <div class="col-sm-8">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="use_demo" name="use_demo" value="1">
                                            <label class="form-check-label" for="use_demo">
                                                Use demo mode (test with sample data)
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">
                                            Enable this to test the import process with SimpleFIN demo data.
                                        </small>
                                    </div>
                                </div>

                                <!-- Connection Error Display -->
                                @if($errors->has('connection'))
                                    <div class="alert alert-danger" role="alert">
                                        <strong>Connection Error:</strong> {{ $errors->first('connection') }}
                                    </div>
                                @endif

                                <!-- SimpleFIN token -->
                                <div class="form-group row mb-3" id="token-group">
                                    <label for="simplefin_token" class="col-sm-4 col-form-label">SimpleFIN token</label>
                                    <div class="col-sm-8">
                                        <input type="text"
                                               class="form-control
                                           @if($errors->has('simplefin_token')) is-invalid @endif"
                                               id="simplefin_token" name="simplefin_token"
                                               autocomplete="off"
                                               value="{{ old('simplefin_token') }}"
                                               placeholder="SimpleFIN token"/>
                                        @if($errors->has('simplefin_token'))
                                            <div class="invalid-feedback">
                                                {{ $errors->first('simplefin_token') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- SimpleFIN CORS Origin URL (Additional Options) -->
                                <div class="form-group row mb-3" id="bridge-url-group">
                                    <label for="simplefin_bridge_url" class="col-sm-4 col-form-label">
                                        CORS Origin URL
                                        <small class="text-muted">(optional)</small>
                                    </label>
                                    <div class="col-sm-8">
                                        <input type="url"
                                               class="form-control
                                           @if($errors->has('simplefin_bridge_url')) is-invalid @endif"
                                               id="simplefin_bridge_url" name="simplefin_bridge_url"
                                               value="{{ $simpleFinOriginUrl  }}"
                                               autocomplete="off"
                                               placeholder="https://your-app.example.com"/>
                                        <small class="form-text text-muted">
                                            Enter the URL where you access this Firefly III Data Importer (e.g., https://your-domain.com). Leave blank if unsure.
                                        </small>
                                        @if($errors->has('simplefin_bridge_url'))
                                            <div class="invalid-feedback">
                                                {{ $errors->first('simplefin_bridge_url') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const demoCheckbox = document.getElementById('use_demo');
                                    const bridgeUrlGroup = document.getElementById('bridge-url-group');
                                    const tokenGroup = document.getElementById('token-group');

                                    function toggleSimpleFINFields() {
                                        if (demoCheckbox.checked) {
                                            bridgeUrlGroup.style.display = 'none';
                                            tokenGroup.style.display = 'none';
                                        } else {
                                            bridgeUrlGroup.style.display = 'flex';
                                            tokenGroup.style.display = 'flex';
                                        }
                                    }

                                    // Initial state
                                    toggleSimpleFINFields();

                                    // Listen for changes
                                    demoCheckbox.addEventListener('change', toggleSimpleFINFields);
                                });
                                </script>

                            @endif

                            <!-- importable FILE -->
                            @if('file' === $flow)
                            <div class="form-group row mb-3">
                                <label for="importable_file" class="col-sm-4 col-form-label">Importable file</label>
                                <div class="col-sm-8">
                                    <input type="file"
                                           class="form-control
                                           @if($errors->has('importable_file')) is-invalid @endif"
                                           id="importable_file" name="importable_file"
                                           placeholder="Importable file"
                                           accept=".xml,.csv"/>
                                    @if($errors->has('importable_file'))
                                    <div class="invalid-feedback">
                                        {{ $errors->first('importable_file') }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @endif

                            <!-- CONFIG FILE  -->
                            <div class="form-group row mb-3">
                                <label for="config_file" class="col-sm-4 col-form-label">Optional configuration
                                    file</label>
                                <div class="col-sm-8">
                                    <input type="file" class="form-control" id="config_file" name="config_file"
                                           placeholder="Configuration file"
                                           accept=".json"/>
                                </div>
                            </div>

                            <!-- PRE MADE CONFIG FILE -->
                            @if(count($list) > 0)
                            <div class="form-group row mb-3">
                                <label for="config_file" class="col-sm-4 col-form-label">Pre-made configuration
                                    file</label>
                                <div class="col-sm-8">
                                    <select class="form-control" name="existing_config">
                                        <option value="" label="Upload or manual config">Upload or manual config
                                        </option>
                                        @foreach($list as $file)
                                        <option value="{{ $file }}" label="{{ $file }}">{{ $file }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @endif
                            <div class="row">
                                <div class="col-lg-12">
                                    <!-- continue -->
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

    @if('simplefin' === $flow)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const demoCheckbox = document.getElementById('use_demo');
            const tokenGroup = document.getElementById('token-group');
            const tokenInput = document.getElementById('simplefin_token');

            function toggleDemoMode() {
                if (demoCheckbox.checked) {
                    tokenGroup.style.display = 'none';
                    tokenInput.required = false;
                } else {
                    tokenGroup.style.display = 'flex';
                    //tokenInput.required = true;
                }
            }

            demoCheckbox.addEventListener('change', toggleDemoMode);

            // Initial state
            toggleDemoMode();
        });
    </script>
    @endif
@endsection
