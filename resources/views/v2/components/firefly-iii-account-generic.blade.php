@if('disabled' !== $account['import_account']->status)
    <select style="width:100%;"
            class="custom-select custom-select-sm form-control"
            name="accounts[{{  $account['import_account']->id }}]"
            onchange="handleAccountSelection('{{ $account['import_account']->id }}', this.value)"
            id="account-select-{{ $account['import_account']->id }}">

        <!-- Create New Account option -->
        <option value="create_new"
                @php
                    $configuredAccount = $configuration->getAccounts()[$account['import_account']->id] ?? null;
                    $mappedTo = $account['mapped_to'] ?? null;
                    $isCreateNewSelected = (!$configuredAccount || $configuredAccount === 'create_new') && !$mappedTo;
                @endphp
                @if($isCreateNewSelected) selected @endif>➕ Create new account</option>

        <!-- loop all Firefly III account groups (assets, liabilities) -->
        @foreach($account['firefly_iii_accounts'] as $accountGroupKey => $accountGroup)
            {{-- $accountGroupKey is 'assets' or 'liabilities' --}}
            {{-- $accountGroup is the array of account objects --}}
            @if(is_array($accountGroup) && count($accountGroup) > 0)
                <optgroup label="{{ ucfirst($accountGroupKey) }}">
                    @foreach($accountGroup as $ff3Account) {{-- $ff3Account is now a single Firefly III Account object/array --}}
                        <option value="{{ $ff3Account->id  }}"
                                @php
                                    $isSelected = false;
                                    // First check if mapped_to matches this account
                                    if (isset($account['mapped_to']) && (string) $account['mapped_to'] === (string) $ff3Account->id) {
                                        $isSelected = true;
                                    }
                                    // Otherwise check configuration for pre-selection
                                    else {
                                        foreach($configuration->getAccounts() as $key => $preConfig) {
                                            if((string) $key === (string) $account['import_account']->id && (int) $preConfig === (int) ($ff3Account->id)) {
                                                $isSelected = true;
                                                break;
                                            }
                                        }
                                    }
                                @endphp
                                @if($isSelected) selected="selected" @endif
                                label="{{ $ff3Account->name  }} @if('' !== (string) $ff3Account->iban) ({{ $ff3Account->iban}}) @endif">
                            {{ $ff3Account->name  }} @if('' !== (string)$ff3Account->iban) ({{ $ff3Account->iban }}) @endif
                        </option>
                    @endforeach
                </optgroup>
            @endif
        @endforeach
    </select>

    <!-- Status text for existing account selection -->
    <div id="existing-account-status-container-{{ $account['import_account']->id }}" class="p-3">
        <div class="row">
            <div class="col-12">
                <div class="text-end">
                    <small id="existing-account-status-{{ $account['import_account']->id }}" class="text-success">
                        <i class="fas fa-check-circle me-1"></i>Ready for import
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden field to indicate account creation is requested when create_new is selected -->
    <input type="hidden"
           id="create-new-indicator-{{ $account['import_account']->id }}"
           name="new_accounts[{{  $account['import_account']->id }}][create]"
           value="0">

    <!-- #10550 do not set do_import to true for ALL accounts. -->
    <!--
    <input type="hidden"
           name="do_import[{{  $account['import_account']->id }}]"
           value="1">
   -->

    <script>
        function handleAccountSelection(accountId, selectedValue) {
            const isCreateNew = selectedValue === 'create_new';

            console.log('Account selection changed:', {
                accountId: accountId,
                selectedValue: selectedValue,
                isCreateNew: isCreateNew
            });

            // Update hidden field to indicate create new status
            const createIndicator = document.getElementById('create-new-indicator-' + accountId);
            if (createIndicator) {
                createIndicator.value = isCreateNew ? '1' : '0';
                console.log('Updated create indicator for account:', accountId, 'value:', createIndicator.value);
            } else {
                console.warn('Create new indicator not found for account:', accountId);
            }

            // Toggle widget visibility with enhanced error handling
            try {
                if (typeof window.toggleAccountNameEditing === 'function') {
                    window.toggleAccountNameEditing(accountId, isCreateNew);
                } else {
                    // Fallback: directly control widget if toggle function not available
                    console.warn('toggleAccountNameEditing function not found, using fallback');
                    const widget = document.getElementById('create-account-widget-' + accountId);

                    if (widget) {
                        if (isCreateNew) {
                            // Show widget
                            if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                                const collapse = new bootstrap.Collapse(widget, { show: true });
                            } else {
                                widget.classList.add('show');
                            }
                            console.log('Showed widget for account:', accountId);
                        } else {
                            // Hide widget
                            if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                                const collapse = new bootstrap.Collapse(widget, { hide: true });
                            } else {
                                widget.classList.remove('show');
                            }
                            console.log('Hid widget for account:', accountId);
                        }
                    } else {
                        console.error('Widget not found for account:', accountId);
                    }
                }
            } catch (error) {
                console.error('Error in widget coordination for account:', accountId, error);
            }

            // Toggle existing account status container visibility
            const existingAccountStatusContainer = document.getElementById('existing-account-status-container-' + accountId);
            if (existingAccountStatusContainer) {
                if (isCreateNew) {
                    // Hide entire status container when creating new account
                    existingAccountStatusContainer.style.display = 'none';
                } else {
                    // Show status container when existing account selected
                    existingAccountStatusContainer.style.display = 'block';
                }
                console.log('Updated existing account status container visibility for account:', accountId, 'visible:', !isCreateNew);
            } else {
                console.warn('Existing account status container not found for account:', accountId);
            }

            // Validate form fields if widget is being shown
            if (isCreateNew) {
                setTimeout(() => validateAccountWidget(accountId), 100);
            }
        }

        // Validate widget form fields
        function validateAccountWidget(accountId) {
            const widget = document.getElementById('create-account-widget-' + accountId);
            if (!widget || !widget.classList.contains('show')) {
                return;
            }

            const nameInput = widget.querySelector('input[name*="[name]"]');
            const typeSelect = widget.querySelector('select[name*="[type]"]');

            if (nameInput && !nameInput.value.trim()) {
                console.warn('Account name is empty for:', accountId);
            }

            if (typeSelect && !typeSelect.value) {
                console.warn('Account type not selected for:', accountId);
            }
        }

        // Handle import checkbox state changes - Visibility-based UX
        function handleImportToggle(accountId, isImportEnabled) {
            const ff3AccountContent = document.getElementById('firefly-account-content-' + accountId);
            const notImportedText = document.getElementById('not-imported-text-' + accountId);

            if (ff3AccountContent && notImportedText) {
                if (isImportEnabled) {
                    // Show Firefly III account content
                    ff3AccountContent.style.display = 'block';
                    notImportedText.style.display = 'none';
                } else {
                    // Hide Firefly III account content and show "Not Imported" text
                    ff3AccountContent.style.display = 'none';
                    notImportedText.style.display = 'block';
                }

                console.log('Import toggle for account:', accountId, 'enabled:', isImportEnabled);
            } else {
                console.error('Could not find firefly account content or not imported text elements for account:', accountId);
            }
        }

        // Enhanced initialization with error handling
        document.addEventListener('DOMContentLoaded', function() {
            const select = document.getElementById('account-select-{{ $account['import_account']->id }}');
            const importCheckbox = document.getElementById('do_import_{{ $account['import_account']->id }}');
            const accountId = '{{ $account['import_account']->id }}';

            if (select) {
                console.log('Initializing account selection for:', accountId);

                // Set up change event listener with enhanced handling
                select.addEventListener('change', function(event) {
                    try {
                        handleAccountSelection(accountId, event.target.value);
                    } catch (error) {
                        console.error('Error handling account selection change:', error);
                    }
                });

                // Initialize current state
                try {
                    if (importCheckbox && !importCheckbox.checked) {
                        handleImportToggle(accountId, false);
                    } else {
                        handleAccountSelection(accountId, select.value);
                    }
                } catch (error) {
                    console.error('Error during initialization for account:', accountId, error);
                }
            } else {
                console.error('Account select element not found for:', accountId);
            }

            // Set up import checkbox listener
            if (importCheckbox) {
                importCheckbox.addEventListener('change', function(event) {
                    try {
                        handleImportToggle(accountId, event.target.checked);
                    } catch (error) {
                        console.error('Error handling import checkbox change:', error);
                    }
                });

                console.log('Import checkbox listener attached for:', accountId);
            } else {
                console.error('Import checkbox not found for:', accountId);
            }
        });
    </script>
@endif
