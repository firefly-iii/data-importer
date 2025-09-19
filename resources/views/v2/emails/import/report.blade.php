@component('mail::message')
# Result of your import on {{ $time }}@if('' !== $configFile), using configuration file `{{ $configFile }}`@endif

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

@if(count($rateLimits) > 0)
## Rate limit information
@endif

@foreach($rateLimits as $index => $objList)
@foreach($objList as $message)
- Item #{{ $index + 1 }}: {!! $message !!}
@endforeach
@endforeach

<hr>

@if(0 === count($errors) and 0 === count($messages) and 0 === count($warnings) and 0 === count($rateLimits))

*No messages, warnings or errors: nothing to report*

@endif
@if('' !== $url)
@component('mail::button', ['url' => $url])
Go to Firefly III
@endcomponent
@endif

Enjoy!<br>
Firefly III Data Importer, @if(str_starts_with($version, 'develop')){{ $version }} @else v{{ $version }}@endif

@endcomponent
