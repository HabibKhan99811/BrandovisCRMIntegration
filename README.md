# Brandovise CRM Integration Assessment

This repository contains the solution for the CRM & Automation Developer assessment for Brandovise. The application integrates a web interface with Zoho CRM to import and manage customer contracts.

---

## 🚀 How to Run Locally

1. **Clone the repository:**
   ```bash
   gh repo clone HabibKhan99811/BrandovisCRMIntegration
   cd BrandovisCRMIntegration
   ```

2. **Install Dependencies:**
   ```bash
   composer install
   ```

3. **Environment Setup:**
   - Copy the `.env.example` to `.env`.
   - Ensure the following Zoho credentials are set in your `.env` file (see Authentication section below):
     ```env
     ZOHO_CLIENT_ID=your_client_id
     ZOHO_CLIENT_SECRET=your_client_secret
     ZOHO_REFRESH_TOKEN=your_refresh_token
     ZOHO_ACCOUNTS_URL=https://accounts.zoho.com
     ZOHO_BASE_URL=https://www.zohoapis.com/crm/v2
     ```

4. **Run the Application:**
   ```bash
   php artisan key:generate
   php artisan serve
   ```
   Visit `http://localhost:8000` in your browser.

---

## 🔐 Zoho Authentication & Secrets

**Handling Authentication:**
The application uses Zoho's OAuth 2.0 flow. 
- An initial script/route was used to get the consent screen and exchange the `authorization_code` for an `access_token` and `refresh_token`.
- In the live application, the `ZohoService` uses the `refresh_token` to automatically generate a fresh `access_token` whenever required, utilizing `Cache::remember` to store the token for its 1-hour lifespan to prevent hitting rate limits.

**Where secrets live:**
All sensitive credentials (`client_id`, `client_secret`, and `refresh_token`) are strictly kept server-side in the `.env` file. They are never exposed to the frontend/browser, ensuring complete security.

---

## 🗄️ Data Modeling in Zoho CRM

**Contacts Module:**
Customers are mapped to the standard **Contacts** module. This makes sense because customers are natural contacts, and Zoho provides out-of-the-box fields like Name, Email, and Phone which align perfectly with the `kontakte_export.csv`. The `Kundennummer` field is used as a unique identifier for upserting.

**Contracts Module:**
A custom module named **Contracts** was created. Fields were added to match the export:
- `Vertragsnummer` (Unique ID)
- `Produkt`, `Versicherer` (Text fields)
- `Beginn`, `Ablaufdatum` (Date fields)
- `Jahresbeitrag` (Currency/Decimal)
- `Makler`, `Status`, `Zahlweise` (Dropdowns/Text)
- `Follow_up_Created` (Boolean/Checkbox) to track if a renewal task exists.

**Linking Contracts to Customers:**
The link is established using a **Lookup Relationship**. In the custom Contracts module, there is a Lookup field mapping to the Contacts module. During the import via API, the system first creates/fetches the Contact ID using `Kundennummer`, and then assigns that `contactId` to the Contract payload (`'Customer' => ['id' => $contactId]`). 

---

## 🕵️ Data Anomalies & Decisions (CSV Import)

The synthetic data contained several inconsistencies which were handled programmatically during the preview/import phase:

1. **Duplicate Contracts:** 
   - *Problem:* `VN-2021-0417` was present twice in `vertraege_export.csv`.
   - *Decision:* Implemented an array check (`in_array`) during parsing. If a duplicate `Vertragsnummer` is found in the current batch, it is flagged as `DUPLICATE_ENTRY` and skipped to prevent logical errors before hitting the API.

2. **Orphan Contracts:**
   - *Problem:* Some contracts (e.g., linked to Customer `K-1099`) referenced a `Kundennummer` that did not exist in `kontakte_export.csv`.
   - *Decision:* Validated every contract against the extracted valid customer IDs. Orphans are flagged as `ORPHAN_CUSTOMER` and skipped, as creating a contract without a valid customer violates the business logic.

3. **German Number Formatting:**
   - *Problem:* `Jahresbeitrag` used German formatting (e.g., `1.284,00`).
   - *Decision:* The parser strips the periods (thousands separator) and replaces commas with dots (`str_replace`) before casting to a float. This ensures Zoho receives a valid numerical value.

---

## 🔄 Handling Repeat Imports

Running the import twice is completely safe and **will not** result in duplicate records. 
This is achieved by utilizing Zoho's `upsert` API endpoints instead of standard `insert`. 
- For Contacts, the `duplicate_check_fields` is set to `['Kundennummer']`.
- For Contracts, the `duplicate_check_fields` is set to `['Vertragsnummer']`.
If the import is run again, the application simply updates the existing records instead of creating new ones, resulting in zero data duplication.

---

## ⏳ What I Would Do Differently With More Time

1. **Background Queues for Import:** Currently, parsing and hitting the Zoho API happens within the synchronous HTTP request cycle. For much larger datasets, this would timeout. I would move the actual API execution to a Laravel Queue/Job (e.g., `ProcessZohoImport`) so the user gets immediate UI feedback while the import runs in the background.
2. **Webhooks for Two-Way Sync:** If someone modifies a contract directly inside Zoho CRM, the local app wouldn't instantly know. I would implement Zoho Webhooks to listen for changes and keep the local app's cache updated.
3. **Advanced Frontend Interactions:** Move from basic Blade templates to a robust frontend framework like Vue.js or Livewire for more seamless, single-page application (SPA) feeling interactions, especially for filtering the console dashboard.
