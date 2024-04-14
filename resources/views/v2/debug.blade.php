<html>
<head>
    <title>Firefly III Data Importer Debug</title>
</head>
<body>

<p style="font-family:Arial, Arial, Helvetica, sans-serif;font-size:12pt;width:600px;">
    Firefly III data importer debug page
</p>
<p style="font-family:Arial, Arial, Helvetica, sans-serif;font-size:12pt;width:800px;">
    Copy and paste the content of this textarea in your issue. <strong>Please do not add quotes or backticks, it breaks the table.</strong>
</p>
<textarea rows="30" cols="100" name="debug_info" id="debug_info" style="font-family:Menlo, Monaco, Consolas, monospace;font-size:8pt;">
Debug information generated at {{ $now }} for Firefly III Data Importer version **{{ config('importer.version') }}**.

{{ $table }}
</textarea>
<script type="text/javascript">
    var textArea = document.getElementById('debug_info');
    var text = textArea.value;
    var timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    text = text.replace('[BrowserTZ]', timeZone);
    textArea.value = text;
</script>

<p style="font-family:Arial, Arial, Helvetica, sans-serif;font-size:12pt;width:600px;color:#a00;">
    <a href="{{ route('index') }}">Back to index</a>
</p>

<p style="font-family:Arial, Arial, Helvetica, sans-serif;font-size:12pt;width:600px;color:#a00;">
    Extra info. Do not share this lightly!
</p>

<textarea rows="30" cols="100" name="log_info" style="font-family:Menlo, Monaco, Consolas, monospace;font-size:7pt;">
```
{{ $logContent }}
```
</textarea>

<p style="font-family:Arial, Arial, Helvetica, sans-serif;font-size:12pt;width:600px;color:#a00;">
    <a href="{{ route('index') }}">Back to index</a>
</p>

</body>
</html>
