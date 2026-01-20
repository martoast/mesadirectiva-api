<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tus Boletos</title>
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
            Hola {{ $order->customer_name }},
        </div>

        <div class="intro">
            Tus boletos están adjuntos a este correo.
        </div>

        <div class="section-title">Detalles del Evento</div>
        <div class="event-details">
            <div class="event-name">{{ $event->name }}</div>
            <div class="event-info">
                {{ \Carbon\Carbon::parse($event->starts_at)->setTimezone($event->timezone ?? 'America/Mexico_City')->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
                a las
                {{ \Carbon\Carbon::parse($event->starts_at)->setTimezone($event->timezone ?? 'America/Mexico_City')->format('g:i A') }}
            </div>
            @if($event->location_type === 'venue' && $event->location)
                <div class="event-info">
                    {{ $event->location['name'] ?? '' }}
                    @if(!empty($event->location['address']))
                        - {{ $event->location['address'] }}@if(!empty($event->location['city'])), {{ $event->location['city'] }}@endif
                    @endif
                </div>
            @elseif($event->location_type === 'online')
                <div class="event-info">Evento en línea</div>
            @endif
        </div>

        <div class="section-title">Tus Boletos ({{ $ticketItems->count() }})</div>
        <div class="tickets-list">
            @foreach($ticketItems as $item)
                <div class="ticket-item">
                    <div class="ticket-bullet"></div>
                    <div class="ticket-info">
                        <span class="ticket-attendee">{{ $item->attendee_name ?: $order->customer_name }}</span>
                        <span class="ticket-type">
                            -
                            @if($item->ticketTier)
                                {{ $item->ticketTier->name }}
                            @elseif($item->table && $item->seat)
                                {{ $item->table->name }} · Asiento {{ $item->seat->label }}
                            @elseif($item->table)
                                {{ $item->table->name }}
                            @else
                                {{ $item->item_name ?: 'Entrada General' }}
                            @endif
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="order-number">
            Orden #: {{ $order->order_number }}
        </div>

        <div class="instructions">
            Por favor ten tu boleto (impreso o en tu teléfono) listo en la entrada.
        </div>

        <div class="footer">
            ¡Te esperamos!
        </div>
    </div>
</body>
</html>
