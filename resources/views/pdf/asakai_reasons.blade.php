<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            margin: 10px;
            size: landscape;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 7px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid black;
            padding: 2px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow: hidden;
        }
        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            border: none;
        }
        .no-border {
            border: none !important;
        }
        .center {
            text-align: center;
        }
        .bold {
            font-weight: bold;
        }
        .blue-bg {
            background-color: #dce6f1;
        }
        .inner-table {
            width: 100%;
            border: none;
            margin: 0;
            table-layout: fixed;
        }
        .inner-table td {
            border: 1px solid black;
            padding: 0;
            text-align: center;
        }
        .info-label {
            display: inline-block;
            width: 35px;
        }
        .monitoring-cell {
            padding: 0 !important;
            vertical-align: top;
        }
        .vertical-text-wrapper {
            width: 100%;
            display: block;
            -webkit-transform: rotate(-90deg);
            transform: rotate(-90deg);
            white-space: nowrap;
            margin: 0 auto;
            transform-origin: center center;
            font-size: 6px;
        }
        .vertical-cell {
            height: 60px;
            vertical-align: middle;
            text-align: center;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @php
        // Calculate total pages needed (5 rows per page)
        $rowsPerPage = 5;
        $allReasons = $reasons->toArray();
        $totalReasons = count($allReasons);
        $totalPages = max(1, ceil($totalReasons / $rowsPerPage));
    @endphp

    @for($currentPage = 1; $currentPage <= $totalPages; $currentPage++)
    <div class="{{ $currentPage < $totalPages ? 'page-break' : '' }}">
        <!-- Top Header Section -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 0px; border-bottom: none;">
            <tr>
                <td class="bold" style="width: 5%; border: 1px solid black; padding: 2px; text-align: left; border-bottom:none;">DEPT</td>
                <td style="width: 10%; border: 1px solid black; padding: 2px; border-bottom:none;">{{ $dept ?? '' }}</td>
                <td rowspan="3" style="vertical-align: middle; text-align: center; border: 1px solid black; border-bottom:none;">
                    <h1 style="margin: 0; font-size: 20px; text-decoration: underline;">KONTROL MONITORING ABNORMALITY / BIRA</h1>
                </td>
            </tr>
            <tr>
                <td class="bold" style="border: 1px solid black; padding: 2px; text-align: left; border-bottom:none;">BULAN</td>
                <td style="border: 1px solid black; padding: 2px; border-bottom:none;">{{ $month ?? '' }}</td>
            </tr>
            <tr>
                <td class="bold" style="border: 1px solid black; padding: 2px; text-align: left; border-bottom:none;">TAHUN</td>
                <td style="border: 1px solid black; padding: 2px; border-bottom:none;">{{ $year ?? '' }}</td>
            </tr>
        </table>

        <!-- Main Data Table -->
        <table>
            <thead>
                <tr class="blue-bg">
                    <th rowspan="3" style="width: 2%" class="center">NO</th>
                    <th rowspan="3" style="width: 5%" class="center">TANGGAL</th>
                    <th rowspan="3" style="width: 14%" class="center">ABNORMALITY / PROBLEM</th>
                    <th rowspan="3" style="width: 11%" class="center">ANALISA PENYEBAB</th>
                    <th rowspan="3" style="width: 11%" class="center">PERBAIKAN</th>
                    <th rowspan="3" style="width: 4%" class="center">Nama<br>Proses</th>
                    <th colspan="13" class="center">MONITORING PROBLEM C/M & AUDIT SCHEDULE</th>
                    <th rowspan="3" style="width: 3.5%" class="center">RESULT</th>
                    <th rowspan="3" style="width: 4%" class="center">CONCERN FEEDBACK</th>
                    <th rowspan="3" style="width: 4%" class="center">MANAGER</th>
                </tr>
                <tr class="blue-bg">
                    <!-- MONTH cell - disejajarkan dengan A.SCH -->
                    <th rowspan="2" style="width: 5.6%;"> </th> 
                    
                    <th colspan="4" class="center">BULAN KE-1</th>
                    <th colspan="4" class="center">BULAN KE-2</th>
                    <th colspan="4" class="center">BULAN KE-3</th>
                </tr>
                <tr class="blue-bg">
                    <!-- Month 1 -->
                    <th style="width: 7.86%;">I</th> <th style="width: 7.86%;">II</th> <th style="width: 7.86%;">III</th> <th style="width: 7.86%;">IV</th>
                    <!-- Month 2 -->
                    <th style="width: 7.86%;">I</th> <th style="width: 7.86%;">II</th> <th style="width: 7.86%;">III</th> <th style="width: 7.86%;">IV</th>
                    <!-- Month 3 -->
                    <th style="width: 7.86%;">I</th> <th style="width: 7.86%;">II</th> <th style="width: 7.86%;">III</th> <th style="width: 7.86%;">IV</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // Always show 5 rows per page
                    $startIndex = ($currentPage - 1) * $rowsPerPage;
                    $endIndex = min($startIndex + $rowsPerPage, count($allReasons));
                    $pageReasons = array_slice($allReasons, $startIndex, $rowsPerPage);
                @endphp

                @for($i = 0; $i < $rowsPerPage; $i++)
                    @php
                        $reason = $pageReasons[$i] ?? null;
                        $rowNumber = $startIndex + $i + 1;
                    @endphp
                <tr>
                    <td class="center">{{ $rowNumber }}</td>
                    <td class="center">{{ $reason ? \Carbon\Carbon::parse($reason['date'])->format('d-M-y') : '' }}</td>
                    <td style="padding: 1px; vertical-align: top;">
                        <table class="no-border" style="width: 100%; font-size: 7px; table-layout: auto;">
                            <tr>
                                <td class="no-border" style="width: 35px;">Part No</td>
                                <td class="no-border" style="width: 5px;">:</td>
                                <td class="no-border">{{ $reason['part_no'] ?? '' }}</td>
                            </tr>
                            <tr>
                                <td class="no-border">Part Name</td>
                                <td class="no-border">:</td>
                                <td class="no-border">{{ $reason['part_name'] ?? '' }}</td>
                            </tr>
                            <tr>
                                <td class="no-border">Problem</td>
                                <td class="no-border">:</td>
                                <td class="no-border">{{ $reason['problem'] ?? '' }}</td>
                            </tr>
                            <tr>
                                <td class="no-border">Qty</td>
                                <td class="no-border">:</td>
                                <td class="no-border">{{ $reason['qty'] ?? '' }}</td>
                            </tr>
                            <tr>
                                <td class="no-border">Dept</td>
                                <td class="no-border">:</td>
                                <td class="no-border">{{ $reason['section'] ?? '' }}</td>
                            </tr>
                            <tr>
                                <td class="no-border">Line</td>
                                <td class="no-border">:</td>
                                <td class="no-border">{{ $reason['line'] ?? '' }}</td>
                            </tr>
                        </table>
                    </td>
                    <td style='vertical-align: top;'>{{ $reason['penyebab'] ?? '' }}</td>
                    <td style='vertical-align: top;'>{{ $reason['perbaikan'] ?? '' }}</td>
                    <td class="center"></td> 
                    
                    <!-- MONITORING CELL (colspan 13) -->
                    <td colspan="13" class="monitoring-cell">
                        <!-- A. SCH Row -->
                        <table class="inner-table" cellspacing="0" cellpadding="0">
                            <tr style="height: 15px;">
                                <td class="vertical-cell" style="width: 7.2%; border-right: 1px solid black; border-top: none; border-left: none;">
                                    <div class="vertical-text-wrapper">A. SCH</div>
                                </td>
                                @for($m = 1; $m <= 3; $m++)
                                    <td style="width: 7.2%; border-right: 1px dashed black; border-top:none;"></td>
                                    <td style="width: 7.2%; border-right: 1px dashed black; border-top:none;"></td>
                                    <td style="width: 7.2%; border-right: 1px dashed black; border-top:none;"></td>
                                    <td style="width: 7.2%; border-right: none; border-top:none;"></td>
                                @endfor
                            </tr>
                        </table>
                        
                        <!-- MONITORING PROBLEM GRID -->
                        <table class="inner-table" cellspacing="0" cellpadding="0" style="border: none;">
                            <!-- Header Rows -->
                            <tr>
                                <!-- Vertical Header -->
                                <td rowspan="6" class="vertical-cell" style="width: 7.7%; border-right: 1px solid black; border-bottom: none; border-left: none; border-top: none;">
                                    <div class="vertical-text-wrapper">MONITORING<br>PROBLEM</div>
                                </td>
                                 
                                <!-- Hari ke Header (Spans 31 cols) -->
                                <td style="width: 15%; border-right: 1px solid black; border-bottom: 1px solid black; border-top: none;"></td> 
                                <td colspan="31" style="background-color: #f0f0f0; padding: 1px; border-bottom: 1px solid black; border-right: none; text-align: center; border-top: none;">Hari ke</td>
                            </tr>
                            <tr>
                                <td style="border-right: 1px solid black; border-bottom: 1px solid black; border-top: none;"></td>
                                @for($j = 1; $j <= 31; $j++)
                                    <td style="width: 2.7%; font-size: 5px; text-align: center; border-bottom: 1px solid black; border-right: none; padding: 0;">{{ $j }}</td>
                                @endfor
                            </tr>

                            @foreach(['Akt Bulan Ke-1', 'Akt Bulan Ke-2', 'Akt Bulan Ke-3'] as $label)
                            <tr>
                                <td style="font-size: 6px; text-align: left; padding-left: 2px; height: 12px; border-bottom: 1px solid black; border-right: 1px solid black; white-space: nowrap;width: 10%; width:23px">{{ $label }}</td>
                                @for($j = 1; $j <= 31; $j++)
                                    <td style="border-bottom: 1px solid black; border-right: none;"></td>
                                @endfor
                            </tr>
                            @endforeach

                             <tr>
                                <td style="font-size: 6px; text-align: left; padding-left: 2px; border-right: 1px solid black; border-bottom: none;">Target</td>
                                @for($j = 1; $j <= 31; $j++)
                                    <td style="font-size: 6px; text-align: center; border-right: none; border-bottom: none; padding: 0;">0</td>
                                @endfor
                            </tr>
                        </table>
                    </td>

                    <td></td> <!-- RESULT -->
                    <td></td> <!-- CONCERN FEEDBACK -->
                    <td></td> <!-- MANAGER -->
                </tr>
                @endfor
            </tbody>
        </table>
        
        <!-- Printed at timestamp -->
        <div style="text-align: right; margin-top: 10px; font-size: 8px; color: #383838ff;">
            Printed at: {{ date('d-M-Y H:i:s') }}
        </div>
    </div>
    @endfor
</body>
</html>