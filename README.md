...
PaymentSipsBundle
=================

Warning: This bundle is still in beta, don't use it in production.

Don't take anything in this file for granted, I'm still writing stuff.

Installation
------------

Run:

    composer install kptive/payment-sips-bundle *@dev


Register the routing in your app/config/routing.yml:

    kptive_payment_sips:
        resource: "@KptivePaymentSipsBundle/Controller/"
        type:     annotation
        prefix:   /


*Copy your sips foler in app/sips/*


Configuration
-------------


    kptive_payment_sips:
        config:
            merchant_id: "082584341411111"
            merchant_country: fr
            normal_return_url: %base_url%/checkout/complete
            cancel_return_url: %base_url%/checkout/cancel
            automatic_response_url: %base_url%/payment/sips/notification
            pathfile: "%kernel.root_dir%/config/sips/param/pathfile"
            # default_language: fr
            # default_template_file: ~
            currency_code: 978
        bin:
            request_bin: "%kernel.root_dir%/config/sips/bin/static/request"
            response_bin: "%kernel.root_dir%/config/sips/bin/static/response"


Usage
-----

You can create a PaymentBundle.

Implement a PaymentListener:

    <?php

    namespace Acme\PaymentBundle\EventListener;

    use Doctrine\ORM\EntityManager;
    use JMS\Payment\CoreBundle\PluginController\Event\PaymentStateChangeEvent;
    use JMS\Payment\CoreBundle\Model\PaymentInterface;

    class PaymentListener
    {

        protected $entityManager;

        public function __construct(EntityManager $entityManager)
        {
            $this->entityManager = $entityManager;
        }

        public function onPaymentStateChange(PaymentStateChangeEvent $event)
        {
            if (PaymentInterface::STATE_DEPOSITED === $event->getNewState()) {
                $sale = $this
                    ->entityManager
                    ->getRepository('Acme\PaymentBundle\Entity\Sale')
                    ->findOneBy(array('paymentInstruction' => $event->getPaymentInstruction()));

                $payedAt = new \DateTime();

                $sale->setPayedAt($payedAt);

                $this->entityManager->persist($sale);
                $this->entityManager->flush();
            }
        }
    }

And register it as a service:

    acme_payment.listener.payment:
        class: Acme\PaymentBundle\EventListener\PaymentListener
        arguments:
            - @doctrine.orm.entity_manager
        tags:
            - { name: kernel.event_listener, event: payment.state_change, method: onPaymentStateChange }



Contributing
------------

See CONTRIBUTING file.


Unit Tests
----------

Run:

    phpunit


Credits
-------

* Hubert Moutot <hubert.moutot@gmail.com>
* [All contributors](https://github.com/KptiveStudio/payment-sips-bundle/contributors)

A great thank you to Johannes M Schmitt for his awesome JMSPayementCoreBundle.

License
-------

PaymentSipsBundle is released under the MIT License. See the bundled LICENSE file for details.
