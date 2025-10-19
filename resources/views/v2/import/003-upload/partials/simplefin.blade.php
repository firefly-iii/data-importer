<div class="form-group row mb-3">
    <label for="use_demo" class="col-sm-4 col-form-label">Demo Mode</label>
    <div class="col-sm-8">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="use_demo" name="use_demo" value="1">
            <label class="form-check-label" for="use_demo">
                Use demo mode (test with sample data)
            </label>
        </div>
        <small class="form-text text-muted">
            Enable this to test the import process with SimpleFIN demo data.
        </small>
    </div>
</div>

@if($errors->has('connection'))
    <div class="alert alert-danger" role="alert">
        <strong>Connection Error:</strong> {{ $errors->first('connection') }}
    </div>
@endif

<div class="form-group row mb-3" id="token-group">
    <label for="simplefin_token" class="col-sm-4 col-form-label">SimpleFIN token</label>
    <div class="col-sm-8">
        <input type="text"
               class="form-control
                                       @if($errors->has('simplefin_token')) is-invalid @endif"
               id="simplefin_token" name="simplefin_token"
               autocomplete="off"
               value="{{ $settings['simplefin']['token'] }}"
               placeholder="SimpleFIN token"/>
        @if($errors->has('simplefin_token'))
            <div class="invalid-feedback">
                {{ $errors->first('simplefin_token') }}
            </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const demoCheckbox = document.getElementById('use_demo');
        const tokenGroup = document.getElementById('token-group');

        function toggleSimpleFINFields() {
            if (demoCheckbox.checked) {
                tokenGroup.style.display = 'none';
            } else {
                tokenGroup.style.display = 'flex';
            }
        }

        // Initial state
        toggleSimpleFINFields();

        // Listen for changes
        demoCheckbox.addEventListener('change', toggleSimpleFINFields);
    });
</script>

