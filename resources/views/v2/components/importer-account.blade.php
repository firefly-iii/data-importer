<tr>
    <td style="width:45%">
        <x-importer-account-title :account="$account" :configuration="$configuration"/>
    </td>
    <td style="width:10%">
        @if('disabled' !== $account['import_account']->status)
            @if(count($account['firefly_iii_accounts']) > 0)
                &rarr;
        @endif
        @endif
    </td>
    <td  style="width:45%">
        <!-- TODO this is one of those things to merge into one generic type -->
        @if(0 === count($account['firefly_iii_accounts']))
            <span class="text-danger">There are no Firefly III accounts to import into</span>
        @endif
        @if(0 !== count($account['firefly_iii_accounts']))
            <x-firefly-iii-account-generic :account="$account"  :configuration="$configuration"/>
        @endif
</td>
</tr>
