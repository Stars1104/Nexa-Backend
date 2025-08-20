<?php

namespace App\Mail;

use App\Models\DeliveryMaterial;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeliveryMaterialApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $material;
    public $contract;
    public $creator;
    public $brand;

    /**
     * Create a new message instance.
     */
    public function __construct(DeliveryMaterial $material)
    {
        $this->material = $material;
        $this->contract = $material->contract;
        $this->creator = $material->creator;
        $this->brand = $material->brand;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Material Aprovado - Nexa Platform',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.delivery-material-approved',
            with: [
                'material' => $this->material,
                'contract' => $this->contract,
                'creator' => $this->creator,
                'brand' => $this->brand,
            ],
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