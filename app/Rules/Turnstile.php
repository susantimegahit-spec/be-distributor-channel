<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Turnstile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            $fail('The Turnstile verification is required.');
            return;
        }

        $secret = config('services.turnstile.secret');

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

            if (!$response->successful() || !$response->json('success')) {
                \Illuminate\Support\Facades\Log::error('Turnstile verification failed', [
                    'secret' => substr($secret, 0, 6) . '...',
                    'response_body' => $response->json(),
                    'status' => $response->status()
                ]);
                $fail('Verification failed. Please try again.');
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Turnstile exception', ['error' => $e->getMessage()]);
            $fail('Unable to verify Turnstile.');
        }
    }
}
