<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(StoreUserRequest $request)
    {
        $request->validated($request->all());
        $role = env('USER', 'user');

        // Check if there is a token AND the user is Admin
        if (Auth::guard('sanctum')->check()) {
            $currentUser = Auth::guard('sanctum')->user();
            if ($currentUser->role === env('ADMIN', 'admin')) {
                // Allow Admin to set the role
                $role = $request->role ?? env('USER', 'user');
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role,
        ]);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('API Token of '.$user->name)->plainTextToken,
        ]);
    }

    public function registerAdmin(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Login Logic
            if (! Auth::attempt($request->only(['email', 'password']))) {
                return response()->json([
                    'message' => 'Credentials do not match.',
                ], 401);
            }
        } else {
            // Register Logic
            $name = $request->name ?? 'Admin ' . \Illuminate\Support\Str::random(6);

            $user = User::create([
                'name' => $name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => env('ADMIN', 'admin'),
            ]);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('API Token of '.$user->name)->plainTextToken,
        ]);
    }

    public function loginOrRegister(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Login Logic
            if (! Auth::attempt($request->only(['email', 'password']))) {
                return response()->json([
                    'message' => 'Credentials do not match.',
                ], 401);
            }
        } else {
            // Register Logic
            if (!$request->name) {
                return response()->json([
                    'message' => 'Name is required for new registration.',
                ], 422);
            }

            // Optional: Check password strength/length for new users
            if (strlen($request->password) < 8) {
                return response()->json([
                    'message' => 'Password must be at least 8 characters.',
                ], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => env('USER', 'user'),
            ]);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('API Token of '.$user->name)->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    // set role for admin
    public function setRole(Request $request, User $user)
    {
        // $user = $request->user();
        $data = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', [
                env('ADMIN', 'admin'),
                env('EDITOR', 'editor'),
                env('AUTHOR', 'author'),
                env('REVIEWER', 'reviewer'),
                env('USER', 'user'),
            ])],
        ]);
        // $user->role = $request->role;
        if ($request->user()->role !== env('ADMIN', 'admin')) {
            return response()->json([
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        }
        $user->role = $data['role'];
        $user->save();

        return response()->json([
            'message' => 'Role updated successfully.',
        ]);
    }

    // Validate Token Method
    public function validateToken(Request $request)
    {
        return response()->json([
            'valid' => true,
            'user' => $request->user(),
            'message' => 'Token is valid',
        ]);
    }
}
