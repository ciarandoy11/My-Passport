<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Create Stripe Invoice</title>
        <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; color: #333; } .form-container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #fff; border: 1px solid #ccc; border-radius: 8px; } h1 { color: #002061; text-align: center; } label { display: block; margin: 10px 0 5px; } input, textarea { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; } .btn { background-color: #3241FF; color: white; padding: 10px 15px; border: none; cursor: pointer; } .btn:hover { background-color: #565656; }
        </style>
    </head>
    <body>
        <div class="form-container">
            <h1>Create Stripe Invoice</h1>
            <form id="invoiceForm">
                <label for="customer_name">Customer Name</label>
                <input type="text" id="customer_name" name="customer_name" required>
                <label for="customer_email">Customer Email</label>
                <input type="email" id="customer_email" name="customer_email" required>
                <h3>Items</h3>
                <div id="items-container">
                    <div class="item-container">
                        <label for="description">Description</label>
                        <input type="text" class="description" required>
                        <label for="quantity">Quantity</label>
                        <input type="number" class="quantity" required>
                        <label for="unit_price">Unit Price (EUR)</label>
                        <input type="number" class="unit_price" required>
                    </div>
                </div>
                <button type="button" id="addItem" class="btn">Add Item</button>
                <h3>Payment Method</h3>
                <input type="text" id="payment_method" placeholder="Enter Payment Method (Optional)">
                <button type="submit" class="btn">Create Invoice</button>
            </form>
            <div id="invoiceResponse"></div>
        </div>
        <script>
            document.getElementById('addItem').addEventListener('click', function() {
                let itemContainer = document.createElement('div');
                itemContainer.classList.add('item-container');
                itemContainer.innerHTML = ` <label for="description">Description</label> <input type="text" class="description" required> <label for="quantity">Quantity</label> <input type="number" class="quantity" required> <label for="unit_price">Unit Price (USD)</label> <input type="number" class="unit_price" required> `;

                document.getElementById('items-container').appendChild(itemContainer);
            });

            document.getElementById('invoiceForm').addEventListener('submit', function(event) {
                event.preventDefault();
                const customer_name = document.getElementById('customer_name').value;
                const customer_email = document.getElementById('customer_email').value;
                const payment_method = document.getElementById('payment_method').value;
                let items = [];
                document.querySelectorAll('.item-container').forEach(item => {
                    const description = item.querySelector('.description').value;
                    const quantity = parseFloat(item.querySelector('.quantity').value);
                    const unit_price = parseFloat(item.querySelector('.unit_price').value);
                    items.push({ description, quantity, unit_price });
                });
                fetch('https://podrota-ciarandoy.eu1.pitunnel.net/invoice-server/createInvoiceServer.js', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_name, customer_email, items, payment_method }),
                })
                .then(response => response.json()) .then(data => {
                    if (data.invoice) {
                        document.getElementById('invoiceResponse').innerText = `Invoice Created: ${data.invoice.id}`;
                    } else {
                        document.getElementById('invoiceResponse').innerText = `Error: ${data.error}`;
                    }
                })
                .catch(error => {
                    document.getElementById('invoiceResponse').innerText = `Error: ${error.message}`;
                });
            });
        </script>
    </body>
</html>
