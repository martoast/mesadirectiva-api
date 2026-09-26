<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de compra</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #1a1a1a;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .greeting {
            font-size: 18px;
            margin-bottom: 24px;
        }

        .intro {
            margin-bottom: 24px;
            color: #4a4a4a;
        }

        .section-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #8a8a8a;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 8px;
        }

        .event-details {
            background-color: #fafafa;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .event-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .event-info {
            font-size: 14px;
            color: #4a4a4a;
            margin-bottom: 4px;
        }

        .tickets-list {
            margin-bottom: 24px;
        }

        .ticket-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .ticket-item:last-child {
            border-bottom: none;
        }

        .ticket-bullet {
            width: 8px;
            height: 8px;
            background-color: #2d5a27;
            border-radius: 50%;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .ticket-info {
            font-size: 14px;
        }

        .ticket-attendee {
            font-weight: 600;
        }

        .ticket-type {
            color: #6a6a6a;
        }

        .order-number {
            font-size: 13px;
            color: #8a8a8a;
            margin-bottom: 24px;
        }

        .instructions {
            background-color: #f0f7f0;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 14px;
            color: #2d5a27;
        }

        .item-price {
            margin-left: auto;
            padding-left: 12px;
            font-size: 14px;
            white-space: nowrap;
        }

        .item-meta {
            display: block;
            font-size: 13px;
            color: #6a6a6a;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            font-weight: 700;
            padding: 12px 0 0;
            margin-bottom: 24px;
            border-top: 1px solid #e5e5e5;
        }

        .footer {
            font-size: 13px;
            color: #8a8a8a;
            text-align: center;
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #e5e5e5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="greeting">
            {{ $emailGreeting }}
        </div>

        <div class="intro">
            {{ $emailIntro }}
        </div>

        <div class="section-title">Producto</div>
        <div class="event-details">
            <div class="event-name">{{ $event->name }}</div>
            @if($pickupLocation)
                <div class="event-info">Entrega: {{ $pickupLocation }}</div>
            @endif
        </div>

        <div class="section-title">Tu compra</div>
        <div class="tickets-list">
            @foreach($lineItems as $item)
                <div class="ticket-item">
                    <div class="ticket-bullet"></div>
                    <div class="ticket-info">
                        <span class="ticket-attendee">
                            @if($item->quantity > 1){{ $item->quantity }} × @endif{{ $item->ticketTier->name ?? $item->item_name }}
                        </span>
                        @if($item->attendee_name || $item->student_key)
                            <span class="item-meta">
                                {{ $item->attendee_name }}@if($item->attendee_name && $item->student_key) · @endif{{ $item->student_key ? 'Clave ' . $item->student_key : '' }}
                            </span>
                        @endif
                        @if($item->attendee_note)
                            <span class="item-meta">{{ $item->attendee_note }}</span>
                        @endif
                    </div>
                    <div class="item-price">${{ number_format($item->total_price, 2) }}</div>
                </div>
            @endforeach
        </div>

        <div class="total-row">
            <span>Total</span>
            <span>${{ number_format($order->total, 2) }} MXN</span>
        </div>

        <div class="order-number">
            Orden #: {{ $order->order_number }}
        </div>

        @if($emailInstructions)
            <div class="instructions">
                {{ $emailInstructions }}
            </div>
        @endif

        <div class="footer">
            {{ $emailFooter }}
        </div>
    </div>
</body>
</html>
