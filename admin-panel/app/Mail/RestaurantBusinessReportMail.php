<?php

namespace App\Mail;

use App\Exports\RestaurantBusinessReportExport;
use App\Models\Restaurant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class RestaurantBusinessReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Restaurant $restaurant,
        public array $report
    ) {
    }

    public function build(): self
    {
        $filename = 'business-report-'
            . Str::slug($this->restaurant->name ?: 'restaurant')
            . '-'
            . Str::slug((string) $this->report['period_label'])
            . '.xlsx';

        return $this
            ->subject('Business report for ' . $this->restaurant->name . ' - ' . $this->report['period_label'])
            ->view('emails.restaurant-business-report')
            ->attachData(
                Excel::raw(
                    new RestaurantBusinessReportExport($this->restaurant, $this->report, collect($this->report['orders'] ?? [])),
                    ExcelFormat::XLSX
                ),
                $filename,
                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
    }
}