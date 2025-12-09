<?php

namespace App\Exports;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class OrderExport implements FromView, ShouldAutoSize, WithStyles,WithColumnWidths ,WithHeadings, WithEvents
{
    use Exportable;
    protected $data;

    public function __construct($data) {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('file-exports.order-export', [
            'data' => $this->data,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
        ];
    }

    public function styles(Worksheet $sheet) {
        $sheet->getStyle('A1')->getFont()->setBold(true);
        $sheet->getStyle('A4:T4')->getFont()->setBold(true)->getColor()
        ->setARGB('FFFFFF');

        $sheet->getStyle('A4:T4')->getFill()->applyFromArray([
            'fillType' => 'solid',
            'rotation' => 0,
            'color' => ['rgb' => '063C93'],
        ]);

        $sheet->getStyle('P5:P'.$this->data['orders']->count() + 4)->getFill()->applyFromArray([
            'fillType' => 'solid',
            'rotation' => 0,
            'color' => ['rgb' => 'D6BC00'],
        ]);
        $sheet->getStyle('O5:O'.$this->data['orders']->count() + 4)->getFill()->applyFromArray([
            'fillType' => 'solid',
            'rotation' => 0,
            'color' => ['rgb' => 'FFF9D1'],
        ]);

        $sheet->setShowGridlines(false);

        return [
            // Define the style for cells with data
            'A1:T'.$this->data['orders']->count() + 4 => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '000000'], // Specify the color of the border (optional)
                    ],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = $this->data['orders']->count() + 4;

                // Alignment across dynamic ranges
                $event->sheet->getStyle("A1:{$lastColumn}1")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle("A4:{$lastColumn}{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getStyle("A2:{$lastColumn}3")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Header row font + fill for dynamic last column
                $event->sheet->getStyle("A4:{$lastColumn}4")->getFont()->setBold(true)->getColor()->setARGB('FFFFFF');
                $event->sheet->getStyle("A4:{$lastColumn}4")->getFill()->applyFromArray([
                    'fillType' => 'solid',
                    'rotation' => 0,
                    'color' => ['rgb' => '063C93'],
                ]);

                // Dynamic borders across entire data area
                $event->sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => '000000'],
                        ],
                    ],
                ]);

                // Merge dynamic header/info rows
                $event->sheet->mergeCells("A1:{$lastColumn}1");
                $event->sheet->mergeCells('A2:B2');
                $event->sheet->mergeCells("C2:{$lastColumn}2");
                $event->sheet->mergeCells('A3:B3');
                $event->sheet->mergeCells("C3:{$lastColumn}3");
                $event->sheet->mergeCells("D2:{$lastColumn}2");

                if($this->data['order_status'] != 'all'){
                    $event->sheet->mergeCells('A2:B3');
                    $event->sheet->mergeCells("C2:{$lastColumn}3");
                    // Keep special merges for O:P as in original styling
                    $event->sheet->mergeCells('O4:P4');
                    $this->data['orders']->each(function($item,$index) use($event) {
                        $index+=5;
                        $event->sheet->mergeCells("O$index:P$index");
                    });
                }
                if(isset($this->data['data-from']) && $this->data['data-from'] == 'vendor'){
                    $event->sheet->mergeCells('O4:P4');
                    $this->data['orders']->each(function($item,$index) use($event) {
                        $index+=5;
                        $event->sheet->mergeCells("O$index:P$index");
                    });
                }

                $event->sheet->getRowDimension(2)->setRowHeight(110);
                $event->sheet->getDefaultRowDimension()->setRowHeight(30);
            },
        ];
    }
    public function headings(): array
    {
        return [
           '1'
        ];
    }
}
