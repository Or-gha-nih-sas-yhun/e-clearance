@props([
    'items' => [],
    'total' => 0,
    'label' => 'accounts',
    'tone' => 'blue',
])

@php
    $rows = collect($items)
        ->map(fn ($item) => [
            'label' => (string) ($item['label'] ?? 'Unassigned'),
            'value' => (int) ($item['value'] ?? 0),
        ])
        ->filter(fn ($item) => $item['value'] > 0)
        ->values();
    $accountTotal = (int) $total;
    $largest = max(1, (int) $rows->max('value'));
@endphp

<div class="summary-account-chart tone-{{ $tone }}">
    <div class="summary-account-total">
        <span>Total {{ $label }}</span>
        <strong>{{ number_format($accountTotal) }}</strong>
    </div>

    <div class="summary-account-bars">
        @forelse($rows as $row)
            @php
                $barWidth = max(5, (int) round(($row['value'] / $largest) * 100));
                $share = $accountTotal > 0 ? (int) round(($row['value'] / $accountTotal) * 100) : 0;
            @endphp
            <div class="summary-account-row">
                <div class="summary-account-label">
                    <span>{{ $row['label'] }}</span>
                    <strong>{{ number_format($row['value']) }} <small>({{ $share }}%)</small></strong>
                </div>
                <div class="summary-account-track" role="img" aria-label="{{ $row['label'] }}: {{ $row['value'] }}">
                    <span style="width:{{ $barWidth }}%"></span>
                </div>
            </div>
        @empty
            <div class="summary-account-empty"><i class="bi bi-bar-chart" aria-hidden="true"></i>No account data yet.</div>
        @endforelse
    </div>
</div>
