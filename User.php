<?php

class User
{
    private $user;

    public function __construct()
    {
        $this->user = [
            "name" => "Сергей",
            "settings" => [
                "attributes" => [
                    'paymentin.isAttachedToInvoice' => new ah(['value' => false])
                ]
            ]
        ];
    }

    public function get($path, $default = null)
    {
        return new ah([
            'paymentin.isAttachedToInvoice' => new ah(['value' => false])
        ]);
    }
}
