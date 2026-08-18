<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    {{-- Matches resources/views/app.blade.php so the consent screen doesn't flash
         the wrong theme on the way in from the assistant. --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? "system" }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    <style>
        html {
            background-color: hsl(0 0% 100%);
        }

        html.dark {
            background-color: hsl(20 14.3% 4.1%);
        }
    </style>

    <title>Authorize {{ $client->name }} - {{ config('app.name', 'PostDoffo') }}</title>

    <meta name="theme-color" content="#f97316">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @fonts

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
<div class="flex min-h-screen flex-col items-center justify-center p-6">
    <div class="w-full max-w-md space-y-6">

        <div class="flex items-center justify-center gap-3">
            <img src="/favicon.svg" alt="" class="size-9 shrink-0">
            <span class="font-display text-lg leading-none font-semibold tracking-tight">
                {{ config('app.name', 'PostDoffo') }}
            </span>
        </div>

        <div class="rounded-xl border border-border bg-card text-card-foreground shadow-sm">
            <div class="space-y-2 p-6 pb-4 text-center">
                <h1 class="text-xl font-semibold tracking-tight">
                    Connect {{ $client->name }}?
                </h1>
                <p class="text-sm text-muted-foreground">
                    It is asking to work in your {{ config('app.name', 'PostDoffo') }} account
                    through the Model Context Protocol.
                </p>
            </div>

            <div class="space-y-4 px-6 pb-6">
                <div class="rounded-lg border border-border bg-muted/50 p-4">
                    <p class="mb-1 text-xs tracking-wide text-muted-foreground uppercase">Signed in as</p>
                    <p class="truncate font-medium">{{ $user->email }}</p>
                </div>

                @if (count($scopes) > 0)
                    <div class="space-y-2.5">
                        <p class="text-sm font-medium">It will be able to:</p>

                        <ul class="space-y-2.5">
                            @foreach ($scopes as $scope)
                                <li class="flex items-start gap-2.5">
                                    <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"></span>
                                    <span class="text-sm text-muted-foreground">{{ $scope->description }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- The thing a consent screen most often fails to say plainly: this
                     is account-wide, and it is not limited to one workspace. --}}
                <p class="rounded-lg border border-border bg-muted/40 p-3 text-xs leading-relaxed text-muted-foreground">
                    This covers every workspace you belong to, and it acts as you — your role in
                    each workspace still decides what it may change. You can disconnect it at any
                    time from Settings, under MCP access.
                </p>
            </div>

            <div class="flex items-center gap-3 border-t border-border p-6">
                <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1" id="denyForm">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="state" value="">
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit"
                            class="inline-flex h-10 w-full items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50">
                        Cancel
                    </button>
                </form>

                <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1" id="authorizeForm">
                    @csrf
                    <input type="hidden" name="state" value="">
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" id="authorizeButton"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium whitespace-nowrap text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50">
                        <svg id="loadingSpinner" class="hidden size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span id="authorizeText">Authorize</span>
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-xs text-muted-foreground">
            Only authorize applications you trust.
        </p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('authorizeForm');
        const button = document.getElementById('authorizeButton');
        const authorizeText = document.getElementById('authorizeText');
        const loadingSpinner = document.getElementById('loadingSpinner');

        form.addEventListener('submit', function () {
            button.disabled = true;
            authorizeText.textContent = 'Authorizing...';
            loadingSpinner.classList.remove('hidden');

            // Desktop clients open this in a popup and expect it to close itself
            // once the redirect back to them has happened. window.close() is a
            // no-op in a normal tab, so the redirect stands on its own there.
            setTimeout(function () {
                const checkRedirect = setInterval(function () {
                    if (!window.location.href.includes('/oauth/authorize') ||
                        window.location.search.includes('code=') ||
                        window.location.search.includes('error=')) {
                        clearInterval(checkRedirect);
                        window.close();
                    }
                }, 100);

                setTimeout(function () {
                    clearInterval(checkRedirect);
                    window.close();
                }, 5000);
            }, 200);
        });

        document.getElementById('denyForm').addEventListener('submit', function () {
            setTimeout(function () {
                window.close();
            }, 200);
        });
    });
</script>
</body>
</html>
