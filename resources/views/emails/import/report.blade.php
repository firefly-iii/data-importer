@component('mail::message')
# Result of your import on {{ $time }}

<hr>

@if(count($errors) > 0)
## Errors
@endif

@foreach($errors as $index => $objList)
@foreach($objList as $message)
- Line #{{ $index + 1 }}: {!! $message !!}
@endforeach
@endforeach

@if(count($warnings) > 0)
## Warnings
@endif

@foreach($warnings as $index => $objList)
@foreach($objList as $message)
- Line #{{ $index + 1 }}: {!! $message !!}
@endforeach
@endforeach

@if(count($messages) > 0)
## Messages
@endif

@foreach($messages as $index => $objList)
@foreach($objList as $message)
- Line #{{ $index + 1 }}: {!! $message !!}
@endforeach
@endforeach

<hr>

@component('mail::button', ['url' => $url])
Go to Firefly III
@endcomponent

Enjoy!<br>
Firefly III Data Importer, v{{ config('importer.version')  }}

@endcomponent
