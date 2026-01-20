<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket - <?php echo e($orderItem->ticket_code); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background: #fff;
            color: #1a1a1a;
            font-size: 12px;
            line-height: 1.4;
        }

        .ticket {
            width: 100%;
            height: 100%;
            padding: 24px;
            display: flex;
            flex-direction: column;
        }

        .header {
            text-align: center;
            padding-bottom: 16px;
            border-bottom: 2px solid #1a1a1a;
            margin-bottom: 16px;
        }

        .event-name {
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .event-datetime {
            font-size: 13px;
            color: #4a4a4a;
            margin-bottom: 4px;
        }

        .event-location {
            font-size: 11px;
            color: #6a6a6a;
        }

        .section {
            margin-bottom: 16px;
        }

        .section-label {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #8a8a8a;
            margin-bottom: 4px;
        }

        .section-value {
            font-size: 14px;
            font-weight: 600;
        }

        .attendee-name {
            font-size: 18px;
            font-weight: 700;
        }

        .ticket-type {
            font-size: 14px;
            font-weight: 600;
            color: #2d5a27;
        }

        .qr-section {
            text-align: center;
            padding: 20px 0;
            border-top: 1px dashed #ccc;
            border-bottom: 1px dashed #ccc;
            margin: 16px 0;
        }

        .qr-code {
            margin-bottom: 8px;
        }

        .qr-code img {
            width: 150px;
            height: 150px;
        }

        .ticket-code {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .footer {
            margin-top: auto;
            text-align: center;
            padding-top: 16px;
        }

        .order-number {
            font-size: 10px;
            color: #8a8a8a;
            margin-bottom: 8px;
        }

        .instructions {
            font-size: 10px;
            color: #6a6a6a;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <!-- Header: Event Info -->
        <div class="header">
            <div class="event-name"><?php echo e($event->name); ?></div>
            <div class="event-datetime">
                <?php echo e(\Carbon\Carbon::parse($event->starts_at)->setTimezone($event->timezone ?? 'America/Mexico_City')->format('l, F j, Y')); ?>

                &middot;
                <?php echo e(\Carbon\Carbon::parse($event->starts_at)->setTimezone($event->timezone ?? 'America/Mexico_City')->format('g:i A')); ?>

            </div>
            <?php if($event->location_type === 'venue' && $event->location): ?>
                <div class="event-location">
                    <?php echo e($event->location['name'] ?? ''); ?>

                    <?php if(!empty($event->location['address'])): ?>
                        <br><?php echo e($event->location['address']); ?><?php if(!empty($event->location['city'])): ?>, <?php echo e($event->location['city']); ?><?php endif; ?> <?php if(!empty($event->location['state'])): ?>, <?php echo e($event->location['state']); ?><?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php elseif($event->location_type === 'online'): ?>
                <div class="event-location">Online Event</div>
            <?php endif; ?>
        </div>

        <!-- Attendee Section -->
        <div class="section">
            <div class="section-label">Attendee</div>
            <div class="attendee-name"><?php echo e($orderItem->attendee_name ?: $order->customer_name); ?></div>
        </div>

        <!-- Ticket Type Section -->
        <div class="section">
            <div class="section-label">Ticket Type</div>
            <div class="ticket-type">
                <?php if($orderItem->ticketTier): ?>
                    <?php echo e($orderItem->ticketTier->name); ?>

                <?php elseif($orderItem->table && $orderItem->seat): ?>
                    <?php echo e($orderItem->table->name); ?> &middot; Seat <?php echo e($orderItem->seat->label); ?>

                <?php elseif($orderItem->table): ?>
                    <?php echo e($orderItem->table->name); ?>

                <?php else: ?>
                    <?php echo e($orderItem->item_name ?: 'General Admission'); ?>

                <?php endif; ?>
            </div>
        </div>

        <!-- QR Code Section -->
        <div class="qr-section">
            <div class="qr-code">
                <img src="<?php echo e($qrCode); ?>" alt="QR Code">
            </div>
            <div class="ticket-code"><?php echo e($orderItem->ticket_code); ?></div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="order-number">Order: <?php echo e($order->order_number); ?></div>
            <div class="instructions">Present this ticket at entry</div>
        </div>
    </div>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/pdf/ticket.blade.php ENDPATH**/ ?>