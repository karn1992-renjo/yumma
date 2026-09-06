@php
    $__rows = is_array($data ?? null) ? $data : [];
@endphp
@if(empty($__rows))
    <div class="text-muted small">No data.</div>
@else
    <table class="table table-sm mb-0">
        <tbody>
        @foreach($__rows as $key => $value)
            <tr>
                <td class="text-muted small text-nowrap" style="width: 220px;">{{ is_string($key) ? \Illuminate\Support\Str::headline($key) : $key }}</td>
                <td class="small">
                    @if(is_array($value))
                        @php $isScalarList = array_is_list($value) && collect($value)->every(fn ($v) => ! is_array($v)); @endphp
                        @if($value === [])
                            <span class="text-muted">&mdash;</span>
                        @elseif($isScalarList)
                            {{ collect($value)->map(fn ($v) => is_bool($v) ? ($v ? 'Yes' : 'No') : ($v ?? '—'))->implode(', ') }}
                        @else
                            <div class="border rounded p-2 bg-white">
                                @include('admin.ai._partials.value-table', ['data' => $value])
                            </div>
                        @endif
                    @elseif(is_bool($value))
                        <span class="badge {{ $value ? 'bg-success' : 'bg-secondary' }}">{{ $value ? 'Yes' : 'No' }}</span>
                    @elseif(is_null($value) || $value === '')
                        <span class="text-muted">&mdash;</span>
                    @else
                        {{ $value }}
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
