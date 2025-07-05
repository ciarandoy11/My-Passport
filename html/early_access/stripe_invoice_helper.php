<?php
require_once __DIR__ . '/vendor/autoload.php';

function createStripeInvoice($stripeSecret, $customerData, $amount, $currency, $description, $metadata = []) {
    try {
        \Stripe\Stripe::setApiKey($stripeSecret);

        // Try to find existing customer by email
        $customers = \Stripe\Customer::all(['email' => $customerData['email'], 'limit' => 1]);
        if (count($customers->data) > 0) {
            $stripeCustomer = $customers->data[0];
        } else {
            $stripeCustomer = \Stripe\Customer::create([
                'email' => $customerData['email'],
                'name' => $customerData['name'],
                'metadata' => $metadata
            ]);
        }

        $amountInCents = (int)($amount * 100);

        // Create invoice (this will automatically pick up all unbilled invoice items for this customer)
        $invoice = \Stripe\Invoice::create([
            'customer' => $stripeCustomer->id,
            'auto_advance' => true,
            'description' => $description,
            'metadata' => $metadata
        ]);

        // Create invoice item (this adds it to the customer's unbilled items)
        \Stripe\InvoiceItem::create([
            'customer' => $stripeCustomer->id,
            'amount' => $amountInCents, // Use the calculated integer amount
            'currency' => $currency,
            'description' => $description,
            'invoice' => $invoice->id, // Explicitly link to the created invoice
        ]);

        return [
            'success' => true,
            'invoice_id' => $invoice->id,
            'customer_id' => $stripeCustomer->id,
            'status' => $invoice->status,
            'amount_passed_to_stripe' => $amount, // original amount for debugging
            'amount_in_cents_passed_to_stripe' => $amountInCents // final integer amount for debugging
        ];
    } catch (\Exception $e) {
        $amountInCents = (int)($amount * 100); // Calculate even on error for debugging
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'amount_passed_to_stripe' => $amount,
            'amount_in_cents_calculated' => $amountInCents
        ];
    }
} 