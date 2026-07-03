<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed | PowerMail Core</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f6f7fb;
            color: #111827;
            font-family: Arial, sans-serif;
        }

        main {
            width: min(100% - 32px, 520px);
            padding: 32px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        }

        h1 {
            margin: 0 0 12px;
            font-size: 28px;
        }

        p {
            margin: 0;
            color: #4b5563;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <main>
        <h1>You are unsubscribed</h1>
        <p>{{ $contact->email }} will no longer receive marketing emails.</p>
    </main>
</body>
</html>
