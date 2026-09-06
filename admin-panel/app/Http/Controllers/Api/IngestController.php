<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\WebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Inbound webhooks FROM the standalone apps. Currently only HRMS → admin, so an
 * employee created in HRMS also exists in the identity provider.
 */
class IngestController extends Controller
{
    public function employeeUser(Request $request)
    {
        $secret = (string) AppSetting::getValue('integration_hrms_secret', '');
        abort_unless($secret !== '' && WebhookSignature::verify($request, $secret), 401, 'bad signature');

        $data = $request->input('data', $request->all());
        $v = validator($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string',   // already hashed
        ])->validate();

        $user = User::updateOrCreate(
            ['email' => $v['email']],
            [
                'name' => $v['name'],
                'phone' => $v['phone'] ?? ('HR' . random_int(1000000000, 9999999999)),
                'password' => $v['password'] ?? Hash::make(Str::random(32)),
                'is_active' => true,
            ]
        );
        if (! $user->hasRole('employee')) {
            $user->assignRole('employee');
        }

        return response()->json(['ok' => true, 'user_id' => $user->id]);
    }
}
