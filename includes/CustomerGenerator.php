<?php

namespace FluentCartSimulator;

use FluentCart\App\Models\Customer;

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

    public static function createFakeCustomer($faker)
    {
        $country = $faker->randomElement(array_keys(self::$countryCodes));
        $city = $faker->randomElement(self::$countryCodes[$country]);
        $gender = $faker->randomElement(['male', 'female']);

        $customer = Customer::query()->create([
            'email'      => $faker->unique()->safeEmail(),
            'first_name' => $faker->firstName($gender),
            'last_name'  => $faker->lastName(),
            'country'    => $country,
            'city'       => $city,
            'state'      => $faker->state(),
            'postcode'   => $faker->postcode(),
            'status'     => 'active',
        ]);

        return $customer ? $customer->id : null;
    }

    public static function getRandomExistingCustomer()
    {
        $customerIds = Customer::query()
            ->select('id')
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        if (empty($customerIds)) {
            return null;
        }

        return $customerIds[array_rand($customerIds)];
    }
}
