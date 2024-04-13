@extends('layout.v2')
@section('content')
    <div class="container">
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <h1>{{ $mainTitle }}</h1>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        {{ $subTitle }}
                    </div>
                    <div class="card-body">
                        <p>
                            Set up the meaning of each column in your file.
                        </p>
                        <p>
                            Each column in your importable file has a role, it contains a specific type of content.
                            By configuring these roles here, you tell the importer how to approach and treat
                            the data in each column. <a target="_blank"
                                                        href="https://docs.firefly-iii.org/references/data-importer/roles/">Read
                                the documentation</a> to learn more
                            about this process.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @if(!$errors->isEmpty())
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-header">
                        Errors :(
                    </div>
                    <div class="card-body">
                        <p class="text-danger">Some error(s) occurred:</p>
                        <ul>
                            @foreach($errors->all() as $error)
                            <li class="text-danger">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="row mt-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        Role configuration
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('005-roles.post') }}" accept-charset="UTF-8">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}"/>
                            <table class="table">
                                <tr>
                                    <th>Column</th>
                                    <th>Example data</th>
                                    <th>Role</th>
                                    <th>Map data?</th>
                                </tr>
                                @foreach($columns as $index => $column)
                                <tr>
                                    <td>{{ $column }}
                                        @if($index === $configuration->getUniqueColumnIndex() && 'cell' === $configuration->getDuplicateDetectionMethod())
                                        <br/>
                                        <span class="text-muted small">This is the unique column</span>
                                        @endif
                                        </td>
                                    <td>
                                        @if(count($examples[$index] ?? []) > 0)
                                            @foreach($examples[$index] as $example)
                                                <pre style="color:#e83e8c;margin-bottom:0;">{{ $example }}</pre>
                                          @endforeach
                                        @endif
                                    </td>
                                    <td>
                                        @if($index === $configuration->getUniqueColumnIndex() && 'cell' === $configuration->getDuplicateDetectionMethod())
                                        <p class="form-text">
                                                    <span class="text-muted small">
                                                        This column is your unique identifier, so it will be fixed to
                                                    </span>
                                            <code class="small">{{ $configuration->getUniqueColumnType() }}</code>
                                        </p>
                                        <input type="hidden" name="roles[{{ $index }}]"
                                               value="{{ $configuration->getUniqueColumnType() }}"/>
                                        @else
                                        <select name="roles[{{ $index }}]" id="roles_{{ $index }}"
                                                class="form-control">
                                            @foreach($roles as $key => $role)
                                            <option value="{{ $key }}"
                                                    @if(($configuredRoles[$index] ?? false) === $key) selected @endif
                                                    label="{{ __('import.column_' . $key) }}">{{ __('import.column_'. $key) }}</option>
                                            @endforeach
                                        </select>
                                        @endif
                                    </td>
                                    <td>
                                        <label for="do_mapping_{{ $index }}">
                                            {{--  reverse if statement is pretty sloppy but OK. --}}
                                            @if($index === $configuration->getUniqueColumnIndex() && 'cell' === $configuration->getDuplicateDetectionMethod())
                                            &nbsp;
                                            @else
                                            <input type="checkbox"
                                                   @if($configuredDoMapping[$index] ?? false) checked @endif
                                                   name="do_mapping[{{ $index }}]" id="do_mapping_{{ $index }}"
                                                   value="1"/>
                                            @endif
                                        </label>
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                            <button type="submit" class="float-end btn btn-primary">Submit &rarr;</button>
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
                            <a href="{{ route('back.config') }}" class="btn btn-secondary"><span
                                    class="fas fa-arrow-left"></span> Go back to configuration</a>
                            <a href="{{ route('flush') }}" class="btn btn-danger text-white btn-sm"><span
                                    class="fas fa-redo-alt"></span> Start over</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
