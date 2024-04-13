<input
    id="do_import_{{ $account['import_account']->id }}"
    type="checkbox"
    name="do_import[{{ $account['import_account']->id }}]"
    value="1"
    aria-describedby="accountsHelp"
    @if('disabled' === $account['import_account']->status) disabled="disabled" @endif
    @if(0 !== ($configuration->getAccounts()[$account['import_account']->id] ?? '')) checked="checked" @endif
/>
<label
    class="form-check-label"
    for="do_import_{{ $account['import_account']->id }}"
    @if('' !== $account['import_account']->iban) title="IBAN: {{ $account['import_account']->iban }}" @endif
>
    @if('' !== $account['import_account']->name)
    Account "{{ $account['import_account']->name }}"
    @else
    Account with no name
    @endif
</label>
<br>
<small>
    @foreach($account['import_account']->extra as $key => $item)
        @if('' !== $item)
        {{ $key }}: {{ $item }}<br>
        @endif
    @endforeach
</small>
@if('disabled' === $account['import_account']->status)
<small class="text-danger">(this account is disabled)</small>
@endif
