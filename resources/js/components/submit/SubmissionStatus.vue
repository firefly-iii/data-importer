<!--
  - SubmissionStatus.vue
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
                <div class="card-header">Data submission
                    ({{ combiCount }} submission<span
                        v-if="combiCount !== 1">(s)</span>)
                </div>
                <div class="card-body">
                    <p>
                        The final step in the import process is the <strong>submission of data</strong>.
                    </p>
                    <ol>
                        <li v-for="combi in combinations" :key="combi.conversion_identifier">
                            The converted transactions will be submitted to your Firefly III API.
                        </li>
                    </ol>
                </div>
                <p>
                    Please press
                    <button class="btn btn-success" type="button" v-on:click="callAllStart">Start job<span
                        v-if="combiCount !== 1">s</span>
                        &rarr;
                    </button>
                    to start.
                </p>
            </div>
            <div class="card" v-if="triedAnyStart">
                <div class="card-body">
                    <div v-for="(item, index) in computedStatus" :key="index">
                        <h4>Import job #{{ index + 1 }}</h4>
                        <div v-if="'waiting_to_start' === item.status">
                            <p>Waiting for the job to start..</p>
                        </div>
                        <div v-if="'submission_running' === item.status">
                            <p>
                                The submission is running, please wait.
                            </p>
                            <div class="progress">
                                <div aria-valuemax="100" aria-valuemin="0"
                                     aria-valuenow="100" class="progress-bar progress-bar-striped progress-bar-animated"
                                     role="progressbar" style="width: 100%"></div>
                            </div>
                            <submission-messages
                                :errors="errors[item.identifier]"
                                :messages="messages[item.identifier]"
                                :warnings="warnings[item.identifier]"
                            ></submission-messages>
                        </div>
                        <div v-if="'submission_errored' === item.status">
                            <p class="text-danger">
                                The submission could not be started, or failed due to an error. Please check the log
                                files.
                                Sorry about this :(
                            </p>
                            <submission-messages
                                :errors="errors[item.identifier]"
                                :messages="messages[item.identifier]"
                                :warnings="warnings[item.identifier]"
                            ></submission-messages>
                        </div>
                        <div v-if="'submission_done' === item.status">
                            <p>
                                The submission routine has finished 🎉!
                            </p>
                            <submission-messages
                                :errors="errors[item.identifier]"
                                :messages="messages[item.identifier]"
                                :warnings="warnings[item.identifier]"
                            ></submission-messages>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "SubmissionStatus",
    /*
* The component's data.
*/
    data() {
        return {
            triedToStart: {},
            status: {},
            messages: [],
            warnings: [],
            errors: [],
            downloadUrl: window.configDownloadUrl,
            jobBackUrl: window.jobBackUrl,
            flushUrl: window.flushUrl,

            combinations: {},
            combiCount: 0,
            triedAnyStart: false,
            computedStatus: [],
            finishedAll: false,
        };
    },
    props: [],
    mounted() {
        this.combinations = window.combinations;
        this.combiCount = window.combiCount;

        // create array of statuses.
        let index = 0;
        for (let item in this.combinations) {
            if (this.combinations.hasOwnProperty(item)) {
                let current = this.combinations[item];

                // set in status:
                Vue.set(this.computedStatus, index, {
                    identifier: current.conversion_identifier,
                    status: 'waiting_to_start',
                    triedToStart: false
                })

                this.status[current.conversion_identifier] = 'waiting_to_start';
                this.triedToStart[current.conversion_identifier] = false;
                this.getJobStatus(index, current.conversion_identifier);
                index++;
            }
        }
        console.log(this.status);
    },
    methods: {
        getJobStatus: function (index, identifier) {
            console.log('get submission status');
            const url = jobStatusUrl + '?identifier=' + identifier;
            axios.get(url).then((response) => {

                // first try post result:
                if (true === this.triedToStart[identifier] && 'submission_errored' === this.status[identifier]) {
                    console.error('Job ' + identifier + ' failed! :(');
                    return;
                }

                // handle success
                this.errors[identifier] = response.data.errors;
                this.warnings[identifier] = response.data.warnings;
                this.messages[identifier] = response.data.messages;

                // update status
                Vue.set(this.computedStatus, index, {
                    identifier: identifier,
                    status: response.data.status,
                    triedToStart: this.triedToStart[identifier]
                })

                console.log(`Submission status returned for job ${identifier} is "${response.data.status}".`);
                if (false === this.triedToStart[identifier] && 'waiting_to_start' === response.data.status) {
                    // call to job start.
                    console.log('Job hasn\'t started yet. Show user some info');
                    this.status[identifier] = response.data.status;
                    return;
                }
                if (true === this.triedToStart[identifier] && 'waiting_to_start' === response.data.status) {
                    console.log('Job hasn\'t started yet, but its been tried.');
                }
                if (true === this.triedToStart[identifier] && 'submission_errored' === response.data.status) {
                    console.error('Job failed');
                    this.status[identifier] = response.data.status;
                    return;
                }
                if ('submission_running' === response.data.status) {
                    console.log('Job is running...');
                    this.status[identifier] = response.data.status;
                }
                if ('submission_done' === response.data.status) {
                    console.log('Job is done!');
                    this.status[identifier] = response.data.status;
                    return;
                }
                if ('submission_errored' === response.data.status) {
                    console.error('Job is kill.');
                    console.error(response.data);
                    return;
                }
                let random = 1000 + Math.floor(Math.random() * 501);
                setTimeout(function () {
                    console.log('Fired on setTimeout(' + random + ')');
                    this.getJobStatus(index, identifier);
                }.bind(this), random);
            });
        },
        callAllStart: function () {
            this.triedAnyStart = true;
            let index = 0;
            for (let item in this.combinations) {
                if (this.combinations.hasOwnProperty(item)) {
                    let current = this.combinations[item];
                    this.callStart(index, current.conversion_identifier);
                    index++;
                }
            }
        },
        callStart: function (index, identifier) {
            let url = jobStartUrl + '?identifier=' + identifier;
            this.triedToStart[identifier] = true;
            axios.post(url).then((response) => {
                //console.log('POST was OK');
                //this.getJobStatus(index, identifier);
                this.getJobStatus(index, identifier);
            }).catch((error) => {
                console.error('JOB HAS FAILED :(');
                this.status[identifier] = 'conv_errored';
                this.getJobStatus(index, identifier);
            });
        },
    },
    watch: {}
}
</script>
