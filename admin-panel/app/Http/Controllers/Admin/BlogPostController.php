<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogPostController extends Controller
{
    public function index()
    {
        $posts = BlogPost::orderBy('created_at', 'desc')->paginate(20);

        return view('admin.blog.index', compact('posts'));
    }

    public function create()
    {
        return view('admin.blog.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'author_name' => 'nullable|string|max:255',
            'status' => 'required|in:draft,published',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        $data = [
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['title']),
            'category' => $validated['category'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'],
            'author_name' => $validated['author_name'] ?? null,
            'status' => $validated['status'],
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ];

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = MediaStorage::store($request->file('cover_image'), 'blog');
        }

        BlogPost::create($data);

        return redirect()->route('admin.blog.index')
            ->with('success', 'Blog post created successfully!');
    }

    public function edit(BlogPost $blog)
    {
        return view('admin.blog.edit', ['post' => $blog]);
    }

    public function update(Request $request, BlogPost $blog)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'author_name' => 'nullable|string|max:255',
            'status' => 'required|in:draft,published',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        $data = [
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'],
            'author_name' => $validated['author_name'] ?? null,
            'status' => $validated['status'],
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
        ];

        if ($validated['title'] !== $blog->title) {
            $data['slug'] = $this->uniqueSlug($validated['title'], $blog->id);
        }

        if ($blog->status !== 'published' && $validated['status'] === 'published') {
            $data['published_at'] = now();
        }

        if ($request->hasFile('cover_image')) {
            if ($blog->cover_image) {
                MediaStorage::delete($blog->cover_image);
            }
            $data['cover_image'] = MediaStorage::store($request->file('cover_image'), 'blog');
        }

        $blog->update($data);

        return redirect()->route('admin.blog.index')
            ->with('success', 'Blog post updated successfully!');
    }

    public function destroy(BlogPost $blog)
    {
        if ($blog->cover_image) {
            MediaStorage::delete($blog->cover_image);
        }

        $blog->delete();

        return redirect()->route('admin.blog.index')
            ->with('success', 'Blog post deleted successfully!');
    }

    public function togglePublish(BlogPost $blog)
    {
        $nextStatus = $blog->status === 'published' ? 'draft' : 'published';

        $blog->update([
            'status' => $nextStatus,
            'published_at' => $nextStatus === 'published' ? ($blog->published_at ?? now()) : $blog->published_at,
        ]);

        return redirect()->back()->with('success', 'Blog post '.($nextStatus === 'published' ? 'published' : 'unpublished').'!');
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $suffix = 2;

        while (
            BlogPost::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
