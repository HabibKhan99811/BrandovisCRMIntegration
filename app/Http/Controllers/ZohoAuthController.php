<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ZohoAuthController extends Controller
{
    public function redirect()
    {
        $params = [
            // Tamam standard aur custom modules ka full access
            'scope'         => 'ZohoCRM.modules.ALL,ZohoCRM.modules.custom.ALL,ZohoCRM.settings.ALL,ZohoCRM.coql.READ,ZohoCRM.org.READ',
            'client_id'     => config('services.zoho.client_id'),
            'response_type' => 'code',
            'access_type'   => 'offline',
            'redirect_uri'  => config('services.zoho.redirect_uri'),
            'prompt'        => 'consent', 
        ];

        $url = config('services.zoho.accounts_url')
            . '/oauth/v2/auth?'
            . http_build_query($params);

        return redirect($url);
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Zoho OAuth rejected: ' . $request->get('error')
            ], 400);
        }

        $response = Http::withOptions([
           'verify' => env('APP_ENV') === 'local' ? 'C:\xampp\php\extras\ssl\cacert.pem' : true,
        ])->asForm()->post(
            config('services.zoho.accounts_url') . '/oauth/v2/token',
            [
                'code'          => $request->code,
                'client_id'     => config('services.zoho.client_id'),
                'client_secret' => config('services.zoho.client_secret'),
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => config('services.zoho.redirect_uri'),
            ]
        );

        $data = $response->json();
        return response()->json($data);
    }

    public function test()
    {
        $accountsUrl = config('services.zoho.accounts_url', 'https://accounts.zoho.com');
        $baseUrl     = config('services.zoho.base_url', 'https://www.zohoapis.com/crm/v2');
        $clientId    = config('services.zoho.client_id');
        $clientSecret= config('services.zoho.client_secret');
        $refreshToken= config('services.zoho.refresh_token');

            //Check karo credentials load ho bhi rahe hain ya nahi
            if (!$clientId || !$clientSecret || !$refreshToken) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Credentials .env ya config se nahi mil rahe. Cache clear karein: php artisan config:clear',
                    'loaded_keys' => [
                        'client_id' => $clientId ? 'FOUND' : 'MISSING',
                        'client_secret' => $clientSecret ? 'FOUND' : 'MISSING',
                        'refresh_token' => $refreshToken ? 'FOUND' : 'MISSING',
                    ]
                ], 500);
            }

            // Refresh Token se Access Token hasil karo
            $tokenResponse = Http::asForm()->post("{$accountsUrl}/oauth/v2/token", [
                'refresh_token' => $refreshToken,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'grant_type'    => 'refresh_token',
            ]);

            if ($tokenResponse->failed() || !isset($tokenResponse['access_token'])) {
                return response()->json([
                    'status' => 'OAUTH_FAILED',
                    'message' => 'Zoho se Access Token nahi mila. Refresh Token ya Client Secret ghalat hai.',
                    'zoho_response' => $tokenResponse->json() ?? $tokenResponse->body()
                ], 400);
            }

            $accessToken = $tokenResponse['access_token'];

            // Simple CRM Module Call (Contacts list fetch karein)
            $crmResponse = Http::withToken($accessToken)
                ->get("{$baseUrl}/Contacts?per_page=2");

            // Custom Contracts Module Call
            $contractsResponse = Http::withToken($accessToken)
                ->get("{$baseUrl}/Contracts?per_page=2");

            return response()->json([
                'status' => 'SUCCESS',
                'oauth_connection' => 'Connected successfully!',
                'contacts_test' => [
                    'status_code' => $crmResponse->status(),
                    'records_found' => isset($crmResponse['data']) ? count($crmResponse['data']) : 0,
                    'response' => $crmResponse->json()
                ],
                'contracts_test' => [
                    'status_code' => $contractsResponse->status(),
                    'records_found' => isset($contractsResponse['data']) ? count($contractsResponse['data']) : 0,
                    'response' => $contractsResponse->json()
                ]
            ]);
    }
}
