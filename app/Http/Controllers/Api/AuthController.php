<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Exposant;
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
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'user_type' => 'required|in:visiteur,exposant',
            'company_name' => 'required_if:user_type,exposant|string|max:255',
            'siren_number' => 'required_if:user_type,exposant|string|size:9|regex:/^[0-9]+$/|unique:companies,siren_number',
        ]);

        // Validation de l'unicité de l'email selon le type d'utilisateur
        if ($request->user_type === 'visiteur') {
            $emailExists = User::where('email', $request->email)->exists() ||
                Exposant::where('email', $request->email)->exists();
        } else {
            $emailExists = Exposant::where('email', $request->email)->exists() ||
                User::where('email', $request->email)->exists();
        }

        if ($emailExists) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => ['email' => ['Cet email est déjà utilisé']]
            ], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Transaction pour créer utilisateur + company ensemble
        DB::beginTransaction();

        try {
            $userData = [
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ];

            $company = null;
            $user = null;

            if ($request->user_type === 'visiteur') {
                // 1️⃣ Créer un visiteur
                $user = User::create($userData);
            } else {
                // 1️⃣ Créer un exposant
                $user = Exposant::create($userData);

                // 2️⃣ Créer la company
                $company = Company::create([
                    'exposant_id' => $user->id,
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
                        'user_type' => $request->user_type,
                        'company' => $company ? [
                            'id' => $company->id,
                            'company_name' => $company->company_name,
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

        // Vérifier dans les deux tables
        $existsInUsers = User::where('email', $request->email)->exists();
        $existsInExposants = Exposant::where('email', $request->email)->exists();

        $exists = $existsInUsers || $existsInExposants;

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

        $user = null;
        $userType = null;

        // Chercher dans la table users (visiteurs)
        $visiteur = User::where('email', $request->email)->first();
        if ($visiteur && Hash::check($request->password, $visiteur->password)) {
            $user = $visiteur;
            $userType = 'visiteur';
        }

        // Si pas trouvé, chercher dans la table exposants
        if (!$user) {
            $exposant = Exposant::with('company')->where('email', $request->email)->first();
            if ($exposant && Hash::check($request->password, $exposant->password)) {
                $user = $exposant;
                $userType = 'exposant';
            }
        }

        if (!$user) {
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
                    'user_type' => $userType,
                    'company' => ($userType === 'exposant' && $user->company) ? [
                        'id' => $user->company->id,
                        'company_name' => $user->company->company_name,
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
