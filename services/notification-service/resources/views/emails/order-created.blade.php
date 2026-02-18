<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
        .order-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #4CAF50; }
        .item { padding: 10px 0; border-bottom: 1px solid #eee; }
        .total { font-size: 18px; font-weight: bold; color: #4CAF50; margin-top: 15px; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>✓ Order Confirmed!</h1>
    </div>

    <div class="content">
        <p>Hello,</p>
        <p>Thank you for your order! Your order has been confirmed and is being processed.</p>

        <div class="order-details">
            <h3>Order Details</h3>
            <p><strong>Order Number:</strong> {{ $order['order_number'] }}</p>
            <p><strong>Status:</strong> {{ ucfirst($order['status']) }}</p>
            <p><strong>Order Date:</strong> {{ \Carbon\Carbon::parse($order['created_at'])->format('F j, Y g:i A') }}</p>

            @if(isset($order['items']) && count($order['items']) > 0)
                <h4>Items:</h4>
                @foreach($order['items'] as $item)
                    <div class="item">
                        <strong>{{ $item['product_name'] }}</strong><br>
                        Quantity: {{ $item['quantity'] }} × ${{ number_format($item['price'], 2) }} = ${{ number_format($item['subtotal'], 2) }}
                    </div>
                @endforeach
            @endif

            <p class="total">Total: ${{ number_format($order['total_amount'], 2) }}</p>
        </div>

        <p>We'll send you another email when your order ships.</p>
        <p>Thank you for shopping with us!</p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} E-commerce Platform. All rights reserved.</p>
    </div>
</div>
</body>
</html>
