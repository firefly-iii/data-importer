@if('disabled' !== $account['import_account']->status)
    <select style="width:100%;"
            class="custom-select custom-select-sm form-control"
            name="accounts[{{ $account['import_account']->id }}]">
        <!-- loop all Firefly III accounts -->
        @foreach($account['firefly_iii_accounts'] as $ff3Account)
            <option value="{{ $ff3Account->id }}"
                    {{-- loop configuration --}}
                    @foreach($configuration->getAccounts() as $key => $preConfig)
                        {{-- if this account matches, pre-select dropdown. --}}
                        @if($key === $account['import_account']->id && $preConfig === $ff3Account->id) selected="selected"
                    @endif
                    @endforeach
                    label="{{ $ff3Account->name }} @if($ff3Account->iban) ({{ $ff3Account->iban }}) @endif">
                {{ $ff3Account->id }}:
                {{ $ff3Account->name }} @if($ff3Account->iban)
                    ({{ $ff3Account->iban }})
                @endif
            </option>
        @endforeach
    </select>
@endif
