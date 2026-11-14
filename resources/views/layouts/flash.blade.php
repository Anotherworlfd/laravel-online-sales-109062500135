@if (session('success'))
    <div class="card" style="border-left:4px solid #3d8b5e">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="card" style="border-left:4px solid #b3261e">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="card" style="border-left:4px solid #b3261e">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif