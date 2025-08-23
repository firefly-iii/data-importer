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
                        <p>Map data in your import to your Firefly III instance.</p>
                        <p>
                            Entries in your import may already exist in another form in your own Firefly III
                            instance. Be sure to <a target="_blank"
                                                    href="https://docs.firefly-iii.org/how-to/data-importer/import/map-data/">
                                check out the documentation</a>, because this is where
                            the magic happens.
                        </p>
                        <p class="text-info">
                            Account names with "lots&nbsp;&nbsp;&nbsp;&nbsp;of&nbsp;&nbsp;spaces" may seemingly lose
                            those spaces. Fear not, those will be perfectly preserved.
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
                        Form
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('006-mapping.post') }}" accept-charset="UTF-8">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}"/>
                            @foreach($data as $index => $row)
                            <h3>{{ $index }}: {{ __('import.column_'.$row['role']) }}</h3>
                            <table class="table">
                                <tr>
                                    <th style="width:50%;">Field value</th>
                                    <th style="width:50%;">Mapped to</th>
                                </tr>
                                @foreach($row['values'] as $valueIndex => $value)
                                <tr>
                                    <td>
                                        <pre style="color:#e83e8c;">{{ $value }}</pre>
                                        <input type="hidden" name="values[{{ $index }}][{{ $loop->index }}]"
                                               value="{{ $value }}"/>
                                    </td>
                                    <td>
                                        <select name="mapping[{{ $index }}][{{ $loop->index }}]"
                                                class="form-control">
                                            <option value="0" label="(do not map / automap)">(do not map /
                                                automap)
                                            </option>
                                            @foreach($row['mapping_data'] as $key => $maps)
                                            <!-- if is array go one level deeper -->
                                            @if(is_iterable($maps))
                                            <optgroup label="{{ $key }}">
                                                @foreach($maps as $singleId => $singleEntry)
                                                <option
                                                    @if($singleId === ($row['mapped'][$value] ?? false)) selected @endif
                                                    label="{{ $singleEntry }}"
                                                    value="{{ $singleId }}">
                                                    {{ $singleEntry }}
                                                </option>
                                                @endforeach
                                            </optgroup>
                                            @else
                                            <option
                                                @if($key === ($row['mapped'][$value] ?? false)) selected @endif
                                                    label="{{ $maps }}" value="{{ $key }}">
                                                {{ $maps }}
                                            </option>
                                            @endif
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                            @endforeach
                            <button type="submit" class="btn btn-primary float-end">Submit &rarr;</button>
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
                            <a href="{{ route('back.roles') }}" class="btn btn-secondary"><span
                                    class="fas fa-arrow-left"></span> Go back to the previous step</a>
                            <a href="{{ route('flush') }}" class="btn btn-danger text-white btn-sm"><span
                                    class="fas fa-redo-alt"></span> Start over</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection
