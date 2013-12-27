<?php

namespace Kptive\PaymentSipsBundle\Plugin;

use JMS\Payment\CoreBundle\PluginController\PluginControllerInterface;
use JMS\Payment\CoreBundle\Model\PaymentInterface;
use JMS\Payment\CoreBundle\PluginController\Result;
use Doctrine\ORM\EntityManager;
use Kptive\PaymentSipsBundle\Client\Client;

/**
 * @author Hubert Moutot <hubert.moutot@gmail.com>
 */
class ReturnHandler
{

    const SUCCESS = 1;

    const FAILED = 2;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var PluginControllerInterface
     */
    protected $pluginController;

    /**
     * Constructor
     *
     * @param EntityManager $entityManager an EntityManager instance
     * @param Client        $client        a Client instance
     */
    public function __construct(EntityManager $entityManager, Client $client, PluginControllerInterface $pluginController)
    {
        $this->entityManager = $entityManager;
        $this->client = $client;
        $this->pluginController = $pluginController;
    }

    /**
     * Handles the notification
     *
     * @param string $data the encrypted data provided by the bank
     *
     * @return int NotificationHandler::SUCCESS|NotificationHandler::FAILED
     */
    public function handle($instruction, $data)
    {
        $response = $this->client->handleResponseData($data);

        if (null === $pendingTransaction = $instruction->getPendingTransaction()) {
            $payment = $this->pluginController->createPayment($instruction->getId(), $instruction->getAmount() - $instruction->getDepositedAmount());
        } else {
            $payment = $pendingTransaction->getPayment();
        }

        foreach ($response as $key => $val) {
            $instruction->getExtendedData()->set($key, $val);
        }

        $result = $pluginController->approveAndDeposit($payment->getId(), $payment->getTargetAmount());

        if (Result::STATUS_SUCCESS !== $result->getStatus()) {
            throw new \RuntimeException('Transaction was not successful: '.$result->getReasonCode());
        }

        return self::SUCCESS;
    }
}
