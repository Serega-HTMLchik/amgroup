<?php

class MoyskladApp
{
    private $invoiceOutData;
    public function __construct($invoiceOutData)
    {
        $this->invoiceOutData = $invoiceOutData;
    }
    public function getEntityRows($entity, $params)
    {
        return $this->invoiceOutData;
        // return
        //     [
        //         "sum" => $this->invoiceOutData['sum'],
        //         "payedSum" => $this->invoiceOutData['payedSum'],
        //     ]
        // ;
    }
    public function sendEntity($entity, $data)
    {
        echo "Отправка $entity:\n";
        print_r($data);
    }
    public function getJsonApi()
    {
        return $this;
    }

}