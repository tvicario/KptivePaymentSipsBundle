<?php

namespace Kptive\PaymentSipsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @author Hubert Moutot <hubert.moutot@gmail.com>
 */
class SipsController extends Controller
{

    public function notificationAction(Request $request)
    {
        $data = $request->request->get('DATA');

        $result = $this->get('kptive_payment_sips.notification_handler')->handle($data);

        if (NotificationHandler::SUCCESS === $result) {
            return new Response('OK', 200);
        } else {
            return new Response('FAILED', 500);
        }
    }

}
