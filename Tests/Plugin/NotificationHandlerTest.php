<?php

namespace Kptive\PaymentSipsBundle\Tests\Plugin;

use JMS\Payment\CoreBundle\Entity\FinancialTransaction;
use JMS\Payment\CoreBundle\Entity\Payment;
use JMS\Payment\CoreBundle\Entity\PaymentInstruction;
use JMS\Payment\CoreBundle\Model\PaymentInterface;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use JMS\Payment\CoreBundle\PluginController\Result;
use Kptive\PaymentSipsBundle\Plugin\NotificationHandler;

/**
 * @author Hubert Moutot <hubert.moutot@gmail.com>
 */
class NotificationHandlerTest extends \PHPUnit_Framework_TestCase
{

    private $notificationHandler;

    private $ppc;

    private $transaction;

    public function setUp()
    {
        $this->transaction = new FinancialTransaction();
        $this->payment = new Payment(new PaymentInstruction(100, 'eur', 'sips'), 100);
        $this->transaction->setPayment($this->payment);

        $repository = $this->getRepositoryMock($this->transaction);
        $em = $this->getEntityManagerMock($repository);
        $client = $this->getClientMock();
        $this->ppc = $this->getPluginControllerMock();

        $this->notificationHandler = new NotificationHandler($em, $client, $this->ppc);
    }

    public function testHandleSuccess()
    {
        $this->ppc
            ->expects($this->once())
            ->method('approveAndDeposit')
            ->will($this->returnValue(new Result($this->transaction, Result::STATUS_SUCCESS, PluginInterface::REASON_CODE_SUCCESS)));

        $this->payment->setState(PaymentInterface::STATE_APPROVING);

        $result = $this->notificationHandler->handle('data');

        $this->assertEquals(NotificationHandler::SUCCESS, $result);
    }

    public function testHandleFailedState()
    {
        $this->payment->setState(PaymentInterface::STATE_NEW);

        $result = $this->notificationHandler->handle('data');

        $this->assertEquals(NotificationHandler::FAILED, $result);
    }

    public function testHandleFailedResult()
    {
        $this->ppc
            ->expects($this->once())
            ->method('approveAndDeposit')
            ->will($this->returnValue(new Result($this->transaction, Result::STATUS_FAILED, PluginInterface::REASON_CODE_INVALID)));

        $this->payment->setState(PaymentInterface::STATE_APPROVING);

        $result = $this->notificationHandler->handle('data');

        $this->assertEquals(NotificationHandler::FAILED, $result);
    }

    public function getEntityManagerMock($repository)
    {
        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();

        $em->expects($this->once())
            ->method('getRepository')
            ->with('JMS\Payment\CoreBundle\Entity\FinancialTransaction')
            ->will($this->returnValue($repository));

        return $em;
    }

    public function getRepositoryMock($transaction)
    {
        $repository = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->setMethods(array('findOneByTrackingId'))
            ->getMock();

        $repository->expects($this->once())
            ->method('findOneByTrackingId')
            ->will($this->returnValue($transaction));

        return $repository;
    }

    public function getClientMock()
    {
        $client = $this->getMockBuilder('Kptive\PaymentSipsBundle\Client\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $client->expects($this->once())
            ->method('handleResponseData')
            ->will($this->returnValue(array('transaction_id' => 1)));

        return $client;
    }

    public function getPluginControllerMock()
    {
        $ppc = $this->getMockBuilder('JMS\Payment\CoreBundle\PluginController\EntityPluginController')
            ->disableOriginalConstructor()
            ->getMock();

        return $ppc;
    }

}
