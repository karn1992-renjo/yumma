@extends('layouts.admin')

@section('title', 'Add Listed Menu Item')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $priceStep = number_format(1 / pow(10, $currencyDecimals), $currencyDecimals, '.', '');
@endphp

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Add Listed Menu Item</h1>
            <p class="text-muted mb-0">Create a custom item or assign a global catalog item to any restaurant.</p>
        </div>
        <a href="{{ route('admin.listed-menu.index') }}" class="btn btn-light">Back</a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="mb-1 fw-bold">Assign Global Menu Item</h5>
                <p class="text-muted small mb-0">Use a master catalog item and list it for a selected restaurant.</p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.listed-menu.from-global') }}" data-listed-menu-global-form>
                @csrf
                @include('admin.listed-menu.partials.global-form')
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-1 fw-bold">Create Custom Menu Item</h5>
            <p class="text-muted small mb-0">Build a restaurant-specific item. You can optionally link it to a global item.</p>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.listed-menu.store') }}" enctype="multipart/form-data" data-listed-menu-form>
                @csrf
                @include('admin.listed-menu.partials.form', ['mode' => 'create'])
            </form>
        </div>
    </div>
</div>
@endsection

@include('admin.listed-menu.partials.scripts')
