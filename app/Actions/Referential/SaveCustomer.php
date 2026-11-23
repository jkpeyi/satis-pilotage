<?php

namespace App\Actions\Referential;

use App\Enums\CustomerType;
use App\Models\Customer;

final class SaveCustomer
{
    /**
     * @param  array{name: string, type: ?CustomerType, country: ?string, notes: ?string}  $data
     */
    public function handle(array $data, ?Customer $customer = null): Customer
    {
        $customer ??= new Customer;
        $customer->fill($data)->save();

        return $customer;
    }
}
