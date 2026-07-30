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
        try {
            $user = User::find($userId);

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
        } catch (\Throwable $e) {
            $this->message = "Gagal kill user: " . $e->getMessage();
        }
    }

    /**
     * Render the Livewire component view.
     */
    public function render()
    {
        try {
            $query = User::query();

            // Safely check for role relation
            if (method_exists(User::class, 'role')) {
                $query->with('role');
            }

            // Safely check for personal access tokens count
            if (Schema::hasTable('personal_access_tokens')) {
                $query->withCount('tokens');
            }

            if (!empty($this->search)) {
                $searchTerm = '%' . strtolower($this->search) . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('LOWER(username) LIKE ?', [$searchTerm])
                      ->orWhereRaw('LOWER(name) LIKE ?', [$searchTerm])
                      ->orWhereRaw('LOWER(email) LIKE ?', [$searchTerm]);
                });
            }

            $users = $query->take(25)->get();
        } catch (\Throwable $e) {
            $users = collect();
            $this->message = "Error loading users: " . $e->getMessage();
        }

        return view('livewire.pulse.user-control-card', [
            'users' => $users,
        ]);
    }
}
