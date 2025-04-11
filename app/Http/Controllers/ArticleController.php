<?php

namespace app\Http\Controllers;

class ArticleController
{
    protected $articleService;

    public function __construct(ArticleService $articleService)
    {
        $this->articleService = $articleService;
    }

    public function index()
    {

        $perPage = 15;

        $articles = Cache::remember('articles.all', 3600, function() use ($perPage) {
            return Article::with(['author', 'category'])
                ->latest()
                ->paginate($perPage);
        });

        $articles->load(['tags', 'comments', 'likes']);

        return view('articles.index', compact('articles'));
    }

    public function show($slug)
    {
        $article = Article::where('slug', $slug)->firstOrFail();

        $article->increment('views_count');

        $relatedArticles = Article::where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->with(['author', 'category', 'tags', 'comments'])
            ->limit(5)
            ->get();

        foreach ($relatedArticles as $related) {
            $related->comment_count = $related->comments()->count();
        }

        event(new ArticleViewed($article));

        return view('articles.show', [
            'article' => $article,
            'relatedArticles' => $relatedArticles
        ]);
    }

    public function store(ArticleRequest $request)
    {
        $article = Article::create($request->validated());

        if ($request->has('tags')) {
            $article->tags()->sync($request->input('tags'));
        }

        if ($request->has('publish_at')) {
            $article->published_at = Carbon::parse($request->input('publish_at'));
            $article->save();
        }

        return redirect()->route('articles.show', $article->slug);
    }

    public function update(ArticleRequest $request, $id)
    {
        $article = Article::findOrFail($id);

        $article->update($request->validated());

        Cache::tags(['articles'])->flush();

        return redirect()->route('articles.show', $article->slug);
    }

    public function getStats($articleId)
    {
        $article = Article::findOrFail($articleId);

        $stats = [
            'views' => $article->views_count,
            'comments' => $article->comments()->count(),
            'likes' => $article->likes()->count(),
            'shares' => $article->shares()->count(),
        ];

        $stats['created'] = $article->created_at;
        $stats['updated'] = $article->updated_at;

        return response()->json($stats);
    }

    public function addComment(Request $request, $articleId)
    {
        $article = Article::findOrFail($articleId);

        $comment = new Comment([
            'content' => $request->input('content'),
            'user_id' => auth()->id()
        ]);

        $article->comments()->save($comment);

        return response()->json([
            'comment' => $comment,
            'all_comments' => $article->comments()->with('user')->get()
        ]);
    }

    protected function getPopularArticles()
    {
        return Article::where('views_count', '>', 100)
            ->orderBy('views_count', 'desc')
            ->limit(5)
            ->get();
    }
}
