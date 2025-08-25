<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="icon" href="/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap">
    @vite(['resources/scss/main.scss', 'resources/ts/main.ts'])
    @if(env('VITE_HTTPS', false))
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @endif

</head>
<body id="applied">
<noscript>
    <strong>
        We're sorry but <?= config('app.name') ?> doesn't work properly without JavaScript enabled. Please enable it to
        continue.
    </strong>
</noscript>
<div id="app"></div>


</body>
</html>
