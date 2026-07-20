@if($timesheet->correctionRequests->isNotEmpty())
<div class="content-card mt-3 border border-warning-subtle">
    <div class="content-card-header d-flex justify-content-between align-items-start gap-3">
        <div><h2 class="h5 mb-1">Correction requests need review</h2><div class="small text-muted">Resolve every open request together. Accepting any request returns the timesheet to the employee.</div></div>
        <span class="badge text-bg-warning">{{ $timesheet->correctionRequests->count() }} open</span>
    </div>
    <form method="post" action="{{ route('timesheet-corrections.resolve', $timesheet) }}" data-confirm="Resolve all correction requests with these decisions?">
        @csrf
        <div class="content-card-body">
            @error('decisions')<div class="alert alert-danger">{{ $message }}</div>@enderror
            @foreach($timesheet->correctionRequests as $correction)
            <fieldset class="p-3 border rounded-3 mb-3">
                <legend class="float-none w-auto px-2 fs-6 fw-semibold">Request #{{ $correction->id }} · {{ $correction->requester->name }}</legend>
                <p class="mb-3">{{ $correction->comment }}</p>
                <div class="table-responsive mb-3"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Project</th><th class="text-end">Regular</th><th class="text-end">OT</th><th>Description</th></tr></thead><tbody>@foreach($correction->entries as $item)<tr><td>{{ $item->work_date->format('d M Y') }}</td><td>{{ $item->project_code }}</td><td class="text-end">{{ number_format((float)$item->regular_hours, 2) }}</td><td class="text-end">{{ number_format((float)$item->overtime_hours, 2) }}</td><td>{{ $item->description ?: '—' }}</td></tr>@endforeach</tbody></table></div>
                <div class="d-flex flex-wrap gap-3 mb-2"><div class="form-check"><input class="form-check-input" id="accept-{{ $correction->id }}" type="radio" name="decisions[{{ $correction->id }}]" value="accepted" required><label class="form-check-label" for="accept-{{ $correction->id }}">Accept correction</label></div><div class="form-check"><input class="form-check-input" id="dismiss-{{ $correction->id }}" type="radio" name="decisions[{{ $correction->id }}]" value="dismissed" required><label class="form-check-label" for="dismiss-{{ $correction->id }}">Dismiss request</label></div></div>
                <label class="form-label small" for="dismissal-{{ $correction->id }}">Dismissal reason (required when dismissed)</label><textarea class="form-control @error('dismissal_comments.'.$correction->id) is-invalid @enderror" id="dismissal-{{ $correction->id }}" name="dismissal_comments[{{ $correction->id }}]" rows="2" maxlength="2000">{{ old('dismissal_comments.'.$correction->id) }}</textarea>@error('dismissal_comments.'.$correction->id)<div class="invalid-feedback">{{ $message }}</div>@enderror
            </fieldset>
            @endforeach
            <button class="btn btn-warning" type="submit">Resolve all requests</button>
        </div>
    </form>
</div>
@endif
