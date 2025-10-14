<?php

namespace App\Mail;

use App\Models\RoleRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RoleRequestApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $roleRequest;

    /**
     * Create a new message instance.
     */
    public function __construct(RoleRequest $roleRequest)
    {
        $this->roleRequest = $roleRequest;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations! Your Role Request Has Been Approved!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.role_request_approved', // We'll create this view next
            with: [
                'roleName' => $this->roleRequest->role->name,
                'userName' => $this->roleRequest->user->name ?? $this->roleRequest->user->email,
            ],
        );
    }
}