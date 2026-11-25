<?php

namespace App\Notifications;

use App\Models\MembershipApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class MembershipApplicationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public int $applicationId;

    public string $fullName;

    public string $referenceNo;

    public string $applicationStatus;

    public function __construct(MembershipApplication $application)
    {
        // Store the submission details as they were when submitted.
        $this->applicationId = $application->id;
        $this->fullName = $application->full_name;
        $this->referenceNo = $application->reference_no;
        $this->applicationStatus = $application->status;

        $this->onQueue('membership-mail');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Membership application received: '.$this->referenceNo)
            ->greeting('Hello '.$this->fullName.',')
            ->line('We have received your membership application.')
            ->line('Reference number: '.$this->referenceNo)
            ->line('Application status: '.ucfirst($this->applicationStatus))
            ->line('Please keep your reference number for future inquiries.')
            ->line('Our staff will review your application.')
            ->salutation('Membership Office');
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Membership application email failed after retries.', [
            'application_id' => $this->applicationId,
            'reference_no' => $this->referenceNo,
            'exception_type' => $exception::class,
        ]);
    }
}