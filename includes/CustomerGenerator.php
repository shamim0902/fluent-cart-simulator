<?php

namespace FluentCartSimulator;

use FluentCart\App\Models\Customer;
use FluentCart\App\Models\CustomerAddresses;

class CustomerGenerator
{
    private static $countryCodes = [
        'US' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Dallas'],
        'CA' => ['Toronto', 'Vancouver', 'Montreal', 'Calgary', 'Ottawa'],
        'GB' => ['London', 'Manchester', 'Birmingham', 'Liverpool', 'Leeds'],
        'AU' => ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide'],
        'DE' => ['Berlin', 'Munich', 'Hamburg', 'Frankfurt', 'Cologne'],
        'FR' => ['Paris', 'Marseille', 'Lyon', 'Toulouse', 'Nice'],
        'IT' => ['Rome', 'Milan', 'Naples', 'Turin', 'Florence'],
        'ES' => ['Madrid', 'Barcelona', 'Seville', 'Valencia', 'Bilbao'],
        'BR' => ['Sao Paulo', 'Rio de Janeiro', 'Salvador', 'Brasilia'],
        'JP' => ['Tokyo', 'Osaka', 'Kyoto', 'Sapporo', 'Yokohama'],
        'IN' => ['Mumbai', 'Delhi', 'Bangalore', 'Chennai', 'Kolkata'],
        'NL' => ['Amsterdam', 'Rotterdam', 'The Hague', 'Utrecht'],
    ];

    private static $statesByCountry = [
        'US' => ['AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'],
        'CA' => ['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'],
        'GB' => ['ENG', 'SCT', 'WLS', 'NIR'],
        'AU' => ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'],
        'DE' => ['BW', 'BY', 'BE', 'BB', 'HB', 'HH', 'HE', 'MV', 'NI', 'NW', 'RP', 'SL', 'SN', 'ST', 'SH', 'TH'],
        'FR' => ['IDF', 'ARA', 'BRE', 'CVL', 'GES', 'HDF', 'NAQ', 'NOR', 'OCC', 'PDL', 'PAC', 'BFC', 'COR'],
        'IT' => ['AG', 'AL', 'AN', 'AO', 'AR', 'AP', 'AT', 'AV', 'BA', 'BL', 'BN', 'BG', 'BI', 'BO', 'BZ', 'BS', 'BR', 'CA', 'CL', 'CB'],
        'ES' => ['C', 'VI', 'AB', 'A', 'AL', 'O', 'AV', 'BA', 'PM', 'B', 'BU', 'CC', 'CA', 'CS', 'CR', 'CO', 'CU', 'GI', 'GR', 'GU'],
        'BR' => ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'],
        'JP' => ['JP-01', 'JP-02', 'JP-03', 'JP-04', 'JP-05', 'JP-06', 'JP-07', 'JP-08', 'JP-09', 'JP-10', 'JP-13', 'JP-14', 'JP-27', 'JP-26'],
        'IN' => ['AN', 'AP', 'AR', 'AS', 'BR', 'CG', 'CH', 'DL', 'GA', 'GJ', 'HP', 'HR', 'JH', 'JK', 'KA', 'KL', 'MH', 'ML', 'MN', 'MP', 'MZ', 'NL', 'OD', 'PB', 'PY', 'RJ', 'SK', 'TN', 'TS', 'UK', 'UP', 'WB'],
        'NL' => ['DR', 'FL', 'FR', 'GE', 'GR', 'LI', 'NB', 'NH', 'OV', 'UT', 'ZE', 'ZH'],
    ];

    private static $streetNames = [
        'Main St', 'Oak Ave', 'Elm St', 'Park Blvd', 'Cedar Ln', 'Maple Dr',
        'Pine St', 'Washington Ave', 'Lake Rd', 'Hill St', 'River Rd', 'Church St',
        'High St', 'Forest Ave', 'Sunset Blvd', 'Broadway', 'Market St', 'Spring St',
        'Union St', 'Center St', 'Mill Rd', 'Academy St', 'Bridge St', 'School St',
    ];

    public static function createFakeCustomer($faker)
    {
        $country = $faker->randomElement(array_keys(self::$countryCodes));
        $city = $faker->randomElement(self::$countryCodes[$country]);
        $gender = $faker->randomElement(['male', 'female']);
        $state = self::getRandomState($faker, $country);
        $postcode = $faker->postcode();
        $firstName = $faker->firstName($gender);
        $lastName = $faker->lastName();
        $email = $faker->unique()->safeEmail();
        $phone = $faker->numerify('+1##########');
        $address1 = wp_rand(1, 9999) . ' ' . $faker->randomElement(self::$streetNames);
        $address2 = $faker->optional(0.3)->randomElement(['Apt ' . wp_rand(1, 200), 'Suite ' . wp_rand(100, 999), 'Unit ' . wp_rand(1, 50), '']);

        $customer = Customer::query()->create([
            'email'      => $email,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'country'    => $country,
            'city'       => $city,
            'state'      => $state,
            'postcode'   => $postcode,
            'status'     => 'active',
        ]);

        if (!$customer || !$customer->id) {
            return null;
        }

        // Create billing address in fct_customer_addresses
        $fullName = $firstName . ' ' . $lastName;
        $addressData = [
            'customer_id' => $customer->id,
            'is_primary'  => 1,
            'type'        => 'billing',
            'status'      => 'active',
            'name'        => $fullName,
            'address_1'   => $address1,
            'address_2'   => $address2 ?: '',
            'city'        => $city,
            'state'       => $state,
            'postcode'    => $postcode,
            'country'     => $country,
            'phone'       => $phone,
            'email'       => $email,
        ];

        CustomerAddresses::query()->create($addressData);

        // 40% chance of a separate shipping address
        if (wp_rand(1, 100) <= 40) {
            $shipCountry = $faker->randomElement(array_keys(self::$countryCodes));
            $shipCity = $faker->randomElement(self::$countryCodes[$shipCountry]);
            CustomerAddresses::query()->create([
                'customer_id' => $customer->id,
                'is_primary'  => 0,
                'type'        => 'shipping',
                'status'      => 'active',
                'name'        => $fullName,
                'address_1'   => wp_rand(1, 9999) . ' ' . $faker->randomElement(self::$streetNames),
                'address_2'   => '',
                'city'        => $shipCity,
                'state'       => self::getRandomState($faker, $shipCountry),
                'postcode'    => $faker->postcode(),
                'country'     => $shipCountry,
                'phone'       => $phone,
                'email'       => $email,
            ]);
        }

        return $customer->id;
    }

    public static function getRandomState($faker, $country)
    {
        if (isset(self::$statesByCountry[$country])) {
            return $faker->randomElement(self::$statesByCountry[$country]);
        }
        return '';
    }

    public static function getCustomerAddressData($customerId)
    {
        $billingAddress = CustomerAddresses::query()
            ->where('customer_id', $customerId)
            ->where('type', 'billing')
            ->first();

        $shippingAddress = CustomerAddresses::query()
            ->where('customer_id', $customerId)
            ->where('type', 'shipping')
            ->first();

        return [
            'billing'  => $billingAddress,
            'shipping' => $shippingAddress ?: $billingAddress, // fallback to billing
        ];
    }

    public static function getRandomExistingCustomer()
    {
        $customerIds = Customer::query()
            ->select('id')
            ->where('status', 'active')
            ->pluck('id');

        // Handle both plain array and Collection returns from WPFluent
        if (is_object($customerIds) && method_exists($customerIds, 'toArray')) {
            $customerIds = $customerIds->toArray();
        }

        $customerIds = (array) $customerIds;

        if (empty($customerIds)) {
            return null;
        }

        return $customerIds[array_rand($customerIds)];
    }
}
