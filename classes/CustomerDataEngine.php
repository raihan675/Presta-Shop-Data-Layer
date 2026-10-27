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
     * Extracts, normalizes, and SHA-256 hashes customer data specifically for Meta
     * Advanced Matching (em, ph, fn, ln, ct, st, zp, country).
     *
     * @param Customer $customer
     * @param Address|null $address
     * @return array
     */
    public static function buildMetaUserData(Customer $customer, Address $address = null)
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

        return self::hashMetaUserData([
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
        ], $address);
    }

    /**
     * Normalizes and SHA-256 hashes arbitrary user data into Meta Advanced Matching schema.
     * Strictly omits external_id and cookies per privacy specification.
     *
     * @param array $userData
     * @param Address|null $address
     * @return array
     */
    public static function hashMetaUserData(array $userData, Address $address = null)
    {
        $result = [];

        // em (Email)
        $email = !empty($userData['email']) ? $userData['email'] : '';
        if ($hashed = self::hashMetaField($email, 'em')) {
            $result['em'] = $hashed;
        }

        // ph (Phone: digits only with country calling code, no + sign)
        $phone = !empty($userData['phone_number']) ? $userData['phone_number'] : (!empty($userData['phone']) ? $userData['phone'] : '');
        if (!empty($phone)) {
            $cleanPhone = trim((string)$phone);
            if (preg_match('/^[a-f0-9]{64}$/i', $cleanPhone)) {
                $result['ph'] = strtolower($cleanPhone);
            } else {
                $metaPhone = self::normalizeMetaPhone($cleanPhone, $address);
                if ($metaPhone !== '') {
                    $result['ph'] = hash('sha256', $metaPhone);
                }
            }
        }

        $addr = !empty($userData['address']) && is_array($userData['address']) ? $userData['address'] : $userData;

        // fn (First Name)
        $fn = !empty($addr['first_name']) ? $addr['first_name'] : (!empty($userData['first_name']) ? $userData['first_name'] : '');
        if ($hashed = self::hashMetaField($fn, 'fn')) {
            $result['fn'] = $hashed;
        }

        // ln (Last Name)
        $ln = !empty($addr['last_name']) ? $addr['last_name'] : (!empty($userData['last_name']) ? $userData['last_name'] : '');
        if ($hashed = self::hashMetaField($ln, 'ln')) {
            $result['ln'] = $hashed;
        }

        // ct (City)
        $ct = !empty($addr['city']) ? $addr['city'] : '';
        if ($hashed = self::hashMetaField($ct, 'ct')) {
            $result['ct'] = $hashed;
        }

        // st (State)
        $st = !empty($addr['state']) ? $addr['state'] : '';
        if ($hashed = self::hashMetaField($st, 'st')) {
            $result['st'] = $hashed;
        }

        // zp (Postal Code)
        $zp = !empty($addr['postal_code']) ? $addr['postal_code'] : (!empty($addr['postcode']) ? $addr['postcode'] : '');
        if ($hashed = self::hashMetaField($zp, 'zp')) {
            $result['zp'] = $hashed;
        }

        // country (ISO Code)
        $country = !empty($addr['country']) ? $addr['country'] : '';
        if ($hashed = self::hashMetaField($country, 'country')) {
            $result['country'] = $hashed;
        }

        return $result;
    }

    /**
     * Normalizes a specific user data field per Meta specifications and returns SHA-256 hash.
     *
     * @param string $val
     * @param string $field
     * @return string
     */
    public static function hashMetaField($val, $field)
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

            case 'ct':
                // Lowercase, remove spaces and punctuation
                $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $clean));
                if (empty($clean)) {
                    return '';
                }
                break;

            case 'st':
                // Lowercase 2-letter state code
                $clean = strtolower(trim($clean));
                if (empty($clean)) {
                    return '';
                }
                break;

            case 'zp':
                // Lowercase, spaces and dashes removed
                $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $clean));
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
                // Lowercase, strip punctuation and excessive whitespace
                $clean = strtolower(trim(preg_replace('/[[:punct:]]/u', '', $clean)));
                if (empty($clean)) {
                    return '';
                }
                break;
        }

        return hash('sha256', $clean);
    }

    /**
     * Normalizes phone number per Meta Conversions API specifications:
     * Digits only, including country calling code, strictly WITHOUT the '+' prefix.
     *
     * @param string $phone
     * @param Address|null $address
     * @return string
     */
    public static function normalizeMetaPhone($phone, Address $address = null)
    {
        if (empty($phone)) {
            return '';
        }

        $e164 = self::normalizeE164Phone($phone, $address);
        if (empty($e164)) {
            return '';
        }

        return preg_replace('/[^0-9]/', '', $e164);
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

    /**
     * Extracts, normalizes, and SHA-256 hashes customer data for TikTok Advanced Matching
     * (email, phone_number).
     *
     * @param Customer $customer
     * @param Address|null $address
     * @return array
     */
    public static function buildTikTokUserData(Customer $customer, Address $address = null)
    {
        if (!Validate::isLoadedObject($customer)) {
            return [];
        }

        $rawEmail = (string)$customer->email;
        $rawPhone = '';
        if ($address && Validate::isLoadedObject($address)) {
            $rawPhone = !empty($address->phone_mobile) ? (string)$address->phone_mobile : (string)$address->phone;
        }

        return self::hashTikTokUserData([
            'email' => $rawEmail,
            'phone_number' => $rawPhone,
        ], $address);
    }

    /**
     * Normalizes and SHA-256 hashes arbitrary user data into TikTok Advanced Matching schema.
     *
     * @param array $userData
     * @param Address|null $address
     * @return array
     */
    public static function hashTikTokUserData(array $userData, Address $address = null)
    {
        $result = [];

        // 1. Email: lowercase, trimmed, sha256
        $email = !empty($userData['email']) ? trim((string)$userData['email']) : '';
        if ($email !== '') {
            if (preg_match('/^[a-f0-9]{64}$/i', $email)) {
                $result['email'] = strtolower($email);
            } else {
                $cleanEmail = strtolower($email);
                if (filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                    $result['email'] = hash('sha256', $cleanEmail);
                }
            }
        }

        // 2. Phone Number: E.164 (+[country code][digits]), sha256
        $phone = !empty($userData['phone_number']) ? (string)$userData['phone_number'] : (!empty($userData['phone']) ? (string)$userData['phone'] : '');
        if ($phone !== '') {
            $cleanPhone = trim($phone);
            if (preg_match('/^[a-f0-9]{64}$/i', $cleanPhone)) {
                $result['phone_number'] = strtolower($cleanPhone);
            } else {
                $normalizedPhone = self::normalizeE164Phone($cleanPhone, $address);
                if ($normalizedPhone !== '') {
                    $result['phone_number'] = hash('sha256', $normalizedPhone);
                }
            }
        }

        return $result;
    }

    /**
     * Builds Microsoft Advertising Enhanced Conversions (pid) user data.
     *
     * @param Customer $customer
     * @param Address|null $address
     * @return array
     */
    public static function buildMicrosoftUserData(Customer $customer, Address $address = null)
    {
        if (!Validate::isLoadedObject($customer)) {
            return [];
        }

        $rawEmail = (string)$customer->email;
        $rawPhone = '';
        if ($address && Validate::isLoadedObject($address)) {
            $rawPhone = !empty($address->phone_mobile) ? (string)$address->phone_mobile : (string)$address->phone;
        }

        return self::hashMicrosoftUserData([
            'email' => $rawEmail,
            'phone_number' => $rawPhone,
        ], $address);
    }

    /**
     * Normalizes and SHA-256 hashes user data into Microsoft Advertising pid schema.
     *
     * @param array $userData
     * @param Address|null $address
     * @return array
     */
    public static function hashMicrosoftUserData(array $userData, Address $address = null)
    {
        $pid = [];

        // 1. Email: lowercase, remove accents, strip periods and plus aliases, sha256
        $email = !empty($userData['email']) ? trim((string)$userData['email']) : '';
        if ($email !== '') {
            if (preg_match('/^[a-f0-9]{64}$/i', $email)) {
                $hashedEmail = strtolower($email);
            } else {
                $cleanEmail = strtolower($email);
                if (filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                    // Microsoft normalization: remove subaddressing '+...' and periods before @
                    $parts = explode('@', $cleanEmail, 2);
                    if (count($parts) === 2) {
                        $local = preg_replace('/\+.*$/', '', $parts[0]);
                        $local = str_replace('.', '', $local);
                        $cleanEmail = $local . '@' . $parts[1];
                    }
                    $hashedEmail = hash('sha256', $cleanEmail);
                } else {
                    $hashedEmail = '';
                }
            }
            if (!empty($hashedEmail)) {
                $pid['email'] = $hashedEmail;
                $pid['em'] = $hashedEmail;
            }
        }

        // 2. Phone: E.164 (+[country code][digits]), sha256
        $phone = !empty($userData['phone_number']) ? (string)$userData['phone_number'] : (!empty($userData['phone']) ? (string)$userData['phone'] : '');
        if ($phone !== '') {
            $cleanPhone = trim($phone);
            if (preg_match('/^[a-f0-9]{64}$/i', $cleanPhone)) {
                $hashedPhone = strtolower($cleanPhone);
            } else {
                $normalizedPhone = self::normalizeE164Phone($cleanPhone, $address);
                if ($normalizedPhone !== '') {
                    $hashedPhone = hash('sha256', $normalizedPhone);
                } else {
                    $hashedPhone = '';
                }
            }
            if (!empty($hashedPhone)) {
                $pid['phone_number'] = $hashedPhone;
                $pid['ph'] = $hashedPhone;
            }
        }

        return !empty($pid) ? ['pid' => $pid] : [];
    }

    /**
     * Extracts, normalizes, and formats customer data specifically for OpenAI Ads
     * Advanced Matching (email_sha256, phone_number_sha256, external_id_sha256,
     * first_name_sha256, last_name_sha256, country, city, region, postal_code).
     *
     * @param Customer $customer
     * @param Address|null $address
     * @return array
     */
    public static function buildOpenAiUserData(Customer $customer, Address $address = null)
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
        $city = ($address && !empty($address->city)) ? trim((string)$address->city) : '';
        $postcode = ($address && !empty($address->postcode)) ? trim((string)$address->postcode) : '';

        $stateName = '';
        if ($address && !empty($address->id_state) && class_exists('State')) {
            $stateObj = new State((int)$address->id_state);
            if (Validate::isLoadedObject($stateObj) && !empty($stateObj->name)) {
                $stateName = trim((string)$stateObj->name);
            }
        }

        $countryIso = '';
        if ($address && !empty($address->id_country) && class_exists('Country')) {
            $countryIso = strtoupper(trim((string)Country::getIsoById((int)$address->id_country)));
        }

        return self::hashOpenAiUserData([
            'email' => $rawEmail,
            'phone' => $rawPhone,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'external_id' => (string)$customer->id,
            'city' => $city,
            'region' => $stateName,
            'postal_code' => $postcode,
            'country' => $countryIso,
        ], $address);
    }

    /**
     * Hashes and formats normalized customer dictionary for OpenAI Ads Advanced Matching.
     *
     * @param array $userData
     * @param Address|null $address
     * @return array
     */
    public static function hashOpenAiUserData(array $userData, Address $address = null)
    {
        $openAiUser = [];

        // 1. Email: lowercase, trimmed, UTF-8, sha256
        $email = !empty($userData['email']) ? trim((string)$userData['email']) : '';
        if ($email !== '') {
            if (preg_match('/^[a-f0-9]{64}$/i', $email)) {
                $openAiUser['email_sha256'] = strtolower($email);
            } else {
                $cleanEmail = strtolower($email);
                if (filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                    $openAiUser['email_sha256'] = hash('sha256', $cleanEmail);
                }
            }
        }

        // 2. Phone: country code + digits only, without leading '+' and without leading zeros, sha256
        $phone = !empty($userData['phone_number']) ? (string)$userData['phone_number'] : (!empty($userData['phone']) ? (string)$userData['phone'] : '');
        if ($phone !== '') {
            $cleanPhone = trim($phone);
            if (preg_match('/^[a-f0-9]{64}$/i', $cleanPhone)) {
                $openAiUser['phone_number_sha256'] = strtolower($cleanPhone);
            } else {
                $metaPhone = self::normalizeMetaPhone($cleanPhone, $address);
                if ($metaPhone !== '') {
                    $digits = ltrim($metaPhone, '0');
                    if (strlen($digits) >= 6) {
                        $openAiUser['phone_number_sha256'] = hash('sha256', $digits);
                    }
                }
            }
        }

        // 3. External ID: trimmed, sha256
        $extId = !empty($userData['external_id']) ? trim((string)$userData['external_id']) : '';
        if ($extId !== '') {
            if (preg_match('/^[a-f0-9]{64}$/i', $extId)) {
                $openAiUser['external_id_sha256'] = strtolower($extId);
            } else {
                $openAiUser['external_id_sha256'] = hash('sha256', $extId);
            }
        }

        // 4. First Name: lowercase, strip whitespace & ASCII punctuation, preserve accents, sha256
        $addr = !empty($userData['address']) && is_array($userData['address']) ? $userData['address'] : $userData;
        $fn = !empty($addr['first_name']) ? (string)$addr['first_name'] : (!empty($userData['first_name']) ? (string)$userData['first_name'] : '');
        if ($fn !== '') {
            if (preg_match('/^[a-f0-9]{64}$/i', $fn)) {
                $openAiUser['first_name_sha256'] = strtolower($fn);
            } else {
                $cleanFn = mb_strtolower(trim($fn), 'UTF-8');
                $cleanFn = preg_replace('/[\s\p{P}]+/u', '', $cleanFn);
                if ($cleanFn !== '') {
                    $openAiUser['first_name_sha256'] = hash('sha256', $cleanFn);
                }
            }
        }

        // 5. Last Name: lowercase, strip whitespace & ASCII punctuation, preserve accents, sha256
        $ln = !empty($addr['last_name']) ? (string)$addr['last_name'] : (!empty($userData['last_name']) ? (string)$userData['last_name'] : '');
        if ($ln !== '') {
            if (preg_match('/^[a-f0-9]{64}$/i', $ln)) {
                $openAiUser['last_name_sha256'] = strtolower($ln);
            } else {
                $cleanLn = mb_strtolower(trim($ln), 'UTF-8');
                $cleanLn = preg_replace('/[\s\p{P}]+/u', '', $cleanLn);
                if ($cleanLn !== '') {
                    $openAiUser['last_name_sha256'] = hash('sha256', $cleanLn);
                }
            }
        }

        // 6. Location fields: unhashed strings
        $country = !empty($addr['country']) ? (string)$addr['country'] : (!empty($userData['country']) ? (string)$userData['country'] : '');
        if (!empty($country)) {
            $openAiUser['country'] = strtoupper(trim($country));
        }
        $city = !empty($addr['city']) ? (string)$addr['city'] : (!empty($userData['city']) ? (string)$userData['city'] : '');
        if (!empty($city)) {
            $openAiUser['city'] = trim($city);
        }
        $region = !empty($addr['region']) ? (string)$addr['region'] : (!empty($addr['state']) ? (string)$addr['state'] : (!empty($userData['region']) ? (string)$userData['region'] : ''));
        if (!empty($region)) {
            $openAiUser['region'] = trim($region);
        }
        $postalCode = !empty($addr['postal_code']) ? (string)$addr['postal_code'] : (!empty($addr['postcode']) ? (string)$addr['postcode'] : (!empty($userData['postal_code']) ? (string)$userData['postal_code'] : ''));
        if (!empty($postalCode)) {
            $openAiUser['postal_code'] = trim($postalCode);
        }

        return $openAiUser;
    }

    /**
     * Normalizes phone number to strict E.164 format (+[country code][digits]).
     *
     * @param string $phone
     * @param Address|null $address
     * @return string
     */
    public static function normalizeE164Phone($phone, Address $address = null)
    {
        if (empty($phone)) {
            return '';
        }

        $cleaned = preg_replace('/[^\+0-9]/', '', (string)$phone);
        if (strpos($cleaned, '00') === 0) {
            $cleaned = '+' . substr($cleaned, 2);
        }

        if (strpos($cleaned, '+') !== 0 && $cleaned !== '') {
            if ($address && !empty($address->id_country) && class_exists('Country')) {
                try {
                    $countryObj = new Country((int)$address->id_country);
                    if (Validate::isLoadedObject($countryObj) && !empty($countryObj->call_prefix)) {
                        $cleaned = '+' . (int)$countryObj->call_prefix . ltrim($cleaned, '0');
                    } else {
                        $cleaned = '+' . $cleaned;
                    }
                } catch (\Throwable $e) {
                    $cleaned = '+' . $cleaned;
                }
            } else {
                $cleaned = '+' . $cleaned;
            }
        }

        return $cleaned;
    }

    /**
     * Resolves customer lifecycle acquisition metrics (New vs. Returning Customer and Lifetime Value)
     * authoritatively from PrestaShop's core orders database.
     *
     * @param Customer $customer
     * @param Order|null $currentOrder
     * @return array
     */
    public static function resolveCustomerLifecycleMetrics(Customer $customer, Order $currentOrder = null)
    {
        $idCustomer = Validate::isLoadedObject($customer) ? (int)$customer->id : ($currentOrder ? (int)$currentOrder->id_customer : 0);
        $email = Validate::isLoadedObject($customer) ? trim((string)$customer->email) : '';
        $currentOrderId = ($currentOrder && Validate::isLoadedObject($currentOrder)) ? (int)$currentOrder->id : 0;
        $currentOrderTotal = ($currentOrder && Validate::isLoadedObject($currentOrder)) ? (float)$currentOrder->total_paid_tax_incl : 0.0;

        $priorOrdersCount = 0;
        $totalLifetimeValue = $currentOrderTotal;
        $totalOrdersCount = ($currentOrderId > 0) ? 1 : 0;

        if (class_exists('Db')) {
            try {
                $db = Db::getInstance();

                $whereClause = '`id_customer` = ' . (int)$idCustomer;
                if (!empty($email)) {
                    $whereClause = '(`id_customer` = ' . (int)$idCustomer . ' OR `id_customer` IN (
                        SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer` WHERE `email` = \'' . pSQL($email) . '\'
                    ))';
                }

                $validityCondition = '`valid` = 1';

                // 1. Prior placed orders strictly before this current order with payment accepted
                $priorSql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders`
                             WHERE ' . $whereClause . '
                               AND ' . $validityCondition;
                if ($currentOrderId > 0) {
                    $priorSql .= ' AND `id_order` < ' . (int)$currentOrderId;
                }
                $priorOrdersCount = (int)$db->getValue($priorSql);

                // 2. Cumulative lifetime spend across accepted orders
                $clvSql = 'SELECT SUM(`total_paid_tax_incl`) FROM `' . _DB_PREFIX_ . 'orders`
                           WHERE ' . $whereClause . '
                             AND ' . $validityCondition;
                $lifetimeSum = $db->getValue($clvSql);
                if ($lifetimeSum !== null && $lifetimeSum !== false && (float)$lifetimeSum > 0) {
                    $totalLifetimeValue = (float)$lifetimeSum;
                } elseif ($currentOrderTotal > 0) {
                    $totalLifetimeValue = $currentOrderTotal;
                }

                // If prior orders exist, ensure cumulative lifetime value includes both prior spend and current order total
                if ($priorOrdersCount > 0 && $currentOrderId > 0 && $currentOrderTotal > 0) {
                    $priorSpendSql = 'SELECT SUM(`total_paid_tax_incl`) FROM `' . _DB_PREFIX_ . 'orders`
                                      WHERE ' . $whereClause . '
                                        AND ' . $validityCondition . '
                                        AND `id_order` < ' . (int)$currentOrderId;
                    $priorSpend = (float)$db->getValue($priorSpendSql);
                    if ($priorSpend > 0 && ($totalLifetimeValue < ($priorSpend + $currentOrderTotal))) {
                        $totalLifetimeValue = $priorSpend + $currentOrderTotal;
                    }
                }

                // 3. Total lifetime orders count
                $countSql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders`
                             WHERE ' . $whereClause . '
                               AND ' . $validityCondition;
                $dbTotalCount = (int)$db->getValue($countSql);
                if ($dbTotalCount > 0) {
                    $totalOrdersCount = $dbTotalCount;
                }
                if ($currentOrderId > 0 && $totalOrdersCount < ($priorOrdersCount + 1)) {
                    $totalOrdersCount = $priorOrdersCount + 1;
                }
            } catch (\Throwable $e) {
                $priorOrdersCount = 0;
                $totalLifetimeValue = $currentOrderTotal;
                $totalOrdersCount = 1;
            }
        }

        $isNewCustomer = ($priorOrdersCount === 0);
        $precision = class_exists('PlatformAdapter') ? PlatformAdapter::getCurrencyPrecision(Context::getContext()) : 2;
        $roundedClv = class_exists('PricingEngine') ? PricingEngine::roundHalfUp($totalLifetimeValue, $precision) : round($totalLifetimeValue, $precision);

        return [
            'new_customer' => $isNewCustomer,
            'customer_lifetime_value' => $roundedClv,
            'orders_count' => $totalOrdersCount,
            'prior_orders_count' => $priorOrdersCount,
        ];
    }
}
