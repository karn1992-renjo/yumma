@extends('layouts.admin')

@php $editing = isset($video) && $video; @endphp

@section('title', $editing ? 'Edit App Open Video' : 'Upload App Open Video')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mt-4">{{ $editing ? 'Edit video' : 'Upload video' }}</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.app-open-videos.index') }}">App Open Video</a></li>
                    <li class="breadcrumb-item active">{{ $editing ? 'Edit' : 'Upload' }}</li>
                </ol>
            </div>
            <a href="{{ route('admin.app-open-videos.index') }}" class="btn btn-light"><i class="fas fa-arrow-left me-2"></i> Back</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="table-card">
                <div class="p-4">
                    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.app-open-videos.update', $video) : route('admin.app-open-videos.store') }}">
                        @csrf
                        @if($editing) @method('PUT') @endif

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Plays in <span class="text-danger">*</span></label>
                            <select name="audience" class="form-select @error('audience') is-invalid @enderror" required>
                                @foreach(['customer' => 'Customer app', 'restaurant' => 'Restaurant app'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('audience', $video->audience ?? 'customer') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Each app has its own video; the lowest "Order" number among live videos plays first.</small>
                            @error('audience') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $video->title ?? '') }}" required placeholder="e.g., New features for restaurants">
                            <small class="text-muted">Only you see this; people just see the video.</small>
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Video file {!! $editing ? '' : '<span class="text-danger">*</span>' !!}</label>
                            <input type="file" name="video" accept="video/mp4,video/quicktime,video/x-m4v" class="form-control @error('video') is-invalid @enderror" {{ $editing ? '' : 'required' }}>
                            <small class="text-muted">MP4, MOV or M4V, up to 50 MB. Portrait (9:16) looks best on phones. {{ $editing ? 'Choose a new file to replace it: everyone will then see the new video once.' : '' }}</small>
                            @error('video') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @if($editing)
                                <video src="{{ $video->url() }}" controls preload="metadata" class="mt-3 rounded" style="max-height:260px;max-width:100%"></video>
                            @endif
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Skip button appears after (seconds)</label>
                                <input type="number" name="skip_after_seconds" min="0" max="30" class="form-control" value="{{ old('skip_after_seconds', $video->skip_after_seconds ?? 0) }}">
                                <small class="text-muted">0 = people can skip straight away.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Show from</label>
                                <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', optional($video->starts_at ?? null)->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Show until</label>
                                <input type="datetime-local" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', optional($video->ends_at ?? null)->format('Y-m-d\TH:i')) }}">
                                @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Order</label>
                                <input type="number" name="sort_order" min="0" class="form-control" value="{{ old('sort_order', $video->sort_order ?? 0) }}">
                                <small class="text-muted">If several videos are live, the one with the lowest number plays first.</small>
                            </div>
                            <div class="col-md-8 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', $editing ? $video->is_active : true) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="isActive">Live (people can see it)</label>
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-primary"><i class="fas fa-upload me-2"></i>{{ $editing ? 'Save changes' : 'Upload video' }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
