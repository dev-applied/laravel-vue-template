<x-mail::message>
# {{ $announcement->title }}

{{ $announcement->body }}

@if($announcement->action_url && $announcement->action_label)
<x-mail::button :url="$announcement->action_url">
{{ $announcement->action_label }}
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
