<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait HasCustomerCodeResolver
{
    /**
     * Resolve and validate customer codes from user constraints and request filters.
     *
     * @param Request $request
     * @return array
     */
    protected function resolveCustomerCodes(Request $request): array
    {
        $user = $request->user();
        $allowedCodes = null;

        if ($user && $user->code_customer) {
            $allowedCodes = array_filter(array_map('trim', explode(',', $user->code_customer)));
        }

        $input = $request->get('customer_codes') ?? $request->get('customer_code');
        $requestedCodes = [];
        if ($input) {
            if (is_array($input)) {
                $requestedCodes = $input;
            } else {
                $requestedCodes = array_filter(array_map('trim', explode(',', $input)));
            }
        }

        if ($allowedCodes !== null) {
            if (!empty($requestedCodes)) {
                $intersect = array_values(array_intersect($requestedCodes, $allowedCodes));
                return empty($intersect) ? $allowedCodes : $intersect;
            }
            return $allowedCodes;
        }

        return $requestedCodes;
    }
}
