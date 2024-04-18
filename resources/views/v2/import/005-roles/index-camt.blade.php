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
                            CAMT files feature "layers", where each layer has its own content.
                            Your options per field may be limited. Firefly III will not be able to
                            store <em>all</em> content of a CAMT file. If you feel your choices are too limited, please
                            open <a href="https://github.com/firefly-iii/firefly-iii/issues">an issue on GitHub</a>.
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

                            @foreach($levels as $key => $level)
                                <h4>Level {{ $key }}: {{ $level['title'] }}</h4>
                                <p>
                                    {{ $level['title'] }}: {{ $level['explanation'] }}
                                </p>
                            @if(count($level['fields']) > 0)
                                <table class="table">
                                    <thead>
                                    <tr>
                                        <th style="width:30%;">Field</th>
                                        <th style="width:30%;">Example data</th>
                                        <th>Firefly III role</th>
                                        <th style="width:10%">Map data?</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($level['fields'] as $field)
                                        @if(true === ($field['section'] ?? false))
                                                <tr>
                                                    <th colspan="4">{{ __('camt.section_'. $field['title']) }}</th>
                                                </tr>
                                            @endif
                                        @if(false === ($field['section'] ?? false))
                                                <tr>
                                                    <td>
                                                        {{ __('camt.field_'.$field['title']) }}
                                                        @if(__('camt.field_' . $field['title'] . '_description') !== 'camt.field_' . $field['title'] . '_description')
                                                            <br><span class="text-muted small">{{ __('camt.field_' . $field['title'] . '_description') }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if(0 == count($examples[$field['title']]))
                                                            <small class="text-muted"><em>(no example data)</em></small>
                                                        @endif
                                                            @if(count($examples[$field['title']]) > 0)
                                                                @foreach($examples[$field['title']] as $example)
                                                                <code>{{ $example }}</code><br>
                                                                @endforeach
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if(0 === count(config('camt.roles.' . $field['roles'])))
                                                            <small class="text-muted"><em>(no roles available)</em></small>
                                                        @endif
                                                        @if(count(config('camt.roles.' . $field['roles'])) > 0)
                                                                <select name="roles[{{ $field['title'] }}]" id="roles_{{ $field['title'] }}" class="form-control">
                                                                    @foreach(config('camt.roles.' . $field['roles']) as $roleKey => $role)
                                                                    <option value="{{ $roleKey }}" label="{{ __('import.column_' . $roleKey) }}"
                                                                            @if($field['default_role'] === $roleKey) selected @endif
                                                                            @if($field['default_role'] !== $roleKey && array_key_exists($field['title'], $roles) && $roles[$field['title']] == $roleKey) selected @endif
                                                                    >
                                                                        {{ trans('import.column_' . $roleKey) }}
                                                                    </option>
                                                                    @endforeach
                                                                </select>
                                                        @endif

                                                    </td>
                                                    <td>
                                                        @if(true === $field['mappable'])
                                                            <label for="do_mapping_{{ $field['title'] }}">
                                                                <input type="checkbox" name="do_mapping[{{ $field['title'] }}]" id="do_mapping_{{ $field['title'] }}"
                                                                          @if(($doMapping[$field['title']] ?? false)) checked="checked" @endif
                                                                       value="1"/>
                                                            </label>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                                @endif
                            @endforeach
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
                            <a href="{{ route('flush') }}" class="btn btn-danger btn-sm"><span
                                    class="fas fa-redo-alt"></span> Start over</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

