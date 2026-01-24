/*
 * enablebanking.js
 * Copyright (c) 2025 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

import '../../boot/bootstrap.js';


let enablebanking = function () {
    return {
        selectedCountry: 'XX',
        selectedBank: 'XX',
        loadedCountry: '',
        init() {
            console.log('hello enablebanking');

            // Get the currently selected country from the URL (the country we loaded banks for)
            const urlParams = new URLSearchParams(window.location.search);
            this.loadedCountry = urlParams.get('country') || '';

            // Initialize selectedCountry from the dropdown's current value
            const countrySelect = document.querySelector('select[name="country"]');
            if (countrySelect) {
                // Find the selected option
                const selectedOption = countrySelect.querySelector('option[selected]');
                if (selectedOption && selectedOption.value !== 'XX') {
                    this.selectedCountry = selectedOption.value;
                }
            }

            // Initialize selectedBank from the bank dropdown if it exists
            const bankSelect = document.querySelector('select[name^="bank_"]');
            if (bankSelect) {
                const selectedBankOption = bankSelect.querySelector('option[selected]');
                if (selectedBankOption && selectedBankOption.value !== 'XX') {
                    this.selectedBank = selectedBankOption.value;
                }
            }

            // Watch for country changes - reload page if different country is selected
            this.$watch('selectedCountry', (value) => {
                if (value && value !== 'XX' && value !== this.loadedCountry) {
                    // Reload the page with the new country to fetch its banks
                    const url = new URL(window.location.href);
                    url.searchParams.set('country', value);
                    window.location.href = url.toString();
                }
            });
        },
    }
}


function loadPage() {
    Alpine.data('enablebanking', () => enablebanking());
    Alpine.start();
}

// wait for load until bootstrapped event is received.
document.addEventListener('data-importer-bootstrapped', () => {
    console.log('Loaded through event listener.');
    loadPage();
});
// or is bootstrapped before event is triggered.
if (window.bootstrapped) {
    console.log('Loaded through window variable.');
    loadPage();
}
