<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="User Session Control (Kill User)" details="Revoke Sanctum Tokens & Active Sessions">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 stroke-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
        </x-slot:icon>

        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ url('/monitoringsm/logout') }}" class="px-2.5 py-1 text-xs font-semibold rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-red-500 hover:text-white transition">
                    Logout Admin
                </a>
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        <div class="p-4 space-y-4">
            @if ($message)
                <div class="p-3 text-xs font-semibold rounded-lg bg-red-500/10 border border-red-500/30 text-red-500 flex items-center justify-between">
                    <span>{{ $message }}</span>
                    <button wire:click="$set('message', null)" class="text-red-400 hover:text-red-300">✕</button>
                </div>
            @endif

            <div class="flex items-center justify-between gap-4">
                <div class="relative flex-1">
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari user (nama, username, email)..." 
                        class="w-full px-3 py-1.5 text-xs rounded-md bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:border-red-500"
                    />
                </div>
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    Menampilkan max 20 user
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                    <thead class="text-gray-400 uppercase bg-gray-50 dark:bg-gray-800/50 text-[10px] font-bold">
                        <tr>
                            <th class="px-3 py-2">User ID</th>
                            <th class="px-3 py-2">Pengguna</th>
                            <th class="px-3 py-2">Role</th>
                            <th class="px-3 py-2 text-center">Token Aktif</th>
                            <th class="px-3 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($users as $user)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition">
                                <td class="px-3 py-2 font-mono text-gray-400">#{{ $user->id }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-bold text-gray-900 dark:text-gray-100">{{ $user->name }}</div>
                                    <div class="text-[11px] text-gray-400 font-mono">@ {{ $user->username }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-500/10 text-blue-500 border border-blue-500/20">
                                        {{ $user->role->name ?? 'User' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $user->tokens_count > 0 ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 'bg-gray-500/10 text-gray-400' }}">
                                        {{ $user->tokens_count }} Token
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button 
                                        wire:click="killUser({{ $user->id }})"
                                        wire:confirm="Yakin ingin KILL / Logout paksa user {{ $user->username }} (ID #{{ $user->id }})?"
                                        class="px-2.5 py-1 text-[11px] font-bold text-white bg-red-600 hover:bg-red-700 active:scale-95 rounded shadow-sm transition inline-flex items-center gap-1"
                                    >
                                        <span>🚫 Kill User</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-gray-400 italic">
                                    Tidak ada user yang sesuai dengan pencarian.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
