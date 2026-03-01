<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Vanessa Perfumes') }} API</title>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f5f5f5; }
        .container { text-align: center; padding: 2rem; }
        h1 { color: #333; margin-bottom: 1rem; }
        p { color: #666; }
        code { background: #e0e0e0; padding: 0.2rem 0.5rem; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ config('app.name', 'Vanessa Perfumes') }} API</h1>
        <p>REST API Server is running.</p>
        <p>Base URL: <code>/api/v1</code></p>
        <p>Health Check: <code>GET /api/v1/health</code></p>
    </div>
</body>
</html>
