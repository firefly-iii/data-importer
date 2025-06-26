/*
 * index.js
 * Copyright (c) 2024 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
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
        identifier: '',
        pageStatus: {
            triedToStart: false,
            status: 'init',
        },
        post: {
            result: '',
            errored: false,
            running: false,
            done: false,
        },
        messages: {
            messages: [],
            warnings: [],
            errors: [],
        },
        progress: {
            currentTransaction: 0,
            totalTransactions: 0,
            progressPercentage: 0,
        },
        checkCount: 0,
        maxCheckCount: 1200,
        manualRefreshAvailable: false,
        functionName() {

        },
        getProgressPercentage() {
            return this.progress.progressPercentage;
        },
        getProgressWidth() {
            return this.progress.progressPercentage + '%';
        },
        getProgressDisplay() {
            if (this.progress.totalTransactions === 0) {
                return '';
            }
            return this.progress.currentTransaction + ' / ' + this.progress.totalTransactions + ' transactions';
        },
        hasProgressData() {
            return this.progress.totalTransactions > 0;
        },
        showJobMessages() {
            console.log(this.messages);
            return Object.values(this.messages.messages).length > 0 || Object.values(this.messages.warnings).length > 0 || Object.values(this.messages.errors).length > 0;
        },
        showStartButton() {
            return('init' === this.pageStatus.status || 'waiting_to_start' === this.pageStatus.status) && false === this.pageStatus.triedToStart && false === this.post.errored;
        },
        showWaitingButton() {
            return 'waiting_to_start' === this.pageStatus.status && true === this.pageStatus.triedToStart && false === this.post.errored;
        },
        showTooManyChecks() {
            return 'too_long_checks' === this.pageStatus.status;
        },
        refreshStatus() {
            console.log('Manual refresh triggered');
            this.checkCount = 0;
            this.manualRefreshAvailable = false;
            this.pageStatus.status = 'submission_running';
            this.getJobStatus();
        },
        showPostError() {
            return 'submission_errored' === this.pageStatus.status || this.post.errored
        },
        showWhenRunning() {
            return 'submission_running' === this.pageStatus.status;
        },
        showWhenDone() {
            return 'submission_done' === this.pageStatus.status;
        },
        showIfError() {
            return 'submission_errored' === this.pageStatus.status;
        },
        init() {
            this.identifier = document.querySelector('#data-helper').dataset.identifier;
            console.log('Identifier is ' + this.identifier);
            this.getJobStatus();
        },
        startJobButton() {
            this.pageStatus.triedToStart = true;
            this.pageStatus.status = 'waiting_to_start';
            this.postJobStart();
        },
        postJobStart() {
            this.triedToStart = true;
            this.post.running = true;
            const jobStartUrl = './import/submit/start';
            window.axios.post(jobStartUrl, null,{params: {identifier: this.identifier}}).then((response) => {
                console.log('POST was OK');
                this.getJobStatus();
                this.post.running = false;
            }).catch((error) => {
                if(error.response) {
                    // The request was made and the server responded with a status code
                    // that falls out of the range of 2xx
                    console.error('Error response:', error.response.data);
                    console.error('Status code:', error.response.status);

                    // respond to some different error codes:
                    if(524 === error.response.status) {
                        console.error('Error 524: A timeout occurred. The job is probably still running.');
                        this.triedToStart = true;
                        this.getJobStatus();
                    }
                } else {
                    console.error('JOB HAS FAILED :(');
                    this.post.result = error;
                    this.post.errored = true;
                }
            }).finally(() => {
                    this.getJobStatus();
                    this.triedToStart = true;
                }
            );
            this.getJobStatus();
            this.triedToStart = true;
        },
        getJobStatus() {
            this.checkCount++;
            if (this.checkCount >= this.maxCheckCount) {
                console.log('Block getJobStatus (' + this.checkCount + ')');
                this.pageStatus.status = 'too_long_checks';
                this.manualRefreshAvailable = true;
                return;
            }
            const submitUrl = './import/submit/status';
            window.axios.get(submitUrl, {params: {identifier: this.identifier}}).then((response) => {
                this.pageStatus.status = response.data.status;
                console.log('Status is now ' + response.data.status + ' (' + this.checkCount + ')');

                if (this.checkCount >= this.maxCheckCount) {
                    // error
                    this.pageStatus.status = 'too_long_checks';
                    console.log('Status is now ' + this.pageStatus.status + ' (' + this.checkCount + ')');
                }

                // process messages, warnings and errors:
                this.messages.errors = response.data.errors;
                this.messages.warnings = response.data.warnings;
                this.messages.messages = response.data.messages;

                // process progress data:
                this.progress.currentTransaction = response.data.currentTransaction || 0;
                this.progress.totalTransactions = response.data.totalTransactions || 0;
                this.progress.progressPercentage = response.data.progressPercentage || 0;

                // job has not started yet. Let's wait.
                if (false === this.pageStatus.triedToStart && 'waiting_to_start' === this.pageStatus.status) {
                    this.pageStatus.status = response.data.status;
                    return;
                }
                // user pressed start, but it takes a moment.
                if (true === this.pageStatus.triedToStart && 'waiting_to_start' === this.pageStatus.status) {
                    //console.log('Job hasn\'t started yet, but it\'s been tried.');
                }

                if (true === this.pageStatus.triedToStart && 'submission_errored' === this.pageStatus.status) {
                    console.error('Job status noticed job failed.');
                    this.status = response.data.status;
                    return;
                }

                if ('submission_running' === this.pageStatus.status) {
                    console.log('Conversion is running...')
                }
                if ('submission_done' === this.pageStatus.status) {
                    console.log('Job is done!');
                    this.post.done = true;
                    return;
                }
                if ('submission_errored' === this.pageStatus.status) {
                    console.error('Job is kill.');
                    console.error(response.data);
                    return;
                }
            }).catch((error) => {
                console.error('JOB HAS FAILED :(');
                this.post.result = error;
                this.post.errored = true;
            });
            if (this.checkCount < this.maxCheckCount && !this.post.errored && !this.post.done) {
                setTimeout(function () {
                    this.getJobStatus();
                }.bind(this), 1000);
            }
        }
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
