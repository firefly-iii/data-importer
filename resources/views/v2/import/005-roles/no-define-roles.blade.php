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
                            It looks like you're using import provider "{{ config(sprintf('importer.flow_titles.%s',$flow)) }}". But for this
                            import provider, it is not necessary to do this step. You should be able to move to the next step safely.
                        </p>
                        <p>
                            Please continue to the <a href="TODO_FIX_ME_I_AM_BROKEN">next step to start your new import</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>


    </div>
@endsection
