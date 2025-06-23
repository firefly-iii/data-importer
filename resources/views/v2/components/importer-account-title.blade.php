<div class="d-flex align-items-start p-3 bg-light rounded mb-2 border border-secondary">
    <div class="form-check me-3">
        <input
            id="do_import_{{ $account['import_account']->id }}"
            type="checkbox"
            name="do_import[{{ $account['import_account']->id }}]"
            value="1"
            class="form-check-input"
            aria-describedby="accountsHelp"
            @if('disabled' === $account['import_account']->status) disabled="disabled" @endif
            @php
                $accountId = $account['import_account']->id;
                $configuredValue = $configuration->getAccounts()[$accountId] ?? null;
                $allAccounts = $configuration->getAccounts();
                $mappedTo = $account['mapped_to'] ?? null;

                // Check if account should be checked:
                // 1. If configured explicitly (any non-null value including 0 for "create new")
                // 2. If no configuration exists yet - use sensible defaults
                $shouldCheck = false;

                if ($configuredValue !== null && $configuredValue !== '') {
                    // Explicitly configured (including 0 for "create new")
                    $shouldCheck = true;
                } elseif (empty($allAccounts)) {
                    // No configuration yet - use sensible defaults
                    // Check if there's an automatic mapping
                    if ($mappedTo !== null) {
                        $shouldCheck = true; // Auto-mapped accounts should be checked
                    } else {
                        $shouldCheck = true; // Default to checked for user convenience
                    }
                }
            @endphp
            @if($shouldCheck) checked="checked" @endif
        />
    </div>

    <div class="flex-grow-1">
        <label
            class="form-check-label d-block mb-2"
            for="do_import_{{ $account['import_account']->id }}"
            @if('' !== $account['import_account']->iban) title="IBAN: {{ $account['import_account']->iban }}" @endif
        >
            <div class="d-flex align-items-center mb-1">
                <span class="fw-bold fs-6">{{ $account['import_account']->name ?? 'Unnamed SimpleFIN Account' }}</span>
            </div>
            @if(isset($account['import_account']->org) && is_array($account['import_account']->org) && !empty($account['import_account']->org['name']))
                <div class="text-muted small">
                    <i class="fas fa-building me-1"></i>
                    {{ $account['import_account']->org['name'] }}
                </div>
            @endif
        </label>

        @if(isset($account['import_account']->balance))
        <div class="mb-2">
            <i class="fas fa-coins me-1"></i>
            <span class="badge bg-secondary text-light px-3 py-1 fw-bold">
                {{ number_format((float)$account['import_account']->balance, 2) }} {{ $account['import_account']->currency ?? '' }}
            </span>
            @if(isset($account['import_account']->balance_date) && $account['import_account']->balance_date)
                <small class="text-muted ms-2">({{ date('M j, Y', (int)$account['import_account']->balance_date) }})</small>
            @endif
        </div>
        @endif

        @if(isset($account['import_account']->available_balance) && $account['import_account']->available_balance !== ($account['import_account']->balance ?? null))
        <div class="mb-2">
            <i class="fas fa-wallet me-1"></i>
            <span class="badge bg-secondary text-light px-3 py-1 fw-bold">
                {{ number_format((float)$account['import_account']->available_balance, 2) }} {{ $account['import_account']->currency ?? '' }}
            </span>
        </div>
        @endif

        <div class="d-flex align-items-center justify-content-between">
            <small class="text-muted">
                <i class="fas fa-id-card me-1"></i>
                <code class="text-muted">{{ $account['import_account']->id ?? 'N/A' }}</code>
            </small>
            @if('disabled' === $account['import_account']->status)
                <small class="text-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Disabled
                </small>
            @endif
        </div>

        {{-- Display 'extra' fields if any --}}
        @php $extraData = (array)($account['import_account']->extra ?? []); @endphp
        @if(count($extraData) > 0)
            <div class="mt-2 pt-2 border-top border-secondary">
                @foreach($extraData as $key => $item)
                    @if(!empty($item) && is_scalar($item))
                    <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                        <span>{{ ucfirst(str_replace(['_', '-'], ' ', $key)) }}:</span>
                        <span>{{ $item }}</span>
                    </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
