<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewInquiryNotification extends Notification
{
    public function __construct(
        public Inquiry $inquiry,
        public string $url,
        public ?string $title = null,
        public ?string $body = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        $title = $this->inquiry->property?->title ?? 'a property';

        return [
            'title' => $this->title ?? 'New inquiry from '.$this->inquiry->name,
            'body' => $this->body ?? Str::limit($this->inquiry->name.' asked about '.$title.': '.$this->inquiry->message, 200),
            'url' => $this->url,
        ];
    }
}
