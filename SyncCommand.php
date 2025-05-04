<?php

/**
 * Это фрагмент класса SyncCommand для запуска синхронизации платежей между Альфа-Банком и МойСклад.
 * Фрагмент из реального проекта, с которым Вам придется работать.
 *
 * Класс ah - Обертка над массивом для удобной работы с ним.
 * PaymentsIn - Входящий платеж МойСклад (пример данных во вложении)
 * InvoiceOut - Счет покупателю в МойСклад (пример данных во вложении)
 * MoyskladApp - Клиент для доступа к API МойСклад
 */

/**
 * В компанию обратился клиент со следующей проблемой:
 *
 * 200 входящих платежей у клиента привязываются к одному и тому же счету, хотя у каждого входящего платежа
 * должен быть свой индивидуальный счет к которому он должен быть привязан.
 *
 * В аудите платежей видно, что все они были созданы в одно и то же время нашей интеграцией.
 * Платежи действительно имеют разное назначение и содержат корректный номер счета.
 *
 * Пример назначения платежа из кейса:
 * "Оплата по сч/ф 1020 от 19.02.2025 по договору № Б\Н от 16.12.2024 за
 * Закупка поломоечных машин ТР ЮГ в т.ч. НДС 40.487,50"
 */

/**
 * Задача:
 *
 * 1. Выяснить, по какой причине произошла некорректная привязка платежей к счету.
 * 2. Внести изменения в код, чтобы кейс больше не повторился.
 * 3. Сделать рефакторинг метода, учучшив его читаемость и понятность.
 */

class SyncCommand
{

    /**
     * Метод отвечает за связываение платежа и счета покупателю. Это необходимо для того,
     * чтобы менеджеры понимали, что данный счет уже оплачен.
     *
     * @param ah $paymentsIn
     * @param MoyskladApp $msApp
     * @return void|null
     */
    protected function attachToInvoiceOut(ah $paymentsIn, MoyskladApp $msApp)
    {
        $attributes = $this->user->get('settings.' . AttributeModel::TABLE_NAME, new ah());
        $isAttachedToInvoiceAttr = $attributes->get('paymentin.isAttachedToInvoice')->getAll();

        $invoicesOut = $this->getUnpaidInvoices($msApp);

        $updatePayment = [];
        $updateInvoiceOut = [];
        $usedInvoiceIds = [];
        $paymentsIn->each(function ($payment) use ($invoicesOut, &$updatePayment, &$updateInvoiceOut, &$isAttachedToInvoiceAttr, &$usedInvoiceIds) {
            if (!$this->isValidPayment($payment)) {
                return;
            }

            foreach ($invoicesOut as &$invoiceOut) {
                // Пропускаем уже использованные счета
                if ($this->shouldSkipInvoice($invoiceOut, $usedInvoiceIds)) {
                    continue;
                }

                if (!$this->isValidInvoice($invoiceOut)) {
                    continue;
                }

                if (!$this->isSameCounterparty($invoiceOut, $payment)) {
                    continue;
                }

                if (!$this->canAttachPaymentToInvoice($invoiceOut, $payment)) {
                    continue;
                }

                $isAttachedToInvoiceAttr['value'] = true;
                $payment['attributes'] = [$isAttachedToInvoiceAttr];
                $payment['operations'] = [['meta' => $invoiceOut['meta']]];
                $updatePayment[] = $payment;

                $invoiceOut['payments'] = [['meta' => $payment['meta']]];
                $updateInvoiceOut[] = $invoiceOut;
                $usedInvoiceIds[] = $invoiceOut['id'];
                return;
            }
        });

        $this->sendUpdates($updatePayment, $updateInvoiceOut, $msApp);
    }

    /**
     * @param $invoiceName
     * @param $paymentPurpose
     *
     * @return bool
     */
    protected static function invoiceNumberInPurpose($invoiceName, $paymentPurpose): bool
    {
        if (preg_match('/сч\/ф\s+(\d+)/u', $paymentPurpose, $matches)) {
            return true;
        }
        return false;
    }

    protected function isValidPayment($payment): bool
    {
        return !empty($payment['organizationAccount']['meta']['href']) && !empty($payment['paymentPurpose']);
    }

    protected function isValidInvoice($invoiceOut): bool
    {
        return !empty($invoiceOut['organizationAccount']['meta']['href']);
    }
    protected function getUnpaidInvoices(MoyskladApp $msApp)
    {
        $msApi = $msApp->getJsonApi();
        $invoicesOut = $msApi->getEntityRows('invoiceout', ['expand' => 'organizationAccount, agent']);

        return (new ah($invoicesOut))->filter(function ($invoice) {
            return (int) $invoice['sum'] !== (int) $invoice['payedSum'] * 100;
        })->getAll();
    }
    protected function shouldSkipInvoice($invoiceOut, $usedInvoiceIds): bool
    {
        return in_array($invoiceOut['id'], $usedInvoiceIds, true);
    }
    protected function isSameCounterparty($invoiceOut, $payment): bool
    {
        $invoice = new ah($invoiceOut);
        return TextHelper::isEqual($invoice['agent']['meta']['href'], $payment['agent']['meta']['href']) &&
            TextHelper::isEqual($invoice['organizationAccount']['meta']['href'], $payment['organizationAccount']['meta']['href']) &&
            TextHelper::isEqual($invoice['organization']['meta']['href'], $payment['organization']['meta']['href']);
    }
    protected function sendUpdates($updatePayment, $updateInvoiceOut, $msApp)
    {
        $msApi = $msApp->getJsonApi();
        if (!empty($updatePayment)) {
            $msApi->sendEntity('paymentin', $updatePayment);
        }
        if (!empty($updateInvoiceOut)) {
            $msApi->sendEntity('invoiceout', $updateInvoiceOut);
        }
    }
    protected function canAttachPaymentToInvoice($invoiceOut, $payment): bool
    {
        $invoice = new ah($invoiceOut);

        $invoiceName = $invoice['name'];
        $paymentPurpose = $payment['paymentPurpose'];
        $invoiceSum = $invoice['sum'];
        $paymentSum = $payment['sum'];

        if ($this->invoiceNumberInPurpose($invoiceName, $paymentPurpose)) {
            return true;
        }

        if ($invoiceSum == $paymentSum) {
            $invoiceDate = date('d.m.Y', strtotime($invoice['moment']));
            if (strpos($paymentPurpose, $invoiceDate) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Остальные методы класса. Для решения задачи они не нужны.
     */
    private $user;

    public function setUser($user)
    {
        $this->user = $user;
    }
    public function startAttachToInvoiceOut($paymentsIn, $msApp)
    {
        $this->attachToInvoiceOut($paymentsIn, $msApp);
    }
}