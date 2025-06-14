<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function index()
    {
        $articles = Article::where('status', 'published')->get();
        return response()->json([
            'articles' => ArticleResource::collection($articles),
        ]);
    }

    public function allBlogs(Request $request){
        $articles = Article::all();
        return response()->json([
            'articles' => ArticleResource::collection($articles),
        ]);
    }

    public function show($slug)
    {
        $article = Article::where('slug', $slug)->firstOrFail();
        return response()->json([
            'article' => $article,
        ]);
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'title' => 'required|string|max:255|unique:articles,title',
            'content' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'required|in:published,draft',
            'author' => 'required|string',
            'categories' => 'required|array',
            'categories.*' => 'exists:categories,id',
        ]);

        if($validation->fails()){
            return response()->json([
                'error' => true,
                'errors' => $validation->errors(),
                'message' => 'Validation Failed',
            ]);
        }

        $article = new Article();
        $article->title = $request->title;
        $article->content = $request->content;
        $article->slug = Str::slug($request->title);
        $article->categories()->sync($request->categories);
        //save blog image

        if($request->hasFile('image')){
            $image = $request->file('image');
            $imageName = time().'.'.$image->getClientOriginalExtension();
            $image->move(public_path('images'), $imageName);
            $article->image = $imageName;
        }
        $article->status = $request->status;
        $article->author = $request->author;
        $article->save();

        return response()->json([
            'message' => 'Article created successfully',
            'article' => $article,
        ]);
    }


    public function getBlogCategory(Request $request){
        $categories = Category::latest()->paginate(10);
        return $categories;

    }

    public function storeCategory(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        if($validation->fails()){
            return response()->json([
                'error' => true,
                'errors' => $validation->errors(),
                'message' => 'Validation Failed',
            ]);
        }

        $category = new Category();
        $category->name = $request->name;
        $category->slug = Str::slug($request->name);
        $category->save();

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category,
        ]);
    }
}
