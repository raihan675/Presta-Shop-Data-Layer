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
        $countryCode = ($address && !empty($address->id_country) && class_exists('Country')) ? strtoupper((string)Country::getIsoById((int)$address->id_country)) : '';

        $stateCode = '';
        if ($address && !empty($address->id_state) && class_exists('State')) {
            $stateObj = new State((int)$address->id_state);
            if (Validate::isLoadedObject($stateObj) && !empty($stateObj->iso_code)) {
                $stateCode = strtoupper(trim((string)$stateObj->iso_code));
            }
        }

        // 2. Unhashed / Raw Customer Payload
        $addressData = array_filter([
            'first_name' => !empty($firstName) ? $firstName : null,
            'last_name' => !empty($lastName) ? $lastName : null,
            'street' => !empty($street) ? $street : null,
            'city' => !empty($city) ? $city : null,
            'state' => !empty($stateCode) ? $stateCode : null,
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

    /**
     * Extracts, normalizes, and SHA-256 hashes customer data specifically for Pinterest
     * Enhanced / Advanced Matching (em, ph, fn, ln, ct, st, zp, country).
     *
     * @param Customer $customer
     * @param Address|null $address
     * @return array
     */
    public static function buildPinterestUserData(Customer $customer, Address $address = null)
    {
        if (!Validate::isLoadedObject($customer)) {
            return [];
        }

        $rawEmail = (string)$customer->email;
        $rawPhone = '';
        if ($address && Validate::isLoadedObject($address)) {
            $rawPhone = !empty($address->phone_mobile) ? (string)$address->phone_mobile : (string)$address->phone;
        }

        $firstName = ($address && !empty($address->firstname)) ? (string)$address->firstname : (string)$customer->firstname;
        $lastName = ($address && !empty($address->lastname)) ? (string)$address->lastname : (string)$customer->lastname;
        $city = ($address && !empty($address->city)) ? (string)$address->city : '';
        $postcode = ($address && !empty($address->postcode)) ? (string)$address->postcode : '';

        $stateCode = '';
        if ($address && !empty($address->id_state) && class_exists('State')) {
            $stateObj = new State((int)$address->id_state);
            if (Validate::isLoadedObject($stateObj) && !empty($stateObj->iso_code)) {
                $stateCode = (string)$stateObj->iso_code;
            }
        }

        $countryCode = ($address && !empty($address->id_country) && class_exists('Country')) ? (string)Country::getIsoById((int)$address->id_country) : '';

        return self::hashPinterestUserData([
            'email' => $rawEmail,
            'phone_number' => $rawPhone,
            'address' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'city' => $city,
                'state' => $stateCode,
                'postal_code' => $postcode,
                'country' => $countryCode,
            ]
        ]);
    }

    /**
     * Normalizes and SHA-256 hashes arbitrary user data into Pinterest Advanced Matching schema.
     *
     * @param array $userData
     * @return array
     */
    public static function hashPinterestUserData(array $userData)
    {
        $result = [];

        // em (Email)
        $email = !empty($userData['email']) ? $userData['email'] : '';
        if ($hashed = self::hashPinterestField($email, 'em')) {
            $result['em'] = $hashed;
        }

        // ph (Phone: digits only)
        $phone = !empty($userData['phone_number']) ? $userData['phone_number'] : (!empty($userData['phone']) ? $userData['phone'] : '');
        if ($hashed = self::hashPinterestField($phone, 'ph')) {
            $result['ph'] = $hashed;
        }

        $addr = !empty($userData['address']) && is_array($userData['address']) ? $userData['address'] : $userData;

        // fn (First Name)
        $fn = !empty($addr['first_name']) ? $addr['first_name'] : (!empty($userData['first_name']) ? $userData['first_name'] : '');
        if ($hashed = self::hashPinterestField($fn, 'fn')) {
            $result['fn'] = $hashed;
        }

        // ln (Last Name)
        $ln = !empty($addr['last_name']) ? $addr['last_name'] : (!empty($userData['last_name']) ? $userData['last_name'] : '');
        if ($hashed = self::hashPinterestField($ln, 'ln')) {
            $result['ln'] = $hashed;
        }

        // ct (City)
        $ct = !empty($addr['city']) ? $addr['city'] : '';
        if ($hashed = self::hashPinterestField($ct, 'ct')) {
            $result['ct'] = $hashed;
        }

        // st (State)
        $st = !empty($addr['state']) ? $addr['state'] : '';
        if ($hashed = self::hashPinterestField($st, 'st')) {
            $result['st'] = $hashed;
        }

        // zp (Postal Code)
        $zp = !empty($addr['postal_code']) ? $addr['postal_code'] : (!empty($addr['postcode']) ? $addr['postcode'] : '');
        if ($hashed = self::hashPinterestField($zp, 'zp')) {
            $result['zp'] = $hashed;
        }

        // country (ISO Code)
        $country = !empty($addr['country']) ? $addr['country'] : '';
        if ($hashed = self::hashPinterestField($country, 'country')) {
            $result['country'] = $hashed;
        }

        return $result;
    }

    /**
     * Normalizes a specific user data field per Pinterest specifications and returns SHA-256 hash.
     *
     * @param string $val
     * @param string $field
     * @return string
     */
    public static function hashPinterestField($val, $field)
    {
        if (empty($val) || !is_string($val)) {
            return '';
        }

        $clean = trim($val);
        if ($clean === '') {
            return '';
        }

        // Check if value is already a 64-character lowercase hex string
        if (preg_match('/^[a-f0-9]{64}$/i', $clean)) {
            return strtolower($clean);
        }

        switch ($field) {
            case 'em':
                $clean = strtolower($clean);
                if (!filter_var($clean, FILTER_VALIDATE_EMAIL)) {
                    return '';
                }
                break;

            case 'ph':
                // Digits only
                $clean = preg_replace('/[^0-9]/', '', $clean);
                if (empty($clean)) {
                    return '';
                }
                break;

            case 'ct':
                // Lowercase, remove spaces and punctuation
                $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $clean));
                if (empty($clean)) {
                    return '';
                }
                break;

            case 'st':
                // Lowercase, 2-letter code if available
                $clean = strtolower(trim($clean));
                if (empty($clean)) {
                    return '';
                }
                break;

            case 'zp':
                // Lowercase, spaces removed
                $clean = strtolower(preg_replace('/\s+/', '', $clean));
                if (empty($clean)) {
                    return '';
                }
                break;

            case 'country':
                // 2-character ISO country code in lowercase
                $clean = strtolower(trim($clean));
                if (empty($clean)) {
                    return '';
                }
                break;

            case 'fn':
            case 'ln':
            default:
                $clean = strtolower($clean);
                break;
        }

        return hash('sha256', $clean);
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
