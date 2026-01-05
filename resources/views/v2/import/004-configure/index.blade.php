@extends('layout.v2')
@section('content')
    <div class="container" x-data="index">
        <!-- this is a bit of a hack, but it works well enough to sync AlpineJS and the configuration object -->
        <span id="date-range-helper" data-date-range="{{$configuration->getDateRange()  }}"></span>
        <span id="date-format-helper" data-date-format="{{$configuration->getDate()  }}"></span>
        <span id="detection-method-helper" data-method="{{$configuration->getDuplicateDetectionMethod()}}"></span>
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <h1>{{ $mainTitle }}</h1>
            </div>
        </div>

        <!-- error -->
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
        <!-- end of error -->


        <!-- user has no accounts -->
        @include('import.004-configure.partials.no-account-warning')

        <!-- user has accounts! -->
        @if(count($applicationAccounts['assets']) > 0 || count($applicationAccounts['liabilities']) > 0 || $flow !== 'file')
            <!-- opening box with instructions -->
            @include('import.004-configure.partials.opening-box')

            <!-- start of form -->
            <form method="post" action="{{ route('configure-import.post', [$identifier]) }}" accept-charset="UTF-8" id="store">
                <input type="hidden" name="_token" value="{{ csrf_token() }}"/>

                <!-- overrule settings when the flow is not "file" -->
                @if('file' !== $flow)
                    <input type="hidden" name="ignore_duplicate_transactions" value="1"/>
                @endif

                {{--
                <!-- SimpleFIN account configuration -->
                @if('simplefin' === $flow)
                    @include('import.004-configure.partials.simplefin-accounts')
                    @include('import.004-configure.partials.simplefin-options')
                @endif
                <!-- End of SimpleFIN Import Options -->

                <!-- Account selection for Gocardless and Spectre -->
                <!-- also date range settings -->
                @if('nordigen' === $flow || 'spectre' === $flow || 'lunchflow' === $flow)
                    @include('import.004-configure.partials.gocardless-spectre-accounts')
                    @include('import.004-configure.partials.gocardless-spectre-dates')
                @endif
                <!-- end of account selection and date range settings -->
                --}}

                <!-- Account selection and date range settings for all third party data providers -->
                @if('file' !== $flow)
                    @include('import.004-configure.partials.data-importer-accounts')
                    @include('import.004-configure.partials.data-importer-dates')
                @endif


                <!-- spectre specific options -->
                @if('spectre' === $flow)
                    @include('import.004-configure.partials.spectre-options')
                @endif
                <!-- end of spectre options -->

                <!-- Nordigen / GoCardless specific options -->
                @if('nordigen' === $flow)
                    @include('import.004-configure.partials.gocardless-options')
                @endif
                <!-- end of Nordigen / GoCardless options -->

                <!-- camt.053 options -->
                @if('file' === $flow && 'camt'  === $configuration->getContentType())
                    @include('import.004-configure.partials.camt-053-options')
                @endif
                <!-- end of camt.053 options -->
                <!-- start of CSV options -->
                @if('file' === $flow && 'csv'  === $configuration->getContentType())
                    @include('import.004-configure.partials.csv-options')
                @endif
                <!-- end of CSV options -->

                <!-- generic import options -->
                @include('import.004-configure.partials.generic-options')
                <!-- end of generic import options -->

                <!-- duplicate detection options -->
                @include('import.004-configure.partials.duplicate-detection-options')
                <!-- end of duplicate detection options -->

                <!-- other options -->
                @include('import.004-configure.partials.import-options')

                <!-- end of other options -->

                <!-- start of submit button -->
                <div class="row mt-3">
                    <div class="col-lg-10 offset-lg-1">
                        <div class="card">
                            <div class="card-header">
                                Submit!
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <button type="submit" class="float-end btn btn-primary">Submit &rarr;</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        @endif
        <div class="row mt-3">
            <div class="col-lg-10 offset-lg-1">
                <div class="card">
                    <div class="card-body">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('flush') }}" class="btn text-white btn-danger btn-sm"><span
                                    class="fas fa-redo-alt"></span> Start over</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection
@section('scripts')
    @vite(['src/pages/configuration/index.js'])
@endsection
