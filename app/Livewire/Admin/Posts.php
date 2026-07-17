<?php

namespace App\Livewire\Admin;

use App\Models\Post;
use App\Support\Auditor;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Admin → Blog (Module 30). List, create and edit posts: title/slug, category,
 * excerpt, body (safe light markup), WebP/JPEG cover, SEO title/description,
 * and draft/publish. Publishing stamps published_at; only published + past-dated
 * posts are ever public.
 */
#[Layout('components.layouts.admin')]
class Posts extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $editingId = null;

    public bool $showForm = false;

    public string $title = '';

    public string $slug = '';

    public string $category = 'News';

    public string $excerpt = '';

    public string $body = '';

    public $cover = null;

    public ?string $coverUrl = null;

    public string $meta_title = '';

    public string $meta_description = '';

    public string $status = 'draft';

    public ?string $saved = null;

    public function newPost(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $post = Post::findOrFail($id);
        $this->editingId = $post->id;
        $this->title = $post->title;
        $this->slug = $post->slug;
        $this->category = $post->category;
        $this->excerpt = (string) $post->excerpt;
        $this->body = $post->body;
        $this->coverUrl = $post->cover_image_url;
        $this->cover = null;
        $this->meta_title = (string) $post->meta_title;
        $this->meta_description = (string) $post->meta_description;
        $this->status = $post->status;
        $this->showForm = true;
        $this->saved = null;
    }

    public function updatedTitle(): void
    {
        // Auto-suggest a slug only for a brand-new post the user hasn't slugged.
        if ($this->editingId === null && $this->slug === '') {
            $this->slug = Str::slug($this->title);
        }
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'title' => 'required|string|max:160',
            'slug' => 'nullable|string|max:180|regex:/^[a-z0-9\-]*$/',
            'category' => 'required|string|max:40',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string|max:60000',
            'cover' => 'nullable|file|mimes:webp,jpg,jpeg,png|max:2048',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:300',
            'status' => 'required|in:draft,published',
        ]);

        $slug = $this->slug !== '' ? Str::slug($this->slug) : $this->title;
        $slug = Post::uniqueSlug($slug, $this->editingId);

        $data = [
            'title' => trim($this->title),
            'slug' => $slug,
            'category' => trim($this->category),
            'excerpt' => trim($this->excerpt) ?: null,
            'body' => trim($this->body),
            'meta_title' => trim($this->meta_title) ?: null,
            'meta_description' => trim($this->meta_description) ?: null,
            'status' => $this->status,
        ];

        if ($this->cover) {
            $data['cover_image_url'] = MediaStorage::storePublic($this->cover, 'blog');
        }

        if ($this->editingId) {
            $post = Post::findOrFail($this->editingId);
            // Stamp published_at the first time it goes live.
            if ($this->status === 'published' && ! $post->published_at) {
                $data['published_at'] = now();
            }
            if ($this->status === 'draft') {
                $data['published_at'] = null;
            }
            $post->update($data);
        } else {
            $data['author_id'] = Auth::id();
            $data['published_at'] = $this->status === 'published' ? now() : null;
            $post = Post::create($data);
        }

        Auditor::log('post.saved', Post::class, $post->id, ['status' => $post->status]);
        $this->saved = "Post “{$post->title}” saved ({$post->status}).";
        $this->dispatch('nx-toast', type: 'success', message: 'Post saved.');
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $post = Post::findOrFail($id);
        Auditor::log('post.deleted', Post::class, $post->id);
        $post->delete();
        $this->saved = 'Post deleted.';
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'showForm', 'title', 'slug', 'category', 'excerpt', 'body', 'cover', 'coverUrl', 'meta_title', 'meta_description', 'status']);
        $this->category = 'News';
        $this->status = 'draft';
    }

    public function render()
    {
        return view('livewire.admin.posts', [
            'posts' => Post::latest()->paginate(10),
        ]);
    }
}
