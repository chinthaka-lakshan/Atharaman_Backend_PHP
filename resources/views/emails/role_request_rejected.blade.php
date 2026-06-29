@component('mail::message')
# Update on Your Role Request

Hello {{ $userName }},

We regret to inform you that your request to become a **{{ ucwords(str_replace('_', ' ', $roleName)) }}** has been **rejected**.

This may be due to incomplete documentation, mismatched details, or other administrative reasons. Please review your application details and resubmit if necessary, or contact support for more information.

We appreciate your interest.

@component('mail::button', ['url' => url('/contact-us')])
Contact Support
@endcomponent

Sincerely,<br>
{{ config('app.name') }} Team
@endcomponent