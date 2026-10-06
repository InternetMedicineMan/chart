<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\SchemaOrg;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use View;

class BlogController extends Controller
{
    public function index(): Response
    {
        $articles = Article::with('user')
            ->where('active', true)
            ->latest()->get();

        return Inertia::render('Blog', [
            'articles' => $articles,
            'seo' => [
                'title' => __('Blog'),
                'description' => __('Blog Description'),
            ],
        ]);
    }

    public function article(Article $article): Response
    {
        abort_if(! $article->active, 404);

        View::share(['schema' => ['article' => app(SchemaOrg::class)->article($article->load('user'))]]);

        return Inertia::render('Article', [
            'article' => $article->load('user'),
            'seo' => [
                'title' => $article->seo_title ?? $article->title,
                'description' => $article->seo_description ?? Str::limit($article->content, 160),
                'canonical' => route('blog.article', ['article' => $article]),
            ],
        ]);
    }
}
