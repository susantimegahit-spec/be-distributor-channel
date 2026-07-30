<?php

namespace App\Livewire\Pulse;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Livewire\Card;

class UserControlCard extends Card
{
    public string $search = '';
    public ?string $message = null;

    /**
     * Kill (force logout) a specific user by revoking all Sanctum tokens & active sessions.
     */
    public function killUser(int $userId): void
    {
        $user = User::with('role')->find($userId);

        if (!$user) {
            $this->message = "User ID {$userId} tidak ditemukan.";
            return;
        }

        // 1. Revoke / delete all Sanctum personal access tokens
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        // 2. Clear sessions table if session table exists
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $userId)->delete();
        }

        $username = $user->username ?? $user->name ?? "ID #{$userId}";
        $this->message = "🚫 User {$username} (ID: {$userId}) telah berhasil di-KILL & di-logout dari seluruh device!";
    }

    /**
     * Render the Livewire component view.
     */
    public function render()
    {
        // Retrieve users with active tokens or recent activity
        $query = User::with(['role'])->withCount('tokens');

        if (!empty($this->search)) {
            $searchTerm = '%' . strtolower($this->search) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(username) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(name) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$searchTerm]);
            });
        }

        $users = $query->orderBy('updated_at', 'desc')->take(20)->get();

        return view('livewire.pulse.user-control-card', [
            'users' => $users,
        ]);
    }
}
