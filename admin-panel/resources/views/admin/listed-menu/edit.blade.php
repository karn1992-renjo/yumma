@extends('layouts.admin')

@section('title', 'Edit Listed Menu Item')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $priceStep = number_format(1 / pow(10, $currencyDecimals), $currencyDecimals, '.', '');
@endphp

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Edit Listed Menu Item</h1>
            <p class="text-muted mb-0">Update {{ $menuItem->name }} for {{ $menuItem->restaurant?->name ?? 'restaurant' }}.</p>
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

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.listed-menu.update', $menuItem) }}" enctype="multipart/form-data" data-listed-menu-form>
                @csrf
                @method('PUT')
                @include('admin.listed-menu.partials.form', ['mode' => 'edit'])
            </form>
        </div>
    </div>
</div>
@endsection

@include('admin.listed-menu.partials.scripts')
