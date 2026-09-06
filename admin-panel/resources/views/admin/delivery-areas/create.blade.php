@extends('layouts.admin')

@section('title', 'Create Delivery Area')
@section('header', 'Create Delivery Area')

@section('styles')
    @include('admin.delivery-areas._map-styles')
@endsection

@section('content')
    @include('admin.delivery-areas._form', ['area' => null])
@endsection

@section('scripts')
    @include('admin.delivery-areas._map')
@endsection
