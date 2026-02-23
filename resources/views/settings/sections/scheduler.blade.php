<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">Scheduler</h5>
        <p class="text-muted small mb-0">Cron jobs and scheduled tasks.</p>
    </div>
</div>

<div class="app-card overflow-hidden">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4" width="80">Actions</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4" width="80">Sequence</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4">Cron Job</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4" width="120">Frequency (H:M)</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4" width="100">Status</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4" width="140">Last scan started</th>
                        <th class="text-uppercase small fw-bold text-muted py-3 px-4" width="140">Last scan ended</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cronTasks ?? [] as $task)
                    <tr>
                        <td class="px-4">
                            <div class="d-flex align-items-center gap-1">
                                <span class="text-muted" style="cursor: grab;" title="Reorder"><i class="bi bi-grip-vertical"></i></span>
                                <a href="#" class="text-muted" title="Edit"><i class="bi bi-pencil"></i></a>
                            </div>
                        </td>
                        <td class="px-4">{{ $task['sequence'] }}</td>
                        <td class="px-4 fw-semibold">{{ $task['name'] }}</td>
                        <td class="px-4">{{ $task['frequency'] }}</td>
                        <td class="px-4">
                            @if ($task['status_active'])
                            <span class="badge bg-success">Active</span>
                            @else
                            <span class="badge bg-secondary">In Active</span>
                            @endif
                        </td>
                        <td class="px-4 text-muted small">{{ $task['last_start'] }}</td>
                        <td class="px-4 text-muted small">{{ $task['last_end'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-5 text-center text-muted">
                            <i class="bi bi-clock-history d-block mb-2" style="font-size: 2rem; opacity: .5;"></i>
                            No scheduled tasks found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
