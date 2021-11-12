<?php
declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Class ImportFinished
 */
class ImportFinished extends Mailable
{
    use Queueable, SerializesModels;

    public $time;
    public $errors;
    public $warnings;
    public $messages;
    public $url;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $log)
    {
        $this->time     = date('Y-m-d \@ H:i:s');
        $this->url      = config('csv_importer.url');
        $this->errors   = $log['errors'] ?? [];
        $this->warnings = $log['warnings'] ?? [];
        $this->messages = $log['messages'] ?? [];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $address = (string) config('mail.from.address');
        $name    = (string) config('mail.from.name');

        return $this->from($address, $name)->markdown('emails.import.finished');
    }
}
