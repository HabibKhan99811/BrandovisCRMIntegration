<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZohoService
{
    protected $baseUrl;
    protected $accountsUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.zoho.base_url', 'https://www.zohoapis.com/crm/v2');
        $this->accountsUrl = config('services.zoho.accounts_url', 'https://accounts.zoho.com');
    }

    public function getAccessToken()
    {
        return Cache::remember('zoho_access_token', 3000, function () {
            $response = Http::asForm()->post("{$this->accountsUrl}/oauth/v2/token", [
                'refresh_token' => config('services.zoho.refresh_token'),
                'client_id'     => config('services.zoho.client_id'),
                'client_secret' => config('services.zoho.client_secret'),
                'grant_type'    => 'refresh_token',
            ]);

            if ($response->failed() || !isset($response['access_token'])) {
                throw new \Exception("Zoho Token Refresh Failed: " . $response->body());
            }

            return $response['access_token'];
        });
    }

    public function createContract(array $data)
    {
        $token = $this->getAccessToken();

        $payload = [
            'data' => [
                [
                    'Name'              => (string) $data['vertragsnummer'],
                    'Vertragsnummer'    => (string) $data['vertragsnummer'],
                    'Customer'          => ['id' => $data['customer_id']],
                    'Produkt'           => $data['produkt'] ?? null,
                    'Versicherer'       => $data['versicherer'] ?? null,
                    'Beginn'            => !empty($data['beginn']) ? $data['beginn'] : null,
                    'Ablaufdatum'       => !empty($data['ablaufdatum']) ? $data['ablaufdatum'] : null,
                    'Jahresbeitrag'     => (float) ($data['jahresbeitrag'] ?? 0),
                    'Zahlweise'         => $data['zahlweise'] ?? 'monatlich',
                    'Status'            => 'Aktiv',
                    'Makler'            => $data['makler'] ?? null,
                    'Follow_up_Created' => false,
                ]
            ],
            // update if duplicate records are found
            'duplicate_check_fields' => ['Vertragsnummer']
        ];

        $response = Http::withToken($token)->post("{$this->baseUrl}/Contracts/upsert", $payload);

        if ($response->failed()) {
            throw new \Exception("Failed to upsert contract in Zoho: " . $response->body());
        }

        return $response->json();
    }

    public function getContactsList()
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)->get("{$this->baseUrl}/Contacts?fields=id,Full_Name,Kundennummer,Email&per_page=200");

        if ($response->successful() && isset($response['data'])) {
            return $response['data'];
        }

        return [];
    }

    public function upsertContacts(array $contacts)
    {
        $token = $this->getAccessToken();
        $payload = [];

        foreach ($contacts as $c) {
            $payload[] = [
                'Kundennummer'  => $c['Kundennummer'],
                'Salutation'    => $c['Anrede'] ?? null,
                'First_Name'    => $c['Vorname'] ?? '',
                'Last_Name'     => $c['Nachname'] ?? 'Unknown',
                'Email'         => $c['Email'] ?? null,
                'Phone'         => $c['Telefon'] ?? null,
                'Mailing_Street'=> $c['Strasse'] ?? null,
                'Mailing_Zip'   => $c['PLZ'] ?? null,
                'Mailing_City'  => $c['Ort'] ?? null,
                'Date_of_Birth' => !empty($c['Geburtsdatum']) ? $c['Geburtsdatum'] : null,
            ];
        }

        // Upsert by Kundennummer
        $response = Http::withToken($token)->post("{$this->baseUrl}/Contacts/upsert", [
            'data' => $payload,
            'duplicate_check_fields' => ['Kundennummer']
        ]);

        return $response->json();
    }


    // Contacts ki Zoho IDs fetch karna Kundennummer ke mutabiq
    public function getContactIdMap()
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)->get("{$this->baseUrl}/Contacts?fields=id,Kundennummer&per_page=200");
        
        $map = [];
        if ($response->successful() && isset($response['data'])) {
            foreach ($response['data'] as $rec) {
                if (!empty($rec['Kundennummer'])) {
                    $map[$rec['Kundennummer']] = $rec['id'];
                }
            }
        }
        return $map;
    }

    // Contracts ko Zoho me Upsert karna (Lookup Link ke sath)
    public function upsertContracts(array $contracts, array $contactMap)
    {
        $token = $this->getAccessToken();
        $payload = [];

        foreach ($contracts as $c) {
            $kn = $c['Kundennummer'];
            $contactId = $contactMap[$kn] ?? null;

            if (!$contactId) continue;

            $payload[] = [
                'Name'               => $c['Vertragsnummer'], 
                'Vertragsnummer'     => $c['Vertragsnummer'],
                'Customer'           => ['id' => $contactId],
                'Produkt'            => $c['Produkt'] ?? null,
                'Versicherer'        => $c['Versicherer'] ?? null,
                'Beginn'             => $c['Beginn'] ?? null,
                'Ablaufdatum'        => $c['Ablaufdatum'] ?? null,
                'Jahresbeitrag'      => (float)$c['Jahresbeitrag'],
                'Zahlweise'          => $c['Zahlweise'] ?? null,
                'Status'             => $c['Status'] ?? null,
                'Makler'             => $c['Makler'] ?? null,
                'Follow_up_Created'  => false,
            ];
        }

        $response = Http::withToken($token)->post("{$this->baseUrl}/Contracts/upsert", [
            'data' => $payload,
            'duplicate_check_fields' => ['Vertragsnummer']
        ]);

        return $response->json();
    }
    
    public function getContractsWithCustomerDetails()
    {
        try {
            $token = $this->getAccessToken();
            $res = Http::withToken($token)->get("{$this->baseUrl}/Contracts?fields=Vertragsnummer,Produkt,Versicherer,Ablaufdatum,Jahresbeitrag,Makler,Customer,Follow_up_Created&per_page=200");
            if ($res->status() === 204) {
                Log::info("Zoho Contracts: No records found in CRM (204 No Content).");
                return [];
            }

            if (!$res->successful() || !isset($res['data'])) {
                Log::error("Zoho Contracts Fetch Error: " . $res->body());
                return [];
            }

            $records = [];
            $now = Carbon::now();

            foreach ($res['data'] as $row) {
                // Date Parsing with fallback for German format (e.g. DD.MM.YYYY)
                $days = 9999;
                $rawDate = $row['Ablaufdatum'] ?? null;

                if (!empty($rawDate)) {
                    try {
                        $expiry = str_contains($rawDate, '.') 
                            ? Carbon::createFromFormat('d.m.Y', trim($rawDate)) 
                            : Carbon::parse($rawDate);

                        $days = (int) $now->diffInDays($expiry, false);
                    } catch (\Throwable $dateEx) {
                        Log::warning("Date parse failed for contract {$row['id']}: " . $dateEx->getMessage());
                    }
                }

                // Customer details lookup handling
                $customer = $row['Customer'] ?? [];

                $records[] = [
                    'id'                => $row['id'] ?? '',
                    'Vertragsnummer'    => $row['Vertragsnummer'] ?? '',
                    'Produkt'           => $row['Produkt'] ?? '',
                    'Versicherer'       => $row['Versicherer'] ?? '',
                    'Ablaufdatum'       => $rawDate ?? '',
                    'Jahresbeitrag'     => $row['Jahresbeitrag'] ?? 0,
                    'Makler'            => $row['Makler'] ?? '',
                    'Follow_up_Created' => (bool) ($row['Follow_up_Created'] ?? false),
                    'days_remaining'    => $days,
                    'Customer_id'       => $customer['id'] ?? '',
                    'Customer_Name'     => $customer['name'] ?? 'N/A',
                    'Customer_Email'    => $customer['Email'] ?? '',
                    'Customer_Phone'    => $customer['Phone'] ?? '',
                ];
            }

            // sorting based on Urgency 
            usort($records, fn($a, $b) => $a['days_remaining'] <=> $b['days_remaining']);

            return $records;

        } catch (\Throwable $e) {
            Log::error("Critical Exception in getContractsWithCustomerDetails: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return [];
        }
    }

    public function createRenewalTask($contractId, $vertragsnummer, $customerId, $customerName, $expiryDate, $makler)
    {
        $token = $this->getAccessToken();

        $dueDate = Carbon::parse($expiryDate)->subDays(30)->toDateString();

        $taskPayload = [
            'data' => [
                [
                    'Subject'     => "Renewal call — {$customerName} ({$vertragsnummer})",
                    'Due_Date'    => $dueDate,
                    '$se_module'  => 'Contracts',
                    'What_Id'     => $contractId,
                    'Who_Id'      => $customerId,
                    'Status'      => 'Not Started',
                    'Description' => "Automated renewal reminder generated for agent {$makler}.",
                ]
            ]
        ];

        $taskRes = Http::withToken($token)->post("{$this->baseUrl}/Tasks", $taskPayload);

        if ($taskRes->failed()) {
            throw new \Exception("Failed to create Task in Zoho: " . $taskRes->body());
        }

        // Contract record update karna (Follow_up_Created = true)
        $updatePayload = [
            'data' => [
                [
                    'id' => $contractId,
                    'Follow_up_Created' => true
                ]
            ]
        ];

        $updateRes = Http::withToken($token)->put("{$this->baseUrl}/Contracts", $updatePayload);

        if ($updateRes->failed()) {
            throw new \Exception("Failed to update Contract follow-up status: " . $updateRes->body());
        }

        return true;
    }

    public function autoCreateExpiringFollowups(int $daysThreshold = 30)
    {
        $token = $this->getAccessToken();
        $res = Http::withToken($token)->get(
            "{$this->baseUrl}/Contracts?fields=id,Vertragsnummer,Ablaufdatum,Customer,Makler,Follow_up_Created&per_page=200"
        );

        if ($res->status() === 204 || !$res->successful()) {
            return ['status' => 'no_records', 'created' => 0];
        }

        $records = $res->json()['data'] ?? [];
        $now = Carbon::now();
        $createdCount = 0;

        foreach ($records as $row) {
            // Agar pehle se task ban chuka hai to skip
            if (!empty($row['Follow_up_Created'])) {
                continue;
            }

            $rawDate = $row['Ablaufdatum'] ?? null;
            if (!$rawDate) {
                continue;
            }

            try {
                $expiry = str_contains($rawDate, '.') 
                    ? Carbon::createFromFormat('d.m.Y', trim($rawDate)) 
                    : Carbon::parse($rawDate);

                $diffDays = (int) $now->diffInDays($expiry, false);

                // Agar agle 30 din mein expire ho raha ho (diffDays >= 0 aur <= 30)
                if ($diffDays >= 0 && $diffDays <= $daysThreshold) {
                    $customerId = $row['Customer']['id'] ?? null;
                    $customerName = $row['Customer']['name'] ?? 'Customer';

                    $this->createRenewalTask(
                        $row['id'],
                        $row['Vertragsnummer'] ?? '',
                        $customerId,
                        $customerName,
                        $rawDate,
                        $row['Makler'] ?? 'Agent'
                    );

                    $createdCount++;
                }
            } catch (\Throwable $e) {
                Log::warning("Scheduler skipped contract {$row['id']}: " . $e->getMessage());
            }
        }

        return ['status' => 'success', 'created' => $createdCount];
    }
}