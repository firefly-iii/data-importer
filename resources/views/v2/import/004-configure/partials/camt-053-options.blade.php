<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                CAMT.{{$camtType}} import options
            </div>
            <div class="card-body">
                <div class="form-group row mb-3">
                    <div class="col-sm-3">How to handle "Level-D" data</div>
                    <div class="col-sm-9">
                        <select id="grouped_transaction_handling"
                                name="grouped_transaction_handling"
                                class="form-control"
                                aria-describedby="grouped_transaction_handling_help">
                            <option
                                label="Create multiple single transactions, for each Level-D record"
                                @if('single' === $configuration->getGroupedTransactionHandling()) selected
                                @endif
                                value="single">Create multiple single transactions, for each Level-D
                                record
                            </option>
                            <option disabled
                                    label="Create one split transaction with splits for each record"
                                    @if('split' === $configuration->getGroupedTransactionHandling()) selected
                                    @endif
                                    value="split">Create one split transaction with splits for each
                                record
                            </option>
                            <option
                                label='Drop "level-D" data, sum and merge all details in a single transaction'
                                @if('group' === $configuration->getGroupedTransactionHandling()) selected
                                @endif
                                value="group">Drop "level-D" data, sum and merge all details in a
                                single transaction
                            </option>
                        </select>
                        <small class="form-text text-muted">
                            It's not recommended to drop the "level-D" data, you may lose details.
                        </small>
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <div class="col-sm-3">Use the entire address of the opposing part?</div>
                    <div class="col-sm-9">
                        <div class="form-check">
                            <input class="form-check-input"
                                   @if($configuration->isUseEntireOpposingAddress()) checked @endif
                                   type="checkbox" id="use_entire_opposing_address"
                                   name="use_entire_opposing_address" value="1"
                                   aria-describedby="useEntireOpposingAddressHelp">
                            <label class="form-check-label" for="use_entire_opposing_address">
                                Yes
                            </label>
                            <small id="use_entire_opposing_address_help"
                                   class="form-text text-muted">
                                <br>
                                The default is to only use the name, and only use the address
                                details when no name is available.
                                If you select this option, both name and address will always be used
                                (when available).
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
