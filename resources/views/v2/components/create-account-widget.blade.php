<!-- Create Account Widget - Hidden by default, shown when "Create New Account" is selected -->
<div class="collapse mt-1" id="create-account-widget-{{ $account['import_account']->id }}">
    <div class="card">
        <div class="card-body">
            <!-- Account Name -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center flex-grow-1">
                            <label class="form-label mb-0 me-3" style="min-width: 60px;">Name:</label>
                            <span id="account-name-display-{{ $account['import_account']->id }}" class="fw-bold">{{ $account['import_account']->name ?? 'New Account' }}</span>
                            <input type="text"
                                   class="form-control form-control-sm d-none"
                                   id="account-name-edit-{{ $account['import_account']->id }}"
                                   name="new_accounts[{{ str_replace(' ', '_', $account['import_account']->id) }}][name]"
                                   value="{{ $account['import_account']->name ?? 'New Account' }}"
                                   style="display: inline-block; width: auto; min-width: 200px;">
                        </div>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    id="edit-name-btn-{{ $account['import_account']->id }}"
                                    onclick="toggleAccountNameEdit('{{ $account['import_account']->id }}', true)"
                                    title="Edit account name">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-success btn-sm d-none"
                                    id="commit-name-btn-{{ $account['import_account']->id }}"
                                    onclick="commitAccountNameEdit('{{ $account['import_account']->id }}')"
                                    title="Save account name">
                                <i class="fas fa-check"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm d-none"
                                    id="cancel-name-btn-{{ $account['import_account']->id }}"
                                    onclick="toggleAccountNameEdit('{{ $account['import_account']->id }}', false)"
                                    title="Cancel edit">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Account Type -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="d-flex align-items-center">
                        <label class="form-label mb-0 me-3" style="min-width: 60px;">Type:</label>
                        <select class="form-control"
                                id="new-account-type-{{ $account['import_account']->id }}"
                                name="new_accounts[{{ str_replace(' ', '_', $account['import_account']->id) }}][type]"
                                onchange="updateAccountTypeVisibility('{{ $account['import_account']->id }}')"
                                required>
                            @php
                                // Simplified account type inference - default all to Asset Account
                                $inferredType = 'asset'; // All accounts default to Asset Account
                                $accountName = strtolower($account['import_account']->name ?? '');
                            @endphp

                            <option value="asset" @if($inferredType === 'asset') selected @endif>Asset Account</option>
                            <option value="liability" @if($inferredType === 'liability') selected @endif>Liability Account</option>
                            <option value="expense" @if($inferredType === 'expense') selected @endif>Expense Account</option>
                            <option value="revenue" @if($inferredType === 'revenue') selected @endif>Revenue Account</option>
                        </select>
                        <div class="invalid-feedback">
                            Please select an account type.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Role Section (Asset accounts only) -->
            <div class="row mb-3" id="account-role-section-{{ $account['import_account']->id }}" style="@if($inferredType === 'asset') display: block; @else display: none; @endif">
                <div class="col-12">
                    <div class="d-flex align-items-center">
                        <label class="form-label mb-0 me-3" style="min-width: 60px;">Role:</label>
                        <select class="form-control"
                                id="new-account-role-{{ $account['import_account']->id }}"
                                name="new_accounts[{{ str_replace(' ', '_', $account['import_account']->id) }}][account_role]">
                            @php
                                // Intelligent role detection for asset accounts
                                $inferredRole = 'defaultAsset'; // Default fallback

                                if ($inferredType === 'asset') {
                                    // Negative balance detection (priority for credit cards)
                                    if (isset($account['import_account']->balance) &&
                                        floatval($account['import_account']->balance) < 0) {
                                        $inferredRole = 'ccAsset';
                                    }
                                    // Credit card name pattern detection
                                    elseif (preg_match('/credit\s*card|visa|mastercard|amex|american\s*express|discover/', $accountName)) {
                                        $inferredRole = 'ccAsset';
                                    }
                                    // Savings account detection
                                    elseif (preg_match('/savings|save|high\s*yield|money\s*market|cd|certificate/', $accountName)) {
                                        $inferredRole = 'savingAsset';
                                    }
                                    // Cash wallet detection
                                    elseif (preg_match('/cash|wallet|petty\s*cash/', $accountName) ||
                                            (isset($account['import_account']->balance) &&
                                             floatval($account['import_account']->balance) < 1000 &&
                                             floatval($account['import_account']->balance) > 0)) {
                                        $inferredRole = 'cashWalletAsset';
                                    }
                                    // Shared asset detection (joint accounts)
                                    elseif (preg_match('/joint|shared|family|couple/', $accountName)) {
                                        $inferredRole = 'sharedAsset';
                                    }
                                }
                            @endphp
                            <option value="defaultAsset" @if($inferredRole === 'defaultAsset') selected @endif>Default Asset</option>
                            <option value="sharedAsset" @if($inferredRole === 'sharedAsset') selected @endif>Shared Asset</option>
                            <option value="savingAsset" @if($inferredRole === 'savingAsset') selected @endif>Savings Account</option>
                            <option value="ccAsset" @if($inferredRole === 'ccAsset') selected @endif>Credit Card</option>
                            <option value="cashWalletAsset" @if($inferredRole === 'cashWalletAsset') selected @endif>Cash Wallet</option>
                        </select>
                        <div class="invalid-feedback">
                            Please select an account role.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liability Role and Direction Section (Liability accounts only) -->
            <div id="liability-fields-section-{{ $account['import_account']->id }}" style="@if($inferredType === 'liability') display: block; @else display: none; @endif">
                <!-- Role Selection -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="d-flex align-items-center">
                            <label class="form-label mb-0 me-3" style="min-width: 60px;">Role:</label>
                            <select class="form-control"
                                    id="liability-type-{{ $account['import_account']->id }}"
                                    name="new_accounts[{{ str_replace(' ', '_', $account['import_account']->id) }}][liability_type]">
                                @php
                                    // Intelligent liability role detection
                                    $inferredLiabilityRole = 'debt'; // Default fallback

                                    if (preg_match('/mortgage|home\s*loan/', $accountName)) {
                                        $inferredLiabilityRole = 'mortgage';
                                    }
                                    elseif (preg_match('/loan|auto\s*loan|student\s*loan|personal\s*loan/', $accountName)) {
                                        $inferredLiabilityRole = 'loan';
                                    }
                                @endphp
                                <option value="debt" @if($inferredLiabilityRole === 'debt') selected @endif>Debt</option>
                                <option value="loan" @if($inferredLiabilityRole === 'loan') selected @endif>Loan</option>
                                <option value="mortgage" @if($inferredLiabilityRole === 'mortgage') selected @endif>Mortgage</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select a liability role.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Direction Selection -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="d-flex align-items-center">
                            <label class="form-label mb-0 me-3" style="min-width: 60px;">Direction:</label>
                            @php
                                // Balance-based direction logic
                                $inferredDirection = 'credit'; // Default fallback

                                if (isset($account['import_account']->balance)) {
                                    $balance = floatval($account['import_account']->balance);
                                    // Negative balance = we owe them (credit)
                                    // Positive balance = they owe us (debit)
                                    $inferredDirection = ($balance < 0) ? 'credit' : 'debit';
                                }
                            @endphp
                            <div class="btn-group" role="group" data-bs-toggle="buttons">
                                <input type="radio" class="btn-check"
                                       name="new_accounts[{{ str_replace(' ', '_', $account['import_account']->id) }}][liability_direction]"
                                       id="direction-credit-{{ $account['import_account']->id }}"
                                       value="credit"
                                       @if($inferredDirection === 'credit') checked @endif>
                                <label class="btn btn-outline-primary btn-sm" for="direction-credit-{{ $account['import_account']->id }}">
                                    We owe them
                                </label>

                                <input type="radio" class="btn-check"
                                       name="new_accounts[{{ str_replace(' ', '_', $account['import_account']->id) }}][liability_direction]"
                                       id="direction-debit-{{ $account['import_account']->id }}"
                                       value="debit"
                                       @if($inferredDirection === 'debit') checked @endif>
                                <label class="btn btn-outline-primary btn-sm" for="direction-debit-{{ $account['import_account']->id }}">
                                    They owe us
                                </label>
                            </div>
                            <div class="invalid-feedback">
                                Please select a liability direction.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance and Currency Section -->
            <div class="row mb-3">
                <div class="col-12">
                    <!-- Balance Label Row with Edit Button -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0"><i class="fas fa-coins me-2"></i>Balance:</label>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    id="edit-balance-btn-{{ $account['import_account']->id }}"
                                    onclick="toggleBalanceCurrencyEdit('{{ $account['import_account']->id }}', true)"
                                    title="Edit balance and currency">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-success btn-sm d-none"
                                    id="commit-balance-btn-{{ $account['import_account']->id }}"
                                    onclick="commitBalanceCurrencyEdit('{{ $account['import_account']->id }}')"
                                    title="Save changes">
                                <i class="fas fa-check"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm d-none"
                                    id="cancel-balance-btn-{{ $account['import_account']->id }}"
                                    onclick="toggleBalanceCurrencyEdit('{{ $account['import_account']->id }}', false)"
                                    title="Cancel edit">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Balance and Currency Values Row -->
                    <div class="d-flex align-items-center">
                        @php
                            $rawBalance = $account['import_account']->balance ?? null;
                            $convertedFloat = (float)($rawBalance ?? '0.00');
                            $displayFormat = number_format($convertedFloat, 2);
                        @endphp

                        <!-- Balance Display/Edit -->
                        <div class="me-3 flex-grow-1">
                            <span id="balance-display-{{ $account['import_account']->id }}" class="fw-bold">
                                {{ $displayFormat }}
                            </span>
                            <input type="number"
                                   step="0.01"
                                   class="form-control form-control-sm d-none"
                                   id="balance-edit-{{ $account['import_account']->id }}"
                                   name="new_accounts[{{ str_replace(' ', '_', $account['import_account']->id) }}][opening_balance]"
                                   value="{{ $convertedFloat }}"
                                   placeholder="0.00">
                        </div>

                        <!-- Currency Display/Edit -->
                        <div>
                            @php
                                // Check for previously committed currency selection, fall back to SimpleFIN currency, then EUR
                                $accountId = $account['import_account']->id;
                                $newAccounts = $configuration->getNewAccounts();
                                $committedCurrency = $newAccounts[$accountId]['currency'] ?? null;
                                $displayCurrency = $committedCurrency ?? $account['import_account']->currencyCode ?? 'EUR';
                            @endphp
                            <span id="currency-display-{{ $account['import_account']->id }}" class="fw-bold">
                                {{ $displayCurrency }}
                            </span>
                            <select class="form-control form-control-sm d-none"
                                    id="currency-edit-{{ $account['import_account']->id }}"
                                    name="new_accounts[{{ str_replace(' ', '_', $account['import_account']->id) }}][currency]">
                                @php
                                    // Use the same committed currency logic for option selection
                                    $defaultCurrency = $displayCurrency;
                                @endphp
                                @foreach($currencies ?? [] as $currencyId => $currencyDisplay)
                                    @php
                                        // Extract ISO code from currency display (e.g., "US Dollar (EUR)" -> "EUR")
                                        preg_match('/\(([A-Z]{3})\)/', $currencyDisplay, $matches);
                                        $isoCode = $matches[1] ?? 'EUR';
                                    @endphp
                                    <option value="{{ $isoCode }}" @if($isoCode === $defaultCurrency) selected @endif>
                                        {{ $currencyDisplay }}
                                    </option>
                                @endforeach
                                @if(empty($currencies))
                                    <option value="USD">EUR (Euro)</option>
                                @endif
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metadata section removed - debug information not valuable in production widget -->

            <!-- Dynamic status footer area -->
            <div class="row">
                <div class="col-12">
                    <div class="text-end">
                        <small id="widget-status-{{ $account['import_account']->id }}" class="text-muted">
                            <i class="fas fa-check-circle me-1"></i>
                            Ready for import
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Track edit states to prevent form submission during edits
    window.accountEditStates = window.accountEditStates || {};


    // Initialize visibility on page load
    document.addEventListener('DOMContentLoaded', function() {
        @foreach($accounts ?? [] as $account)
            updateAccountTypeVisibility('{{ $account['import_account']->id }}');
        @endforeach
    });

    // Updated function to handle both account role and liability field visibility
    function updateAccountTypeVisibility(accountId) {
        updateAccountRoleVisibility(accountId);
        updateLiabilityFieldsVisibility(accountId);
    }

    function updateAccountRoleVisibility(accountId) {
        const typeSelect = document.getElementById('new-account-type-' + accountId);
        const roleSection = document.getElementById('account-role-section-' + accountId);

        if (!typeSelect || !roleSection) {
            console.error('Required elements not found for role visibility update:', accountId);
            return;
        }

        if (typeSelect.value === 'asset') {
            roleSection.style.display = 'block';
            console.log('Showed role section for asset account:', accountId);
        } else {
            roleSection.style.display = 'none';
            console.log('Hidden role section for non-asset account:', accountId);
        }
    }

    function updateLiabilityFieldsVisibility(accountId) {
        const typeSelect = document.getElementById('new-account-type-' + accountId);
        const liabilitySection = document.getElementById('liability-fields-section-' + accountId);

        if (!typeSelect || !liabilitySection) {
            console.error('Required elements not found for liability visibility update:', accountId);
            return;
        }

        if (typeSelect.value === 'liability') {
            liabilitySection.style.display = 'block';
            console.log('Showed liability fields for account:', accountId);
        } else {
            liabilitySection.style.display = 'none';
            console.log('Hidden liability fields for account:', accountId);
        }
    }

    // Enhanced widget visibility control functions with error handling
    function showAccountWidget(accountId) {
        try {
            const widget = document.getElementById('create-account-widget-' + accountId);
            if (!widget) {
                console.error('Widget element not found for account:', accountId);
                return false;
            }

            if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                const collapse = new bootstrap.Collapse(widget, { show: true });
                console.log('Showed account widget using Bootstrap for:', accountId);
            } else {
                widget.classList.add('show');
                console.log('Showed account widget using fallback for:', accountId);
            }

            // Initialize edit states
            window.accountEditStates[accountId] = {
                nameEditing: false,
                balanceEditing: false
            };

            return true;
        } catch (error) {
            console.error('Error showing widget for account:', accountId, error);
            return false;
        }
    }

    // Account name inline editing functions moved to global layout



    // Form submission state management and status updates
    function updateFormSubmitState() {
        const form = document.querySelector('form');
        if (!form) return;

        const submitButtons = form.querySelectorAll('[type="submit"]');
        let hasActiveEdits = false;

        // Check if any account is in edit state and update status text
        for (const accountId in window.accountEditStates) {
            const state = window.accountEditStates[accountId];
            const statusElement = document.getElementById('widget-status-' + accountId);

            if (state.nameEditing || state.balanceEditing) {
                hasActiveEdits = true;
                if (statusElement) {
                    statusElement.innerHTML = '<i class="fas fa-exclamation-triangle me-1 text-warning"></i>Finish editing before import';
                    statusElement.className = 'text-warning';
                }
            } else {
                if (statusElement) {
                    statusElement.innerHTML = '<i class="fas fa-check-circle me-1"></i>Ready for import';
                    statusElement.className = 'text-muted';
                }
            }
        }

        // Disable/enable submit buttons based on edit state
        submitButtons.forEach(button => {
            if (hasActiveEdits) {
                button.disabled = true;
                button.title = 'Complete all edits before submitting';
            } else {
                button.disabled = false;
                button.title = '';
            }
        });
    }

    function hideAccountWidget(accountId) {
        try {
            const widget = document.getElementById('create-account-widget-' + accountId);
            const select = document.getElementById('account-select-' + accountId);

            if (widget) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                    const collapse = new bootstrap.Collapse(widget, { hide: true });
                    console.log('Hid account widget using Bootstrap for:', accountId);
                } else {
                    widget.classList.remove('show');
                    console.log('Hid account widget using fallback for:', accountId);
                }

                // Clear any validation states
                const inputs = widget.querySelectorAll('.is-invalid');
                inputs.forEach(input => input.classList.remove('is-invalid'));
            }

            // Reset dropdown to first non-create option
            if (select && select.options.length > 1) {
                for (let i = 1; i < select.options.length; i++) {
                    if (select.options[i].value !== 'create_new') {
                        select.selectedIndex = i;
                        break;
                    }
                }
                console.log('Reset dropdown selection for account:', accountId);
            }

            return true;
        } catch (error) {
            console.error('Error hiding widget for account:', accountId, error);
            return false;
        }
    }

    // Real-time validation feedback
    function setupWidgetValidation(accountId) {
        const widget = document.getElementById('create-account-widget-' + accountId);
        if (!widget) return;

        const nameInput = widget.querySelector('input[name*="[name]"]');
        const typeSelect = widget.querySelector('select[name*="[type]"]');

        if (nameInput) {
            nameInput.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.classList.add('is-invalid');
                    console.warn('Account name validation failed for:', accountId);
                } else {
                    this.classList.remove('is-invalid');
                }
            });

            nameInput.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
        }

        if (typeSelect) {
            typeSelect.addEventListener('change', function() {
                if (!this.value) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        }
    }

    // Initialize widget on page load
    document.addEventListener('DOMContentLoaded', function() {
        const accountId = '{{ $account['import_account']->id }}';
        console.log('Initializing create account widget for:', accountId);

        // Initialize edit states
        window.accountEditStates = window.accountEditStates || {};
        window.accountEditStates[accountId] = {
            nameEditing: false,
            balanceEditing: false
        };

        // Set up real-time validation
        setupWidgetValidation(accountId);

        // Initialize duplicate checking
        if (window.updateDuplicateStatus) {
            window.updateDuplicateStatus(accountId);
        }

        // Set up custom event listeners
        document.addEventListener('accountWidgetToggled', function(event) {
            const detail = event.detail;
            console.log('Account widget toggle event:', detail);

            if (detail.accountId === accountId && detail.success && detail.isCreateNew) {
                // Widget was successfully shown, set up any additional handlers
                setupWidgetValidation(accountId);
                // Initialize edit states
                window.accountEditStates[accountId] = {
                    nameEditing: false,
                    balanceEditing: false
                };
            }
        });

        // Set up keyboard shortcuts for inline editing
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                // Cancel any active edits on Escape
                const state = window.accountEditStates[accountId];
                if (state && state.nameEditing) {
                    toggleAccountNameEdit(accountId, false);
                }
                if (state && state.balanceEditing) {
                    toggleBalanceCurrencyEdit(accountId, false);
                }
            }
            if (event.key === 'Enter' && (event.target.id.includes('name-edit') || event.target.id.includes('balance-edit'))) {
                // Commit edits on Enter
                if (event.target.id.includes('name-edit')) {
                    commitAccountNameEdit(accountId);
                } else if (event.target.id.includes('balance-edit')) {
                    commitBalanceCurrencyEdit(accountId);
                }
                event.preventDefault();
            }
        });
    });

    // Duplicate function definitions removed - using the globally accessible versions above

    // Form validation enhancement
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(event) {
                // Validate create account widgets that are visible
                let isValid = true;
                const visibleWidgets = document.querySelectorAll('.collapse.show[id^="create-account-widget-"]');

                console.log('Form submission: Validating', visibleWidgets.length, 'visible widgets');

                visibleWidgets.forEach(widget => {
                    const nameInput = widget.querySelector('input[name*="[name]"]');
                    const typeSelect = widget.querySelector('select[name*="[type]"]');

                    if (nameInput && !nameInput.value.trim()) {
                        nameInput.classList.add('is-invalid');
                        isValid = false;
                        console.warn('Form validation failed: Account name empty');
                    } else if (nameInput) {
                        nameInput.classList.remove('is-invalid');
                    }

                    if (typeSelect && !typeSelect.value) {
                        typeSelect.classList.add('is-invalid');
                        isValid = false;
                        console.warn('Form validation failed: Account type not selected');
                    } else if (typeSelect) {
                        typeSelect.classList.remove('is-invalid');
                    }
                });

                if (!isValid) {
                    console.error('Form validation failed, preventing submission');
                    event.preventDefault();
                    event.stopPropagation();
                } else {
                    console.log('Form validation passed');
                }
            });
        }
    });
</script>
