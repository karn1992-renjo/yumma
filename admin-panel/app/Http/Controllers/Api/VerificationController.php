<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CashfreeVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Lightweight, unauthenticated field-level checks used by the driver and
 * restaurant apps to verify a document/number in realtime while the user is
 * still filling out the registration form - before any partner application
 * record exists. Nothing here is persisted; the final values are re-verified
 * and stored against the application by PartnerApplicationController::submit.
 */
class VerificationController extends Controller
{
    public function __construct(private readonly CashfreeVerificationService $verification)
    {
    }

    public function gstin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gstin' => ['required', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i'],
            'business_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->invalid('Enter a valid 15-character GSTIN.');
        }

        return $this->respond($this->verification->verifyGstin(
            $request->input('gstin'),
            $request->input('business_name')
        ));
    }

    public function pan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pan' => ['required', 'string', 'regex:/^[A-Z]{5}\d{4}[A-Z]{1}$/i'],
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->invalid('Enter a valid 10-character PAN.');
        }

        return $this->respond($this->verification->verifyPan(
            $request->input('pan'),
            $request->input('name')
        ));
    }

    public function vehicleRc(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_number' => 'required|string|min:4|max:20',
        ]);

        if ($validator->fails()) {
            return $this->invalid('Enter a valid vehicle registration number.');
        }

        return $this->respond($this->verification->verifyVehicleRc(
            $request->input('vehicle_number'),
            $this->verification->newVerificationId('rc-live')
        ));
    }

    public function drivingLicense(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'license_number' => 'required|string|min:4|max:50',
            'dob' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->invalid('Enter a valid licence number and date of birth.');
        }

        return $this->respond($this->verification->verifyDrivingLicense(
            $request->input('license_number'),
            $request->input('dob'),
            $this->verification->newVerificationId('dl-live')
        ));
    }

    public function panDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'front_image' => 'required|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->invalid('Upload a valid PAN card image (JPG/PNG, max 5MB).');
        }

        $path = $request->file('front_image')->store('partner_documents/pan-live', 'public');

        try {
            return $this->respond($this->verification->verifyPanFromImage(
                $path,
                $this->verification->newVerificationId('pan-live')
            ));
        } finally {
            Storage::disk('public')->delete($path);
        }
    }

    public function aadhaarDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'front_image' => 'required|image|max:5120',
            'back_image' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->invalid('Upload a valid Aadhaar card image (JPG/PNG, max 5MB).');
        }

        $frontPath = $request->file('front_image')->store('partner_documents/aadhaar-live', 'public');
        $backPath = $request->hasFile('back_image')
            ? $request->file('back_image')->store('partner_documents/aadhaar-live', 'public')
            : null;

        try {
            return $this->respond($this->verification->verifyAadhaarFromImage(
                $frontPath,
                $this->verification->newVerificationId('aadhaar-live'),
                $backPath
            ));
        } finally {
            Storage::disk('public')->delete($frontPath);
            if ($backPath) {
                Storage::disk('public')->delete($backPath);
            }
        }
    }

    private function respond(array $result)
    {
        return response()->json(['success' => true] + $result);
    }

    private function invalid(string $message)
    {
        return response()->json(['success' => false, 'status' => 'invalid', 'message' => $message], 422);
    }
}
