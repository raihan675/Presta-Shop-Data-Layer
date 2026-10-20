<?php
/**
 * CustomerDataEngine: Authoritative extraction, normalization,
 * and SHA-256 hashing for Enhanced Conversions, Meta CAPI, and TikTok Events API.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomerDataEngine
{
    /**
     * Extracts and normalizes customer data from Customer and Address records.
     */
    public static function buildCustomerPayload(Customer $customer, Address $address = null)
    {
        if (!Validate::isLoadedObject($customer)) {
            return [];
        }

        // 1. Raw Sanitized Extraction
        $rawEmail = strtolower(trim((string)$customer->email));
        
        $rawPhone = '';
        if ($address && Validate::isLoadedObject($address)) {
            $rawPhone = !empty($address->phone_mobile) ? (string)$address->phone_mobile : (string)$address->phone;
        }

        // 2. Normalization
        $normalizedEmail = self::normalizeEmail($rawEmail);
        $normalizedPhone = self::normalizePhone($rawPhone);
        $firstName = $address ? strtolower(trim((string)$address->firstname)) : strtolower(trim((string)$customer->firstname));
        $lastName = $address ? strtolower(trim((string)$address->lastname)) : strtolower(trim((string)$customer->lastname));
        $city = $address ? strtolower(trim((string)$address->city)) : '';
        $postcode = $address ? strtoupper(trim((string)$address->postcode)) : '';
        $countryCode = $address ? strtoupper(Country::getIsoById((int)$address->id_country)) : '';

        // 3. Cryptographic Hashes (SHA-256)
        $userData = [
            'email' => !empty($normalizedEmail) ? hash('sha256', $normalizedEmail) : null,
            'phone_number' => !empty($normalizedPhone) ? hash('sha256', $normalizedPhone) : null,
            'address' => [
                'first_name' => !empty($firstName) ? hash('sha256', $firstName) : null,
                'last_name' => !empty($lastName) ? hash('sha256', $lastName) : null,
                'street' => ($address && !empty($address->address1)) ? hash('sha256', strtolower(trim((string)$address->address1))) : null,
                'city' => !empty($city) ? $city : null,
                'postal_code' => !empty($postcode) ? $postcode : null,
                'country' => !empty($countryCode) ? $countryCode : null,
            ],
            'meta_external_id' => hash('sha256', (string)$customer->id),
        ];

        return array_filter($userData);
    }

    public static function normalizeEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        return strtolower(trim((string)$email));
    }

    public static function normalizePhone($phone)
    {
        if (empty($phone)) return '';
        // Remove non-numeric characters except +
        $cleaned = preg_replace('/[^\+0-9]/', '', (string)$phone);
        if (strpos($cleaned, '00') === 0) {
            $cleaned = '+' . substr($cleaned, 2);
        }
        return $cleaned;
    }
}
