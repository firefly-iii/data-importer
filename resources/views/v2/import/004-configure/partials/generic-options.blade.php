<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                Various import options
            </div>
            <div class="card-body">
                <div class="form-group row mb-3">
                    <label for="default_account" class="col-sm-3 col-form-label">Default import account</label>
                    <div class="col-sm-9">
                        <select id="default_account" name="default_account" class="form-control"
                                aria-describedby="defaultAccountHelp">
                            @foreach($applicationAccounts as $accountGroup => $accountList)
                                <optgroup label="{{ $accountGroup }}">
                                    {% for account in accountList %}
                                    @foreach($accountList as $account)
                                        <option
                                            @if($configuration->getDefaultAccount() === $account['id']) selected @endif
                                        value="{{ $account['id'] }}"
                                            label="{{ $account['name'] }}">{{ $account['name'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <small id="defaultAccountHelp" class="form-text text-muted">
                            Select the asset account you want to link transactions to, if your import doesn't have enough meta data to determine this.
                        </small>
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <div class="col-sm-3">Rules</div>
                    <div class="col-sm-9">
                        <div class="form-check">
                            <input class="form-check-input"
                                   @if($configuration->isRules()) checked @endif
                                   type="checkbox" id="rules" name="rules" value="1"
                                   aria-describedby="rulesHelp">
                            <label class="form-check-label" for="rules">
                                Yes
                            </label>
                            <small id="rulesHelp" class="form-text text-muted">
                                <br>Select if you want Firefly III to apply your rules to the import.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <div class="col-sm-3">Import tag</div>
                    <div class="col-sm-9">
                        <div class="form-check">
                            <input class="form-check-input"
                                   @if($configuration->isAddImportTag()) checked @endif
                                   type="checkbox" id="add_import_tag" name="add_import_tag" value="1"
                                   aria-describedby="add_import_tagHelp">
                            <label class="form-check-label" for="add_import_tag">
                                Yes
                            </label>
                            <small id="add_import_tagHelp" class="form-text text-muted">
                                <br>When selected Firefly III will add a tag to each imported transaction
                                denoting the import; this groups your import under a tag.
                            </small>
                        </div>
                    </div>
                </div>
                <!-- both camt and csv support custom tag -->
                <div class="form-group row mb-3">
                    <label for="date" class="col-sm-3 col-form-label">Custom import tag</label>
                    <div class="col-sm-9">
                        <input type="text" name="custom_tag" class="form-control" id="custom_tag"
                               placeholder="Custom import tag"
                               value="{{ $configuration->getCustomTag() }}"
                               aria-describedby="custom_tagHelp">
                        <small id="custom_tagHelp" class="form-text text-muted">
                            You can set your own import tag to easily distinguish imports
                            or just because you don't like the default one.
                            <a href="https://docs.firefly-iii.org/how-to/data-importer/advanced/custom-import-tag/" target="_blank">Read more in the documentation</a>.
                        </small>
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <div class="col-sm-3">Map data</div>
                    <div class="col-sm-9">
                        <div class="form-check">
                            <input class="form-check-input"
                                   @if($configuration->isMapAllData()) checked @endif
                                   type="checkbox"
                                   id="map_all_data"
                                   name="map_all_data" value="1" aria-describedby="mapAllDataHelp">
                            <label class="form-check-label" for="map_all_data">
                                Yes
                            </label>
                            <small id="mapAllDataHelp" class="form-text text-muted">
                                <br>You get the opportunity to link your data to existing Firefly III
                                data, for a cleaner import.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
