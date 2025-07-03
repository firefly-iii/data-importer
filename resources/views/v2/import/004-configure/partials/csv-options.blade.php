<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                CSV file import options
            </div>
            <div class="card-body">
                <div class="form-group row mb-3">
                    <div class="col-sm-3">Headers</div>
                    <div class="col-sm-9">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="headers" name="headers" value="1"
                                   aria-describedby="headersHelp"
                                   @if($configuration->isHeaders()) checked @endif
                            >
                            <label class="form-check-label" for="headers">
                                Yes
                            </label><br>
                            <small id="headersHelp" class="form-text text-muted">
                                Select this checkbox when your importable file is a CSV-like file
                                and has headers on the first line of the
                                file.
                            </small>
                        </div>
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <div class="col-sm-3">Convert to UTF-8</div>
                    <div class="col-sm-9">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   @if($configuration->isConversion()) checked @endif
                                   id="conversion" name="conversion" value="1"
                                   aria-describedby="conversionHelp">
                            <label class="form-check-label" for="conversion">
                                Yes
                            </label><br>
                            <small id="conversionHelp" class="form-text text-muted">
                                Try to convert your file to UTF-8. This may lead to weird
                                characters.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label for="delimiter" class="col-sm-3 col-form-label">CSV-file
                        delimiter</label>
                    <div class="col-sm-9">
                        <select id="delimiter" name="delimiter" class="form-control"
                                aria-describedby="delimiterHelp">
                            <option value="comma"
                                    @if('comma' === $configuration->getDelimiter()) selected @endif
                                    label="A comma (,)">A comma (,)
                            </option>
                            <option value="semicolon"
                                    @if('semicolon' === $configuration->getDelimiter()) selected
                                    @endif
                                    label="A semicolon (;)">A semicolon (;)
                            </option>
                            <option value="tab"
                                    @if('tab' === $configuration->getDelimiter()) selected @endif
                                    label="A tab (invisible)">A tab (invisible)
                            </option>
                        </select>
                        <small id="delimiterHelp" class="form-text text-muted">
                            If your file is a CSV file, select the field separator of the file. This
                            is almost always a comma.
                        </small>
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label for="date" class="col-sm-3 col-form-label">Date format</label>
                    <div class="col-sm-9">
                        <input type="text" name="date" class="form-control"
                               placeholder="Date format" x-model="dateFormat"
                               value="{{ $configuration->getDate() ?? 'Y-m-d' }}"
                               @change="getParsedDate"
                               aria-describedby="dateHelp">
                        <small id="dateHelp" class="form-text text-muted">
                            1. Read more about the date format <a
                                href="https://www.php.net/manual/en/datetime.format.php">on this
                                page</a><br>
                            2. Make sure this example date's format matches your file:
                            <strong x-show="!loadingParsedDate" x-text="parsedDateFormat">1984-09-17</strong>
                            <em x-show="loadingParsedDate" class="fas fa-cog fa-spin"></em>
                            <br>
                            3. If your file contains something like "5 mei 2023", prefix with your
                            country code like so <code>nl:d F Y</code>
                        </small>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
