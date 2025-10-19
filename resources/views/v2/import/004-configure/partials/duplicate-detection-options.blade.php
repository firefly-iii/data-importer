<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                Duplicate transaction detection
            </div>
            <div class="card-body">
                <div class="col-sm-9 offset-sm-3">
                    <p class="text-muted">
                        Firefly III can automatically detect duplicate transactions. This is pretty
                        foolproof. In some special cases however,
                        you want more control over this process. Read more about the options below in <a
                            href="https://docs.firefly-iii.org/how-to/data-importer/import/csv/"
                            target="_blank">the documentation</a>.
                    </p>
                </div>

                @if('file' === $flow)
                    <div class="form-group row mb-3">
                        <label for="X" class="col-sm-3 col-form-label">General detection options</label>
                        <div class="col-sm-9">
                            <div class="form-check">
                                <input class="form-check-input"
                                       @if($configuration->isIgnoreDuplicateLines()) checked @endif
                                       type="checkbox" value="1" id="ignore_duplicate_lines"
                                       name="ignore_duplicate_lines" aria-describedby="duplicateHelp">
                                <label class="form-check-label" for="ignore_duplicate_lines">
                                    Do not import duplicate lines or entries in the importable file.
                                </label>
                                <br>
                                <small class="form-text text-muted" id="duplicateHelp">
                                    Whatever method you choose ahead, it's smart to make the importer ignore
                                    any
                                    duplicated lines or entries in your importable file.
                                </small>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="form-group row mb-3">
                    <label for="duplicate_detection_method" class="col-sm-3 col-form-label">Detection method</label>
                    <div class="col-sm-9">
                        <select id="duplicate_detection_method" name="duplicate_detection_method" x-model="detectionMethod"
                                class="form-control" aria-describedby="duplicate_detection_method_help">
                            <option label="No duplicate detection"
                                    @if('none' === $configuration->getDuplicateDetectionMethod()) selected @endif
                                    value="none">No duplicate detection
                            </option>
                            <option label="Content-based"
                                    @if('classic' === $configuration->getDuplicateDetectionMethod()) selected @endif
                                    value="classic">Content-based detection
                            </option>
                            <option label="Identifier-based"
                                    @if('cell' === $configuration->getDuplicateDetectionMethod()) selected @endif
                                    value="cell">Identifier-based detection
                            </option>
                        </select>
                        <small id="duplicate_detection_method_help" class="form-text text-muted">
                            For more details on these detection method see <a
                                href="https://docs.firefly-iii.org/references/data-importer/duplicate-detection/"
                                target="_blank">the documentation</a>. If you're not sure, don't change it.
                        </small>
                    </div>
                </div>
                @if('file' === $flow)
                    <div class="form-group row mb-3" id="unique_column_index_holder" x-show="'cell' === detectionMethod">
                        <label for="unique_column_index" class="col-sm-3 col-form-label">Unique column
                            index</label>
                        <div class="col-sm-9">
                            <input type="number" step="1" name="unique_column_index" class="form-control"
                                   id="unique_column_index" placeholder="Column index"
                                   value="{{ $configuration->getUniqueColumnIndex() }}"
                                   aria-describedby="unique_column_index_help">
                            <small id="unique_column_index_help" class="form-text text-muted">
                                This field is only relevant for the "identifier-based" detection option.
                                Indicate which column / field contains the unique identifier. Start counting from
                                zero!
                            </small>
                        </div>
                    </div>
                @endif

                <div class="form-group row" id="unique_column_type_holder" x-show="'cell' === detectionMethod">
                    <label for="unique_column_type" class="col-sm-3 col-form-label">Unique column
                        type</label>
                    <div class="col-sm-9">
                        <select id="unique_column_type" name="unique_column_type" class="form-control"
                                aria-describedby="unique_column_type_help">
                            @foreach($uniqueColumns as $columnType => $columnName)
                                <option label="{{ $columnName }}"
                                        @if($configuration->getUniqueColumnType() === $columnType) selected @endif
                                        value="{{ $columnType }}">{{ $columnName }}</option>
                            @endforeach
                        </select>

                        <small id="unique_column_type_help" class="form-text text-muted">
                            This field is only relevant for the "identifier-based" detection option.
                            Select
                            the type of value you expect in
                            the unique identifier. What must Firefly III search for?
                        </small>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
