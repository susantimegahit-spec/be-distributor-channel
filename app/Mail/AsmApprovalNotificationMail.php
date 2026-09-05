<?php

namespace App\Mail;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AsmApprovalNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public SalesOrder $salesOrder;
    public string $approveUrl;
    public string $rejectUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(SalesOrder $salesOrder, int $asmUserId)
    {
        $this->salesOrder = $salesOrder;

        // Generate temporary signed URLs for quick action from email (valid for 3 days)
        $this->approveUrl = URL::temporarySignedRoute(
            'sales-orders.email-action',
            now()->addDays(3),
            [
                'id' => $salesOrder->id,
                'action' => 'approve',
                'user_id' => $asmUserId,
            ]
        );

        $this->rejectUrl = URL::temporarySignedRoute(
            'sales-orders.email-action',
            now()->addDays(3),
            [
                'id' => $salesOrder->id,
                'action' => 'reject',
                'user_id' => $asmUserId,
            ]
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Persetujuan Sales Order Baru - ' . $this->salesOrder->order_no,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.asm_approval_notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
