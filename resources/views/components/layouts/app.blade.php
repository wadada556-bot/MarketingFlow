<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Marketing Tracker' }}</title>
    <style>
        :root { --primary: #2563eb; --border: #e5e7eb; --bg: #f9fafb; --text: #1f2937; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--bg); color: var(--text); padding: 2rem; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header-title { font-size: 1.5rem; font-weight: bold; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border); padding-bottom: 1rem; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.875rem; }
        th, td { border-bottom: 1px solid var(--border); padding: 12px; text-align: left; }
        th { background-color: var(--bg); font-weight: 600; color: #4b5563; }
        tr:hover { background-color: #f3f4f6; }
        
        .filter-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; align-items: center; }
        select, button, .btn-reset { padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 0.875rem; }
        button { background-color: var(--primary); color: white; border: none; cursor: pointer; }
        button:hover { background-color: #1d4ed8; }
        .btn-reset { text-decoration: none; color: #4b5563; background: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="header-title">{{ $title ?? 'Dashboard' }}</h1>
        {{ $slot }}
    </div>
</body>
</html>