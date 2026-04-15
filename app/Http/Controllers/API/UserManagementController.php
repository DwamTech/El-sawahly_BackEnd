<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{
    /**
     * Get current authenticated user profile.
     */
    public function profile()
    {
        return response()->json(auth()->user());
    }

    /**
     * Update current authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:1048576',
            'email' => 'email|unique:users,email,'.$user->id,
            'current_password' => 'required_with:new_password',
            'new_password' => 'nullable|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->name;
        }

        if ($request->has('email')) {
            $data['email'] = $request->email;
        }

        // Change password if provided
        if ($request->filled('new_password')) {
            // Verify current password
            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'errors' => ['current_password' => ['كلمة المرور الحالية غير صحيحة']],
                ], 422);
            }

            $data['password'] = Hash::make($request->new_password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * List all users (Admin only).
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(20);

        return response()->json($users);
    }

    /**
     * Show specific user details (Admin only).
     */
    public function show($id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }

        return response()->json($user);
    }

    /**
     * Create new user (Admin only).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:1048576',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:'.implode(',', User::manageableRoles()),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح',
            'user' => $user,
        ], 201);
    }

    /**
     * Update user (Admin only).
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:1048576',
            'email' => 'email|unique:users,email,'.$id,
            'role' => 'in:'.implode(',', User::manageableRoles()),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->name;
        }

        if ($request->has('email')) {
            $data['email'] = $request->email;
        }

        if ($request->has('role')) {
            if ((int) $user->id === (int) auth()->id() && $request->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'message' => 'لا يمكنك خفض صلاحيات حسابك الإداري الحالي',
                ], 403);
            }

            $data['role'] = $request->role;
        }

        $user->update($data);

        return response()->json([
            'message' => 'تم تحديث المستخدم بنجاح',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Change user password (Admin only).
     */
    public function changePassword(Request $request, $id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }

        $validator = Validator::make($request->all(), [
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح',
        ]);
    }

    /**
     * Delete user (Admin only).
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'لا يمكنك حذف حسابك الخاص',
            ], 403);
        }

        if ($user->role === User::ROLE_ADMIN && User::query()->where('role', User::ROLE_ADMIN)->count() <= 1) {
            return response()->json([
                'message' => 'لا يمكن حذف آخر حساب إداري في النظام',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'تم حذف المستخدم بنجاح',
        ]);
    }
}
