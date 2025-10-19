@if(count($list) > 0)
    <div class="form-group row mb-3">
        <label for="config_file" class="col-sm-4 col-form-label">Pre-made configuration
            file</label>
        <div class="col-sm-8">
            <select class="form-control" name="existing_config">
                <option value="" label="Upload or manual config">Upload or manual config
                </option>
                @foreach($list as $file)
                    <option value="{{ $file }}" label="{{ $file }}">{{ $file }}</option>
                @endforeach
            </select>
        </div>
    </div>
@endif
