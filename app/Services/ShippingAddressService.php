<?php

namespace App\Services;

class ShippingAddressService
{
    public function getAddAddressData($request,$customerId,$addressType):array
    {
        $city = $request['city_id'] ? (\App\Models\Governorate::find($request['city_id'])->name_ar ?? null) : ($request['city'] ?? null);
        $name = $request['f_name'];
        if (!empty($request['l_name'])) {
            $name .= ' '.$request['l_name'];
        }
        return [
            'customer_id' => $customerId,
            'contact_person_name' => $name,
            'address_type' => $addressType,
            'address' => $request['address'],
            'city' => $city,
            'zip' => $request['zip_code'] ?? null,
            'country' => $request['country'] ?? null,
            'phone' => $request['phone'],
            'is_billing' => 0,
            'latitude' => 0,
            'longitude' => 0,
        ];
    }
}
