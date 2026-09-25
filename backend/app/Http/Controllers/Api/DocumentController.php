<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Teacher;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    private const CACHE_TTL = 300;
    private const MAX_FILE_SIZE = 5120;
    private const ALLOWED_MIMES = 'pdf,doc,docx,jpeg,png,jpg';
    private const ALLOWED_TYPES = 'cv,contract,diploma,other';
    private const MAX_LIMIT = 100;
    private const DEFAULT_LIMIT = 25;

    public function index(Request $request, Teacher $teacher): JsonResponse
    {
        try {
            $this->authorizeTeacherAccess($teacher);

            $request->validate([
                'search' => 'nullable|string|max:255',
                'type' => 'nullable|string|in:' . self::ALLOWED_TYPES,
                'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
                'page' => 'nullable|integer|min:1',
            ]);

            $limit = min((int)$request->input('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
            $page = max(1, (int)$request->input('page', 1));
            $search = $request->input('search', null);
            $type = $request->input('type', null);

            $cacheKey = "documents:teacher:{$teacher->id}:search:{$search}:type:{$type}:page:{$page}:limit:{$limit}";

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Document] Cache hit', ['teacher_id' => $teacher->id, 'user_id' => auth()->id()]);
                return response()->json($cached);
            }

            $query = $teacher->documents()->with('uploader:id,name,email');

            if ($search) {
                $query->where('name', 'like', '%' . trim($search) . '%');
            }

            if ($type) {
                $query->where('type', $type);
            }

            $pagination = $query->latest('created_at')
                ->paginate($limit, ['id', 'teacher_id', 'name', 'type', 'file_size', 'mime_type', 'uploaded_by', 'created_at'], 'page', $page);

            $response = [
                'success' => true,
                'data' => $pagination->items(),
                'pagination' => [
                    'total' => $pagination->total(),
                    'per_page' => $pagination->perPage(),
                    'current_page' => $pagination->currentPage(),
                    'last_page' => $pagination->lastPage(),
                    'has_more' => $pagination->hasMorePages(),
                ],
            ];

            Cache::put($cacheKey, $response, now()->addSeconds(self::CACHE_TTL));

            Log::info('[Document] List fetched', [
                'teacher_id' => $teacher->id,
                'user_id' => auth()->id(),
                'total' => $response['pagination']['total'],
            ]);

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Document] Validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Document] Index error', [
                'error' => $e->getMessage(),
                'teacher_id' => $teacher->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function store(Request $request, Teacher $teacher): JsonResponse
    {
        try {
            $this->authorizeTeacherAccess($teacher);

            $validated = $request->validate([
                'file' => 'required|file|mimes:' . self::ALLOWED_MIMES . '|max:' . self::MAX_FILE_SIZE,
                'type' => 'required|string|in:' . self::ALLOWED_TYPES,
                'name' => 'required|string|max:255|unique:documents,name,NULL,id,teacher_id,' . $teacher->id,
            ]);

            if (!$request->hasFile('file')) {
                Log::warning('[Document] File missing on upload', ['teacher_id' => $teacher->id, 'user_id' => auth()->id()]);
                return response()->json(['success' => false, 'message' => 'Fichier manquant'], 400);
            }

            $file = $request->file('file');

            // Validate MIME type
            $allowedMimes = ['application/pdf', 'application/msword',
                           'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                           'image/jpeg', 'image/png'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                Log::warning('[Document] Invalid MIME type', [
                    'mime' => $file->getMimeType(),
                    'teacher_id' => $teacher->id,
                    'user_id' => auth()->id(),
                ]);
                return response()->json(['success' => false, 'message' => 'Type de fichier non autorisé'], 422);
            }

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

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'upload_document',
                'model' => 'Document',
                'model_id' => $document->id,
                'ip_address' => $request->ip(),
            ]);

            $this->invalidateTeacherCache($teacher->id);

            Log::info('[Document] Uploaded', [
                'document_id' => $document->id,
                'teacher_id' => $teacher->id,
                'file_size' => $file->getSize(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploadé',
                'data' => $document,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Document] Validation error on upload', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Document] Store error', [
                'error' => $e->getMessage(),
                'teacher_id' => $teacher->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        try {
            $this->authorizeDocumentAccess($document);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:documents,name,' . $document->id . ',id,teacher_id,' . $document->teacher_id,
                'type' => 'sometimes|string|in:' . self::ALLOWED_TYPES,
                'file' => 'nullable|file|mimes:' . self::ALLOWED_MIMES . '|max:' . self::MAX_FILE_SIZE,
            ]);

            if ($request->hasFile('file')) {
                $file = $request->file('file');

                $allowedMimes = ['application/pdf', 'application/msword',
                               'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                               'image/jpeg', 'image/png'];
                if (!in_array($file->getMimeType(), $allowedMimes)) {
                    Log::warning('[Document] Invalid MIME on update', [
                        'mime' => $file->getMimeType(),
                        'document_id' => $document->id,
                        'user_id' => auth()->id(),
                    ]);
                    return response()->json(['success' => false, 'message' => 'Type de fichier non autorisé'], 422);
                }

                // Delete old file
                if (Storage::disk('local')->exists($document->file_path)) {
                    Storage::disk('local')->delete($document->file_path);
                    Log::info('[Document] Old file deleted', ['document_id' => $document->id]);
                }

                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('documents/teacher_' . $document->teacher_id, $fileName, 'local');

                $document->file_path = $path;
                $document->mime_type = $file->getMimeType();
                $document->file_size = $file->getSize();
            }

            if (isset($validated['name'])) {
                $document->name = $validated['name'];
            }

            if (isset($validated['type'])) {
                $document->type = $validated['type'];
            }

            $document->save();

            $this->invalidateTeacherCache($document->teacher_id);

            Log::info('[Document] Updated', [
                'document_id' => $document->id,
                'teacher_id' => $document->teacher_id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document modifié',
                'data' => $document,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Document] Validation error on update', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Document] Update error', [
                'error' => $e->getMessage(),
                'document_id' => $document->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function destroy(Document $document): JsonResponse
    {
        try {
            $this->authorizeDocumentAccess($document);

            $documentId = $document->id;
            $teacherId = $document->teacher_id;
            $filePath = $document->file_path;

            // Delete file from storage
            if (Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
                Log::debug('[Document] File deleted from storage', ['document_id' => $documentId]);
            }

            $document->delete();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'delete_document',
                'model' => 'Document',
                'model_id' => $documentId,
                'ip_address' => request()->getClientIp(),
            ]);

            $this->invalidateTeacherCache($teacherId);

            Log::info('[Document] Deleted', [
                'document_id' => $documentId,
                'teacher_id' => $teacherId,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document supprimé',
                'document_id' => $documentId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Document] Delete error', [
                'error' => $e->getMessage(),
                'document_id' => $document->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function download(Document $document): Response|JsonResponse
    {
        try {
            $this->authorizeDocumentAccess($document);

            if (!Storage::disk('local')->exists($document->file_path)) {
                Log::warning('[Document] File not found for download', [
                    'document_id' => $document->id,
                    'path' => $document->file_path,
                    'user_id' => auth()->id(),
                ]);
                return response()->json(['success' => false, 'message' => 'Fichier non trouvé'], 404);
            }

            Log::info('[Document] Downloaded', [
                'document_id' => $document->id,
                'teacher_id' => $document->teacher_id,
                'user_id' => auth()->id(),
            ]);

            return Storage::disk('local')->download($document->file_path, $document->name);
        } catch (\Throwable $e) {
            Log::error('[Document] Download error', [
                'error' => $e->getMessage(),
                'document_id' => $document->id ?? null,
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    private function authorizeTeacherAccess(Teacher $teacher): void
    {
        $user = auth()->user();
        if ($user->id !== $teacher->user_id && !$user->hasAnyRole(['admin', 'hr_manager', 'secretary', 'accountant'])) {
            Log::warning('[Document] Unauthorized teacher access', [
                'teacher_id' => $teacher->id,
                'user_id' => $user->id,
                'user_roles' => $user->roles->pluck('name')->toArray(),
            ]);
            abort(403, 'Non autorisé');
        }
    }

    private function authorizeDocumentAccess(Document $document): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated');
        }
        if (!$document->teacher || ($user->id !== $document->teacher->user_id && !$user->hasAnyRole(['admin', 'hr_manager', 'secretary', 'accountant']))) {
            Log::warning('[Document] Unauthorized document access', [
                'document_id' => $document->id,
                'user_id' => $user->id,
                'user_roles' => $user->roles->pluck('name')->toArray(),
            ]);
            abort(403, 'Non autorisé');
        }
    }

    private function invalidateTeacherCache(int $teacherId): void
    {
        Cache::tags(["documents:teacher:{$teacherId}"])->flush();
    }
}
