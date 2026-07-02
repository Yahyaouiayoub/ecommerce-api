<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;

class LogSentEmail
{
    /**
     * Handle the MessageSent event.
     *
     * Automatically logs every outgoing email to the email_logs table
     * so administrators can track delivery history.
     */
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $recipients = $this->getRecipients($message);
            $subject = $message->getSubject() ?? '(no subject)';
            $mailable = $event->data['__laravel_mailable'] ?? null;

            // Determine the mailable class name
            $mailableType = null;
            $relatedType = null;
            $relatedId = null;

            if ($mailable) {
                $mailableType = get_class($mailable);

                // Extract the related model from the mailable (e.g. Order, Invoice, Refund)
                // Common convention: mailable stores the model as a public property
                $publicProps = (new \ReflectionObject($mailable))->getProperties(\ReflectionProperty::IS_PUBLIC);
                foreach ($publicProps as $prop) {
                    $value = $prop->getValue($mailable);
                    if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                        // Skip Setting, User if linked to order/invoice context
                        if ($value instanceof \App\Models\Setting) continue;

                        $relatedType = get_class($value);
                        $relatedId = $value->getKey();
                        break;
                    }
                }
            }

            $mailerDriver = config('mail.default', 'unknown');

            foreach ($recipients as $email) {
                EmailLog::create([
                    'recipient_email' => $email,
                    'subject'         => $subject,
                    'mailable_type'   => $mailableType,
                    'mailer_driver'   => $mailerDriver,
                    'status'          => 'sent',
                    'headers'         => [
                        'message_id' => $this->getMessageId($message),
                    ],
                    'related_type'    => $relatedType,
                    'related_id'      => $relatedId,
                ]);
            }
        } catch (\Exception $e) {
            // Don't let logging failures break email delivery
            logger()->warning('Failed to log email delivery', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract recipient email addresses from the message.
     */
    private function getRecipients($message): array
    {
        $emails = [];
        $tos = $message->getTo() ?? [];
        foreach ($tos as $addr) {
            $emails[] = $addr->getAddress();
        }
        return array_unique(array_filter($emails));
    }

    /**
     * Safely extract the Message-ID from the Symfony Email object.
     *
     * getMessageId() is not available in all Symfony Mime versions,
     * so fall back to reading the MessageId header directly.
     */
    private function getMessageId($message): ?string
    {
        if (method_exists($message, 'getMessageId')) {
            return $message->getMessageId();
        }
        try {
            $header = $message->getHeaders()->get('MessageId');
            return $header ? $header->getBody() : null;
        } catch (\Exception) {
            return null;
        }
    }
}
