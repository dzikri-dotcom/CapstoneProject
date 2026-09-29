<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji {{ $staff->user->name }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1e293b; }
        table { width: 100%; border-collapse: collapse; }
        .header { border-bottom: 3px solid #c8252c; padding-bottom: 8px; margin-bottom: 14px; }
        .brand { font-size: 15px; font-weight: bold; color: #c8252c; }
        .muted { color: #64748b; }
        .title { font-size: 13px; font-weight: bold; margin: 0 0 2px; }
        .right { text-align: right; }
        .info td { padding: 2px 0; }
        .info .label { width: 110px; color: #64748b; }
        .items { margin-top: 14px; }
        .items th { background: #f1f5f9; text-align: left; padding: 5px 6px; font-size: 8px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #cbd5e1; }
        .items td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .items .segments { font-size: 8px; color: #64748b; }
        .note { font-size: 8px; color: #b45309; }
        .summary { margin-top: 14px; width: 55%; margin-left: 45%; }
        .summary td { padding: 4px 6px; }
        .summary .total td { font-size: 12px; font-weight: bold; background: #fef2f2; border-top: 2px solid #c8252c; }
        .terbilang { margin-top: 8px; padding: 6px 8px; background: #f8fafc; border-left: 3px solid #c8252c; font-style: italic; }
        .watermark { position: fixed; top: 38%; left: 18%; font-size: 96px; font-weight: bold; color: #fbe4e5; transform: rotate(-30deg); z-index: -1; }
        .sign { margin-top: 34px; width: 42%; margin-left: 58%; text-align: center; }
        .footer { margin-top: 26px; font-size: 7.5px; color: #94a3b8; }
    </style>
</head>
<body>
@if (! $period->isFinal())
    <div class="watermark">DRAFT</div>
@endif

<div class="header">
    <table>
        <tr>
            <td>
                <div class="brand">Unit Logistik</div>
                <div class="muted">Telkom University Surabaya</div>
            </td>
            <td class="right">
                <div class="title">SLIP GAJI STUDENT STAFF</div>
                <div class="muted">Periode {{ $period->start_date->translatedFormat('j F Y') }} – {{ $period->end_date->translatedFormat('j F Y') }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="info">
    <tr><td class="label">Nama</td><td><strong>{{ $staff->user->name }}</strong></td></tr>
    <tr><td class="label">NIM</td><td>{{ $staff->nim }}</td></tr>
    <tr><td class="label">Program studi</td><td>{{ $staff->study_program }}</td></tr>
    <tr><td class="label">Status periode</td><td>{{ $period->status->label() }}</td></tr>
</table>

<table class="items">
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Shift</th>
        <th>Masuk</th>
        <th>Pulang</th>
        <th class="right">Dibayar</th>
        <th>Rincian (menit × tarif/jam)</th>
        <th class="right">Nominal</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($items as $item)
        <tr>
            <td>{{ $item->date->translatedFormat('D, j M') }}</td>
            <td>{{ substr($item->shift_start, 0, 5) }}–{{ substr($item->shift_end, 0, 5) }}</td>
            <td>{{ $item->check_in_at?->format('H:i') ?? '—' }}</td>
            <td>{{ $item->check_out_at?->format('H:i') ?? '—' }}</td>
            <td class="right">{{ $item->paid_minutes }} mnt</td>
            <td class="segments">
                @foreach ($item->breakdown['segments'] ?? [] as $segment)
                    {{ $segment['from'] }}–{{ $segment['to'] }}: {{ $segment['minutes'] }} × Rp{{ number_format($segment['rate'], 0, ',', '.') }}<br>
                @endforeach
                @if (! $item->check_in_at)
                    <span class="note">Tidak hadir</span>
                @elseif (! $item->check_out_at)
                    <span class="note">Belum check-out, tidak dibayar</span>
                @endif
            </td>
            <td class="right">Rp{{ number_format((float) $item->amount, 0, ',', '.') }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">Tidak ada shift pada periode ini.</td></tr>
    @endforelse
    </tbody>
</table>

<table class="summary">
    <tr><td class="muted">Shift hadir</td><td class="right">{{ $detail->total_shifts }} shift</td></tr>
    <tr><td class="muted">Tidak hadir</td><td class="right">{{ $absentCount }} shift</td></tr>
    <tr><td class="muted">Total jam dibayar</td><td class="right">{{ $paidDuration }}</td></tr>
    <tr><td class="muted">Pembulatan</td><td class="right">{{ $roundingLabel }}</td></tr>
    <tr class="total"><td>Total gaji</td><td class="right">Rp{{ number_format((float) $detail->total_amount, 0, ',', '.') }}</td></tr>
</table>
<div class="terbilang">Terbilang: {{ $terbilang }}</div>

<div class="sign">
    Surabaya, {{ $issuedAt->translatedFormat('j F Y') }}<br>
    Koordinator Logistik
    <br><br><br><br><br>
    ( ................................................ )
</div>

<div class="footer">
    Dokumen dibuat otomatis oleh Sistem Presensi Logistik pada {{ $issuedAt->translatedFormat('j F Y H:i') }} WIB.
    Gaji dihitung dari jam hadir aktual di dalam jam shift × tarif slot jam yang berlaku pada tanggal shift.
</div>
</body>
</html>
