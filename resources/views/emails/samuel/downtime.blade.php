<x-mail::message>
# Samuel AI Downtime Alert

The health check for Samuel AI has failed.

**Error Details:**
{{ $errorMessage }}

Please check the server logs and services immediately.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
