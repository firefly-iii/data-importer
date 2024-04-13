<div x-show="showJobMessages()">
    <div x-show="Object.values(messages.errors).length > 0">
        <strong class="text-danger">Error(s) from the import process</strong>
        <ul>
            <template x-for="(messageList, index) in messages.errors" :key="index">
                <li>
                    Line #<span x-text="index"></span>:
                    <template x-if="messageList.length === 1">
                        <template x-for="message in messageList">
                            <span x-text="message"></span>
                        </template>
                    </template>
                    <template x-if="messageList.length > 1">
                    <ol>
                        <template x-for="message in messageList">
                            <li x-text="message"></li>
                        </template>
                    </ol>
                    </template>
                </li>
            </template>
        </ul>
    </div>

    <div x-show="Object.values(messages.warnings).length > 0">
        <strong class="text-warning">Warning(s) from the import process</strong>
        <ul>
            <template x-for="(messageList, index) in messages.warnings" :key="index">
                <li>
                    Line #<span x-text="index"></span>:
                    <template x-if="messageList.length === 1">
                        <template x-for="message in messageList">
                            <span x-text="message"></span>
                        </template>
                    </template>
                    <template x-if="messageList.length > 1">
                        <ol>
                            <template x-for="message in messageList">
                                <li x-text="message"></li>
                            </template>
                        </ol>
                    </template>
                </li>
            </template>
        </ul>
    </div>


    <div x-show="Object.values(messages.messages).length > 0">
        <strong class="text-info">Message(s) from the import process</strong>
        <ul>
            <template x-for="(messageList, index) in messages.messages" :key="index">
                <li>
                    Line #<span x-text="index"></span>:
                    <template x-if="messageList.length === 1">
                        <template x-for="message in messageList">
                            <span x-html="message"></span>
                        </template>
                    </template>
                    <template x-if="messageList.length > 1">
                        <ol>
                            <template x-for="message in messageList">
                                <li x-text="message"></li>
                            </template>
                        </ol>
                    </template>
                </li>
            </template>
        </ul>
    </div>
</div>

