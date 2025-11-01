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
        loadingParsedDate: true,
        dateFormat: 'Y-m-d',
        parsedDateFormat: 'hello',
        dateRange: '',
        detectionMethod: '',
        getParsedDate() {
            this.loadingParsedDate = true;
            const parseUrl = './import/php_date';
            window.axios.get(parseUrl, {params: {format: this.dateFormat}}).then((response) => {
                this.parsedDateFormat = response.data.result;
                this.loadingParsedDate = false;
            }).catch((error) => {
                this.parsedDateFormat = ':(';
                this.loadingParsedDate = false;
            });
        },
        init() {
            this.dateRange = document.querySelector('#date-range-helper').dataset.dateRange;
            this.detectionMethod = document.querySelector('#detection-method-helper').dataset.method;
            this.dateFormat = document.querySelector('#date-format-helper').dataset.dateFormat;
            console.log('detection method', this.detectionMethod);
            this.getParsedDate();
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
