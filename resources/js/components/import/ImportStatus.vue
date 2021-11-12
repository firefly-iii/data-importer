<!--
  - ImportStatus.vue
  - Copyright (c) 2020 james@firefly-iii.org
  -
  - This file is part of the Firefly III CSV importer
  - (https://github.com/firefly-iii/csv-importer).
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
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">Import status</div>
                <div class="card-body" v-if="'waiting_to_start' === this.status && false === this.triedToStart">
                    <p>
                        The tool is ready to import your data. Press "start job" to start.
                        <a :href="this.downloadUrl" title="Download configuration file.">
                            You can download a configuration file of your import</a>, so you can make a quick start the next time you import.
                    </p>
                    <div class="row">
                        <div class="col-lg-6">
                            <!-- go back to upload -->
                            <a :href="this.jobBackUrl" class="btn btn-secondary">&larr; Go back</a>
                        </div>
                        <div class="col-lg-6">
                            <button
                                    class="btn btn-success float-right"
                                    v-on:click="callStart" type="button">Start job &rarr;
                            </button>
                        </div>
                    </div>
                    <p>

                    </p>
                </div>
                <div class="card-body" v-if="'waiting_to_start' === this.status && true === this.triedToStart">
                    <p>Waiting for the job to start..
                    </p>
                </div>
                <div class="card-body" v-if="'job_running' === this.status">
                    <p>
                        Job is running, please wait.
                    </p>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="100" aria-valuemin="0"
                             aria-valuemax="100" style="width: 100%"></div>
                    </div>
                    <import-messages
                            :messages="this.messages"
                            :warnings="this.warnings"
                            :errors="this.errors"
                    ></import-messages>
                </div>
                <div class="card-body" v-if="'job_done' === this.status ">
                    <p>
                        The import routine has finished 🎉. You can <a :href="this.flushUrl" class="btn btn-success btn-sm">start a new import</a>,
                        <a class="btn btn-info btn-sm" :href="this.downloadUrl" title="Download configuration file.">download the import configuration</a>
                        or inspect the results of the import further below:
                    </p>
                    <import-messages
                            :messages="this.messages"
                            :warnings="this.warnings"
                            :errors="this.errors"
                    ></import-messages>
                    <p>
                        Thank you for using this tool. <a rel="noopener noreferrer" href="https://github.com/firefly-iii/firefly-iii/issues" target="_blank">Please share any feedback you may have</a>.
                    </p>
                </div>
                <div class="card-body" v-if="'job_errored' === this.status">
                    <p class="text-danger">
                        The job could not be started, or failed due to an error. Please check the log files. Sorry about this :(.
                    </p>
                    <import-messages
                            :messages="this.messages"
                            :warnings="this.warnings"
                            :errors="this.errors"
                    ></import-messages>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        name: "ImportStatus",
        /*
    * The component's data.
    */
        data() {
            return {
                triedToStart: false,
                status: '',
                messages: [],
                warnings: [],
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
                    // handle success
                    this.status = response.data.status;
                    this.errors = response.data.errors;
                    this.warnings = response.data.warnings;
                    this.messages = response.data.messages;
                    console.log(`Job status is ${this.status}.`);
                    if (false === this.triedToStart && 'waiting_to_start' === this.status) {
                        // call to job start.
                        console.log('Job hasn\'t started yet. Show user some info');
                        return;
                    }
                    if (true === this.triedToStart && 'waiting_to_start' === this.status) {
                        console.log('Job hasn\'t started yet.');
                    }
                    if ('job_done' === this.status) {
                        console.log('Job is done!');
                        return;
                    }
                    if('job_errored' === this.status) {
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
            callStart: function () {
                console.log('Call job start URL: ' + jobStartUrl);
                axios.post(jobStartUrl).then((response) => {
                    this.getJobStatus();
                }).catch((error) => {
                    this.triedToStart = true;
                    this.status = 'job_errored';
                });
                this.getJobStatus();
                this.triedToStart = true;
            },
        },
        watch: {}
    }
</script>
