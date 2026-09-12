<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Import & Preview — Broker Console</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">

    <!-- Top Navbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 tracking-tight">Brokerage Data Import</h1>
                <p class="text-xs text-slate-500">Upload and preview CSV data before synchronizing with Zoho CRM</p>
            </div>
            <a href="{{ route('contracts.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-800 flex items-center gap-1">
                Contracts Console &rarr;
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8 space-y-8">

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Step 1: File Upload Form -->
        <section class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-800 mb-1">Step 1: Upload CSV Files</h2>
            <p class="text-xs text-slate-500 mb-6">Select both contacts and contracts CSV files to generate a validation preview.</p>

            <form action="{{ route('import.preview') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @csrf

                <!-- Kontakte CSV -->
                <div class="border border-dashed border-slate-300 rounded-xl p-5 hover:border-blue-500 transition-colors bg-slate-50/50">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Customers File (`kontakte_export.csv`)</label>
                    <span class="text-xs text-slate-500 block mb-3">Semicolon-separated customer records</span>
                    <input type="file" name="kontakte_csv" accept=".csv" required
                           class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    @error('kontakte_csv') <p class="text-rose-600 text-xs mt-2">{{ $message }}</p> @enderror
                </div>

                <!-- Vertraege CSV -->
                <div class="border border-dashed border-slate-300 rounded-xl p-5 hover:border-blue-500 transition-colors bg-slate-50/50">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Contracts File (`vertraege_export.csv`)</label>
                    <span class="text-xs text-slate-500 block mb-3">Semicolon-separated contracts mapped by Kundennummer</span>
                    <input type="file" name="vertraege_csv" accept=".csv" required
                           class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                    @error('vertraege_csv') <p class="text-rose-600 text-xs mt-2">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg shadow transition-colors flex items-center gap-2">
                        <span>Parse & Generate Preview</span> &rarr;
                    </button>
                </div>
            </form>
        </section>

        <!-- Step 2: Preview & Validation Section (Displayed only when parsed data exists) -->
        @if(isset($previewData))
            <!-- Summary Stats -->
            <section class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Customers</div>
                    <div class="text-2xl font-bold text-slate-800 mt-2">{{ count($previewData['contacts']) }}</div>
                    <span class="text-xs text-emerald-600 font-medium">Valid records</span>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Contracts</div>
                    <div class="text-2xl font-bold text-slate-800 mt-2">{{ count($previewData['contracts']) }}</div>
                    <span class="text-xs text-emerald-600 font-medium">Ready for import</span>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Annual Volume</div>
                    <div class="text-2xl font-bold text-slate-800 mt-2">€ {{ number_format($previewData['totalVolume'], 2) }}</div>
                    <span class="text-xs text-slate-500">Calculated Jahresbeitrag</span>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Issues Flagged</div>
                    <div class="text-2xl font-bold {{ count($previewData['issues']) > 0 ? 'text-amber-600' : 'text-emerald-600' }} mt-2">
                        {{ count($previewData['issues']) }}
                    </div>
                    <span class="text-xs text-slate-500">Skipped or adjusted</span>
                </div>
            </section>

            <!-- Detected Issues Card -->
            @if(count($previewData['issues']) > 0)
                <section class="bg-amber-50 border border-amber-200 rounded-2xl p-6">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <h3 class="text-sm font-bold text-amber-900">Validation Notice & Data Inconsistencies Detected</h3>
                    </div>
                    <p class="text-xs text-amber-800 mb-4">The following anomalies were caught by the parser. Records with fatal issues will be omitted from the Zoho CRM upload to avoid corrupting relational lookups.</p>
                    <ul class="space-y-2">
                        @foreach($previewData['issues'] as $issue)
                            <li class="text-xs bg-white/70 p-3 rounded-lg border border-amber-200 flex items-start justify-between text-amber-950">
                                <div>
                                    <span class="font-semibold px-2 py-0.5 rounded bg-amber-200 text-amber-900 text-[10px] mr-2">{{ $issue['type'] }}</span>
                                    <span>{{ $issue['message'] }}</span>
                                </div>
                                <span class="font-mono text-slate-500">{{ $issue['record_ref'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <!-- Confirmation Action Bar -->
            <div class="bg-slate-900 text-white p-5 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h3 class="font-semibold text-sm">Ready to write data to Zoho CRM?</h3>
                    <p class="text-xs text-slate-400">Records will be upserted using Kundennummer and Vertragsnummer to prevent duplicates.</p>
                </div>
                <form action="{{ route('import.confirm') }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-semibold text-sm px-6 py-2.5 rounded-xl transition-colors shadow">
                        Confirm & Import Records to Zoho CRM
                    </button>
                </form>
            </div>

            <!-- Preview Data Tables (Tabs or Stacked) -->
            <section class="space-y-6">
                <!-- Contracts Preview -->
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                        <h3 class="font-semibold text-sm text-slate-800">Contracts Queue Preview ({{ count($previewData['contracts']) }})</h3>
                        <span class="text-xs text-slate-500">Showing all verified contracts</span>
                    </div>
                    <div class="overflow-x-auto max-h-96">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-slate-100/75 sticky top-0 text-slate-600 uppercase font-semibold">
                                <tr>
                                    <th class="p-3">Vertragsnummer</th>
                                    <th class="p-3">Kundennummer</th>
                                    <th class="p-3">Produkt</th>
                                    <th class="p-3">Versicherer</th>
                                    <th class="p-3">Expiry Date</th>
                                    <th class="p-3 text-right">Jahresbeitrag</th>
                                    <th class="p-3">Makler</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono text-slate-700">
                                @foreach($previewData['contracts'] as $contract)
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="p-3 font-semibold text-blue-600">{{ $contract['Vertragsnummer'] }}</td>
                                        <td class="p-3">{{ $contract['Kundennummer'] }}</td>
                                        <td class="p-3 font-sans">{{ $contract['Produkt'] }}</td>
                                        <td class="p-3 font-sans">{{ $contract['Versicherer'] }}</td>
                                        <td class="p-3">{{ $contract['Ablaufdatum'] }}</td>
                                        <td class="p-3 text-right font-sans">€ {{ number_format((float)$contract['Jahresbeitrag'], 2) }}</td>
                                        <td class="p-3 font-sans">{{ $contract['Makler'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Customers Preview -->
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                        <h3 class="font-semibold text-sm text-slate-800">Customers Queue Preview ({{ count($previewData['contacts']) }})</h3>
                        <span class="text-xs text-slate-500">Showing all verified contacts</span>
                    </div>
                    <div class="overflow-x-auto max-h-72">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-slate-100/75 sticky top-0 text-slate-600 uppercase font-semibold">
                                <tr>
                                    <th class="p-3">Kundennummer</th>
                                    <th class="p-3">Name</th>
                                    <th class="p-3">Email</th>
                                    <th class="p-3">Telefon</th>
                                    <th class="p-3">Ort</th>
                                    <th class="p-3">Makler</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @foreach($previewData['contacts'] as $contact)
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="p-3 font-mono font-semibold text-slate-900">{{ $contact['Kundennummer'] }}</td>
                                        <td class="p-3">{{ $contact['Anrede'] }} {{ $contact['Vorname'] }} {{ $contact['Nachname'] }}</td>
                                        <td class="p-3 text-slate-500">{{ $contact['Email'] }}</td>
                                        <td class="p-3 font-mono">{{ $contact['Telefon'] }}</td>
                                        <td class="p-3">{{ $contact['PLZ'] }} {{ $contact['Ort'] }}</td>
                                        <td class="p-3">{{ $contact['Makler'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

    </main>
</body>
</html>