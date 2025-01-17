<?php

namespace App\Notifications\Agent\Remittance;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SenderEmail extends Notification
{
    use Queueable;

    public $user;
    public $data;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($user,$data)
    {
        $this->user = $user;
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $user = $this->user;
        $data = $this->data;
        $trx_id = $this->data->trx_id;
        $date = Carbon::now();
        $dateTime = $date->format('Y-m-d h:i:s A');
        if($data->transaction_type == 'bank-transfer') {
            return (new MailMessage())
                        ->greeting("Hello ".$user->fullname." !")
                        ->subject($data->title)
                        ->line("Send Remittance request to send admin successfully, details of Send Remittance:")
                        ->line($data->title)
                        ->line("Transaction Id: " .$trx_id)
                        ->line("Transaction Type: " . ucwords(str_replace('-', ' ', @$data->transaction_type)))
                        ->line("Request Amount: " . $data->request_amount)
                        ->line("Exchange Rate: " . $data->exchange_rate)
                        ->line("Fees & Charges: " . $data->charges)
                        ->line("Total Payable Amount: " . $data->payable)
                        ->line("Sending Country: " . $data->sending_country)
                        ->line("Receiving Country: " . $data->receiving_country)
                        ->line("Sender Recipient Name: " . $data->sender_recipient_name)
                        ->line("Receiver Recipient Name: " . $data->receiver_recipient_name)
                        ->line("Bank Name: " . $data->alias)
                        ->line("Receiver Will Get: " . $data->receiver_get)
                        ->line("Status: ". $data->status)
                        ->line("Date And Time: " .$dateTime)
                        ->line('Thank you for using our application!');
        }else if($data->transaction_type == 'wallet-to-wallet-transfer'){

            return (new MailMessage())
                    ->greeting("Hello ".$user->fullname." !")
                    ->subject($data->title)
                    ->line("Send Remittance request to send successfully, details of Send Remittance:")
                    ->line($data->title)
                    ->line("Transaction Id: " .$trx_id)
                    ->line("Transaction Type: " . ucwords(str_replace('-', ' ', @$data->transaction_type)))
                    ->line("Request Amount: " . $data->request_amount)
                    ->line("Exchange Rate: " . $data->exchange_rate)
                    ->line("Fees & Charges: " . $data->charges)
                    ->line("Total Payable Amount: " . $data->payable)
                    ->line("Sending Country: " . $data->sending_country)
                    ->line("Receiving Country: " . $data->receiving_country)
                    ->line("Sender Recipient Name: " . $data->sender_recipient_name)
                    ->line("Receiver Recipient Name: " . $data->receiver_recipient_name)
                    ->line("Receiver Will Get: " . $data->receiver_get)
                    ->line("Status: Success")
                    ->line("Date And Time: " .$dateTime)
                    ->line('Thank you for using our application!');

        }else{
            return (new MailMessage())
                    ->greeting("Hello ".$user->fullname." !")
                    ->subject($data->title)
                    ->line("Send Remittance request to send admin successfully, details of Send Remittance:")
                    ->line($data->title)
                    ->line("Transaction Id: " .$trx_id)
                    ->line("Transaction Type: " . ucwords(str_replace('-', ' ', @$data->transaction_type)))
                    ->line("Request Amount: " . $data->request_amount)
                    ->line("Exchange Rate: " . $data->exchange_rate)
                    ->line("Fees & Charges: " . $data->charges)
                    ->line("Total Payable Amount: " . $data->payable)
                    ->line("Sending Country: " . $data->sending_country)
                    ->line("Receiving Country: " . $data->receiving_country)
                    ->line("Sender Recipient Name: " . $data->sender_recipient_name)
                    ->line("Receiver Recipient Name: " . $data->receiver_recipient_name)
                    ->line("Pickup Point: " . $data->alias)
                    ->line("Receiver Will Get: " . $data->receiver_get)
                    ->line("Status: ". $data->status)
                    ->line("Date And Time: " .$dateTime)
                    ->line('Thank you for using our application!');

        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
