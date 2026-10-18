<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index()
    {
        $posts = BlogPost::published()
            ->orderBy('published_at', 'desc')
            ->paginate(9);

        return view('blog', compact('posts'));
    }

    public function show(BlogPost $post)
    {
        abort_unless($post->status === 'published', 404);

        return view('blog-detail', compact('post'));
    }
}
