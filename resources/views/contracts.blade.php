<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Contract Console — Brandovise</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Expiring Contracts Console</h1>
                <p class="text-xs text-slate-500">Monitor policy expiry windows and dispatch renewal tasks to Zoho CRM</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('importcsv') }}" class="text-xs font-semibold px-3 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors">
                    &larr; CSV Import
                </a>
                <button onclick="document.getElementById('newContractModal').classList.remove('hidden')" 
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2 shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add New Contract
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-8 space-y-6">

        <!-- Notification Toast Bar -->
        <div id="toast" class="hidden p-4 rounded-xl text-sm font-medium transition-all shadow"></div>

        <!-- Filter Controls -->
        <section class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center gap-4">
            <!-- Makler Filter -->
            <div>
                <label for="maklerFilter" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Responsible Makler</label>
                <select id="maklerFilter" onchange="applyFilters()" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-blue-500">
                    <option value="ALL">All Agents</option>
                    @foreach($maklers as $makler)
                        <option value="{{ $makler }}">{{ $makler }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Expiry Window Filter -->
            <div>
                <label for="windowFilter" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Expiry Window</label>
                <select id="windowFilter" onchange="applyFilters()" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-blue-500">
                    <option value="ALL">All Active Contracts</option>
                    <option value="30">Ending within 30 Days (High Urgency)</option>
                    <option value="90">Ending within 90 Days</option>
                    <option value="180">Ending within 180 Days</option>
                </select>
            </div>

            <!-- Follow-up Status Filter -->
            <div>
                <label for="followupFilter" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Follow-up State</label>
                <select id="followupFilter" onchange="applyFilters()" class="text-sm border border-slate-300 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-blue-500">
                    <option value="ALL">All</option>
                    <option value="PENDING">Action Needed</option>
                    <option value="DONE">Follow-up Created</option>
                </select>
            </div>
        </section>

        <!-- Contracts Table -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead class="bg-slate-100 border-b border-slate-200 text-slate-600 uppercase font-semibold">
                        <tr>
                            <th class="p-3.5">Urgency</th>
                            <th class="p-3.5">Contract & Product</th>
                            <th class="p-3.5">Customer Details</th>
                            <th class="p-3.5">Ablaufdatum (Expiry)</th>
                            <th class="p-3.5 text-right">Jahresbeitrag</th>
                            <th class="p-3.5">Makler</th>
                            <th class="p-3.5 text-center">Renewal Follow-up</th>
                        </tr>
                    </thead>
                    <tbody id="contractsTableBody" class="divide-y divide-slate-100">
                        @forelse($contracts as $c)
                            <tr class="hover:bg-slate-50/80 transition-colors contract-row"
                                data-makler="{{ $c['Makler'] ?? '' }}"
                                data-days="{{ $c['days_remaining'] }}"
                                data-followup="{{ $c['Follow_up_Created'] ? 'DONE' : 'PENDING' }}"
                                id="row-{{ $c['id'] }}">
                                
                                <!-- Urgency Indicator -->
                                <td class="p-3.5">
                                    @if($c['days_remaining'] <= 30)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-800 animate-pulse">
                                            &bull; {{ $c['days_remaining'] }}d left
                                        </span>
                                    @elseif($c['days_remaining'] <= 90)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800">
                                            {{ $c['days_remaining'] }}d left
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">
                                            {{ $c['days_remaining'] }}d left
                                        </span>
                                    @endif
                                </td>

                                <!-- Contract & Product -->
                                <td class="p-3.5">
                                    <div class="font-mono font-bold text-blue-600">{{ $c['Vertragsnummer'] }}</div>
                                    <div class="text-slate-800 font-medium">{{ $c['Produkt'] }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $c['Versicherer'] }}</div>
                                </td>

                                <!-- Customer Info -->
                                <td class="p-3.5">
                                    <div class="font-semibold text-slate-900">{{ $c['Customer_Name'] }}</div>
                                    <div class="text-slate-500">{{ $c['Customer_Email'] }}</div>
                                    <div class="text-slate-500 font-mono">{{ $c['Customer_Phone'] }}</div>
                                </td>

                                <!-- Expiry Date -->
                                <td class="p-3.5 font-mono text-slate-700">
                                    {{ $c['Ablaufdatum'] }}
                                </td>

                                <!-- Premium -->
                                <td class="p-3.5 text-right font-semibold text-slate-800">
                                    € {{ number_format((float)($c['Jahresbeitrag'] ?? 0), 2) }}
                                </td>

                                <!-- Makler -->
                                <td class="p-3.5 text-slate-700">
                                    {{ $c['Makler'] }}
                                </td>

                                <!-- Follow-up Action Button -->
                                <td class="p-3.5 text-center action-cell">
                                    @if($c['Follow_up_Created'])
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Follow-up Set
                                        </span>
                                    @else
                                        <button onclick="createFollowup('{{ $c['id'] }}', '{{ $c['Vertragsnummer'] }}', '{{ $c['Customer_id'] }}', '{{ addslashes($c['Customer_Name']) }}', '{{ $c['Ablaufdatum'] }}', '{{ addslashes($c['Makler']) }}')"
                                                id="btn-{{ $c['id'] }}"
                                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1.5 rounded-lg text-xs transition-colors shadow">
                                            Set Renewal Follow-up
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-400">No contract records found in Zoho CRM.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- Add Contract Modal -->
<div id="newContractModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-800 text-lg">Add New Insurance Contract</h3>
            <button onclick="document.getElementById('newContractModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>
        
        <form id="newContractForm" onsubmit="saveContract(event)" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Select Customer</label>
                <select name="customer_id" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="">-- Choose Customer --</option>
                    @foreach($contacts as $customer)
                        <option value="{{ $customer['id'] }}">{{ $customer['Full_Name'] ?? 'Customer' }} ({{ $customer['Kundennummer'] ?? '' }})</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Vertragsnummer</label>
                    <input type="text" name="vertragsnummer" placeholder="e.g. VN-2026-001" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Makler (Agent)</label>
                    <input type="text" name="makler" placeholder="e.g. Max Mustermann" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Produkt</label>
                    <input type="text" name="produkt" placeholder="e.g. Privathaftpflicht" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Versicherer</label>
                    <input type="text" name="versicherer" placeholder="e.g. Allianz" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Beginn</label>
                    <input type="date" name="beginn" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Ablaufdatum</label>
                    <input type="date" name="ablaufdatum" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Jahresbeitrag (€)</label>
                    <input type="number" step="0.01" name="jahresbeitrag" placeholder="e.g. 189.50" required class="w-full border border-gray-300 rounded-lg p-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Zahlweise</label>
                    <select name="zahlweise" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm">
                        <option value="monatlich">monatlich</option>
                        <option value="vierteljährlich">vierteljährlich</option>
                        <option value="halbjährlich">halbjährlich</option>
                        <option value="jährlich" selected>jährlich</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 border-t flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('newContractModal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="submit" id="saveContractBtn" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Save to Zoho</button>
            </div>
        </form>
    </div>
</div>
<!-- Floating Toast Container -->
<div id="toast-notification-box" class="fixed top-5 right-5 z-50 flex flex-col gap-3 max-w-md w-full px-4 transition-all duration-300">
    @if(session('success'))
        <div id="toast-success" class="flex items-start gap-3 p-4 rounded-xl shadow-lg border border-emerald-200 bg-white text-emerald-950 transition-all duration-300 transform translate-y-0 opacity-100">
            <div class="p-1 rounded-lg bg-emerald-100 text-emerald-600 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div class="flex-1 text-sm font-medium leading-relaxed pt-0.5">
                {{ session('success') }}
            </div>
            <button onclick="dismissToast('toast-success')" class="text-gray-400 hover:text-gray-600 transition shrink-0 ml-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    @if(session('info'))
        <div id="toast-info" class="flex items-start gap-3 p-4 rounded-xl shadow-lg border border-blue-200 bg-white text-blue-950 transition-all duration-300 transform translate-y-0 opacity-100">
            <div class="p-1 rounded-lg bg-blue-100 text-blue-600 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex-1 text-sm font-medium leading-relaxed pt-0.5">
                {{ session('info') }}
            </div>
            <button onclick="dismissToast('toast-info')" class="text-gray-400 hover:text-gray-600 transition shrink-0 ml-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div id="toast-error" class="flex items-start gap-3 p-4 rounded-xl shadow-lg border border-rose-200 bg-white text-rose-950 transition-all duration-300 transform translate-y-0 opacity-100">
            <div class="p-1 rounded-lg bg-rose-100 text-rose-600 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex-1 text-sm font-medium leading-relaxed pt-0.5">
                {{ session('error') }}
            </div>
            <button onclick="dismissToast('toast-error')" class="text-gray-400 hover:text-gray-600 transition shrink-0 ml-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif
</div>
    <!-- AJAX Client-side Scripts -->
    <script>
        // toas
        function dismissToast(id) {
            const toast = document.getElementById(id);
            if (toast) {
                toast.classList.add('opacity-0', 'translate-x-full');
                setTimeout(() => toast.remove(), 600);
            }
        }

        // 4 seconds baad auto-dismiss ho jayega
        document.addEventListener('DOMContentLoaded', () => {
            ['toast-success', 'toast-info', 'toast-error'].forEach(id => {
                const toast = document.getElementById(id);
                if (toast) {
                    setTimeout(() => dismissToast(id), 6500);
                }
            });
        });
        // Filters Logic
        function applyFilters() {
            const makler = document.getElementById('maklerFilter').value;
            const windowDays = document.getElementById('windowFilter').value;
            const followup = document.getElementById('followupFilter').value;

            document.querySelectorAll('.contract-row').forEach(row => {
                const rMakler = row.dataset.makler;
                const rDays = parseInt(row.dataset.days);
                const rFollowup = row.dataset.followup;

                let show = true;

                if (makler !== 'ALL' && rMakler !== makler) show = false;
                if (followup !== 'ALL' && rFollowup !== followup) show = false;
                if (windowDays !== 'ALL' && rDays > parseInt(windowDays)) show = false;

                row.style.display = show ? '' : 'none';
            });
        }

        // Create Task in Zoho CRM without Page Reload
        async function createFollowup(contractId, vertragsnummer, customerId, customerName, expiryDate, makler) {
            const btn = document.getElementById(`btn-${contractId}`);
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = 'Creating Task...';

            try {
                const response = await fetch("{{ route('contracts.followup') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        contract_id: contractId,
                        vertragsnummer: vertragsnummer,
                        customer_id: customerId,
                        customer_name: customerName,
                        expiry_date: expiryDate,
                        makler: makler
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    // Update UI dynamically without full page reload
                    const cell = btn.closest('.action-cell');
                    cell.innerHTML = `
                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Follow-up Set
                        </span>
                    `;
                    document.getElementById(`row-${contractId}`).dataset.followup = 'DONE';
                    showToast('Follow-up task created in Zoho CRM successfully!', 'success');
                } else {
                    throw new Error(result.message || 'Operation failed');
                }
            } catch (err) {
                btn.disabled = false;
                btn.innerText = originalText;
                showToast(err.message, 'error');
            }
        }

        function showToast(msg, type) {
            const toast = document.getElementById('toast');
            toast.className = type === 'success' 
                ? 'p-4 rounded-xl text-sm font-medium transition-all shadow bg-emerald-50 text-emerald-900 border border-emerald-200' 
                : 'p-4 rounded-xl text-sm font-medium transition-all shadow bg-rose-50 text-rose-900 border border-rose-200';
            toast.innerText = msg;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 5000);
        }

        async function saveContract(e) {
            e.preventDefault();
            const btn = document.getElementById('saveContractBtn');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = 'Saving to Zoho...';

            const form = document.getElementById('newContractForm');
            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());

            try {
                const response = await fetch("{{ route('contracts.store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                });

                const res = await response.json();

                if (response.ok && res.success) {
                    // Backend se aane wala dynamic message use karein
                    const toastType = res.is_new ? 'success' : 'info';
                    showToast(res.message, toastType);

                    // Modal hide aur form clear karein
                    document.getElementById('newContractModal').classList.add('hidden');
                    form.reset();

                    // Screen refresh taake updated/new contract table mein appear ho jaye
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    throw new Error(res.message || 'Failed to save contract');
                }
            } catch (err) {
                showToast(err.message, 'error');
                btn.disabled = false;
                btn.innerText = originalText;
            }
        }

        // Toast function mein 'info' type handle karne ke liye update:
        function showToast(msg, type) {
            const toast = document.getElementById('toast');
            if (!toast) return;

            let styleClass = 'p-4 rounded-xl text-sm font-medium transition-all shadow ';
            
            if (type === 'success') {
                styleClass += 'bg-emerald-50 text-emerald-900 border border-emerald-200';
            } else if (type === 'info') {
                styleClass += 'bg-blue-50 text-blue-900 border border-blue-200';
            } else {
                styleClass += 'bg-rose-50 text-rose-900 border border-rose-200';
            }

            toast.className = styleClass;
            toast.innerText = msg;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 5000);
        }
    </script>
</body>
</html>