<div class="row mt-3">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                {{ $subTitle }}
            </div>
            <div class="card-body">
                @if('file' === $flow)
                    <p>
                        @if('camt' === $configuration->getContentType())
                            Even though camt.053 is a defined standard, you might want to customize. Some of the most important settings are below.
                            They apply to all records in the uploaded files. If you would like some support,
                            you won't find anything at <a
                                href="https://docs.firefly-iii.org/how-to/data-importer/import/csv/"
                                target="_blank">
                                this page.</a> right now.
                        @endif
                        @if('csv' === $configuration->getContentType())
                            Importable files come in many shapes and forms. Some of the most important
                            settings are below.
                            They apply to all lines in the file. If you would like some support, <a
                                href="https://docs.firefly-iii.org/how-to/data-importer/import/csv/"
                                target="_blank">
                                check out the documentation for this page.</a>
                        @endif
                    </p>
                @endif
                @if('nordigen' === $flow || 'spectre' === $flow)
                    <p>
                        Your
                        @if('nordigen' === $flow)
                            GoCardless
                        @endif
                        @if('spectre' === $flow)
                            Spectre
                        @endif
                        import can be configured and fine-tuned.
                        <a href="https://docs.firefly-iii.org/how-to/data-importer/import/gocardless/"
                           target="_blank">Check
                            out the documentation for this page.</a>
                    </p>
                @endif
                @if('simplefin' === $flow)
                    <p>
                        Configure how your SimpleFIN accounts will be mapped to Firefly III accounts.
                        You can map existing accounts or create new ones during import.
                        Accounts marked for import will have their transactions synchronized based on your date range settings.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
