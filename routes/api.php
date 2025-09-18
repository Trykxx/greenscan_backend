<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ProfileController;
use App\Models\User;
use App\Models\Exposant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes publiques
Route::post('/check-email', [AuthController::class, 'checkEmail']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {

    // Route pour récupérer les infos de l'utilisateur connecté
    Route::get('/user', function (Request $request) {
        $user = $request->user();

        // Déterminer le type d'utilisateur selon la classe du modèle
        if ($user instanceof User) {
            $userType = 'visiteur';
            $company = null;
        } elseif ($user instanceof Exposant) {
            $userType = 'exposant';
            $user->load('company');
            $company = $user->company ? [
                'id' => $user->company->id,
                'company_name' => $user->company->company_name,
                'siren_number' => $user->company->siren_number,
            ] : null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'user_type' => $userType,
                'company' => $company,
            ]
        ]);
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // Routes de profil
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // Routes pour les documents
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::get('/users/{userId}/documents', [DocumentController::class, 'getUserDocuments']);
});
