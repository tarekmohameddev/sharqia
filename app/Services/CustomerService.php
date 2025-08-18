<?php

namespace App\Services;

use App\Traits\FileManagerTrait;

class CustomerService
{
    use FileManagerTrait;

    /**
     * @return array[f_name: mixed, l_name: mixed, email: mixed, phone: mixed, country: mixed, city: mixed, zip: mixed, street_address: mixed, password: string]
     */
    public function getCustomerData(object $request):array
    {
        $cityName = $request['city_id'] ? (\App\Models\Governorate::find($request['city_id'])->name_ar ?? null) : ($request['city'] ?? null);
        return [
            'f_name' => $request['f_name'],
            'l_name' => $request['l_name'] ?? null,
            'email' => $request['email'] ?? null,
            'phone' => $request['phone'],
            'country' => $request['country'] ?? null,
            'city' => $cityName,
            'zip' => $request['zip_code'] ?? null,
            'street_address' => $request['address'] ?? null,
            'password' => bcrypt($request['password'] ?? 'password')
        ];
    }

    public function deleteImage(object|null $data): bool
    {
        if ($data && $data['image']) {
            $this->delete('profile/' . $data['image']);
        };
        return true;
    }
}
