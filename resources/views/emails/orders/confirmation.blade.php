<x-mail::message>
# Order Confirmed!

Thank you for your purchase, {{ $order->customer_name }}!

## Order Details

**Order Number:** {{ $order->order_number }}

**Event:** {{ $event->name }}

**Date:** {{ $event->date->format('F j, Y') }} at {{ $event->time }}

**Location:** {{ $event->location }}

---

## Items Purchased

<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach($order->items as $item)
| {{ $item->item_name }} | {{ $item->quantity }} | ${{ number_format($item->total_price, 2) }} |
@endforeach
| **Total** | | **${{ number_format($order->total, 2) }}** |
</x-mail::table>

---

Please save this email as your receipt. You may be asked to show it at the event.

If you have any questions, please reply to this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
