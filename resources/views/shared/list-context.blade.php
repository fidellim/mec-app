@foreach($listContext['list'] ?? [] as $filter => $value)
    @if(is_array($value))
        @foreach($value as $item)
            <input type="hidden" name="list[{{ $filter }}][]" value="{{ $item }}">
        @endforeach
    @else
        <input type="hidden" name="list[{{ $filter }}]" value="{{ $value }}">
    @endif
@endforeach
