<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="{{ route('index') }}/">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">


    <script type="text/javascript">
        /*!
 * Color mode toggler for Bootstrap's docs (https://getbootstrap.com/)
 * Copyright 2011-2023 The Bootstrap Authors
 * Licensed under the Creative Commons Attribution 3.0 Unported License.
 */

        (() => {
            'use strict'
            // todo store just happens to store in localStorage but if not, this would break.
            const getStoredTheme = () => JSON.parse(localStorage.getItem('darkMode'))

            const getPreferredTheme = () => {
                const storedTheme = getStoredTheme()
                if (storedTheme) {
                    return storedTheme
                }

                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
            }

            const setTheme = theme => {
                if (theme === 'browser' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark')
                    window.theme = 'dark';
                    return;
                }
                if (theme === 'browser' && window.matchMedia('(prefers-color-scheme: light)').matches) {
                    window.theme = 'light';
                    document.documentElement.setAttribute('data-bs-theme', 'light')
                    return;
                }
                document.documentElement.setAttribute('data-bs-theme', theme)
                window.theme = theme;
            }

            setTheme(getPreferredTheme())

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                const storedTheme = getStoredTheme()
                if (storedTheme !== 'light' && storedTheme !== 'dark') {
                    setTheme(getPreferredTheme())
                }
            })
        })()
    </script>
    @yield('styles')
    @vite(['src/sass/app.scss'])



    <title>Firefly III Data Importer // {{ $pageTitle ?? 'No title' }}</title>
</head>
<body>
@if(config('importer.is_external'))
<div class="alert alert-warning" role="alert">
    This Firefly III Data Importer installation is <strong>publicly accessible</strong>. Please read <a
        href="https://docs.firefly-iii.org/references/data-importer/public/" class="alert-link" target="_blank">the considerations</a> (link opens in a new
    window or tab).
</div>
@endif

@yield('content')

<!-- Optional JavaScript -->

@yield('scripts')

@if('' != config('importer.tracker_site_id') and '' != config('importer.tracker_url'))
<!-- Matomo -->
<script>
    var _paq = window._paq = window._paq || [];
    /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    (function () {
        var u = "{{ config('importer.tracker_url') }}";
        _paq.push(['setTrackerUrl', u + 'matomo.php']);
        _paq.push(['setSiteId', '{{ config('importer.tracker_site_id') }}']);
        var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
        g.async = true;
        g.src = u + 'matomo.js';
        s.parentNode.insertBefore(g, s);
    })();
</script>
<noscript><p><img src="{{ config('importer.tracker_url') }}matomo.php?idsite={{ config('importer.tracker_site_id') }}&amp;rec=1" style="border:0;" alt=""/>
    </p></noscript>
<!-- End Matomo Code -->
@endif
<script>
// Global SimpleFIN account management functions
// Moved from component to prevent timing/conflict issues

// Initialize global state
window.accountEditStates = window.accountEditStates || {};

// Account name inline editing functions
window.toggleAccountNameEdit = function(accountId, isEditing, retryCount = 0) {
    try {
        // Check if widget is visible first
        const widget = document.getElementById('create-account-widget-' + accountId);
        if (!widget || !widget.classList.contains('show')) {
            console.warn('Widget not visible for account:', accountId, 'Attempting to show widget first');

            // Try to show widget if it exists but is hidden
            if (widget && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                const collapse = new bootstrap.Collapse(widget, { show: true });
                // Retry after widget is shown
                setTimeout(() => window.toggleAccountNameEdit(accountId, isEditing, retryCount + 1), 200);
                return true;
            }
        }

        const display = document.getElementById('account-name-display-' + accountId);
        const input = document.getElementById('account-name-edit-' + accountId);
        const editBtn = document.getElementById('edit-name-btn-' + accountId);
        const commitBtn = document.getElementById('commit-name-btn-' + accountId);
        const cancelBtn = document.getElementById('cancel-name-btn-' + accountId);

        if (!display || !input || !editBtn || !commitBtn || !cancelBtn) {
            console.error('Name edit elements not found for account:', accountId, {
                display: !!display,
                input: !!input,
                editBtn: !!editBtn,
                commitBtn: !!commitBtn,
                cancelBtn: !!cancelBtn,
                widgetExists: !!widget,
                widgetVisible: widget?.classList.contains('show'),
                retryCount: retryCount
            });

            // Retry mechanism for timing issues
            if (retryCount < 3) {
                console.log('Retrying toggleAccountNameEdit for account:', accountId, 'attempt:', retryCount + 1);
                setTimeout(() => window.toggleAccountNameEdit(accountId, isEditing, retryCount + 1), 100);
                return true;
            }

            // Additional debugging: log all elements with this account ID
            const allElements = document.querySelectorAll('[id*="' + accountId + '"]');
            console.error('All elements with account ID ' + accountId + ':', Array.from(allElements).map(el => el.id));

            return false;
        }

        window.accountEditStates[accountId] = window.accountEditStates[accountId] || {};
        window.accountEditStates[accountId].nameEditing = isEditing;

        console.log('toggleAccountNameEdit called:', {
            accountId: accountId,
            isEditing: isEditing,
            timestamp: new Date().toISOString()
        });

        if (isEditing) {
            display.classList.add('d-none');
            input.classList.remove('d-none');
            editBtn.classList.add('d-none');
            commitBtn.classList.remove('d-none');
            cancelBtn.classList.remove('d-none');
            input.focus();
            input.select();

            // Add keyboard event handler for Enter/Escape
            input.addEventListener('keydown', function(event) {
                console.log('Key pressed in account name edit:', event.key, 'for account:', accountId);
                if (event.key === 'Enter') {
                    event.preventDefault();
                    event.stopPropagation();
                    console.log('Enter key - committing edit for account:', accountId);
                    window.commitAccountNameEdit(accountId);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    console.log('Escape key - cancelling edit for account:', accountId);
                    window.toggleAccountNameEdit(accountId, false);
                }
            }, { once: true });
        } else {
            display.classList.remove('d-none');
            input.classList.add('d-none');
            editBtn.classList.remove('d-none');
            commitBtn.classList.add('d-none');
            cancelBtn.classList.add('d-none');
            // Reset input to original value on cancel
            input.value = display.textContent.trim();
        }

        return true;
    } catch (error) {
        console.error('Error toggling name edit for account:', accountId, error);
        return false;
    }
}

window.commitAccountNameEdit = function(accountId) {
    try {
        const display = document.getElementById('account-name-display-' + accountId);
        const input = document.getElementById('account-name-edit-' + accountId);

        if (!display || !input) {
            console.error('Name edit elements not found for account:', accountId);
            return false;
        }

        const newName = input.value.trim();
        if (!newName) {
            // Use placeholder default if empty
            const defaultName = 'New Account';
            input.value = defaultName;
            display.textContent = defaultName;
        } else {
            display.textContent = newName;
        }

        window.toggleAccountNameEdit(accountId, false);

        // Trigger duplicate check after name commit
        window.updateDuplicateStatus(accountId);

        return true;
    } catch (error) {
        console.error('Error committing name edit for account:', accountId, error);
        return false;
    }
}

// Real-time duplicate account validation
window.updateDuplicateStatus = function(accountId) {
    try {
        const nameInput = document.getElementById('account-name-edit-' + accountId);
        const nameDisplay = document.getElementById('account-name-display-' + accountId);
        const typeSelect = document.getElementById('new-account-type-' + accountId);
        const statusElement = document.getElementById('widget-status-' + accountId);

        if (!statusElement) {
            console.log('DUPLICATE_CHECK: Status element not found for account:', accountId);
            return false;
        }

        // Get current name value (from input if editing, display if not)
        let accountName = '';
        if (nameInput && !nameInput.classList.contains('d-none')) {
            accountName = nameInput.value.trim();
        } else if (nameDisplay) {
            accountName = nameDisplay.textContent.trim();
        }

        // Get current type value
        const accountType = typeSelect ? typeSelect.value : '';

        console.log('DUPLICATE_CHECK: Checking account', {
            accountId: accountId,
            name: accountName,
            type: accountType
        });

        // Clear validation if name or type is empty
        if (!accountName || !accountType) {
            statusElement.innerHTML = '<i class="fas fa-info-circle me-1"></i>Complete name and type';
            statusElement.className = 'text-muted';
            return true;
        }

        // Show checking status
        statusElement.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Checking for duplicates...';
        statusElement.className = 'text-info';

        // Make AJAX request to duplicate check endpoint
        fetch('/import/check-duplicate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                name: accountName,
                type: accountType
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('DUPLICATE_CHECK: Response received', data);

            if (data.isDuplicate) {
                statusElement.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>' + data.message;
                statusElement.className = 'text-warning';
            } else {
                statusElement.innerHTML = '<i class="fas fa-check-circle me-1"></i>Ready for import';
                statusElement.className = 'text-success';
            }
        })
        .catch(error => {
            console.error('DUPLICATE_CHECK: Error during duplicate check', error);
            // Graceful degradation - show ready status on error
            statusElement.innerHTML = '<i class="fas fa-check-circle me-1"></i>Ready for import';
            statusElement.className = 'text-muted';
        });

        return true;
    } catch (error) {
        console.error('DUPLICATE_CHECK: Exception in updateDuplicateStatus', error);
        return false;
    }
}

// Enhanced global function for dropdown coordination
window.toggleAccountNameEditing = function(accountId, isCreateNew) {
    try {
        const widget = document.getElementById('create-account-widget-' + accountId);

        if (!widget) {
            console.error('toggleAccountNameEditing: Widget not found for account:', accountId);
            return false;
        }

        console.log('toggleAccountNameEditing called for account:', accountId, 'createNew:', isCreateNew);

        if (isCreateNew) {
            widget.classList.add('show');
            // Trigger validation check when widget is shown for "Create New Account"
            if (window.updateDuplicateStatus) {
                setTimeout(() => window.updateDuplicateStatus(accountId), 100);
            }
        } else {
            widget.classList.remove('show');
        }

        return true;
    } catch (error) {
        console.error('Error in toggleAccountNameEditing for account:', accountId, error);
        return false;
    }
}

// Balance and currency inline editing functions
window.toggleBalanceCurrencyEdit = function(accountId, isEditing) {
    try {
        const balanceDisplay = document.getElementById('balance-display-' + accountId);
        const currencyDisplay = document.getElementById('currency-display-' + accountId);
        const balanceInput = document.getElementById('balance-edit-' + accountId);
        const currencySelect = document.getElementById('currency-edit-' + accountId);
        const editBalanceBtn = document.getElementById('edit-balance-btn-' + accountId);
        const commitBalanceBtn = document.getElementById('commit-balance-btn-' + accountId);
        const cancelBalanceBtn = document.getElementById('cancel-balance-btn-' + accountId);

        if (!balanceDisplay || !currencyDisplay || !balanceInput || !currencySelect || !editBalanceBtn || !commitBalanceBtn || !cancelBalanceBtn) {
            console.error('Balance/currency edit elements not found for account:', accountId);
            return false;
        }

        window.accountEditStates[accountId] = window.accountEditStates[accountId] || {};
        window.accountEditStates[accountId].balanceEditing = isEditing;

        if (isEditing) {
            balanceDisplay.classList.add('d-none');
            currencyDisplay.classList.add('d-none');
            balanceInput.classList.remove('d-none');
            currencySelect.classList.remove('d-none');
            editBalanceBtn.classList.add('d-none');
            commitBalanceBtn.classList.remove('d-none');
            cancelBalanceBtn.classList.remove('d-none');
            balanceInput.focus();
            balanceInput.select();

            // Add keyboard event handlers for Enter/Escape
            balanceInput.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    event.stopPropagation();
                    window.commitBalanceCurrencyEdit(accountId);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    window.toggleBalanceCurrencyEdit(accountId, false);
                }
            }, { once: true });

            // Also add handler to currency select
            currencySelect.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    event.stopPropagation();
                    window.commitBalanceCurrencyEdit(accountId);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    window.toggleBalanceCurrencyEdit(accountId, false);
                }
            }, { once: true });
        } else {
            balanceDisplay.classList.remove('d-none');
            currencyDisplay.classList.remove('d-none');
            balanceInput.classList.add('d-none');
            currencySelect.classList.add('d-none');
            editBalanceBtn.classList.remove('d-none');
            commitBalanceBtn.classList.add('d-none');
            cancelBalanceBtn.classList.add('d-none');
        }

        return true;
    } catch (error) {
        console.error('Error toggling balance/currency edit for account:', accountId, error);
        return false;
    }
}

window.commitBalanceCurrencyEdit = function(accountId) {
    try {
        const balanceDisplay = document.getElementById('balance-display-' + accountId);
        const currencyDisplay = document.getElementById('currency-display-' + accountId);
        const balanceInput = document.getElementById('balance-edit-' + accountId);
        const currencySelect = document.getElementById('currency-edit-' + accountId);

        if (!balanceDisplay || !currencyDisplay || !balanceInput || !currencySelect) {
            console.error('Balance/currency edit elements not found for account:', accountId);
            return false;
        }

        const newBalance = parseFloat(balanceInput.value);
        const currencyCode = currencySelect.value;

        if (!isNaN(newBalance)) {
            balanceDisplay.textContent = newBalance.toFixed(2);
        }

        if (currencyCode && currencyCode !== '') {
            currencyDisplay.textContent = currencyCode;
        }

        window.toggleBalanceCurrencyEdit(accountId, false);
        return true;
    } catch (error) {
        console.error('Error committing balance/currency edit for account:', accountId, error);
        return false;
    }
}

// Show/hide account role section based on account type
window.updateAccountRoleVisibility = function(accountId) {
    try {
        const typeSelect = document.getElementById(`new-account-type-${accountId}`);
        const roleSection = document.getElementById(`account-role-section-${accountId}`);

        if (typeSelect && roleSection) {
            if (typeSelect.value === 'asset') {
                roleSection.style.display = 'block';
            } else {
                roleSection.style.display = 'none';
            }
        }

        // Trigger duplicate check when account type changes
        if (window.updateDuplicateStatus) {
            window.updateDuplicateStatus(accountId);
        }
        return true;
    } catch (error) {
        console.error('Error in updateAccountRoleVisibility for account:', accountId, error);
        return false;
    }
}

console.log('Global SimpleFIN account management functions loaded');
</script>

</body>
</html>
