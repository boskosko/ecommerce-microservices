<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f44336; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
        .order-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #f44336; }
        .total { font-size: 18px; font-weight: bold; color: #f44336; margin-top: 15px; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>✗ Order Cancelled</h1>
    </div>

    <div class="content">
        <p>Hello,</p>
        <p>Your order has been cancelled as requested.</p>

        <div class="order-details">
            <h3>Cancelled Order Details</h3>
            <p><strong>Order Number:</strong> {{ $order['order_number'] ?? 'N/A' }}</p>
            <p><strong>Status:</strong> Cancelled</p>

            <p class="total">Refund Amount: ${{ number_format($order['total_amount'] ?? 0, 2) }}</p>
        </div>

        <p>Your refund will be processed within 5-7 business days.</p>
        <p>If you have any questions, please don't hesitate to contact us.</p>
        <p>We hope to serve you again soon!</p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} E-commerce Platform. All rights reserved.</p>
    </div>
</div>
</body>
</html>
