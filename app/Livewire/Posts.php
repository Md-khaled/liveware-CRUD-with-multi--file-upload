<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\File;
use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class Posts extends Component
{
    use WithFileUploads;

    #[Rule('required|min:3|max:255')]
    public string $title = '';

    #[Rule('required|min:10')]
    public string $body = '';

    #[Rule('array')]
    #[Rule('max:1024', as: 'photos.*')]
    public array $photos = [];

    public ?Collection $posts = null;
    public ?Collection $currentFiles = null;
    public ?int $post_id = null;
    public bool $isOpen = false;

    public function mount(): void
    {
        $this->currentFiles = new Collection();
    }

    public function render(): View
    {
        $this->posts = Post::with('files')->latest()->get();
        return view('livewire.posts');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->openModal();
    }

    public function store(): void
    {
        $this->validate();

        $post = Post::updateOrCreate(
            ['id' => $this->post_id],
            [
                'title' => $this->title,
                'body' => $this->body,
            ]
        );

        $this->handleFileUploads($post);

        $message = $this->post_id
            ? 'Post updated successfully.'
            : 'Post created successfully.';

        $this->dispatch('post-saved', message: $message);

        $this->closeModal();
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $post = Post::with('files')->findOrFail($id);

        $this->post_id = $post->id;
        $this->title = $post->title;
        $this->body = $post->body;
        $this->currentFiles = $post->files;

        $this->openModal();
    }

    public function delete(int $id): void
    {
        $post = Post::with('files')->findOrFail($id);

        $this->deletePostFiles($post);
        $post->delete();

        $this->dispatch('post-deleted', message: 'Post deleted successfully.');
    }

    public function deleteFile(int $fileId): void
    {
        $file = File::findOrFail($fileId);
        Storage::disk('public')->delete($file->file_path);
        $file->delete();

        $this->refreshCurrentFiles();

        $this->dispatch('file-deleted', message: 'File deleted successfully.');
    }

    public function openModal(): void
    {
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
    }

    private function resetForm(): void
    {
        $this->reset('title', 'body', 'post_id', 'photos');
        $this->currentFiles = new Collection();
    }

    private function handleFileUploads(Post $post): void
    {
        if (empty($this->photos)) {
            return;
        }

        /** @var TemporaryUploadedFile $photo */
        foreach ($this->photos as $photo) {
            $path = $photo->store('files', 'public');
            $post->files()->create(['file_path' => $path]);
        }
    }

    private function deletePostFiles(Post $post): void
    {
        foreach ($post->files as $file) {
            Storage::disk('public')->delete($file->file_path);
        }
    }

    private function refreshCurrentFiles(): void
    {
        if (!$this->post_id) {
            $this->currentFiles = new Collection();
            return;
        }

        $this->currentFiles = File::where('fileable_id', $this->post_id)
            ->where('fileable_type', Post::class)
            ->get();
    }
}
