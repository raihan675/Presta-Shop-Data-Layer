<?php
/**
 * Hashes PII compliant with Google Ads Enhanced Conversions and Meta CAPI standards (SHA-256).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class UserDataHasher
{
    public static function hashCustomerData(Customer $customer, Address $address)
    {
        if (!Validate::isLoadedObject($customer)) {
            return [];
        }

        $rawEmail = strtolower(trim((string)$customer->email));
        $rawPhone = preg_replace('/[^0-9]/', '', $address->phone ? (string)$address->phone : (string)$address->phone_mobile);

        $userData = [
            'email' => !empty($rawEmail) ? hash('sha256', $rawEmail) : null,
            'phone_number' => !empty($rawPhone) ? hash('sha256', $rawPhone) : null,
            'address' => [
                'first_name' => !empty($address->firstname) ? hash('sha256', strtolower(trim((string)$address->firstname))) : null,
                'last_name' => !empty($address->lastname) ? hash('sha256', strtolower(trim((string)$address->lastname))) : null,
                'postal_code' => !empty($address->postcode) ? trim((string)$address->postcode) : null,
                'city' => !empty($address->city) ? trim((string)$address->city) : null,
                'country' => !empty($address->id_country) ? Country::getIsoById((int)$address->id_country) : null,
            ]
        ];

        return array_filter($userData);
    }
}
