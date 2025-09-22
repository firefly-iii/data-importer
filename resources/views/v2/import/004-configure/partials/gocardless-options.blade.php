<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                GoCardless import options
            </div>
            <div class="card-body">
                <!-- Pending Transactions Configuration -->
                <div class="form-group row">
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
                                <br>Select to include pending (unposted) transactions in addition to posted
                                transactions.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
