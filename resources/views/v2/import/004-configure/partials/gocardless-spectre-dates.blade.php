<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                Date range import options
            </div>
            <div class="card-body">
                <div class="form-group row mb-3">
                    <label for="default_account" class="col-sm-3 col-form-label">Date range</label>
                    <div class="col-sm-9">
                        <div class="form-check">
                            <input class="form-check-input date-range-radio" id="date_range_all"
                                   type="radio" name="date_range" value="all" x-model="dateRange"
                                   @if('all' === $configuration->getDateRange()) checked @endif
                                   aria-describedby="rangeHelp"/>
                            <label class="form-check-label" for="date_range_all">Import
                                everything</label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input date-range-radio" id="date_range_partial"
                                   type="radio" name="date_range" x-model="dateRange"
                                   value="partial"
                                   @if('partial' === $configuration->getDateRange()) checked @endif
                                   aria-describedby="rangeHelp"/>
                            <label class="form-check-label" for="date_range_partial">Go back some
                                time</label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input date-range-radio" id="date_range_range"
                                   type="radio" name="date_range" value="range" x-model="dateRange"
                                   @if('range' === $configuration->getDateRange()) checked @endif
                                   aria-describedby="rangeHelp"/>
                            <label class="form-check-label" for="date_range_range">Import a specific
                                range</label>
                            <small id="rangeHelp" class="form-text text-muted">
                                <br>What range to grab from your bank through
                                @if('nordigen' === $flow)
                                    GoCardless?
                                @endif
                                @if('spectre' === $flow)
                                    Spectre?
                                @endif
                            </small>
                        </div>


                    </div>
                </div>

                <div class="form-group row mb-3" id="date_range_partial_settings" x-show="'partial' === dateRange">
                    <div class="col-sm-3">
                        Date range settings
                    </div>
                    <div class="col-sm-3">
                        <input
                            name="date_range_number"
                            id="date_range_number"
                            class="form-control" value="{{ $configuration->getDateRangeNumber() }}"
                            type="number" step="1" min="1" max="365">
                    </div>
                    <div class="col-sm-6">
                        <select class="form-control"
                                name="date_range_unit"
                                id="date_range_unit">
                            <option
                                @if('d' === $configuration->getDateRangeUnit()) selected @endif
                            value="d" label="days">days
                            </option>
                            <option
                                @if('w' === $configuration->getDateRangeUnit()) selected @endif
                            value="w" label="weeks">weeks
                            </option>
                            <option
                                @if('m' === $configuration->getDateRangeUnit()) selected @endif
                            value="m" label="months">months
                            </option>
                            <option
                                @if('y' === $configuration->getDateRangeUnit()) selected @endif
                            value="y" label="years">years
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-group row mb-3" id="date_range_range_settings" x-show="'range' === dateRange">
                    <div class="col-sm-3">
                        Date range settings (from, to)
                    </div>
                    <div class="col-sm-4">
                        <input type="date" name="date_not_before" class="form-control"
                               value="{{ $configuration->getDateNotBefore() }}">
                    </div>
                    <div class="col-sm-4">
                        <input type="date" name="date_not_after" class="form-control"
                               value="{{ $configuration->getDateNotAfter() }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- end of date range options -->
