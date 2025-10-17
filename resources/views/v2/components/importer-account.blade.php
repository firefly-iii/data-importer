<tr>
    <td style="width:45%">
        <x-importer-account-title :account="$account" :configuration="$configuration"/>
    </td>
    <td style="width:10%">
        @if('disabled' !== $account['import_account']->status)
            @if( (isset($account['firefly_iii_accounts']['assets']) && count($account['firefly_iii_accounts']['assets']) > 0) || (isset($account['firefly_iii_accounts']['liabilities']) && count($account['firefly_iii_accounts']['liabilities']) > 0) )
                &rarr;
        @endif
        @endif
    </td>
    <td  style="width:45%">
        <!-- Firefly III Account Content - Visibility Controlled -->
        <div id="firefly-account-content-{{ $account['import_account']->id }}">
            <!-- TODO this is one of those things to merge into one generic type -->
            @if(
                // flow is not simplefin.
                $flow !== 'simplefin' && $flow !== 'lunchflow' &&
                ((!isset($account['firefly_iii_accounts']['assets']) || count($account['firefly_iii_accounts']['assets']) === 0) && (!isset($account['firefly_iii_accounts']['liabilities']) || count($account['firefly_iii_accounts']['liabilities']) === 0) )
                )
                <span class="text-danger">X There are no Firefly III accounts to import into</span>
            @endif
            @if( ($flow === 'simplefin' && $flow === 'lunchflow') || (isset($account['firefly_iii_accounts']['assets']) && count($account['firefly_iii_accounts']['assets']) > 0) || (isset($account['firefly_iii_accounts']['liabilities']) && count($account['firefly_iii_accounts']['liabilities']) > 0) )
                <x-firefly-iii-account-generic :flow="$flow" :account="$account"  :configuration="$configuration"/>
                <x-create-account-widget :account="$account" :configuration="$configuration" :currencies="$currencies ?? []"/>
            @endif
        </div>

        <!-- Not Imported Text - Hidden by Default -->
        <div id="not-imported-text-{{ $account['import_account']->id }}" style="display: none;" class="text-muted py-2">
            <small><i class="fas fa-info-circle fa-sm me-1"></i>Not imported</small>
        </div>
</td>
</tr>
