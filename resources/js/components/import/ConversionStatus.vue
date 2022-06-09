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
            <div class="card" v-if="!triedAnyStart">
                <div class="card-header">Data conversion ({{ combiCount }} conversion<span
                    v-if="combiCount != 1">(s)</span>)
                </div>
                <div class="card-body">
                    <p>
                        The first step in the import process is a <strong>conversion of data</strong>.
                    </p>
                    <ol>
                        <li v-for="combi in combinations" :key="combi.conversion_identifier">
                            <span v-if="combi.flow === 'file'">The importable file you uploaded</span>
                            <span v-if="combi.flow === 'nordigen'">The transactions downloaded from Nordigen</span>
                            <span v-if="combi.flow === 'spectre'">The transactions downloaded from Spectre</span>
                            will be converted to Firefly III compatible transactions.
                        </li>
                    </ol>
                    <p>
                        Please press <strong>Start job<span v-if="combiCount != 1">s</span></strong> to start.
                    </p>
                    <p>
                        <button class="btn btn-success float-end" type="button" v-on:click="callAllStart">Start job<span
                            v-if="combiCount != 1">s</span>
                            &rarr;
                        </button>
                    </p>
                </div>
            </div>
            <div class="card" v-if="triedAnyStart">
                <div class="card-header">Data conversion ({{ combiCount }} conversion<span
                    v-if="combiCount != 1">(s)</span>)
                </div>
                <div class="card-body">
                    <div v-for="(item, index) in computedStatus">
                        <h4>Import job #{{ index + 1 }}</h4>
                        <p>
                            {{ item.identifier }} {{ item.status }}
                        </p>
                        <div v-if="'waiting_to_start' === item.status">
                            <p>Waiting for the job to start..</p>
                        </div>
                        <div v-if="'conv_running' === item.status">
                            <p>
                                The conversion is running, please wait.
                            </p>
                            <div class="progress">
                                <div aria-valuemax="100" aria-valuemin="0"
                                     aria-valuenow="100" class="progress-bar progress-bar-striped progress-bar-animated"
                                     role="progressbar" style="width: 100%"></div>
                            </div>
                            <conversion-messages
                                :errors="errors[item.identifier]"
                                :messages="messages[item.identifier]"
                                :warnings="warnings[item.identifier]"
                            ></conversion-messages>
                        </div>
                        <div v-if="'conv_errored' === item.status">
                            <p class="text-danger">
                                The conversion could not be started, or failed due to an error. Please check the log
                                files.
                                Sorry about this :(
                            </p>
                            <conversion-messages
                                :errors="errors[item.identifier]"
                                :messages="messages[item.identifier]"
                                :warnings="warnings[item.identifier]"
                            ></conversion-messages>
                        </div>
                        <div v-if="'conv_done' === item.status">
                            <p>
                                The conversion routine has finished 🎉. Please wait to be redirected!
                                <span class="fas fa-sync fa-spin"></span>
                            </p>
                            <conversion-messages
                                :errors="errors[item.identifier]"
                                :messages="messages[item.identifier]"
                                :warnings="warnings[item.identifier]"
                            ></conversion-messages>
                        </div>
                    </div>
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
            triedToStart: {},
            status: {},
            messages: {},
            warnings: {},
            errors: {},
            downloadUrl: window.configDownloadUrl,
            jobBackUrl: window.jobBackUrl,
            flushUrl: window.flushUrl,
            combinations: {},
            combiCount: 0,
            triedAnyStart: false,
        };
    },
    computed: {
        computedStatus() {
            let stat = [];
            for(let key in this.status) {
                if(this.status.hasOwnProperty(key)) {
                    let current = this.status[key];
                    stat.push({identifier: key, status: current});
                }
            }
            return stat;
        }
    },
    props: [],
    mounted() {
        this.combinations = window.combinations;
        this.combiCount = window.combiCount;

        for (let item in this.combinations) {
            if (this.combinations.hasOwnProperty(item)) {
                let current = this.combinations[item];
                this.status[current.conversion_identifier] = 'waiting_to_start';
                this.triedToStart[current.conversion_identifier] = false;
                this.getJobStatus(current.conversion_identifier);
                console.log(this.status);
            }
        }

        //this.getJobStatus();
    },
    methods: {
        getJobStatus: function (identifier) {
            console.log('getJobStatus(' + identifier + ')');
            let url = jobStatusUrl + '?identifier=' + identifier;
            axios.get(url).then((response) => {
                this.parseJobStatus(identifier, response.data);
            });
        },
        parseJobStatus: function (identifier, response) {
            console.log(`Returned status of ${identifier} is ${response.status}. Current state is "${this.status[identifier]}"`);
            this.errors[identifier] = response.errors;
            this.warnings[identifier] = response.warnings;
            this.messages[identifier] = response.messages;

            // job already failed before, then don't bother.
            if (true === this.triedToStart[identifier] && 'conv_errored' === this.status[identifier]) {
                console.error('Job ' + identifier + ' already failed :(');
                return;
            }

            this.status[identifier] = response.status;
            console.log(this.status);
            console.log(this.computedStatus);

            // job has not started yet.
            if (false === this.triedToStart[identifier] && 'waiting_to_start' === response.status) {
                console.log('Job ' + identifier + ' hasn\'t started yet. Show user some info');
                return;
            }

            // job was tried to start, but hasnt yet
            if (true === this.triedToStart[identifier] && 'waiting_to_start' === response.status) {
                console.log('Job ' + identifier + ' hasn\'t started yet, but its been tried.');
            }

            // job failed
            if (true === this.triedToStart[identifier] && 'conv_errored' === response.status) {
                console.error('Job ' + identifier + ' failed :( ');
                console.error(response.data);
                return;
            }

            // job is running ...
            if ('conv_running' === response.status) {
                console.log('Job ' + identifier + ' is running...')
            }

            // job is done!
            if ('conv_done' === response.status) {
                console.log('Job is done!');
                this.conditionalRedirect();
                return;
            }
            let random = 1000 + Math.floor(Math.random() * 501);
            setTimeout(function () {
                console.log('Fired on setTimeout of ' + identifier + ' with a break of ' + random + 'ms.');

                this.getJobStatus(identifier);
            }.bind(this), random); // about every second and a half.
        },
        redirectToImport: function () {
            //window.location = importStartUrl;
        },
        conditionalRedirect: function () {
            let redirect = true;
            for(let key in this.status) {
                if(this.status.hasOwnProperty(key)) {
                    let current = this.status[key];
                    console.log('Job ' + key + ' is ' + current);
                    if(current !== 'conv_done') {
                        redirect = false;
                    }
                }
            }
            if(true === redirect) {
                        setTimeout(function () {
                            console.log('Do redirect!')
                            this.redirectToImport();
                        }.bind(this), 3000);
            }
            //console.log('Redirect if all are done (TODO)');
        },
        callAllStart: function () {
            this.triedAnyStart = true;
            for (let item in this.combinations) {
                if (this.combinations.hasOwnProperty(item)) {
                    let current = this.combinations[item];
                    this.callStart(current.conversion_identifier);
                }
            }
        },
        callStart: function (identifier) {
            let url = jobStartUrl + '?identifier=' + identifier;
            console.log('Call job start URL: ' + url);
            this.triedToStart[identifier] = true;
            axios.post(url).then((response) => {
                //console.log('POST was OK');
                //this.getJobStatus(identifier);
            }).catch((error) => {
                console.error('JOB HAS FAILED :(');
                this.status[identifier] = 'conv_errored';
                this.getJobStatus(identifier);
            });
            console.log('POST was OK');
            this.getJobStatus(identifier);
        },
    },
    renderTracked(event) {
        debugger
    },
    renderTriggered(event) {
        debugger
    }
}

</script>
