<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Login - Sistem Kepegawaian</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f3f4f6; }
        .card { background: white; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { font-size: 1.25rem; margin-bottom: 0.5rem; color: #dc2626; }
        p { font-size: 0.875rem; color: #6b7280; margin-bottom: 1.5rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem; color: #374151; }
        input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; margin-bottom: 1rem; box-sizing: border-box; }
        button { width: 100%; padding: 0.5rem 1rem; background: #dc2626; color: white; border: none; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; cursor: pointer; }
        button:hover { background: #b91c1c; }
        .error { color: #dc2626; font-size: 0.75rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>⚠️ Emergency Login</h1>
        <p>Keycloak tidak tersedia. Gunakan kredensial darurat untuk mengakses sistem.</p>

        @if ($errors->has('credentials'))
            <div class="error">{{ $errors->first('credentials') }}</div>
        @endif

        <form method="POST" action="{{ route('emergency.login') }}">
            @csrf

            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="{{ old('username') }}" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login Emergency</button>
        </form>
    </div>
</body>
</html>
