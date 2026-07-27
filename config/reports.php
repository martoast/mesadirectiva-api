<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Summary report columns (coordinadoras de sección / role: viewer)
    |--------------------------------------------------------------------------
    |
    | The reduced, ready-to-use report. Key => column label. Remove or reorder
    | entries to change what coordinators see — no code changes needed.
    | Available keys: event, group, attendee_name, student_key, note, tier,
    | quantity, total, date.
    |
    | Admins and super admins always get the full report (order number, time,
    | transaction id, Stripe account, customer contact data).
    |
    */

    'summary_columns' => [
        'event' => 'Evento',
        'attendee_name' => 'Alumno / Asistente',
        'student_key' => 'Clave',
        'note' => 'Nota (salón, generación)',
        'tier' => 'Pago / Boleto',
        'quantity' => 'Cantidad',
        'total' => 'Total',
        'date' => 'Fecha de pago',
    ],

];
