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
                        <p>
                            Make sure you map at least, or the import will fail:
                        </p>
                        <ul>
                            <li>Amount</li>
                            <li>Transaction date</li>
                        </ul>
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
                                @if($configuration->hasPseudoIdentifier())
                                    @php
                                        $pseudoIdentifier = $configuration->getPseudoIdentifier();
                                    @endphp
                                    <tr style="background-color: #f8f9fa;">
                                        <td>
                                            <strong>{{ __('import.pseudo_identifier_label') }}</strong>
                                            <br/>
                                            <span class="badge bg-info text-white">{{ __('import.pseudo_identifier_badge') }}</span>
                                            <br/>
                                            <span class="text-muted small">
                                                {{ __('import.pseudo_identifier_combines') }} {{ implode(', ', $pseudoIdentifier['source_columns']) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if(count($pseudoExamples) > 0)
                                                @foreach($pseudoExamples as $example)
                                                    @if($example['hashed'] !== null)
                                                        <pre style="color:#999;margin-bottom:0;font-size:0.85em;">{{ $example['raw'] }}</pre>
                                                        <pre style="color:#e83e8c;margin-bottom:0;">{{ $example['hashed'] }}</pre>
                                                    @else
                                                        <pre style="color:#e83e8c;margin-bottom:0;">{{ $example['raw'] }}</pre>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </td>
                                        <td>
                                            <p class="form-text">
                                                <span class="text-muted small">{{ __('import.pseudo_identifier_locked_to') }}</span>
                                                <code class="small">{{ $pseudoIdentifier['role'] ?? 'unknown' }}</code>
                                            </p>
                                            <input type="hidden" name="pseudo_role" value="{{ $pseudoIdentifier['role'] ?? '' }}"/>
                                        </td>
                                        <td>
                                            &nbsp;
                                        </td>
                                    </tr>
                                @endif
                                @foreach($columns as $index => $column)
                                @php
                                    // Check if this column is part of the pseudo identifier (for display purposes)
                                    $isInPseudoIdentifier = false;
                                    if ($configuration->hasPseudoIdentifier()) {
                                        $pseudoIdentifier = $configuration->getPseudoIdentifier();
                                        if (in_array($index, $pseudoIdentifier['source_columns'] ?? [], true)) {
                                            $isInPseudoIdentifier = true;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        {{ $column }}
                                        @if($isInPseudoIdentifier)
                                        <br/>
                                        <span class="badge bg-secondary">{{ __('import.pseudo_identifier_used_in') }}</span>
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
                                        <select name="roles[{{ $index }}]" id="roles_{{ $index }}"
                                                class="form-control">
                                            @foreach($roles as $key => $role)
                                            <option value="{{ $key }}"
                                                    @if(($configuredRoles[$index] ?? false) === $key) selected @endif
                                                    label="{{ __('import.column_' . $key) }}">{{ __('import.column_'. $key) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <label for="do_mapping_{{ $index }}">
                                            <input type="checkbox"
                                                   @if($configuredDoMapping[$index] ?? false) checked @endif
                                                   name="do_mapping[{{ $index }}]" id="do_mapping_{{ $index }}"
                                                   value="1"/>
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
