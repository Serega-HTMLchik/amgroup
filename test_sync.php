<?php

// require_once 'ArrayAccess.php';
require_once 'ah.php';
require_once 'MoyskladApp.php';
require_once 'SyncCommand.php';
require_once 'User.php';
require_once 'AttributeModel.php';
require_once 'TextHelper.php';




// файл с платежами
$paymentsInData = json_decode(file_get_contents('paymentin.json'), true);
// файл с инвойсами
$invoicesOutData = json_decode(file_get_contents('invoiceout.json'), true);

$paymentsIn = new ah($paymentsInData);
$msApp = new MoyskladApp($invoicesOutData);

$sync = new SyncCommand();
$user = new User();
$sync->setUser($user);

$sync->startAttachToInvoiceOut($paymentsIn, $msApp);
