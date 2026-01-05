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
                        <p>Hi there!</p>
                        <p>
                            It looks like you're already authenticated, and you are ready to use import
                            provider "{{ config(sprintf('importer.flow_titles.%s',$flow)) }}".
                        </p>
                        <p>
                            Please continue to the <a href="{{ route('new-import.index', [$flow]) }}">next step to start your new import</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>


    </div>
@endsection
