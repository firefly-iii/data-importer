<!--
  - ConversionStatus.vue
  - Copyright (c) 2021 james@firefly-iii.org
  -
  - This file is part of the Firefly III Data Importer
  - (https://github.com/firefly-iii/data-importer).
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <https://www.gnu.org/licenses/>.
  -->

<template>
    <div class="row mt-3">
        <div class="col-lg-10 offset-lg-1">
            <div class="card">
                <div class="card-header">Data conversion</div>
                <div v-if="'waiting_to_start' === this.status && false === this.triedToStart" class="card-body">
                    <p>
                        The first step in the import process is a <strong>conversion</strong>.
                        <span v-if="flow === 'csv'">The CSV file you uploaded</span>
                        <span v-if="flow === 'nordigen'">The transactions downloaded from Nordigen</span>
                        <span v-if="flow === 'spectre'">The transactions downloaded from Spectre</span>
                        will be converted to Firefly III compatible transactions. Please press <strong>Start
                        job</strong> to start.
                    </p>
                    <p>
                        <button class="btn btn-success float-end" type="button" v-on:click="callStart">Start job
                            &rarr;
                        </button>
                    </p>
                </div>
                <div v-if="'waiting_to_start' === this.status && true === this.triedToStart" class="card-body">
                    <p>Waiting for the job to start..</p>
                </div>
                <div v-if="'conv_running' === this.status" class="card-body">
                    <p>
                        Conversion is running, please wait.
                    </p>
                    <div class="progress">
                        <div aria-valuemax="100" aria-valuemin="0"
                             aria-valuenow="100" class="progress-bar progress-bar-striped progress-bar-animated"
                             role="progressbar" style="width: 100%"></div>
                    </div>
                    <conversion-messages
                        :errors="this.errors"
                        :messages="this.messages"
                        :warnings="this.warnings"
                    ></conversion-messages>
                </div>
                <div v-if="'conv_done' === this.status " class="card-body">
                    <p>
                        The conversion routine has finished 🎉. Please wait to be redirected!
                        <span class="fas fa-sync fa-spin"></span>
                    </p>
                    <conversion-messages
                        :errors="this.errors"
                        :messages="this.messages"
                        :warnings="this.warnings"
                    ></conversion-messages>
                </div>
                <div v-if="'conv_errored' === this.status" class="card-body">
                    <p class="text-danger">
                        The conversion could not be started, or failed due to an error. Please check the log files.
                        Sorry about this :(
                    </p>
                    <conversion-messages
                        :errors="this.errors"
                        :messages="this.messages"
                        :warnings="this.warnings"
                    ></conversion-messages>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "ConversionStatus",
    /*
* The component's data.
*/
    data() {
        return {
            triedToStart: false,
            status: '',
            messages: [],
            warnings: [],
            flow: window.flow,
            errors: [],
            downloadUrl: window.configDownloadUrl,
            jobBackUrl: window.jobBackUrl,
            flushUrl: window.flushUrl
        };
    },
    props: [],
    mounted() {
        console.log(`Mounted, check job at ${jobStatusUrl}.`);
        this.getJobStatus();
    },
    methods: {
        getJobStatus: function () {
            console.log('getJobStatus');
            axios.get(jobStatusUrl).then((response) => {

                // first try post result:
                if (true === this.triedToStart && 'conv_errored' === this.status) {
                    console.error('Job failed!!!');
                    return;
                }

                // handle success
                this.errors = response.data.errors;
                this.warnings = response.data.warnings;
                this.messages = response.data.messages;
                console.log(`Job status is ${response.data.status}.`);
                if (false === this.triedToStart && 'waiting_to_start' === response.data.status) {
                    // call to job start.
                    console.log('Job hasn\'t started yet. Show user some info');
                    this.status = response.data.status;
                    return;
                }
                if (true === this.triedToStart && 'waiting_to_start' === response.data.status) {
                    console.log('Job hasn\'t started yet, but its been tried.');
                }
                if (true === this.triedToStart && 'conv_errored' === response.data.status) {
                    console.error('Job failed');
                    this.status = response.data.status;
                    return;
                }
                if ('conv_running' === response.data.status) {
                    console.log('Conversion is running...')
                    this.status = response.data.status;
                }
                if ('conv_done' === response.data.status) {
                    console.log('Job is done!');
                    this.status = response.data.status;
                    setTimeout(function () {
                        console.log('Do redirect!')
                        this.redirectToImport();
                    }.bind(this), 3000);
                    return;
                }
                if ('conv_errored' === response.data.status) {
                    console.error('Job is kill.');
                    console.error(response.data);
                    return;
                }

                setTimeout(function () {
                    console.log('Fired on setTimeout');
                    this.getJobStatus();
                }.bind(this), 1000);
            });
        },
        redirectToImport: function () {
            window.location = importStartUrl;
        },
        callStart: function () {
            console.log('Call job start URL: ' + jobStartUrl);
            axios.post(jobStartUrl).then((response) => {
                console.log('POST was OK');
                this.getJobStatus();
            }).catch((error) => {
                console.error('JOB HAS FAILED :(');
                this.triedToStart = true;
                this.status = 'conv_errored';
            });
            this.getJobStatus();
            this.triedToStart = true;
        },
    },
    watch: {}
}
</script>
