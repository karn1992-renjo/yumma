@php
    $post = $post ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $post->title ?? '') }}" required>
        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Category</label>
        <input type="text" name="category" class="form-control @error('category') is-invalid @enderror" value="{{ old('category', $post->category ?? '') }}" placeholder="e.g. Engineering, Food & Culture">
        @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Excerpt</label>
        <textarea name="excerpt" class="form-control @error('excerpt') is-invalid @enderror" rows="3" maxlength="500">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
        <div class="form-text">Short teaser shown on the blog listing card.</div>
        @error('excerpt') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Cover Image</label>
        <input type="file" name="cover_image" class="form-control @error('cover_image') is-invalid @enderror" accept="image/*">
        @if(!empty($post?->cover_image))
            <div class="form-text d-flex align-items-center gap-2 mt-2">
                <img src="{{ \App\Services\MediaStorage::url($post->cover_image) }}" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;">
                <span>Current cover image (upload a new file to replace it)</span>
            </div>
        @else
            <div class="form-text">No cover image set yet -- the public page shows a branded placeholder until one is uploaded.</div>
        @endif
        @error('cover_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Author Name</label>
        <input type="text" name="author_name" class="form-control @error('author_name') is-invalid @enderror" value="{{ old('author_name', $post->author_name ?? auth()->user()->name) }}">
        @error('author_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="draft" @selected(old('status', $post->status ?? 'draft') === 'draft')>Draft</option>
            <option value="published" @selected(old('status', $post->status ?? 'draft') === 'published')>Published</option>
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12"><hr></div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Meta Title</label>
        <input type="text" name="meta_title" class="form-control @error('meta_title') is-invalid @enderror" value="{{ old('meta_title', $post->meta_title ?? '') }}">
        <div class="form-text">Falls back to the post title if left blank.</div>
        @error('meta_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Meta Description</label>
        <input type="text" name="meta_description" class="form-control @error('meta_description') is-invalid @enderror" value="{{ old('meta_description', $post->meta_description ?? '') }}" maxlength="500">
        <div class="form-text">Falls back to the excerpt if left blank.</div>
        @error('meta_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
        <div id="blogBodyEditor" style="min-height: 320px; background: #fff; border-radius: 8px;"></div>
        <textarea name="body" id="blogBodyInput" class="@error('body') is-invalid @enderror" style="display:none;">{{ old('body', $post->body ?? '') }}</textarea>
        @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
</div>
