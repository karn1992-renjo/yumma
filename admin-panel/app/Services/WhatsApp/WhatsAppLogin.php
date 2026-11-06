<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppContact;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Log in (or create an account) from the WhatsApp chat with a one-time code, so a customer whose
 * account uses a different mobile number can still order, and a new customer can sign up.
 *
 * The OTP itself is sent and checked by the app's own login code (the SMS provider set in admin),
 * and the account is created by the app's own registration. This class only runs the conversation
 * and, once the code is right, links this WhatsApp number to the account.
 */
class WhatsAppLogin
{
    private const STATES = ['login_choose', 'signup_name', 'signup_otp', 'login_phone', 'login_otp'];
    private const MAX_WRONG_CODES = 5;
    private const MAX_CODES_PER_HOUR = 4;

    public function __construct(private WhatsAppService $whatsapp, private WhatsAppActions $actions)
    {
    }

    public function active(WhatsAppContact $contact): bool
    {
        return in_array($contact->bot_state, self::STATES, true);
    }

    /** No account is linked to this number yet: explain the two ways forward. */
    public function offer(WhatsAppContact $contact, string $reason = 'I could not find an account for this WhatsApp number.'): void
    {
        $this->setState($contact, 'login_choose', []);
        $this->say($contact, "{$reason}\n\nReply:\n1️⃣ to create a new account (takes a minute)\n2️⃣ to log in to an account you already have (OTP)\n\nReply MENU to cancel.");
    }

    /** True when the message was part of (or started) the login flow. */
    public function consumes(WhatsAppContact $contact, string $text, string $word): bool
    {
        if (! $this->active($contact)) {
            if (in_array($word, ['login', 'log in', 'sign in', 'signin'], true)) {
                $linked = $contact->user_id ? User::find($contact->user_id) : null;
                if ($linked && $this->sameNumber($linked->phone, $contact->phone)) {
                    $this->say($contact, "You are already logged in as {$linked->name}. ✅ Reply MENU to see what you can do.");

                    return true;
                }
                $this->startLogin($contact);

                return true;
            }
            if (in_array($word, ['signup', 'sign up', 'register', 'create account'], true)) {
                $this->startSignup($contact);

                return true;
            }

            return false;
        }

        // Leaving the flow.
        if (in_array($word, ['menu', 'hi', 'hello', 'cancel', 'stop', 'home', '0'], true)) {
            $this->setState($contact, null, []);
            $this->say($contact, 'Okay, login cancelled. Reply MENU to see what I can do.');

            return true;
        }

        return match ($contact->bot_state) {
            'login_choose' => $this->choose($contact, $word),
            'signup_name' => $this->signupName($contact, $text),
            'signup_otp' => $this->signupOtp($contact, $word),
            'login_phone' => $this->loginPhone($contact, $text, $word),
            'login_otp' => $this->loginOtp($contact, $word),
            default => false,
        };
    }

    // ---- Steps ---------------------------------------------------------------------------------------------

    private function choose(WhatsAppContact $contact, string $word): bool
    {
        if (in_array($word, ['1', 'new', 'create', 'signup', 'sign up', 'new account'], true)) {
            $this->startSignup($contact);
        } elseif (in_array($word, ['2', 'login', 'log in', 'existing', 'have account'], true)) {
            $this->startLogin($contact);
        } else {
            // Anything else means they moved on: drop the offer and let the normal assistant answer.
            $this->setState($contact, null, []);

            return false;
        }

        return true;
    }

    private function startSignup(WhatsAppContact $contact): void
    {
        $this->setState($contact, 'signup_name', []);
        $this->say($contact, "Let's create your account. 🙌 What is your name?");
    }

    private function signupName(WhatsAppContact $contact, string $text): bool
    {
        $name = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80 || preg_match('/\d{4,}/', $name)) {
            $this->say($contact, 'Please type your name (for example "Asha Verma").');

            return true;
        }

        return $this->sendCode($contact, $contact->phone, 'signup', ['name' => $name]);
    }

    private function signupOtp(WhatsAppContact $contact, string $word): bool
    {
        $ctx = $contact->bot_context['login'] ?? [];
        if ($this->resend($contact, $word, $ctx['phone'] ?? $contact->phone, 'signup', ['name' => $ctx['name'] ?? ''])) {
            return true;
        }
        if (! preg_match('/^\d{4,8}$/', $word)) {
            $this->say($contact, 'Please type the 6-digit code from the SMS, or reply RESEND for a new one.');

            return true;
        }

        $phone = $ctx['phone'] ?? $contact->phone;
        $verified = $this->actions->verifyLoginOtp($phone, $word, 'signup');
        if (! $verified['success']) {
            return $this->wrongCode($contact, $verified['message']);
        }

        $token = (string) ($verified['data']['verified_phone_token'] ?? '');
        $registered = $this->actions->registerCustomer((string) ($ctx['name'] ?? 'Customer'), $phone, $token);
        if (! $registered['success']) {
            $this->setState($contact, null, []);
            $this->say($contact, '⚠️ ' . $registered['message'] . "\nReply MENU to start again.");

            return true;
        }

        return $this->finish($contact, (int) ($registered['data']['user']['id'] ?? 0), $registered['data'], true);
    }

    private function startLogin(WhatsAppContact $contact): void
    {
        $this->setState($contact, 'login_phone', []);
        $this->say($contact, "Which mobile number is your account registered with?\nReply SAME to use this WhatsApp number (" . $contact->phone . '), or type the number.');
    }

    private function loginPhone(WhatsAppContact $contact, string $text, string $word): bool
    {
        $phone = in_array($word, ['same', 'this', 'this number'], true) ? $contact->phone : null;
        if (! $phone) {
            $digits = preg_replace('/\D+/', '', $text);
            if (strlen($digits) < 10 || strlen($digits) > 15) {
                $this->say($contact, 'Please type a valid mobile number (10 digits), or reply SAME.');

                return true;
            }
            $phone = $this->whatsapp->normalize($text);
        }

        return $this->sendCode($contact, $phone, 'login', []);
    }

    private function loginOtp(WhatsAppContact $contact, string $word): bool
    {
        $ctx = $contact->bot_context['login'] ?? [];
        $phone = $ctx['phone'] ?? $contact->phone;
        if ($this->resend($contact, $word, $phone, 'login', [])) {
            return true;
        }
        if (! preg_match('/^\d{4,8}$/', $word)) {
            $this->say($contact, 'Please type the 6-digit code from the SMS, or reply RESEND for a new one.');

            return true;
        }

        $verified = $this->actions->verifyLoginOtp($phone, $word, 'login');
        if (! $verified['success']) {
            return $this->wrongCode($contact, $verified['message']);
        }

        return $this->finish($contact, (int) ($verified['data']['user']['id'] ?? 0), $verified['data'], false);
    }

    // ---- Shared ------------------------------------------------------------------------------------------------

    /** Sends the SMS code through the app's login and moves to the "enter the code" step. */
    private function sendCode(WhatsAppContact $contact, string $phone, string $flow, array $extra): bool
    {
        $key = 'wa_login_codes:' . preg_replace('/\D+/', '', $contact->phone);
        if ((int) Cache::get($key, 0) >= self::MAX_CODES_PER_HOUR) {
            $this->setState($contact, null, []);
            $this->say($contact, 'You have requested too many codes. Please try again in an hour, or log in in the app. Reply MENU for other options.');

            return true;
        }

        $result = $this->actions->sendLoginOtp($phone, $flow);
        if (! $result['success']) {
            // The number already has an account while signing up, or none exists while logging in: guide them.
            if (($result['code'] ?? null) === 'ACCOUNT_EXISTS') {
                $this->setState($contact, 'login_phone', []);
                $this->say($contact, "An account already exists for {$phone}. 👍 Reply SAME to log in to it with an OTP, or type another number.");

                return true;
            }
            if (($result['code'] ?? null) === 'ACCOUNT_NOT_FOUND') {
                $this->setState($contact, 'login_choose', []);
                $this->say($contact, "There is no account with {$phone}. Reply 1 to create a new account, or 2 to try another number.");

                return true;
            }
            $this->setState($contact, null, []);
            $this->say($contact, '⚠️ ' . $result['message'] . "\nReply MENU for other options.");

            return true;
        }
        if (($result['data']['otp_flow'] ?? null) === 'widget') {
            $this->setState($contact, null, []);
            $this->say($contact, "OTP login isn't available in chat with the current SMS setup. Please log in in the app. Reply MENU for other options.");

            return true;
        }

        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addHour());
        $this->setState($contact, $flow === 'signup' ? 'signup_otp' : 'login_otp', ['login' => $extra + ['phone' => $phone, 'wrong' => 0]]);
        $this->say($contact, "🔐 We sent a 6-digit code by SMS to {$phone}. Please type it here.\nDidn't get it? Reply RESEND. Reply MENU to cancel.");

        return true;
    }

    private function resend(WhatsAppContact $contact, string $word, string $phone, string $flow, array $extra): bool
    {
        if (! in_array($word, ['resend', 'resend otp', 'again'], true)) {
            return false;
        }

        return $this->sendCode($contact, $phone, $flow, $extra);
    }

    private function wrongCode(WhatsAppContact $contact, string $message): bool
    {
        $ctx = $contact->bot_context['login'] ?? [];
        $ctx['wrong'] = ((int) ($ctx['wrong'] ?? 0)) + 1;
        if ($ctx['wrong'] >= self::MAX_WRONG_CODES) {
            $this->setState($contact, null, []);
            $this->say($contact, 'Too many wrong codes, so I stopped. Reply MENU and try again later.');

            return true;
        }
        $this->setState($contact, $contact->bot_state, ['login' => $ctx]);
        $this->say($contact, '⚠️ ' . $message . ' (' . (self::MAX_WRONG_CODES - $ctx['wrong']) . " tries left). Type the code again, or reply RESEND.");

        return true;
    }

    /** The code was right: link this WhatsApp number to the account. */
    private function finish(WhatsAppContact $contact, int $userId, array $data, bool $created): bool
    {
        $this->discardAppToken($data['token'] ?? null);
        $user = $userId ? User::find($userId) : null;
        if (! $user) {
            $this->setState($contact, null, []);
            $this->say($contact, 'Something went wrong while logging you in. Please try again, or use the app.');

            return true;
        }

        $contact->forceFill(['user_id' => $user->id, 'profile_name' => $contact->profile_name ?: $user->name])->save();
        $this->setState($contact, null, []);

        $cart = $contact->bot_context['cart'] ?? null;
        $this->say($contact, ($created ? "🎉 Welcome, {$user->name}! Your account is ready." : "✅ Logged in as {$user->name}.")
            . (is_array($cart) && ! empty($cart['items']) ? "\nYour cart is saved. Reply CHECKOUT to place your order." : "\nYou can now order food, track orders and manage your account here. Reply MENU to start."));

        return true;
    }

    /** The app's login makes a mobile-app token; the chat does not use it, so it is deleted at once. */
    private function discardAppToken(?string $plainToken): void
    {
        try {
            if ($plainToken && str_contains($plainToken, '|')) {
                PersonalAccessToken::find((int) explode('|', $plainToken)[0])?->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('Could not discard the WhatsApp login token.', ['error' => $e->getMessage()]);
        }
    }

    private function sameNumber(?string $a, ?string $b): bool
    {
        return substr(preg_replace('/\D+/', '', (string) $a), -10) === substr(preg_replace('/\D+/', '', (string) $b), -10) && $a !== null;
    }

    /** Keeps the cart and delivery location while the login conversation runs. */
    private function setState(WhatsAppContact $contact, ?string $state, array $context): void
    {
        $old = $contact->bot_context ?? [];
        $keep = array_intersect_key($old, array_flip(['mode', 'cart', 'loc']));
        $new = $keep + $context;
        $contact->forceFill(['bot_state' => $state, 'bot_context' => $new ?: null])->save();
    }

    private function say(WhatsAppContact $contact, string $text): void
    {
        try {
            $this->whatsapp->sendText($contact->phone, $text, 'bot');
        } catch (\Throwable $e) {
            Log::warning('WhatsApp login reply failed.', ['phone' => $contact->phone, 'error' => $e->getMessage()]);
        }
    }
}
