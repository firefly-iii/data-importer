<div class="form-group row mb-3">
    <label for="importable_file" class="col-sm-4 col-form-label">Importable file</label>
    <div class="col-sm-8">
        <input type="file"
               class="form-control
                                           @if($errors->has('importable_file')) is-invalid @endif"
               id="importable_file" name="importable_file"
               placeholder="Importable file"
               accept=".xml,.csv"/>
        @if($errors->has('importable_file'))
            <div class="invalid-feedback">
                {{ $errors->first('importable_file') }}
            </div>
        @endif
    </div>
</div>
