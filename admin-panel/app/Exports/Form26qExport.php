<?php

namespace App\Exports;

use App\Exports\Concerns\WithStatutoryStyling;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Deductee-wise TDS for a quarter laid out to the NSDL RPU "Form 26Q — Annexure I"
 * columns. This is an ingestible working paper, not a validated .fvu file.
 */
class Form26qExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $s194o,
        private readonly array $s194c,
        private readonly array $deductor,
    ) {
    }

    public function sheets(): array
    {
        return [
            new Form26qDeductorSheet($this->deductor),
            new Form26qAnnexureSheet('194O', $this->s194o, $this->deductor),
            new Form26qAnnexureSheet('194C', $this->s194c, $this->deductor),
        ];
    }
}

class Form26qDeductorSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    use WithStatutoryStyling;

    public function __construct(private readonly array $deductor)
    {
    }

    public function title(): string
    {
        return 'Form 26Q — Deductor';
    }

    protected function statutoryTitle(): string
    {
        return 'FORM 26Q  ·  Quarterly statement of deduction of tax u/s 200(3)  [Rule 31A]';
    }

    protected function statutoryNote(): string
    {
        return 'FY ' . ($this->deductor['fy'] ?? '') . '  ·  Period ' . ($this->deductor['from'] ?? '') . ' to ' . ($this->deductor['to'] ?? '');
    }

    public function headings(): array
    {
        return ['Particulars', 'Value'];
    }

    public function collection(): Collection
    {
        return collect([
            ['Tax Deduction and Collection Account Number (TAN)', $this->deductor['tan'] ?? ''],
            ['Permanent Account Number (PAN) of the deductor', $this->deductor['pan'] ?? ''],
            ['Name of the deductor', $this->deductor['name'] ?? ''],
            ['Financial Year', $this->deductor['fy'] ?? ''],
            ['Period covered by this statement', ($this->deductor['from'] ?? '') . ' to ' . ($this->deductor['to'] ?? '')],
            ['Type of deductor', 'Company — Other than Government'],
        ]);
    }
}

class Form26qAnnexureSheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    use WithStatutoryStyling;

    public function __construct(
        private readonly string $section,
        private readonly array $statement,
        private readonly array $deductor = [],
    ) {
    }

    public function title(): string
    {
        return 'Annexure I — ' . $this->section;
    }

    protected function statutoryTitle(): string
    {
        return 'FORM 26Q — Annexure I  ·  Deductee-wise break-up of TDS  ·  Section ' . ($this->section === '194C' ? '194C' : '194-O');
    }

    protected function statutoryNote(): string
    {
        return 'FY ' . ($this->deductor['fy'] ?? '') . '  ·  Rows correspond to RPU deductee detail';
    }

    public function headings(): array
    {
        return [
            'Sr. No.',
            'Deductee Code (01-Company / 02-Other)',
            'PAN of the Deductee',
            'Name of the Deductee',
            'Section under which payment made',
            'Total amount paid / credited (₹)',
            'Total tax deducted (₹)',
            'Total tax deposited (₹)',
            'Rate at which deducted (%)',
            'Number of deductions',
        ];
    }

    public function collection(): Collection
    {
        return collect($this->statement['rows'] ?? [])->values()->map(function ($r, $i) {
            $isCompany = in_array(strtolower((string) ($r['deductee_type'] ?? '')), ['company', 'firm'], true);

            return [
                $i + 1,
                $isCompany ? '01' : '02',
                $r['pan'] ?: 'PANNOTAVBL',
                $r['name'] ?? (class_basename($r['party_type'] ?? '') . ' #' . ($r['party_id'] ?? '')),
                $this->section === '194C' ? '194C' : '194O',
                $r['gross'],
                $r['tds'],
                $r['tds'],
                $r['rate'],
                $r['deductions'] ?? 1,
            ];
        });
    }
}
