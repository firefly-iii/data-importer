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
        importFlows: {},
        init() {
            this.checkFireflyIIIConnection();
        },
        loadImportFlows() {
            let importFlowUrl = './api/import-flows';
            window.axios.get(importFlowUrl).then((response) => {
                let flows = response.data;
                for (let i = 0; i < flows.length; i++) {
                    if (flows.hasOwnProperty(i)) {
                        let flow                   = flows[i];
                        flow.loading               = true;
                        flow.errorMessage          = '';
                        flow.error                 = false;
                        flow.authenticated         = false;
                        this.importFlows[flow.key] = flow;
                    }

                }
                this.loading = false;
                this.validateAuthentications();
            });
        },
        validateAuthentications() {
            for (let flow in this.importFlows) {
                if (this.importFlows.hasOwnProperty(flow)) {
                    console.log('Validate ' + flow);
                    if (!this.importFlows[flow].enabled) {
                        console.log('Skip ' + flow);
                        this.importFlows[flow].loading = false;
                        continue;
                    }
                    let validateUrl = './api/import-flows/validate/' + flow;
                    window.axios.get(validateUrl).then((response) => {
                        let result  = response.data.result;
                        let message = response.data.message;
                        console.log('Result for ' + flow + ' = ' + result);
                        if ('NODATA' === result) {
                            // only needs to stop loading, it defaults to "unauthenticated".
                            this.importFlows[flow].loading = false;
                        }
                        if ('NOK' === message) {
                            this.importFlows[flow].loading       = false;
                            this.importFlows[flow].authenticated = false;
                            this.importFlows[flow].error         = true;
                            this.importFlows[flow].errorMessage  = message;
                        }
                        // if ('OK' === message) {
                        //     this.importFlows[flow].loading       = false;
                        //     this.importFlows[flow].authenticated = true;
                        // }
                    }).catch((error) => {
                        console.warn(flow + ' is broken');
                        this.importFlows[flow].loading      = false;
                        this.importFlows[flow].error        = true;
                        this.importFlows[flow].errorMessage = 'Could not load import provider';
                    });
                }
            }
        },
        checkFireflyIIIConnection() {
            let validateUrl = './api/firefly-iii/validate';
            //window.axios.defaults.headers.common['X-CSRF-TOKEN'] = document.head.querySelector('meta[name="csrf-token"]').content;
            window.axios.get(validateUrl).then((response) => {
                let result     = response.data.result;
                let statusCode = response.data.status_code;
                console.log('Result is ', result)

                if ('OK' === result) {
                    this.loadImportFlows();
                    return;
                }

                if ('NEEDS_OAUTH' === result) {
                    console.log('OAuth authentication required, redirecting to token page');
                    window.location.href = tokenPageUrl;
                    return;
                }
                console.log(statusCode);
                if (401 === statusCode) {
                    this.pageProperties.connectionError        = true;
                    this.pageProperties.connectionErrorMessage = 'Firefly III refused the connection. It believes you are not authenticated properly. Perhaps you\'ve not copy-pasted the access token correctly, or it has expired.';
                    return;
                }


                this.pageProperties.connectionError        = true;
                this.pageProperties.connectionErrorMessage = response.data.message;
            }).catch((error) => {
                if (500 === error.response.status) {
                    this.pageProperties.connectionError        = true;
                    this.pageProperties.connectionErrorMessage = 'A "500"-error occurred inside the data importer while trying to check the connection to Firefly III. Sorry about that. Please follow the link to the documentation and consult the log files to see what happened. Sorry about this!';
                    return;
                }
                console.log(error.response.status);
                this.pageProperties.connectionError        = true;
                this.pageProperties.connectionErrorMessage = error;
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
