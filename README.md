KptivePaymentSipsBundle
=======================

The `KptivePaymentSipsBundle` provides access to the Atos SIPS payment solution through
the [JMSPaymentCoreBundle](https://github.com/schmittjoh/JMSPaymentCoreBundle).

Installation
------------

### Step 1

Run:

``` bash
$ php composer.phar require kptive/payment-sips-bundle *@dev
```

Or add the following to your `composer.json` before updating your vendors:

``` js
{
    "require": {
        "kptive/payment-sips-bundle": "*@dev"
    }
}
```

### Step 2

Register the bundle in your `AppKernel` class.
You will also have to register the `JMSPaymentCoreBundle` and
[configure it](http://jmsyst.com/bundles/JMSPaymentCoreBundle/master/configuration).

``` php
<?php
// app/AppKernel.php

    public function registerBundles()
    {
        $bundles = array(
            // ...
            new JMS\Payment\CoreBundle\JMSPaymentCoreBundle(),
            new Kptive\PaymentSipsBundle\KptivePaymentSipsBundle(),
        );

        // ...
    }

    // ...
```

### Step 3

Copy the content of your SIPS folder into `app/sips/`. If you want to put it
elsewhere, just edit the config values of the `pathfile` and binaries locations
(see below).

You will also have to copy or put your own logo images in the right location
depending on what you specified in your `pathfile`.


Configuration
-------------

``` yaml
kptive_payment_sips:
    config:
        merchant_id: "082584341411111"
        merchant_country: fr
        normal_return_url: %base_url%/checkout/complete
        cancel_return_url: %base_url%/checkout/cancel
        automatic_response_url: %base_url%/checkout/notification
        pathfile: %kernel.root_dir%/config/sips/param/pathfile
        currency_code: 978
    bin:
        request_bin: %kernel.root_dir%/config/sips/bin/static/request
        response_bin: %kernel.root_dir%/config/sips/bin/static/response
```

Usage
-----

Let's assume that you have an `AcmePaymentBundle` and that you handle your
orders with a `Acme\PaymentBundle\Entity\Sale` class:

``` php
<?php

namespace Acme\PaymentBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Payment\CoreBundle\Entity\PaymentInstruction;

/**
 * @ORM\Table(name="sale")
 */
class Sale
{

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="JMS\Payment\CoreBundle\Entity\PaymentInstruction")
     */
    private $paymentInstruction;

    /**
     * @ORM\Column(type="decimal", precision=2)
     */
    private $amount;

    /**
     * @ORM\Column(type="datetime", name="payed_at", nullable=true)
     */
    private $payedAt;

    // ...

    public function getId()
    {
        return $this->id;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getPaymentInstruction()
    {
        return $this->paymentInstruction;
    }

    public function setPaymentInstruction(PaymentInstruction $instruction)
    {
        $this->paymentInstruction = $instruction;

        return $this;
    }

    public function getPayedAt()
    {
        return $this->payedAt;
    }

    public function setPayedAt($payedAt)
    {
        $this->payedAt = $payedAt;

        return $this;
    }

```


Create a controller with a `details` action.
This is where your customer can review their order and confirm it.

``` php
<?php

namespace Acme\PaymentBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use JMS\Payment\CoreBundle\Entity\PaymentInstruction;
use Acme\PaymentBundle\Entity\Sale;

/**
 * @Route("/checkout")
 */
class CheckoutController extends Controller
{
    // ...

    /**
     * @Route("/details/{id}", name = "payment_details")
     * @Template()
     */
    public function detailsAction(Sale $sale)
    {
        $request = $this->get('request');
        $em = $this->get('doctrine')->getEntityManager();
        $router = $this->get('router');
        $ppc = $this->get('payment.plugin_controller');

        $confirm = new \StdClass();

        $form = $this->createFormBuilder($confirm)
            ->add('save', 'submit', array('label' => 'confirmer'))
            ->getForm();

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $instruction = new PaymentInstruction($sale->getAmount(), 978, 'sips');

                $ppc->createPaymentInstruction($instruction);

                $sale->setPaymentInstruction($instruction);
                $em->persist($sale);
                $em->flush($sale);

                return new RedirectResponse($router->generate('payment_gateway', array(
                    'id' => $sale->getId(),
                )));
            }
        }

        return array(
            'sale' => $sale,
            'form' => $form->createView()
        );
    }
}
```


As you can see in the previous action, when the user confirms their order, we
create a new `PaymentInstruction` (see the
[JMSPaymentCoreBundle documentation](http://jmsyst.com/bundles/JMSPaymentCoreBundle/master/model)
for more information on how it works).
They are then redirected to the `payment_gateway` route.
This is where we'll make a call to the SIPS API so that we can display the SIPS
credit card choice form.

Let's implement the corresponding action:

``` php
    /**
     * @Route("/gateway/{id}", name="payment_gateway")
     * @Template()
     */
    public function sipsGatewayAction(Sale $sale)
    {
        $client = $this->get('kptive_payment_sips.client');

        $config = array(
            'amount' => $sale->getAmount() * 100,
            'order_id' => $sale->getId(),
        );

        $sips = $client->request($config);

        return array('sips' => $sips);
    }
```

And in the corresponding view, display the form to the user:

``` jinja
{# src/Acme/PaymentBundle/Resources/views/Checkout/sipsGateway.html.twig #}

{{ sips|raw }}
```


When the user has completed the payment workflow on the SIPS platform, they will
be redirected to the `normal_return_url` you configured earlier in the bundle
config section.

Let's implement the action :

``` php
    /**
     * @Route("/complete", name="payment_complete")
     * @Template()
     */
    public function completeAction(Request $request)
    {
        $data = $request->request->get('DATA');
        $em = $this->get('doctrine')->getEntityManager();
        $client = $this->get('kptive_payment_sips.client');

        $response = $client->handleResponseData($data);
        $sale = $em->getRepository('KsPaymentBundle:Sale')->find($response['order_id']);
        $instruction = $sale->getPaymentInstruction();

        $result = $this->get('kptive_payment_sips.return_handler')->handle($instruction, $response);

        return array('sale' => $sale);
    }
```


For now, we didn't do anything with the Sale, we just handled the bank response
and marked the payment as valid.

The JMSPaymentCoreBundle will trigger a `payment.state_change` event.
So we will listen to this event and do everything useful we want in a `PaymentListener`:

``` php
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
                ->getRepository('AcmePaymentBundle:Sale')
                ->findOneBy(array('paymentInstruction' => $event->getPaymentInstruction()));

            $payedAt = new \DateTime();

            $sale->setPayedAt($payedAt);

            // Do various things with the Sale here
            // ...

            $this->entityManager->persist($sale);
            $this->entityManager->flush();
        }
    }
}
```

Register it as a service:

``` xml
        <service id="acme_payment.payment_listener" class="Acme\PaymentBundle\EventListener\PaymentListener">
            <tag name="kernel.event_listener" event="payment.state_change" method="onPaymentStateChange" />
            <argument type="service" id="doctrine.orm.entity_manager">
        </service>
```


And voilà!

If your customer doesn't click on the "Back" button on the bank platform,
a request will be automatically issued to the configured `automatic_response_url`.

You can use the same URL as the `normal_return_url` or implement your own.

**Warning**: those examples don't take security into account. Don't forget to
check the ownership of the sale!


Credits
-------

* KptiveStudio <http://kptivestudio.com>
* Hubert Moutot <hubert.moutot@gmail.com>

A great thank you to Johannes M Schmitt for his awesome JMSPayementCoreBundle.
Thanks to https://github.com/Kitano/KitanoPaymentSipsBundle for the inspiration.

License
-------

KptivePaymentSipsBundle is released under the MIT License.
See the bundled LICENSE file for details.
