@if (session('success'))
    <div class="alert success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert error">
        <strong>Check the form.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
