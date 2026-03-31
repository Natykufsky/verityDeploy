<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #0f172a;">
    <h2 style="margin: 0 0 12px; font-size: 20px;">{{ $payload['title'] ?? 'verityDeploy operational alert' }}</h2>

    <p style="margin: 0 0 12px;">{{ $payload['body'] ?? '' }}</p>

    @if (! empty($payload['context']))
        <ul style="margin: 0 0 12px; padding-left: 20px;">
            @foreach ($payload['context'] as $key => $value)
                <li><strong>{{ str_replace('_', ' ', $key) }}:</strong> {{ is_scalar($value) || is_null($value) ? $value : json_encode($value) }}</li>
            @endforeach
        </ul>
    @endif

    @if (! empty($payload['url']))
        <p style="margin: 0;">
            <a href="{{ $payload['url'] }}" style="color: #b45309;">View details</a>
        </p>
    @endif
</div>
