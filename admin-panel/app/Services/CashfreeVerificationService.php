<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\PartnerApplication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Wraps Cashfree's Secure ID / Verification Suite (VRS) APIs used to verify
 * documents collected during restaurant (seller) and driver partner
 * registration. Docs: https://www.cashfree.com/docs/api-reference/vrs/overview
 */
class CashfreeVerificationService
{
    public function verifyGstin(string $gstin, ?string $businessName = null): array
    {
        return $this->call('post', '/gstin', array_filter([
            'GSTIN' => strtoupper(trim($gstin)),
            'businessName' => $businessName,
        ]));
    }

    public function verifyVehicleRc(string $vehicleNumber, string $verificationId): array
    {
        return $this->call('post', '/vehicle-rc', [
            'verification_id' => $verificationId,
            'vehicle_number' => strtoupper(preg_replace('/\s+/', '', $vehicleNumber)),
        ]);
    }

    public function verifyDrivingLicense(string $dlNumber, string $dob, string $verificationId): array
    {
        return $this->call('post', '/driving-license', [
            'verification_id' => $verificationId,
            'dl_number' => strtoupper(preg_replace('/\s+/', '', $dlNumber)),
            'dob' => $dob,
        ]);
    }

    public function verifyPan(string $pan, ?string $name = null): array
    {
        return $this->call('post', '/pan', array_filter([
            'pan' => strtoupper(trim($pan)),
            'name' => $name,
        ]));
    }

    public function verifyPanFromImage(string $storageDiskPath, string $verificationId): array
    {
        return $this->callMultipart('/document/pan', 'front_image', $storageDiskPath, [
            'verification_id' => $verificationId,
        ]);
    }

    public function verifyAadhaarFromImage(string $storageDiskPath, string $verificationId, ?string $backStorageDiskPath = null): array
    {
        $extra = [];
        if ($backStorageDiskPath) {
            $extra['back_image'] = $backStorageDiskPath;
        }

        return $this->callMultipart('/document/aadhaar', 'front_image', $storageDiskPath, [
            'verification_id' => $verificationId,
        ], $extra);
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled() && (bool) $this->clientId() && (bool) $this->clientSecret();
    }

    public function isEnabled(): bool
    {
        return filter_var(
            AppSetting::getValue('cashfree_vrs_enabled', '0'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Verify whatever documents/numbers are present on a partner application
     * (restaurant or driver) and return the results keyed by document type.
     * Never throws - a failed or unreachable Cashfree call just leaves that
     * document unverified for the admin to review manually.
     */
    public function verifyPartnerApplication(PartnerApplication $application): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $results = [];

        try {
            if ($application->partner_type === 'restaurant') {
                $gstin = $application->gstin_number;
                if ($gstin) {
                    $results['gstin'] = $this->verifyGstin($gstin, $application->business_name);
                }

                if ($application->pan_number) {
                    $results['pan'] = $this->verifyPan($application->pan_number, $application->contact_name);
                }
            } else {
                if ($application->vehicle_number) {
                    $results['vehicle_rc'] = $this->verifyVehicleRc(
                        $application->vehicle_number,
                        $this->newVerificationId('rc-' . $application->id)
                    );
                }

                $dob = $application->dob?->format('Y-m-d')
                    ?? (is_array($application->onboarding_meta) ? ($application->onboarding_meta['date_of_birth'] ?? null) : null);

                if ($application->license_number && $dob) {
                    $results['driving_license'] = $this->verifyDrivingLicense(
                        $application->license_number,
                        $dob,
                        $this->newVerificationId('dl-' . $application->id)
                    );
                }

                if ($application->pan_card) {
                    $results['pan'] = $this->verifyPanFromImage(
                        $application->pan_card,
                        $this->newVerificationId('pan-' . $application->id)
                    );
                }

                if ($application->aadhar_card) {
                    $results['aadhaar'] = $this->verifyAadhaarFromImage(
                        $application->aadhar_card,
                        $this->newVerificationId('aadhaar-' . $application->id)
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Cashfree document verification failed for application ' . $application->id . ': ' . $e->getMessage());
        }

        return $results;
    }

    private function call(string $method, string $path, array $payload): array
    {
        if (! $this->isConfigured()) {
            return $this->result('not_configured', null, 'Cashfree verification credentials are not configured.');
        }

        try {
            $response = $this->http()->{$method}($this->baseUrl() . $path, $payload);
        } catch (\Throwable $e) {
            \Log::error('Cashfree verification request failed: ' . $e->getMessage(), ['path' => $path]);
            return $this->result('error', null, $e->getMessage());
        }

        return $this->normalizeResponse($response);
    }

    private function callMultipart(string $path, string $fileField, string $storageDiskPath, array $fields, array $extraFiles = []): array
    {
        if (! $this->isConfigured()) {
            return $this->result('not_configured', null, 'Cashfree verification credentials are not configured.');
        }

        if (! Storage::disk('public')->exists($storageDiskPath)) {
            return $this->result('error', null, 'Uploaded document could not be found for verification.');
        }

        try {
            $http = $this->http()->attach(
                $fileField,
                Storage::disk('public')->get($storageDiskPath),
                basename($storageDiskPath)
            );

            foreach ($extraFiles as $field => $diskPath) {
                if (! Storage::disk('public')->exists($diskPath)) {
                    continue;
                }
                $http = $http->attach($field, Storage::disk('public')->get($diskPath), basename($diskPath));
            }

            $response = $http->post($this->baseUrl() . $path, $fields);
        } catch (\Throwable $e) {
            \Log::error('Cashfree document verification request failed: ' . $e->getMessage(), ['path' => $path]);
            return $this->result('error', null, $e->getMessage());
        }

        return $this->normalizeResponse($response);
    }

    private function normalizeResponse($response): array
    {
        $data = $response->json() ?? [];

        if (! $response->successful()) {
            return $this->result('error', $data, $data['message'] ?? ('HTTP ' . $response->status()));
        }

        $valid = $data['valid'] ?? null;
        $status = match (true) {
            $valid === true => 'verified',
            $valid === false => 'invalid',
            isset($data['status']) && Str::lower((string) $data['status']) === 'valid' => 'verified',
            isset($data['status']) && Str::lower((string) $data['status']) === 'invalid' => 'invalid',
            default => 'checked',
        };

        return $this->result($status, $data, $data['message'] ?? null);
    }

    private function result(string $status, ?array $data, ?string $message = null): array
    {
        return [
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'details' => $data,
            'message' => $message,
        ];
    }

    public function newVerificationId(string $prefix): string
    {
        return Str::slug($prefix) . '_' . now()->timestamp . '_' . Str::random(6);
    }

    private function http()
    {
        return Http::withHeaders([
            'x-client-id' => $this->clientId(),
            'x-client-secret' => $this->clientSecret(),
            'x-api-version' => AppSetting::getValue('cashfree_vrs_api_version', env('CASHFREE_VRS_API_VERSION', '2022-09-01')),
        ])->timeout(30);
    }

    private function clientId(): ?string
    {
        return AppSetting::getValue('cashfree_vrs_client_id', env('CASHFREE_VRS_CLIENT_ID'))
            ?: AppSetting::getValue('cashfree_key', env('CASHFREE_CLIENT_ID'));
    }

    private function clientSecret(): ?string
    {
        return AppSetting::getValue('cashfree_vrs_client_secret', env('CASHFREE_VRS_CLIENT_SECRET'))
            ?: AppSetting::getValue('cashfree_secret', env('CASHFREE_CLIENT_SECRET'));
    }

    private function baseUrl(): string
    {
        $mode = AppSetting::getValue('cashfree_vrs_mode', AppSetting::getValue('cashfree_mode', 'test'));

        return $mode === 'live'
            ? 'https://api.cashfree.com/verification'
            : 'https://sandbox.cashfree.com/verification';
    }
}
