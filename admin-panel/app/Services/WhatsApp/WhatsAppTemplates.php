<?php

namespace App\Services\WhatsApp;

/**
 * The approved message templates the platform uses. WhatsApp only lets a business start a
 * conversation with a pre-approved template, so every automatic notification maps to one.
 * "body" is the exact text to submit for approval ({{1}}, {{2}}… are the variables, in order).
 *
 * Meta rejects templates with "too many variables for its length", so each message here has at
 * most three variables and plenty of fixed wording around them. Never start or end a body with a
 * variable, and keep variables in order of appearance.
 */
class WhatsAppTemplates
{
    /** @return array<string, array{label:string, audience:string, body:string, vars:string[]}> */
    public static function all(): array
    {
        return [
            'order_placed' => ['label' => 'Order placed', 'audience' => 'Customer',
                'body' => 'Hi {{1}}, thank you for ordering with us! Your order #{{2}} from {{3}} has been placed successfully and the restaurant has been notified. We will keep you updated at every step, and you can ask for a live map link here at any time by replying RIDER.',
                'vars' => ['customer name', 'order number', 'restaurant']],
            'order_accepted' => ['label' => 'Restaurant accepted', 'audience' => 'Customer',
                'body' => 'Good news {{1}}! {{2}} has accepted your order #{{3}} and the kitchen has started working on it. We will let you know as soon as a delivery partner is assigned and your food is on the way. Thank you for your patience.',
                'vars' => ['customer name', 'restaurant', 'order number']],
            'order_rider_assigned' => ['label' => 'Rider assigned', 'audience' => 'Customer',
                'body' => 'Your delivery partner {{1}} has been assigned to pick up your order #{{2}} and will reach the restaurant shortly. You can follow your rider live on the map using this link: {{3}} and we will message you again when your food is on its way.',
                'vars' => ['rider name', 'order number', 'tracking link']],
            'order_out_for_delivery' => ['label' => 'Out for delivery', 'audience' => 'Customer',
                'body' => 'Your order #{{1}} is out for delivery and on its way to you now. Please share the delivery code {{2}} with the rider only when you receive your food. You can follow your rider live here: {{3}} and thank you for choosing us!',
                'vars' => ['order number', 'delivery OTP', 'tracking link']],
            'order_delivered' => ['label' => 'Delivered', 'audience' => 'Customer',
                'body' => 'Your order #{{1}} has been delivered. We hope you enjoyed your meal, {{2}}! Thank you for ordering with us, and please do rate your experience in the app so that we can keep improving. We look forward to serving you again soon.',
                'vars' => ['order number', 'customer name']],
            'order_cancelled' => ['label' => 'Order cancelled', 'audience' => 'Customer',
                'body' => 'We are sorry, your order #{{1}} has been cancelled. The reason given was: {{2}}. If you have already paid online, the refund will be processed automatically as per our refund policy and will reach you in a few working days. Please contact our support team if you need any help.',
                'vars' => ['order number', 'reason']],
            'refund_issued' => ['label' => 'Refund issued', 'audience' => 'Customer',
                'body' => 'A refund of {{1}} for your order #{{2}} has been issued to your {{3}}. Depending on your bank or payment provider it can take up to five to seven working days to show in your account. Thank you for your patience and understanding.',
                'vars' => ['amount', 'order number', 'refunded to']],
            'payment_failed' => ['label' => 'Payment failed', 'audience' => 'Customer',
                'body' => 'Your payment of {{1}} for order #{{2}} could not be completed, so the order has not been placed yet. Please try the payment again using this link: {{3}} or choose another payment method in the app. If any amount was deducted from your account it will be refunded automatically.',
                'vars' => ['amount', 'order number', 'payment link']],
            'gold_welcome' => ['label' => 'Gold membership active', 'audience' => 'Customer',
                'body' => 'Welcome to Gold, {{1}}! Your membership is now active until {{2}}. Enjoy free delivery on every eligible order and exclusive member offers during this period. Open the app and look for the Gold badge at checkout to start saving on your next order.',
                'vars' => ['customer name', 'valid till']],
            'gold_expiring' => ['label' => 'Gold expiring soon', 'audience' => 'Customer',
                'body' => 'Hi {{1}}, your Gold membership will end on {{2}}. Renew it before it expires to keep enjoying free delivery on every order without any break. You can renew in just a few taps here: {{3}} and thank you for being a Gold member!',
                'vars' => ['customer name', 'end date', 'renew link']],
            'wallet_credit' => ['label' => 'Wallet credited', 'audience' => 'Customer',
                'body' => 'Good news! An amount of {{1}} has been added to your wallet and your new wallet balance is {{2}}. You can use this balance on your next order at checkout. Thank you for being with us and happy ordering.',
                'vars' => ['amount', 'balance']],
            'restaurant_new_order' => ['label' => 'New order', 'audience' => 'Restaurant',
                'body' => 'You have a new order! Order #{{1}} with {{2}} items and a total of {{3}} is waiting for you. Please open the restaurant app now to accept it and start preparing, so that the customer receives their food on time. You can also reply ACCEPT here.',
                'vars' => ['order number', 'item count', 'total']],
            'restaurant_order_cancelled' => ['label' => 'Order cancelled', 'audience' => 'Restaurant',
                'body' => 'Order #{{1}} has been cancelled by {{2}}. The reason given was: {{3}}. You do not need to prepare this order any more, and if it was already in preparation please check the restaurant app for the next steps. Thank you for your understanding.',
                'vars' => ['order number', 'cancelled by', 'reason']],
            'restaurant_rider_assigned' => ['label' => 'Rider assigned to your order', 'audience' => 'Restaurant',
                'body' => 'A delivery partner, {{1}}, has been assigned to pick up order #{{2}} and will reach your restaurant shortly. You can call the rider on {{3}} if needed. Please keep the order packed and ready for handover so that the delivery is not delayed.',
                'vars' => ['rider name', 'order number', 'rider phone']],
            'restaurant_approved' => ['label' => 'Restaurant approved', 'audience' => 'Restaurant',
                'body' => 'Congratulations! Your restaurant {{1}} has been approved and is now live on {{2}}. Open the app to set up your menu, timings and prices, and you can start receiving orders from customers right away. We wish you great sales!',
                'vars' => ['restaurant', 'app name']],
            'restaurant_status_changed' => ['label' => 'Restaurant opened / closed', 'audience' => 'Restaurant',
                'body' => 'Your restaurant {{1}} is now {{2}} on {{3}}. When it is closed you will not receive any new orders, and when it is open customers can order from you again. You can change this at any time from the restaurant app or by replying OPEN or CLOSE here.',
                'vars' => ['restaurant', 'OPEN or CLOSED', 'app name']],
            'driver_new_request' => ['label' => 'New delivery request', 'audience' => 'Driver',
                'body' => 'You have a new delivery request! Pick up from {{1}} and deliver to {{2}}, with expected earnings of {{3}}. Please open the delivery app now to accept it before it is offered to another delivery partner.',
                'vars' => ['pickup', 'drop area', 'earnings']],
            'driver_order_cancelled' => ['label' => 'Delivery cancelled', 'audience' => 'Driver',
                'body' => 'Order #{{1}} has been cancelled and you do not need to deliver it. The reason given was: {{2}}. Please check the delivery app for your next assignment. Thank you for your quick support and for being a valuable delivery partner.',
                'vars' => ['order number', 'reason']],
            'driver_status_changed' => ['label' => 'Driver online / offline', 'audience' => 'Driver',
                'body' => 'Hi {{1}}, you are now {{2}} on {{3}}. When you are online you will receive delivery requests nearby, and when you are offline no new requests will be sent to you. You can change this at any time from the delivery app or by replying ONLINE or OFFLINE here.',
                'vars' => ['name', 'ONLINE or OFFLINE', 'app name']],
            'payout_paid' => ['label' => 'Payout paid', 'audience' => 'Restaurant / Driver / Franchise',
                'body' => 'Good news! Your payout of {{1}} has been sent to your {{2}} and should reflect shortly. The payment reference is {{3}}, which you can share with your bank if you need to trace it. Thank you for being a valued partner.',
                'vars' => ['amount', 'account', 'reference']],
            'franchise_credentials' => ['label' => 'Franchise login ready', 'audience' => 'Franchise',
                'body' => 'Welcome {{1}}! Your franchise account is ready and your Franchise ID is {{2}}. You can sign in at any time at {{3}} using your registered mobile number and a one-time code. We have also emailed you the full details. Thank you for partnering with us.',
                'vars' => ['name', 'franchise id', 'login link']],
            'franchise_renewal' => ['label' => 'Franchise renewal due', 'audience' => 'Franchise',
                'body' => 'Hi {{1}}, your franchise plan renews on {{2}}. Please pay the renewal invoice before that date to keep your franchise active without any interruption. You can view and pay it securely here: {{3}} and thank you for being our partner.',
                'vars' => ['name', 'renewal date', 'pay link']],
            'bot_menu' => ['label' => 'Chat menu (reopen conversation)', 'audience' => 'Customer',
                'body' => 'Hi {{1}}, welcome to {{2}}! Reply MENU at any time to order food, track an order, check your wallet and offers, or talk to our team. We are happy to help you with anything you need.',
                'vars' => ['name', 'app name']],
        ];
    }
}
