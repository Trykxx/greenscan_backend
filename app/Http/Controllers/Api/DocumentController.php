<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'firebase_url' => 'nullable|string',
        ]);

        $document = Document::create([
            'user_id' => $request->user_id,
            'name' => $request->name,
            'firebase_url' => $request->firebase_url,
        ]);

        return response()->json([
            'message' => 'Document créé avec succès',
            'data' => $document
        ], 201);
    }

    public function getUserDocuments($userId): JsonResponse
    {
        $documents = Document::where('user_id', $userId)->get();

        return response()->json([
            'data' => $documents
        ]);
    }
}
