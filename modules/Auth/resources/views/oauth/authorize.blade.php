<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorization Request</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f5f5f5; color: #1a1a1a; padding: 1.5rem;
        }
        .card {
            background: #fff; border-radius: 12px; box-shadow: 0 2px 24px rgba(0,0,0,.12);
            max-width: 420px; width: 100%; padding: 2rem;
        }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        .sub { color: #666; font-size: .9rem; margin: 0 0 1.25rem; }
        .client { font-weight: 600; }
        ul.scopes { list-style: none; margin: 0 0 1.5rem; padding: 1rem; background: #fafafa; border-radius: 8px; }
        ul.scopes li { padding: .25rem 0; font-size: .9rem; }
        ul.scopes li::before { content: "✓ "; color: #2e7d32; }
        .actions { display: flex; gap: .75rem; }
        button {
            flex: 1; padding: .7rem 1rem; border: 0; border-radius: 8px; font-size: .95rem;
            font-weight: 600; cursor: pointer;
        }
        .approve { background: #1867c0; color: #fff; }
        .deny { background: #eee; color: #333; }
        form { margin: 0; flex: 1; }
        @media (prefers-color-scheme: dark) {
            body { background: #121212; color: #e0e0e0; }
            .card { background: #1e1e1e; box-shadow: 0 2px 24px rgba(0,0,0,.5); }
            .sub { color: #aaa; }
            ul.scopes { background: #262626; }
            .deny { background: #333; color: #ddd; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Authorization Request</h1>
        <p class="sub">
            <span class="client">{{ $client->name }}</span> is requesting access to your account
            (<strong>{{ $user->email }}</strong>).
        </p>

        @if (count($scopes) > 0)
            <ul class="scopes">
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        @endif

        <div class="actions">
            <form method="post" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <input type="hidden" name="scope" value="{{ $request->scope }}">
                <button type="submit" class="approve">Authorize</button>
            </form>

            <form method="post" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <input type="hidden" name="scope" value="{{ $request->scope }}">
                <button type="submit" class="deny">Cancel</button>
            </form>
        </div>
    </div>
</body>
</html>
