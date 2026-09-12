<?php

namespace App\Http\Controllers;

use App\Services\ZohoService;
use Illuminate\Http\Request;

class ImportCsvController extends Controller
{
    public function index(){
        return view('import');
    }

    public function preview(Request $request){
        $request->validate([
            'kontakte_csv' => 'required|file|mimes:csv,txt',
            'vertraege_csv' => 'required|file|mimes:csv,txt',
        ]);

        // Contacts Parse
        $contactsData = $this->parseCsv($request->file('kontakte_csv'));
        $contacts = [];
        $validKundennummern = [];

        foreach ($contactsData as $row) {
            if (empty($row['Kundennummer'])) continue;
            
            $contacts[] = $row;
            $validKundennummern[] = $row['Kundennummer'];
        }

        // Contracts Parse and Validate 
        $contractsData = $this->parseCsv($request->file('vertraege_csv'));
        $validContracts = [];
        $issues = [];
        $seenVertragsnummern = [];
        $totalVolume = 0.0;

        foreach ($contractsData as $row) {
            $vn = $row['Vertragsnummer'] ?? null;
            $kn = $row['Kundennummer'] ?? null;

            if (!$vn) continue;

            // Duplicate Vertragsnummer check
            if (in_array($vn, $seenVertragsnummern)) {
                $issues[] = [
                    'type' => 'DUPLICATE_ENTRY',
                    'message' => "Duplicate Vertragsnummer {$vn} detected. Second instance skipped.",
                    'record_ref' => $vn,
                ];
                continue;
            }

            // Orphan check (e.g. K-1099 jo customer export me nahi hai)
            if (!in_array($kn, $validKundennummern)) {
                $issues[] = [
                    'type' => 'ORPHAN_CUSTOMER',
                    'message' => "Contract references customer ID {$kn} which does not exist in contacts export.",
                    'record_ref' => $vn,
                ];
                continue;
            }

            // German Number Format Clean karna (e.g. 1284,00 -> 1284.00)
            $cleanAmount = (float) str_replace(',', '.', str_replace('.', '', $row['Jahresbeitrag'] ?? '0'));
            $row['Jahresbeitrag'] = $cleanAmount;
            $totalVolume += $cleanAmount;

            $seenVertragsnummern[] = $vn;
            $validContracts[] = $row;
        }

        $previewData = [
            'contacts' => $contacts,
            'contracts' => $validContracts,
            'totalVolume' => $totalVolume,
            'issues' => $issues,
        ];

        // Parsed data ko session me rakh dein for confirmation
        session(['pending_import' => $previewData]);

        return view('import',compact('previewData'));
    }
    private function parseCsv($file)
    {
        $rows = [];
        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $header = fgetcsv($handle, 0, ';');
            // UTF-8 BOM agar ho to strip karein
            if ($header && isset($header[0])) {
                $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
            }

            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                if (count($header) === count($data)) {
                    $rows[] = array_combine($header, $data);
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    
    public function importConfirm(Request $request, ZohoService $zoho)
    {
        $pending = session('pending_import');

        if (!$pending) {
            return redirect()->route('import.index')->with('error', 'No preview data found. Please upload CSVs again.');
        }

        try {
            $contactRes = $zoho->upsertContacts($pending['contacts'] ?? []);
            $contactMap = $zoho->getContactIdMap();
            $contractRes = $zoho->upsertContracts($pending['contracts'] ?? [], $contactMap);
            session()->forget('pending_import');
            $contactsCreated = 0;
            $contactsUpdated = 0;
            foreach ($contactRes['data'] ?? [] as $item) {
                if (($item['status'] ?? '') === 'success') {
                    ($item['action'] ?? '') === 'insert' ? $contactsCreated++ : $contactsUpdated++;
                }
            }

            $contractsCreated = 0;
            $contractsUpdated = 0;
            foreach ($contractRes['data'] ?? [] as $item) {
                if (($item['status'] ?? '') === 'success') {
                    ($item['action'] ?? '') === 'insert' ? $contractsCreated++ : $contractsUpdated++;
                }
            }

            // Check import is repeat or not
            $isRepeat = ($contactsCreated === 0 && $contractsCreated === 0);

            if ($isRepeat) {
                $msg = "Re-import complete: No duplicate records created. Existing {$contactsUpdated} contacts and {$contractsUpdated} contracts were safely refreshed in Zoho CRM.";
                return redirect()->route('contracts.index')->with('info', $msg);
            }

            $msg = "Import completed successfully: {$contactsCreated} contacts and {$contractsCreated} contracts created ({$contractsUpdated} existing updated).";
            return redirect()->route('contracts.index')->with('success', $msg);

        } catch (\Exception $e) {
            return redirect()->route('import.index')->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
