<?php

namespace App\Mail;

use App\Modules\VendorPortal\Models\Vendor;
use App\Modules\VendorPortal\Models\VendorUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public Vendor $vendor;
    public VendorUser $vendorUser;
    public string $plainPassword;
    public string $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Vendor $vendor, VendorUser $vendorUser, string $plainPassword, ?string $loginUrl = null)
    {
        $this->vendor = $vendor;
        $this->vendorUser = $vendorUser;
        $this->plainPassword = $plainPassword;
        $this->loginUrl = $loginUrl ?: url('/vendor-portal');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[SMESTA PT Susanti Megah] Vendor Registration Approved & Portal Login Credentials (' . $this->vendor->vendor_code . ')',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.vendor_credentials',
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
