<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'user_type' => 'required|in:visiteur,exposant',

            'company_name' => 'required_if:user_type,exposant|string|max:255',
            'siren_number' => 'required_if:user_type,exposant|string|size:9|regex:/^[0-9]+$/|unique:companies,siren_number',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Transaction pour créer user + company ensemble
        DB::beginTransaction();

        try {
            // 1️⃣ Créer l'utilisateur
            $user = User::create([
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => $request->user_type,
            ]);

            // 2️⃣ Si c'est un exposant, créer la company
            $company = null;
            if ($request->user_type === 'exposant') {
                $company = Company::create([
                    'user_id' => $user->id,
                    'company_name' => $request->company_name,
                    'siren_number' => $request->siren_number,
                ]);
            }

            // 3️⃣ Créer le token d'authentification
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit(); // ✅ Valider la transaction

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie !',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'firstName' => $user->firstName,
                        'lastName' => $user->lastName,
                        'email' => $user->email,
                        'user_type' => $user->user_type,
                        'company' => $company ? [
                            'id' => $company->id,
                            'company_name' => $company->name,
                            'siren_number' => $company->siren_number,
                        ] : null,
                    ],
                    'token' => $token,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollback(); // ❌ Annuler en cas d'erreur

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'Cet email est déjà utilisé' : 'Email disponible'
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Récupérer l'utilisateur avec sa company si elle existe
        $user = User::with('company')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie !',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'firstName' => $user->firstName,
                    'lastName' => $user->lastName,
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                    'company' => $user->company ? [
                        'id' => $user->company->id,
                        'company_name' => $user->company->name,
                        'siren_number' => $user->company->siren_number,
                    ] : null,
                ],
                'token' => $token,
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie !'
        ]);
    }
}
