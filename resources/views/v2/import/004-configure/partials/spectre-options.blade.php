<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                Spectre import options
            </div>
            <div class="card-body">
                <div class="form-group row">
                    <label for="X" class="col-sm-3 col-form-label">Ignore Spectre categories</label>
                    <div class="col-sm-9">
                        <div class="form-check">
                            <input class="form-check-input"
                                   @if($configuration->isIgnoreSpectreCategories()) checked @endif
                                   type="checkbox" value="1" id="ignore_spectre_categories"
                                   name="ignore_spectre_categories"
                                   aria-describedby="duplicateSpectre">
                            <label class="form-check-label" for="ignore_spectre_categories">
                                Ignore Spectre's categories.
                            </label>
                        </div>

                        <small class="form-text text-muted" id="duplicateSpectre">
                            Spectre adds categories to each transaction. You can choose to ignore
                            them.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
