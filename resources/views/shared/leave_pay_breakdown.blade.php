@if(! empty($leavePayBreakdowns))
    <div class="alert alert-info border mb-0">
        <div class="fw-semibold mb-2">Payroll pay breakdown</div>
        @foreach($leavePayBreakdowns as $breakdown)
            <div @class(['mt-2' => ! $loop->first])>
                <div class="small text-muted">
                    {{ $breakdown['label'] }} for {{ $breakdown['year'] }}:
                    {{ $breakdown['formatted_requested'] }} calendar {{ (float) $breakdown['requested'] === 1.0 ? 'day' : 'days' }}
                    @if((float) $breakdown['previously_used'] > 0.0)
                        after {{ $breakdown['formatted_previously_used'] }} used or reserved
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach($breakdown['bands'] as $band)
                        <span class="badge text-bg-light border text-dark">
                            {{ $band['label'] }}: {{ $band['formatted_days'] }} {{ (float) $band['days'] === 1.0 ? 'day' : 'days' }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
