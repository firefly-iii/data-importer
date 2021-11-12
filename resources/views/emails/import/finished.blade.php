@component('mail::message')
# Result of your import on {{ $time }}

<hr>

@if(count($errors) > 0)
## Errors
@endif

@foreach($errors as $index => $objList)
@foreach($objList as $message)
- Line #{{ $index + 1 }}: {{ $message }}
@endforeach
@endforeach

@if(count($warnings) > 0)
## Warnings
@endif

@foreach($warnings as $index => $objList)
@foreach($objList as $message)
- Line #{{ $index + 1 }}: {{ $message }}
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

@if(0 === count($errors))
_No errors detected_
@endif

@if(0 === count($warnings))
_No warnings detected_
@endif

@if(0 === count($messages))
_No messages detected_
@endif

@component('mail::button', ['url' => $url])
Go to Firefly III
@endcomponent

Enjoy!<br>
Firefly III CSV importer, v{{ config('csv_importer.version')  }}

@endcomponent
