<?php

namespace Kptive\PaymentSipsBundle\Plugin;

use JMS\Payment\CoreBundle\PluginController\PluginControllerInterface;
use JMS\Payment\CoreBundle\PluginController\Result;
use Doctrine\ORM\EntityManager;

/**
 * @author Hubert Moutot <hubert.moutot@gmail.com>
 */
class ReturnHandler
{

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var PluginControllerInterface
     */
    protected $pluginController;

    /**
     * Constructor
     *
     * @param EntityManager             $entityManager    an EntityManager instance
     * @param PluginControllerInterface $pluginController a PluginControllerInterface instance
     */
    public function __construct(EntityManager $entityManager, PluginControllerInterface $pluginController)
    {
        $this->entityManager = $entityManager;
        $this->pluginController = $pluginController;
    }

    /**
     * Processes the payment
     *
     * @param PaymentInstruction $instruction the PaymentInstruction to handle
     * @param array              $data        the decrypted response data
     *
     * @return Result
     */
    public function handle($instruction, $data)
    {
        if (null === $pendingTransaction = $instruction->getPendingTransaction()) {
            $payment = $this->pluginController->createPayment($instruction->getId(), $instruction->getAmount() - $instruction->getDepositedAmount());
        } else {
            $payment = $pendingTransaction->getPayment();
        }

        foreach ($data as $key => $val) {
            $instruction->getExtendedData()->set($key, $val);
        }

        $result = $this->pluginController->approveAndDeposit($payment->getId(), $payment->getTargetAmount());

        if (Result::STATUS_SUCCESS !== $result->getStatus()) {
            throw new \RuntimeException('Transaction was not successful: ' . $result->getReasonCode());
        }

        return $result;
    }
}
