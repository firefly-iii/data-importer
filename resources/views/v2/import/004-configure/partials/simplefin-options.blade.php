<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                SimpleFIN import options
            </div>
            <div class="card-body">
                <!-- Date Range Configuration -->
                <div class="form-group row mb-3">
                    <label for="date_range" class="col-sm-3 col-form-label">Date range:</label>
                    <div class="col-sm-9">
                        <select name="date_range" id="date_range" class="form-control" onchange="toggleDateRangeInputs()">
                            <option value="all" @if($configuration->getDateRange() === 'all') selected @endif>All time</option>
                            <option value="dynamic" @if($configuration->getDateRange() === 'dynamic') selected @endif>Dynamic range</option>
                            <option value="specific" @if($configuration->getDateRange() === 'specific') selected @endif>Specific dates</option>
                        </select>
                    </div>
                </div>

                <div id="dynamic_range_inputs" style="display: {{ $configuration->getDateRange() === 'dynamic' ? 'block' : 'none' }};">
                    <div class="form-group row mb-3">
                        <label for="date_range_number" class="col-sm-3 col-form-label">Range:</label>
                        <div class="col-sm-5">
                            <input type="number" name="date_range_number" id="date_range_number" class="form-control" value="{{ $configuration->getDateRangeNumber() ?? 30 }}" min="1">
                        </div>
                        <div class="col-sm-4">
                            <select name="date_range_unit" id="date_range_unit" class="form-control">
                                <option value="d" @if($configuration->getDateRangeUnit() === 'd') selected @endif>Days</option>
                                <option value="w" @if($configuration->getDateRangeUnit() === 'w') selected @endif>Weeks</option>
                                <option value="m" @if($configuration->getDateRangeUnit() === 'm') selected @endif>Months</option>
                                <option value="y" @if($configuration->getDateRangeUnit() === 'y') selected @endif>Years</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="specific_dates_inputs" style="display: {{ $configuration->getDateRange() === 'specific' ? 'block' : 'none' }};">
                    <div class="form-group row mb-3">
                        <label for="date_not_before" class="col-sm-3 col-form-label">Start date:</label>
                        <div class="col-sm-9">
                            <input type="date" name="date_not_before" id="date_not_before" class="form-control" value="{{ $configuration->getDateNotBefore() ?? '' }}">
                        </div>
                    </div>
                    <div class="form-group row mb-3">
                        <label for="date_not_after" class="col-sm-3 col-form-label">End date:</label>
                        <div class="col-sm-9">
                            <input type="date" name="date_not_after" id="date_not_after" class="form-control" value="{{ $configuration->getDateNotAfter() ?? '' }}">
                        </div>
                    </div>
                </div>

                <!-- Pending Transactions Configuration -->
                <div class="form-group row mb-3">
                    <div class="col-sm-3">Pending transactions</div>
                    <div class="col-sm-9">
                        <div class="form-check">
                            <input class="form-check-input"
                                   @if($configuration->getPendingTransactions()) checked @endif
                                   type="checkbox" id="pending_transactions" name="pending_transactions" value="1"
                                   aria-describedby="pendingTransactionsHelp">
                            <label class="form-check-label" for="pending_transactions">
                                Include pending transactions
                            </label>
                            <small id="pendingTransactionsHelp" class="form-text text-muted">
                                <br>Select to include pending (unposted) transactions in addition to posted transactions.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function toggleDateRangeInputs() {
        const dateRangeType = document.getElementById('date_range').value;
        const dynamicInputs = document.getElementById('dynamic_range_inputs');
        const specificInputs = document.getElementById('specific_dates_inputs');

        dynamicInputs.style.display = (dateRangeType === 'dynamic') ? 'block' : 'none';
        specificInputs.style.display = (dateRangeType === 'specific') ? 'block' : 'none';
    }
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', toggleDateRangeInputs);
</script>
