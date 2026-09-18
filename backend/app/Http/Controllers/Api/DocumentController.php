<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * Liste des documents pour un enseignant spécifique
     */
    public function index(Request $request, Teacher $teacher): JsonResponse
    {
        $user = auth()->user();
        if ($user->id !== $teacher->user_id && !$user->hasRole(['admin', 'hr_manager', 'secretary', 'accountant'])) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $query = $teacher->documents()->with('uploader')->latest();
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . trim($request->input('search')) . '%');
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        return response()->json($query->paginate($request->input('per_page', 25)));
    }

    /**
     * Upload d'un nouveau document
     */
    public function store(Request $request, Teacher $teacher): JsonResponse
    {
        $user = auth()->user();
        if ($user->id !== $teacher->user_id && !$user->hasRole(['admin', 'hr_manager', 'secretary', 'accountant'])) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,jpeg,png|mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png|max:5120',
            'type' => 'required|string|in:cv,contract,diploma,other',
            'name' => 'required|string|max:255',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('documents/teacher_' . $teacher->id, $fileName, 'local');

            $document = Document::create([
                'teacher_id' => $teacher->id,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);

            // Logger l'action
            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'upload_document',
                'model' => 'Document',
                'model_id' => $document->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Document uploadé avec succès',
                'document' => $document
            ], 201);
        }

        return response()->json(['message' => 'Fichier manquant'], 400);
    }

    /**
     * Suppression d'un document
     */
    public function destroy(Document $document): JsonResponse
    {
        $user = auth()->user();
        if ($user->id !== $document->teacher->user_id && !$user->hasRole(['admin', 'hr_manager', 'secretary', 'accountant'])) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return response()->json(['message' => 'Document supprimé']);
    }

    /**
     * Modification des métadonnées et, si fourni, du fichier.
     */
    public function update(Request $request, Document $document): JsonResponse
    {
        $user = auth()->user();
        if ($user->id !== $document->teacher->user_id && !$user->hasRole(['admin', 'hr_manager', 'secretary', 'accountant'])) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:cv,contract,diploma,other',
            'file' => 'nullable|file|mimes:pdf,doc,docx,jpeg,png|mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png|max:5120',
        ]);

        if ($request->hasFile('file')) {
            if (Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }
            $file = $request->file('file');
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $document->file_path = $file->storeAs('documents/teacher_' . $document->teacher_id, $fileName, 'local');
            $document->mime_type = $file->getMimeType();
            $document->file_size = $file->getSize();
        }

        $document->name = $validated['name'];
        $document->type = $validated['type'];
        $document->save();

        return response()->json(['message' => 'Document modifié', 'document' => $document]);
    }

    /**
     * Download (Lien public via storage:link)
     */
    public function download(Document $document): Response|JsonResponse
    {
        $user = auth()->user();
        if ($user->id !== $document->teacher->user_id && !$user->hasRole(['admin', 'hr_manager', 'secretary', 'accountant'])) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return Storage::disk('local')->download($document->file_path, $document->name);
    }
}
