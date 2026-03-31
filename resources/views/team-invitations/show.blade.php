<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Team Invitation</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: radial-gradient(circle at top, #1f2937, #0f172a 65%);
            color: #e2e8f0;
        }
        .card {
            width: min(720px, calc(100vw - 2rem));
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 24px;
            background: rgba(15,23,42,.9);
            box-shadow: 0 30px 80px rgba(0,0,0,.35);
            padding: 2rem;
        }
        .muted { color: #94a3b8; }
        .grid { display: grid; gap: 1rem; }
        .split { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        label { display: block; font-size: .875rem; color: #cbd5e1; margin-bottom: .375rem; }
        input {
            width: 100%;
            box-sizing: border-box;
            border-radius: 14px;
            border: 1px solid rgba(148,163,184,.2);
            background: rgba(15,23,42,.85);
            color: #e2e8f0;
            padding: .9rem 1rem;
        }
        button, a.button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 14px;
            padding: .9rem 1.1rem;
            background: #f59e0b;
            color: #0f172a;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            border-radius: 999px;
            padding: .35rem .7rem;
            background: rgba(245,158,11,.15);
            color: #fbbf24;
            font-size: .875rem;
            font-weight: 600;
        }
        .alert {
            border-radius: 16px;
            border: 1px solid rgba(239,68,68,.2);
            background: rgba(127,29,29,.25);
            padding: 1rem;
            color: #fecaca;
        }
        .success {
            border-color: rgba(34,197,94,.2);
            background: rgba(20,83,45,.25);
            color: #bbf7d0;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="pill">Team invitation</div>
        <h1 style="margin: 1rem 0 .5rem; font-size: clamp(2rem, 4vw, 3rem);">
            {{ $invitation->team->name }}
        </h1>
        <p class="muted" style="line-height: 1.7; margin-top: 0;">
            You were invited to join this team as <strong>{{ $invitation->role }}</strong>.
            This invitation was sent to <strong>{{ $invitation->email }}</strong>.
        </p>

        @if ($isAccepted)
            <div class="alert success" style="margin-top: 1.5rem;">
                This invitation has already been accepted.
            </div>
        @elseif ($isExpired)
            <div class="alert" style="margin-top: 1.5rem;">
                This invitation has expired. Ask a teammate to send a fresh invite from the Team page.
            </div>
        @else
            <form method="post" action="{{ route('team-invitations.accept', ['token' => request()->route('token')]) }}" class="grid" style="margin-top: 1.5rem;">
                @csrf

                <div class="split">
                    <div>
                        <label for="name">Your name</label>
                        <input id="name" name="name" value="{{ old('name', $invitation->name) }}" placeholder="Jane Doe">
                    </div>
                    <div>
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" placeholder="Create a password">
                    </div>
                </div>

                <div>
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Repeat your password">
                </div>

                @if ($errors->any())
                    <div class="alert">
                        <strong>We could not accept this invitation yet.</strong>
                        <ul style="margin: .75rem 0 0 1.25rem;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div style="display: flex; gap: .75rem; flex-wrap: wrap; align-items: center;">
                    <button type="submit">Accept invitation</button>
                    <span class="muted">If an account already exists for this email, you will be added immediately.</span>
                </div>
            </form>
        @endif
    </main>
</body>
</html>
