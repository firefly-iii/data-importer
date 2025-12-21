<div class="form-group row mb-3">
    <label for="config_file" class="col-sm-4 col-form-label">Optional configuration
        file</label>
    <div class="col-sm-8">
        <input type="file" class="form-control @if($errors->has('importable_file')) is-invalid @endif" id="config_file" name="config_file"
               placeholder="Configuration file"
               accept=".json"/>
        @if($errors->has('config_file'))
            <div class="invalid-feedback">
                {{ $errors->first('config_file') }}
            </div>
        @endif
    </div>
</div>
