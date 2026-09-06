@extends('layouts.admin')

@section('title', 'Edit Delivery Area')
@section('header', 'Edit Delivery Area')

@section('styles')
    @include('admin.delivery-areas._map-styles')
@endsection

@section('content')
    @include('admin.delivery-areas._form', ['area' => $deliveryArea])
@endsection

@section('scripts')
    @include('admin.delivery-areas._map')
@endsection
