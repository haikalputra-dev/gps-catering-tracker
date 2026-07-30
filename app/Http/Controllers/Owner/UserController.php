<?php

declare(strict_types=1);

namespace App\Http\Controllers\Owner;

use App\Domain\Identity\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreUserRequest;
use App\Http\Requests\Owner\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Owner-facing user account management.
 *
 * All operations exclude the owner role. Owners cannot be listed, edited,
 * created or demoted through this controller.
 */
class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->whereIn('role', UserRole::manageableValues())
            ->orderBy('name')
            ->get();

        return view('owner.users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('owner.users.create', [
            'roles' => UserRole::manageableRoles(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => UserRole::from($validated['role']),
            'is_active' => (bool) $validated['is_active'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('owner.users.index')
            ->with('status', 'Account created.');
    }

    public function edit(User $user): View
    {
        $this->guardManageable($user);

        return view('owner.users.edit', [
            'user' => $user,
            'roles' => UserRole::manageableRoles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->guardManageable($user);

        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->role = UserRole::from($validated['role']);
        $user->is_active = (bool) $validated['is_active'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('owner.users.index')
            ->with('status', 'Account updated.');
    }

    /**
     * Reject any attempt to interact with a non-manageable target such as an owner.
     * Returning 404 avoids leaking whether the id belongs to an owner or is unknown.
     */
    private function guardManageable(User $user): void
    {
        if (! in_array($user->role, UserRole::manageableRoles(), true)) {
            throw new NotFoundHttpException();
        }
    }
}
