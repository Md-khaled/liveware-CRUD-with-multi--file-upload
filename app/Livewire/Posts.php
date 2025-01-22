<?php

namespace App\Livewire;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use App\Models\Post;
use Livewire\WithFileUploads;

class Posts extends Component
{
    use WithFileUploads;
    public $posts, $title, $body, $post_id, $photos = [], $currentFiles = [];
    public $isOpen = 0;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function render()
    {
        $this->posts = Post::with('files')->get();
        return view('livewire.posts');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function create()
    {
        $this->resetInputFields();
        $this->openModal();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function openModal()
    {
        $this->isOpen = true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function closeModal()
    {
        $this->isOpen = false;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    private function resetInputFields(){
        $this->title = '';
        $this->body = '';
        $this->post_id = '';
        $this->photos = [];
        $this->currentFiles = [];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function store()
    {
        $this->validate([
            'title' => 'required',
            'body' => 'required',
            'photos.*' => 'image|max:1024',
        ]);

        $post = Post::updateOrCreate(['id' => $this->post_id], [
            'title' => $this->title,
            'body' => $this->body,
        ]);

        if ($this->photos) {
            foreach ($this->photos as $photo) {
                $path = $photo->store('files', 'public');
                $post->files()->create([
                    'file_path' => $path,
                ]);
            }
        }

        session()->flash('message',
            $this->post_id ? 'Post Updated Successfully.' : 'Post Created Successfully.');

        $this->closeModal();
        $this->resetInputFields();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function edit($id)
    {
        $post = Post::with('files')->findOrFail($id);
        $this->post_id = $id;
        $this->title = $post->title;
        $this->body = $post->body;
        $this->currentFiles = $post->files;

        $this->openModal();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function delete($id)
    {
        $post = Post::findOrFail($id);

        foreach ($post->files as $file) {
            Storage::disk('public')->delete($file->file_path);
        }

        $post->delete();
        session()->flash('message', 'Post Deleted Successfully.');
    }

    public function deleteFile($fileId)
    {
        $file = File::findOrFail($fileId);
        Storage::disk('public')->delete($file->file_path);
        $file->delete();

        $this->currentFiles = File::where('fileable_id', $this->post_id)
            ->where('fileable_type', Post::class)
            ->get();

        session()->flash('message', 'File Deleted Successfully.');
    }
}
