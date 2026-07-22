<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'usuario' => 'required|string',
            'senha' => 'required|string',
        ]);

        $usuario = trim($request->usuario);
        $senha = trim($request->senha);

        $user = User::where('usuario', $usuario)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário ou senha inválidos.'
            ], 422);
        }

        $passwordMatches = false;
        $shouldRehash = false;

        // 1. Tentar verificar via Bcrypt (padrão do Laravel)
        if (Hash::check($senha, $user->senha)) {
            $passwordMatches = true;
        } 
        // 2. Se falhar, tentar o fallback de MD5 (legado)
        elseif (strlen($user->senha) === 32) {
            $md5Hash = md5($senha);
            if ($md5Hash === $user->senha) {
                $passwordMatches = true;
                $shouldRehash = true;
            }
        }

        if (!$passwordMatches) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário ou senha inválidos.'
            ], 422);
        }

        // 3. Rehash automático para Bcrypt se o login foi via MD5 legado
        if ($shouldRehash) {
            try {
                $user->senha = Hash::make($senha);
                $user->save();
                Log::info("Senha de usuário ID {$user->id} migrada de MD5 para Bcrypt com sucesso.");
            } catch (\Exception $e) {
                Log::error("Erro ao realizar rehash de senha para o usuário ID {$user->id}: " . $e->getMessage());
            }
        }

        // 4. Gerar o token do Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Você fez login com sucesso.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'usuario' => $user->usuario,
                'nivel_acesso' => $user->nivel_acesso,
            ]
        ]);
    }

    public function userInfo(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'usuario' => $user->usuario,
                'nivel_acesso' => $user->nivel_acesso,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout realizado com sucesso.'
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|min:3',
            'usuario' => 'required|string|email',
            'senha' => 'required|string|min:8',
            'nivel_acesso' => 'required|string',
        ]);

        $usuario = trim($request->usuario);

        $exists = User::where('usuario', $usuario)->exists();
        if ($exists) {
            return response()->json([
                'success' => 0,
                'status' => 422,
                'message' => 'Este e-mail já está em uso!'
            ], 422);
        }

        User::create([
            'nome' => trim($request->nome),
            'usuario' => $usuario,
            'senha' => Hash::make($request->senha),
            'nivel_acesso' => trim($request->nivel_acesso),
        ]);

        return response()->json([
            'success' => 1,
            'status' => 201,
            'message' => 'Você se registrou com sucesso.'
        ], 201);
    }

    public function getUsers()
    {
        try {
            $users = User::select('id', 'nome', 'matricula', 'usuario', 'nivel_acesso', 'criacao')->get();
            return response()->json([
                'status' => 'success',
                'data' => $users,
                'message' => 'User list retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'data' => null,
                'message' => 'Error: ' . $e->getMessage()
            ], 400);
        }
    }

    public function updateUser(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'nome' => 'required|string|min:3',
            'nivel_acesso' => 'required|string',
            'senha' => 'nullable|string',
        ]);

        try {
            $user = User::findOrFail($request->id);
            $user->nome = trim($request->nome);
            $user->nivel_acesso = trim($request->nivel_acesso);
            
            if ($request->filled('senha')) {
                $user->senha = Hash::make($request->senha);
            }
            
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Usuário atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro: ' . $e->getMessage()
            ], 400);
        }
    }
}
