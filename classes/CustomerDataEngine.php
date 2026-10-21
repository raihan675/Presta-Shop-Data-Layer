<?php
/**
 * CustomerDataEngine: Authoritative extraction and normalization of customer data.
 * Supplies raw (unhashed) PII formatted for Google Tag Manager & Meta CAPI.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomerDataEngine
{
    /**
     * Extracts and normalizes customer data from Customer and Address records.
     * Returns raw normalized user data without hashing.
     *
     * @param Customer $customer
     * @param Address|null $address
     * @return array
     */
    public static function buildCustomerPayload(Customer $customer, Address $address = null)
    {
        if (!Validate::isLoadedObject($customer)) {
            return [];
        }

        // 1. Raw Sanitized Extraction & Normalization
        $rawEmail = strtolower(trim((string)$customer->email));
        $normalizedEmail = self::normalizeEmail($rawEmail);
        
        $rawPhone = '';
        if ($address && Validate::isLoadedObject($address)) {
            $rawPhone = !empty($address->phone_mobile) ? (string)$address->phone_mobile : (string)$address->phone;
        }
        $normalizedPhone = self::normalizePhone($rawPhone);

        $firstName = ($address && !empty($address->firstname)) ? trim((string)$address->firstname) : trim((string)$customer->firstname);
        $lastName = ($address && !empty($address->lastname)) ? trim((string)$address->lastname) : trim((string)$customer->lastname);
        $street = ($address && !empty($address->address1)) ? trim((string)$address->address1) : '';
        $city = ($address && !empty($address->city)) ? trim((string)$address->city) : '';
        $postcode = ($address && !empty($address->postcode)) ? trim((string)$address->postcode) : '';
        $countryCode = ($address && !empty($address->id_country)) ? strtoupper(Country::getIsoById((int)$address->id_country)) : '';

        // 2. Unhashed / Raw Customer Payload
        $addressData = array_filter([
            'first_name' => !empty($firstName) ? $firstName : null,
            'last_name' => !empty($lastName) ? $lastName : null,
            'street' => !empty($street) ? $street : null,
            'city' => !empty($city) ? $city : null,
            'postal_code' => !empty($postcode) ? $postcode : null,
            'country' => !empty($countryCode) ? $countryCode : null,
        ]);

        $userData = array_filter([
            'email' => !empty($normalizedEmail) ? $normalizedEmail : null,
            'phone_number' => !empty($normalizedPhone) ? $normalizedPhone : null,
            'address' => !empty($addressData) ? $addressData : null,
        ]);

        return $userData;
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
