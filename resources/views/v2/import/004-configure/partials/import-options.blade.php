<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                Other options
            </div>
            <div class="card-body">

                <h4>Other options</h4>
                <div class="form-group row mb-3">
                    <div class="col-sm-3">Skip form</div>
                    <div class="col-sm-9">
                        <div class="form-check">
                            <input class="form-check-input"
                                   @if($configuration->isSkipForm()) checked @endif
                                   type="checkbox"
                                   id="skip_form" name="skip_form" value="1" aria-describedby="skipHelp">
                            <label class="form-check-label" for="skip_form">
                                Yes
                            </label>
                            <small id="skipHelp" class="form-text text-muted">
                                <br>Skip the options the next time you import and go straight to processing.
                            </small>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
