<?php

namespace App\Engine\Nodes\Apps\Stripe;

use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\Nodes\Apps\AppNode;
use Illuminate\Http\Client\PendingRequest;

class StripeNode extends AppNode
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    protected function execute(string $operation, NodeInput $input): NodeResult
    {
        return match ($operation) {
            'create_customer' => $this->createCustomer($input),
            'create_invoice' => $this->createInvoice($input),
            'create_payment_intent', 'create_charge' => $this->createCharge($input),
            'list_payments' => $this->listPayments($input),
            'retrieve_customer' => $this->retrieveCustomer($input),
            'get_balance' => $this->getBalance($input),
            'create_subscription' => $this->createSubscription($input),
            'list_subscriptions' => $this->listSubscriptions($input),
            'cancel_subscription' => $this->cancelSubscription($input),
            'create_product' => $this->createProduct($input),
            'create_price' => $this->createPrice($input),
            default => $this->fail("Stripe: unknown operation '{$operation}'"),
        };
    }

    private function stripeHttp(NodeInput $input): PendingRequest
    {
        $apiKey = $input->credentials['api_key'] ?? $input->credentials['secret_key'] ?? '';

        return $this->http()
            ->baseUrl(self::BASE_URL)
            ->withBasicAuth($apiKey, '')
            ->asForm();
    }

    private function createCustomer(NodeInput $input): NodeResult
    {
        $response = $this->stripeHttp($input)->post('/customers', [
            'email' => $input->config['email'],
            'name' => $input->config['name'] ?? null,
            'metadata' => $input->config['metadata'] ?? [],
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe create_customer failed: {$response->body()}");
    }

    private function createInvoice(NodeInput $input): NodeResult
    {
        $response = $this->stripeHttp($input)->post('/invoices', [
            'customer' => $input->config['customer_id'],
            'auto_advance' => $input->config['auto_advance'] ?? true,
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe create_invoice failed: {$response->body()}");
    }

    private function createCharge(NodeInput $input): NodeResult
    {
        $response = $this->stripeHttp($input)->post('/payment_intents', [
            'amount' => $input->config['amount'],
            'currency' => $input->config['currency'] ?? 'usd',
            'customer' => $input->config['customer_id'] ?? null,
            'payment_method' => $input->config['payment_method'] ?? null,
            'confirm' => $input->config['confirm'] ?? false,
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe create_charge failed: {$response->body()}");
    }

    private function listPayments(NodeInput $input): NodeResult
    {
        $response = $this->stripeHttp($input)->get('/payment_intents', array_filter([
            'customer' => $input->config['customer_id'] ?? null,
            'limit' => $input->config['limit'] ?? 10,
        ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe list_payments failed: {$response->body()}");
    }

    private function getBalance(NodeInput $input): NodeResult
    {
        $response = $this->stripeHttp($input)->get('/balance');

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe get_balance failed: {$response->body()}");
    }

    private function createSubscription(NodeInput $input): NodeResult
    {
        $response = $this->stripeHttp($input)->post('/subscriptions', array_filter([
            'customer' => $input->config['customer_id'],
            'items' => [['price' => $input->config['price_id']]],
            'trial_period_days' => $input->config['trial_days'] ?? null,
            'currency' => $input->config['currency'] ?? null,
        ]));

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe create_subscription failed: {$response->body()}");
    }

    private function retrieveCustomer(NodeInput $input): NodeResult
    {
        $customerId = $input->config['customer_id'];
        $response = $this->stripeHttp($input)->get("/customers/{$customerId}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe retrieve_customer failed: {$response->body()}");
    }

    private function listSubscriptions(NodeInput $input): NodeResult
    {
        $response = $this->stripeHttp($input)->get('/subscriptions', [
            'customer' => $input->config['customer_id'] ?? null,
            'status' => $input->config['status'] ?? 'active',
            'limit' => $input->config['limit'] ?? 10,
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe list_subscriptions failed: {$response->body()}");
    }

    private function cancelSubscription(NodeInput $input): NodeResult
    {
        $subscriptionId = $input->config['subscription_id'];
        $response = $this->stripeHttp($input)->delete("/subscriptions/{$subscriptionId}");

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe cancel_subscription failed: {$response->body()}");
    }

    private function createProduct(NodeInput $input): NodeResult
    {
        $response = $this->stripeHttp($input)->post('/products', [
            'name' => $input->config['name'],
            'description' => $input->config['description'] ?? null,
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe create_product failed: {$response->body()}");
    }

    private function createPrice(NodeInput $input): NodeResult
    {
        $response = $this->stripeHttp($input)->post('/prices', [
            'unit_amount' => $input->config['unit_amount'],
            'currency' => $input->config['currency'] ?? 'usd',
            'product' => $input->config['product_id'],
            'recurring' => $input->config['recurring'] ?? null,
        ]);

        return $response->successful()
            ? $this->success($response->json())
            : $this->fail("Stripe create_price failed: {$response->body()}");
    }
}
