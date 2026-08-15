<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-outline-variant/60">
        <div>
            <h2 class="text-2xl font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-[28px] text-primary">history</span>
                Audit Trail & Activity History
            </h2>
            <p class="text-sm text-on-surface-variant mt-1">Read-only audit log tracking financial mutations and system entity changes.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 bg-surface-container-high border border-outline-variant text-on-surface-variant text-xs rounded-full font-medium flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                Read-Only System
            </span>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-surface-container-low border border-outline-variant/70 rounded-lg p-4 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <div>
                <label for="date_from" class="block text-xs font-semibold text-on-surface-variant mb-1 uppercase tracking-wider">From Date</label>
                <input type="date" id="date_from" wire:model.live="date_from"
                       class="w-full h-9 bg-background border border-outline-variant text-on-surface rounded px-3 text-xs focus:border-primary focus:ring-1 focus:ring-primary outline-none">
            </div>

            <div>
                <label for="date_to" class="block text-xs font-semibold text-on-surface-variant mb-1 uppercase tracking-wider">To Date</label>
                <input type="date" id="date_to" wire:model.live="date_to"
                       class="w-full h-9 bg-background border border-outline-variant text-on-surface rounded px-3 text-xs focus:border-primary focus:ring-1 focus:ring-primary outline-none">
            </div>

            <div>
                <label for="user_id" class="block text-xs font-semibold text-on-surface-variant mb-1 uppercase tracking-wider">User</label>
                <select id="user_id" wire:model.live="user_id"
                        class="w-full h-9 bg-background border border-outline-variant text-on-surface rounded px-2 text-xs focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="actionFilter" class="block text-xs font-semibold text-on-surface-variant mb-1 uppercase tracking-wider">Action Type</label>
                <select id="actionFilter" wire:model.live="actionFilter"
                        class="w-full h-9 bg-background border border-outline-variant text-on-surface rounded px-2 text-xs focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option value="">All Actions</option>
                    @foreach($availableActions as $action)
                        <option value="{{ $action }}">{{ Str::title(str_replace('_', ' ', $action)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="auditable_type" class="block text-xs font-semibold text-on-surface-variant mb-1 uppercase tracking-wider">Entity Type</label>
                <select id="auditable_type" wire:model.live="auditable_type"
                        class="w-full h-9 bg-background border border-outline-variant text-on-surface rounded px-2 text-xs focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option value="">All Entities</option>
                    @foreach($availableEntityTypes as $class => $label)
                        <option value="{{ $class }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($date_from || $date_to || $user_id || $actionFilter || $auditable_type)
            <div class="flex justify-end pt-2 border-t border-outline-variant/40">
                <button type="button" wire:click="resetFilters" class="text-xs text-primary hover:underline flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">restart_alt</span>
                    Reset All Filters
                </button>
            </div>
        @endif
    </div>

    <!-- Audit Log Table -->
    <div class="bg-surface-container-low border border-outline-variant/70 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-surface-container text-on-surface-variant font-semibold border-b border-outline-variant/70 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="p-3.5">Timestamp</th>
                        <th class="p-3.5">User</th>
                        <th class="p-3.5">Action</th>
                        <th class="p-3.5">Entity Type</th>
                        <th class="p-3.5">Record ID</th>
                        <th class="p-3.5">Summary</th>
                        <th class="p-3.5 text-right">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40 text-on-surface">
                    @forelse($auditLogs as $log)
                        @php
                            $entityName = class_basename($log->auditable_type);
                            $actionLabel = Str::title(str_replace('_', ' ', $log->action));
                            
                            $badgeClass = match(true) {
                                str_contains($log->action, 'created') => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                str_contains($log->action, 'cancelled') or str_contains($log->action, 'deactivated') => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                                str_contains($log->action, 'payment_confirmed') or str_contains($log->action, 'activated') => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30',
                                default => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                            };
                        @endphp
                        <tr class="hover:bg-surface-container/50 transition">
                            <td class="p-3.5 whitespace-nowrap text-on-surface-variant font-mono">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="p-3.5 font-medium">
                                {{ $log->user ? $log->user->name : 'System' }}
                                @if($log->user)
                                    <span class="text-[10px] text-on-surface-variant block">{{ $log->user->email }}</span>
                                @endif
                            </td>
                            <td class="p-3.5 whitespace-nowrap">
                                <span class="px-2 py-0.5 border text-[11px] font-semibold rounded-full {{ $badgeClass }}">
                                    {{ $actionLabel }}
                                </span>
                            </td>
                            <td class="p-3.5 font-medium">
                                {{ $entityName }}
                            </td>
                            <td class="p-3.5 font-mono text-on-surface-variant">
                                #{{ $log->auditable_id }}
                            </td>
                            <td class="p-3.5 max-w-xs truncate text-on-surface-variant">
                                @if(isset($log->details['before']) && isset($log->details['after']))
                                    Updated {{ count($log->details['after']) }} field(s)
                                @elseif(isset($log->details['new']))
                                    New {{ $entityName }} recorded
                                @elseif(isset($log->details['record_status']))
                                    Status changed to {{ $log->details['record_status'] }}
                                @else
                                    Mutation executed
                                @endif
                            </td>
                            <td class="p-3.5 text-right whitespace-nowrap">
                                <button type="button" wire:click="viewDetails({{ $log->id }})"
                                        class="px-2.5 py-1 bg-surface-container-high border border-outline-variant text-primary hover:text-on-primary hover:bg-primary rounded text-xs transition flex items-center gap-1 inline-flex">
                                    <span class="material-symbols-outlined text-[14px]">visibility</span>
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-[36px] block mb-2 opacity-50">search_off</span>
                                No audit log entries found matching the filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($auditLogs->hasPages())
            <div class="p-4 border-t border-outline-variant/60 bg-surface-container">
                {{ $auditLogs->links() }}
            </div>
        @endif
    </div>

    <!-- Detail View Modal -->
    @if($isDetailModalOpen && $selectedLog)
        <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
            <div class="bg-surface-container-low border border-outline-variant rounded-lg p-6 max-w-2xl w-full shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-outline-variant/60">
                    <div>
                        <h3 class="text-base font-bold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-[20px] text-primary">analytics</span>
                            Audit Entry #{{ $selectedLog->id }}
                        </h3>
                        <p class="text-xs text-on-surface-variant mt-0.5">Recorded {{ $selectedLog->created_at->format('Y-m-d H:i:s T') }}</p>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Meta Summary Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-surface-container p-3 rounded border border-outline-variant/40 text-xs">
                    <div>
                        <span class="text-on-surface-variant block text-[10px] uppercase font-semibold">User</span>
                        <span class="font-medium text-on-surface">{{ $selectedLog->user ? $selectedLog->user->name : 'System' }}</span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant block text-[10px] uppercase font-semibold">Action</span>
                        <span class="font-semibold text-primary">{{ Str::title(str_replace('_', ' ', $selectedLog->action)) }}</span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant block text-[10px] uppercase font-semibold">Target Model</span>
                        <span class="font-medium text-on-surface">{{ class_basename($selectedLog->auditable_type) }}</span>
                    </div>
                    <div>
                        <span class="text-on-surface-variant block text-[10px] uppercase font-semibold">Target ID</span>
                        <span class="font-mono text-on-surface">#{{ $selectedLog->auditable_id }}</span>
                    </div>
                </div>

                <!-- State Diff or Details Payload -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-on-surface uppercase tracking-wider">Payload Details</h4>

                    @if(isset($selectedLog->details['before']) && isset($selectedLog->details['after']))
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="bg-surface-container p-3 rounded border border-rose-500/30">
                                <span class="text-rose-400 font-semibold block mb-2 text-[11px]">Before State</span>
                                <pre class="font-mono text-[11px] text-on-surface-variant overflow-x-auto whitespace-pre-wrap">{{ json_encode($selectedLog->details['before'], JSON_PRETTY_PRINT) }}</pre>
                            </div>
                            <div class="bg-surface-container p-3 rounded border border-emerald-500/30">
                                <span class="text-emerald-400 font-semibold block mb-2 text-[11px]">After State</span>
                                <pre class="font-mono text-[11px] text-on-surface-variant overflow-x-auto whitespace-pre-wrap">{{ json_encode($selectedLog->details['after'], JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </div>
                    @else
                        <div class="bg-surface-container p-3 rounded border border-outline-variant/60 text-xs">
                            <pre class="font-mono text-[11px] text-on-surface-variant overflow-x-auto whitespace-pre-wrap">{{ json_encode($selectedLog->details ?? [], JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-3 border-t border-outline-variant/60">
                    <button type="button" wire:click="closeDetailModal" class="px-4 py-2 border border-outline-variant rounded text-xs font-medium text-on-surface-variant hover:bg-surface-container transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
