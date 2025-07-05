            'amount' => $amountInCents,
            'currency' => 'eur',
            'description' => $description,
            'invoice' => $invoice->id,
        ]);

        // Finalize the invoice (optional, but good practice if not auto-finalizing)
        $invoice = \Stripe\Invoice::retrieve($invoice->id); 