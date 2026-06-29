@component('mail::message')
# Role Request Approved

Hello {{ $userName }},

We are pleased to inform you that your request to become a **{{ ucwords(str_replace('_', ' ', $roleName)) }}** has been **approved**!

You now have the necessary permissions and access to your new role features.

@component('mail::button', ['url' => url('/dashboard')])
Go to Dashboard
@endcomponent

Thank you,<br>
{{ config('app.name') }} Team
@endcomponent