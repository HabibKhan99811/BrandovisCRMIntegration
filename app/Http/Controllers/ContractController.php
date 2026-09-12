<?php

namespace App\Http\Controllers;

use App\Services\ZohoService;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(ZohoService $zoho)
    {
        $contracts = $zoho->getContractsWithCustomerDetails();
        $maklers = collect($contracts)->pluck('Makler')->filter()->unique()->values();
        $contacts = $zoho->getContactsList();
        return view('contracts', compact('contracts', 'maklers','contacts'));
    }

    public function createFollowup(Request $request, ZohoService $zoho)
    {
        $request->validate([
            'contract_id'     => 'required|string',
            'vertragsnummer'  => 'required|string',
            'customer_id'     => 'required|string',
            'customer_name'   => 'required|string',
            'expiry_date'     => 'required|string',
            'makler'          => 'nullable|string',
        ]);

        try {
            $zoho->createRenewalTask(
                $request->contract_id,
                $request->vertragsnummer,
                $request->customer_id,
                $request->customer_name,
                $request->expiry_date,
                $request->makler
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, ZohoService $zoho)
    {
        $validated = $request->validate([
            'vertragsnummer' => 'required|string',
            'customer_id'    => 'required|string',
            'produkt'        => 'required|string',
            'versicherer'    => 'required|string',
            'beginn'         => 'nullable|date',
            'ablaufdatum'    => 'required|date',
            'jahresbeitrag'  => 'required|numeric',
            'zahlweise'      => 'nullable|string',
            'makler'         => 'required|string',
        ]);

        try {
            $zohoRes = $zoho->createContract($validated);

            // Zoho ka action check karein: 'insert' ya 'update'
            $firstItem = $zohoRes['data'][0] ?? [];
            $action = $firstItem['action'] ?? 'insert';

            if ($action === 'update') {
                $message = "Contract {$validated['vertragsnummer']} already exists in Zoho CRM. Existing record has been updated.";
                $isNew = false;
            } else {
                $message = "New contract {$validated['vertragsnummer']} created successfully in Zoho CRM!";
                $isNew = true;
            }
            return response()->json([
                'success' => true,
                'is_new'  => $isNew,
                'message' => $message
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
