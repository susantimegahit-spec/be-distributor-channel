<?php

namespace App\Mail;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class FinanceApprovedNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public SalesOrder $salesOrder;

    /**
     * Create a new message instance.
     */
    public function __construct(SalesOrder $salesOrder)
    {
        $this->salesOrder = $salesOrder;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Selesai Disetujui (ORDER APPROVED) - ' . $this->salesOrder->order_no,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.finance_approved_notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // 1. Generate PI PDF dynamically
        try {
            $pdfGenerator = new \App\Services\ProformaInvoicePdfGenerator();
            $pdfData = $pdfGenerator->generate($this->salesOrder);
            $fileName = 'PI-' . ($this->salesOrder->order_no ?: 'order') . '.pdf';
            
            $attachments[] = Attachment::fromData(fn() => $pdfData, $fileName)
                ->withMime('application/pdf');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate PI PDF for email: " . $e->getMessage());
        }

        // 2. Also attach other documents uploaded by the user
        foreach ($this->salesOrder->attachments as $att) {
            $filePath = storage_path('app/public/' . $att->file_path);
            if (file_exists($filePath)) {
                $attachments[] = Attachment::fromPath($filePath)
                    ->as($att->file_name)
                    ->withMime($att->file_type);
            }
        }
        return $attachments;
    }
}
