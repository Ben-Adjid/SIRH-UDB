<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin de Paie</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
            color: #1e293b;
            line-height: 1.6;
            font-size: 11px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #059669;
            padding-bottom: 20px;
        }

        .logo-box {
            width: 80px;
            height: 80px;
            background: white;
            border: 3px solid #059669;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            overflow: hidden;
        }

        .logo-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .university-info {
            text-align: center;
        }

        .university-info h1 {
            font-size: 16px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .university-info p {
            font-size: 9px;
            color: #64748b;
            margin-bottom: 2px;
        }

        .university-motto {
            font-style: italic;
            color: #059669;
            font-weight: 600;
            font-size: 10px;
        }

        .header {
            text-align: center;
            margin: 30px 0;
        }

        .header h2 {
            font-size: 18px;
            color: #059669;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .period {
            font-size: 10px;
            color: #94a3b8;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin: 25px 0;
        }

        .info-row {
            display: table-row;
        }

        .info-cell {
            display: table-cell;
            width: 50%;
            padding: 12px;
            border-left: 3px solid #059669;
            vertical-align: top;
        }

        .info-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }

        .details-table thead {
            background: #f1f5f9;
        }

        .details-table th {
            padding: 10px;
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .details-table td {
            padding: 8px 10px;
            font-size: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .amount {
            text-align: right;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }

        .earning {
            color: #059669;
        }

        .deduction {
            color: #dc2626;
        }

        .summary-section {
            background: #f8fafc;
            border: 2px solid #059669;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }

        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            font-size: 11px;
        }

        .summary-label {
            display: table-cell;
            width: 70%;
            font-weight: 600;
            color: #1e293b;
        }

        .summary-amount {
            display: table-cell;
            text-align: right;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }

        .summary-row.total {
            border-top: 2px solid #e2e8f0;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 12px;
            color: #059669;
        }

        .summary-row.net {
            background: #059669;
            color: white;
            padding: 12px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 12px;
            font-weight: 800;
        }

        .summary-row.net .summary-label,
        .summary-row.net .summary-amount {
            color: white;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #94a3b8;
        }

        .certification {
            margin-top: 20px;
            text-align: right;
            font-size: 8px;
            color: #94a3b8;
            font-style: italic;
        }

        @page {
            margin: 20mm;
            size: A4;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
@php
    $monthNames = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $periodLabel = ($monthNames[(int) $month] ?? '') . ' ' . $year;
@endphp
    <div class="container">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-box">
                @if($logo)
                    <img src="{{ $logo }}" alt="Logo UDB" class="logo-img">
                @else
                    <div style="background: #059669; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 900; font-size: 28px;">UDB</div>
                @endif
            </div>
            <div class="university-info">
                <h1>UNIVERSITÉ DAKAR BOURGUIBA</h1>
                <p>Dakar, Sénégal</p>
                <div class="university-motto">De la ténèbre vers la lumière</div>
            </div>
        </div>

        <!-- Header -->
        <div class="header">
            <h2>BULLETIN DE PAIE</h2>
            <div class="period">{{ $periodLabel }}</div>
        </div>

        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-row">
                <div class="info-cell">
                    <div class="info-label">Enseignant</div>
                    <div class="info-value">{{ $teacher_name }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Matricule</div>
                    <div class="info-value">{{ $employee_id ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-cell">
                    <div class="info-label">Période</div>
                    <div class="info-value">{{ $periodLabel }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-label">Généré le</div>
                    <div class="info-value">{{ $generated_at }}</div>
                </div>
            </div>
        </div>

        <!-- Details Table -->
        @if($details && count($details) > 0)
        <table class="details-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: right;">Montant (XOF)</th>
                    <th style="text-align: center;">Type</th>
                </tr>
            </thead>
            <tbody>
                @foreach($details as $detail)
                <tr>
                    <td>{{ $detail->description }}</td>
                    <td class="amount {{ $detail->type === 'earning' ? 'earning' : 'deduction' }}">
                        {{ $detail->type === 'earning' ? '+' : '-' }}{{ number_format($detail->amount, 0, ',', ' ') }}
                    </td>
                    <td style="text-align: center; font-size: 9px; font-weight: bold; color: {{ $detail->type === 'earning' ? '#059669' : '#dc2626' }};">
                        {{ $detail->type === 'earning' ? 'Gain' : 'Retenue' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Summary Section -->
        <div class="summary-section">
            <div class="summary-row">
                <span class="summary-label">Salaire Brut</span>
                <span class="summary-amount">{{ number_format($gross_salary, 0, ',', ' ') }} XOF</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Retenues & Déductions</span>
                <span class="summary-amount" style="color: #dc2626;">-{{ number_format($deductions, 0, ',', ' ') }} XOF</span>
            </div>
            <div class="summary-row total">
                <span class="summary-label">Sous-Total</span>
                <span class="summary-amount">{{ number_format($gross_salary - $deductions, 0, ',', ' ') }} XOF</span>
            </div>
            <div class="summary-row net">
                <span class="summary-label">NET À PAYER</span>
                <span class="summary-amount">{{ number_format($net_salary, 0, ',', ' ') }} XOF</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            {{-- The PDF's default font has no check-mark glyph; DejaVu Sans ships with DomPDF and does. --}}
            <p><span style="font-family: 'DejaVu Sans', sans-serif;">&#10003;</span> Certifié conforme par l'administration</p>
            <p>Bulletin établi en conformité avec la réglementation en vigueur</p>
            <div class="certification">
                Université Dakar Bourguiba © {{ date('Y') }}
            </div>
        </div>
    </div>
</body>
</html>