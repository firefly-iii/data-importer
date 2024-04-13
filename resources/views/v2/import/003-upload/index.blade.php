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
                            the import options. In a later
                            stage you may even use it for automation.
                        </p>
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
@endsection
