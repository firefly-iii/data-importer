<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                Enable Banking import options
            </div>
            <div class="card-body">
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
