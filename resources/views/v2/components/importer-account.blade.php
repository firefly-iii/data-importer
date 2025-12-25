<tr>
    <td style="width:45%">
        <x-importer-account-title :account="$account" :configuration="$configuration"/>
    </td>
    <td style="width:10%">

        @if('disabled' !== $account['import_account']->status && (isset($account['firefly_iii_accounts']['assets']) && count($account['firefly_iii_accounts']['assets']) > 0) || (isset($account['firefly_iii_accounts']['liabilities']) && count($account['firefly_iii_accounts']['liabilities']) > 0) )
            &rarr;
       @endif
    </td>
    <td style="width:45%">
        <!-- Firefly III account content - visibility controlled -->
        <div id="firefly-account-content-{{ $account['import_account']->id }}">
                <x-firefly-iii-account-generic :flow="$flow" :account="$account" :configuration="$configuration"/>
                <x-create-account-widget :account="$account" :configuration="$configuration"
                                         :currencies="$currencies ?? []"/>
        </div>

        <!-- "Not imported" text - hidden by default -->
        <div id="not-imported-text-{{ $account['import_account']->id }}" style="display: none;" class="text-muted py-2">
            <small><i class="fas fa-info-circle fa-sm me-1"></i>Not imported</small>
        </div>
</td>
</tr>
