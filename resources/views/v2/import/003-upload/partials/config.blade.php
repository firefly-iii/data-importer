<div class="form-group row mb-3">
    <label for="config_file" class="col-sm-4 col-form-label">Optional configuration
        file</label>
    <div class="col-sm-8">
        <input type="file" class="form-control @error('config_file') is-invalid @enderror" id="config_file" name="config_file"
               placeholder="Configuration file"
               accept=".json"/>
        @error('config_file')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror

    </div>
</div>
