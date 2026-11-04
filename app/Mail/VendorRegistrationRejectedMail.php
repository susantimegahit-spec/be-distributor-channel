<?php

namespace App\Mail;

use App\Modules\VendorPortal\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorRegistrationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Vendor $vendor;
    public string $rejectionReason;

    /**
     * Create a new message instance.
     */
    public function __construct(Vendor $vendor, string $rejectionReason)
    {
        $this->vendor = $vendor;
        $this->rejectionReason = $rejectionReason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('mail.from.address', 'noreply@susantimegah.com'),
                'SMESTA Vendor & Logistics Partner Portal'
            ),
            subject: '[SMESTA PT Susanti Megah] Vendor Registration Application Update (' . $this->vendor->vendor_code . ')',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.vendor_rejected',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
