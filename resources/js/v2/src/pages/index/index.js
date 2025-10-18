/*
 * index.js
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


let index = function () {
    return {
        pageProperties: {
            connectionError: false,
            connectionErrorMessage: '',
        },
        loadingFunctions: {
            file: true,
            gocardless: true,
            spectre: true,
            simplefin: true,
            lunchflow: true,
            obg: true,
            eb: true,
            teller: true,
            fints: true,
            basiq: true,
        },
        errors: {
            spectre: '',
            gocardless: '',
            simplefin: '',
            lunchflow: '',
            obg: '',
            eb: '',
            teller: '',
            fints: '',
            basiq: '',
        },
        importFunctions: {
            file: false,
            gocardless: false,
            spectre: false,
            simplefin: false,
            lunchflow: false,
            obg: false,
            eb: false,
            teller: false,
            fints: false,
            basiq: false,
        },
        init() {
            this.checkFireflyIIIConnection();
        },
        checkFireflyIIIConnection() {
            let validateUrl  = './token/validate';
            let tokenPageUrl = './token';
            let providers = ['file', 'gocardless', 'spectre', 'simplefin', 'lunchflow', 'obg', 'eb', 'teller', 'fints', 'basiq'];
            window.axios.get(validateUrl).then((response) => {
                let message = response.data.result;
                // console.log('message is ', message)

                if ('OK' === message) {
                    this.loadingFunctions.file = false;
                    this.importFunctions.file  = true;
                    return;
                }

                if ('NEEDS_OAUTH' === message) {
                    console.log('OAuth authentication required, redirecting to token page');
                    window.location.href = tokenPageUrl;
                    return;
                }
                for (let i = 0; i < providers.length; i++) {
                    let provider = providers[i];
                    this.loadingFunctions[provider] = false;
                    this.importFunctions[provider]  = false;
                    this.errors[provider]           = '';
                }

                this.pageProperties.connectionError        = true;
                this.pageProperties.connectionErrorMessage = response.data.message;
            }).catch((error) => {

                for (let i = 0; i < providers.length; i++) {
                    let provider = providers[i];
                    this.loadingFunctions[provider] = false;
                    this.importFunctions[provider]  = false;
                    this.errors[provider]           = 'The "'+provider+'"-provider is not configured correctly. Please check your settings.';
                }

                this.pageProperties.connectionError        = true;
                this.pageProperties.connectionErrorMessage = error;
            }).finally(() => {
                if (false === this.pageProperties.connectionError) {
                    for (let i = 0; i < providers.length; i++) {
                        let provider = providers[i];
                        this.checkProvider(provider);
                    }
                }
            });
        },
        checkProvider (provider) {
            let validateUrl = './validate/' + provider;
            window.axios.get(validateUrl).then((response) => {
                let message = response.data.result;
                if ('NODATA' === message || 'OK' === message) {
                    this.loadingFunctions[provider] = false;
                    this.importFunctions[provider]  = true;
                    return;
                }
                this.loadingFunctions[provider] = false;
                this.importFunctions[provider]  = false;
                this.errors[provider]           = 'The "'+provider+'"-provider is not configured, or configured incorrectly and cannot be used to import data.';
            }).catch((error) => {
                this.loadingFunctions[provider] = false;
                this.importFunctions[provider]  = false;
                this.errors[provider]           = 'The "'+provider+'"-provider is not configured, or configured incorrectly and cannot be used to import data.';
                console.error(error);
            });
        },
    }
}


function loadPage() {
    Alpine.data('index', () => index());
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
