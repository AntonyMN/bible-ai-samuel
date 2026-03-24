<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class MemoryController extends Controller
{
    /**
     * Display a listing of the user's memories.
     */
    public function index()
    {
        $memories = Memory::where('user_id', Auth::id())
            ->orderBy('is_completed', 'asc')
            ->orderBy('importance', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Memories/Index', [
            'memories' => $memories
        ]);
    }

    /**
     * Store a newly created memory in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:500',
            'category' => 'required|string',
            'importance' => 'required|integer|min:1|max:5',
        ]);

        Memory::create([
            'user_id' => Auth::id(),
            'content' => $request->content,
            'category' => $request->category,
            'importance' => $request->importance,
            'is_completed' => false,
        ]);

        return redirect()->back()->with('message', 'Memory added successfully');
    }

    /**
     * Update the specified memory in storage.
     */
    public function update(Request $request, $id)
    {
        $memory = Memory::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'content' => 'sometimes|string|max:500',
            'category' => 'sometimes|string',
            'importance' => 'sometimes|integer|min:1|max:5',
            'is_completed' => 'sometimes|boolean',
        ]);

        $memory->update($request->only(['content', 'category', 'importance', 'is_completed']));

        return redirect()->back()->with('message', 'Memory updated successfully');
    }

    /**
     * Remove the specified memory from storage.
     */
    public function destroy($id)
    {
        $memory = Memory::where('user_id', Auth::id())->findOrFail($id);
        $memory->delete();

        return redirect()->back()->with('message', 'Memory deleted successfully');
    }
}
