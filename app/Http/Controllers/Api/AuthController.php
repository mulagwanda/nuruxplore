<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'credits_balance' => 10,
            'subscription_plan' => 'free',
        ]);

        // Create API token
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Log into web session
        Auth::guard('web')->login($user);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'redirect' => '/dashboard',
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Create API token
        $token = $user->createToken('auth-token')->plainTextToken;
        
        // Log into web session (for page access)
        Auth::guard('web')->login($user);
        
        // Regenerate session for security
        $request->session()->regenerate();

        return response()->json([
            'user' => $user,
            'token' => $token,
            'redirect' => '/dashboard',
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke API token
        $request->user()->currentAccessToken()->delete();
        
        // Logout from web session
        Auth::guard('web')->logout();
        
        // Invalidate session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}