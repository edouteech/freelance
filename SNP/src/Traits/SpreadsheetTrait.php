<?php

namespace App\Traits;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

use Symfony\Contracts\Translation\TranslatorInterface;

trait SpreadsheetTrait
{
    private $localTranslator;

    /**
     * @required
     */
    public function defineConstants()
    {
        define('FIRST_COLUMN_ASCII_CODE', 65); // ascii code for letter A
        define('FIRST_ROW', 1);
    }

    /**
     * @required
     * @param TranslatorInterface $localTranslator
     */
    public function setTranslator(TranslatorInterface $localTranslator)
    {
        $this->localTranslator = $localTranslator;
    }

    /**
     * @return TranslatorInterface $localTranslator
     */
    public function transalte($id, array $parameters = [], $domain = null, $locale = null)
    {
        return $this->localTranslator->trans($id, $parameters, $domain, $locale);
    }

    private function prepareSpreadsheet(): array
    {
        return [
            'tableStyle' => [
                'borders' => [
                    'vertical' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                    'right' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                    'left' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ]
                ]
            ],
            'headStyle' => [
                'font' => [
                    'bold' => true,
                    'color' => [
                        'argb' => 'FFFFFF',
                    ]
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => [
                        'argb' => '4859d9',
                    ]
                ],
                'borders' => [
                    'vertical' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                    'bottom' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                    'right' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                    'left' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ]
                ]
            ],
            'bodyStyle' => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => [
                        'argb' => 'CFCFCF',
                    ]
                ]
            ]
        ];
    }

    public function generateSpreadsheet(string $class, array $objects, array $keys)
    {
        $spreadsheetStyle = $this->prepareSpreadsheet();
        
        $spreadsheet = new Spreadsheet();
        
        $sheet = $spreadsheet->getActiveSheet();

        $level = 1;
        $asciiCodeFirstLevel = FIRST_COLUMN_ASCII_CODE;
        $asciiCodeSecondLevel = FIRST_COLUMN_ASCII_CODE - 1;

        foreach ($keys as $key) {
            
            $cellCoordinate = (
                $level > 1 ?
                    sprintf('%c%c%d', $asciiCodeSecondLevel, $asciiCodeFirstLevel++, FIRST_ROW)
                    : sprintf('%c%d', $asciiCodeFirstLevel++, FIRST_ROW)
            );

            $sheet->setCellValue(
                $cellCoordinate,
                $this->transalte($key, [], $class)
            );

            if( $asciiCodeFirstLevel > 90 ) {

                $level++;
                $asciiCodeSecondLevel++;
                $asciiCodeFirstLevel = FIRST_COLUMN_ASCII_CODE;

            }            
        }

        $rowCoordinate = (
            $level > 1 ?
                sprintf('%c1:%c%c1', FIRST_COLUMN_ASCII_CODE, $asciiCodeSecondLevel, ($asciiCodeFirstLevel - 1))
                : sprintf('%c1:%c1', FIRST_COLUMN_ASCII_CODE, ($asciiCodeFirstLevel - 1))
        );
        
        $sheet
            ->getStyle($rowCoordinate)
            ->applyFromArray($spreadsheetStyle['headStyle']);

        $row = FIRST_ROW + 1;

        foreach ($objects as $object) { // set body contents

            $level = 1;
            $asciiCodeFirstLevel = FIRST_COLUMN_ASCII_CODE;
            $asciiCodeSecondLevel = FIRST_COLUMN_ASCII_CODE - 1;
            
            foreach ($keys as $key) {

                $getter = sprintf('get%s', ucfirst($key));

                $cellCoordinate = (
                    $level > 1 ?
                        sprintf('%c%c%d', $asciiCodeSecondLevel, $asciiCodeFirstLevel++, $row)
                        : sprintf('%c%d', $asciiCodeFirstLevel++, $row)
                );

                $sheet->setCellValue(
                    $cellCoordinate,
                    $object->$getter()
                );

                if( $asciiCodeFirstLevel > 90 ) {

                    $level++;
                    $asciiCodeSecondLevel++;
                    $asciiCodeFirstLevel = FIRST_COLUMN_ASCII_CODE;

                }

            }

            $rowCoordinate = (
                $level > 1 ?
                    sprintf('%c%d:%c%c%d', FIRST_COLUMN_ASCII_CODE, $row, $asciiCodeSecondLevel, ($asciiCodeFirstLevel - 1), $row)
                    : sprintf('%c%d:%c%d', FIRST_COLUMN_ASCII_CODE, $row, ($asciiCodeFirstLevel - 1), $row)
            );
            
            $sheet
                ->getStyle($rowCoordinate)
                ->applyFromArray( $row % 2 ? [] : $spreadsheetStyle['bodyStyle']);    

            $sheet
                ->getStyle($rowCoordinate)
                ->applyFromArray($spreadsheetStyle['tableStyle']);

            $row++;
        }

        $sheet->getDefaultColumnDimension()->setWidth(15);

        $sheet->setTitle(
            $this->transalte('sheetTitle', [], $class)
        );

        $filename = sprintf('%s_%s.xlsx', $sheet->getTitle(), date('Y_m_d'));

        $filepath = tempnam(sys_get_temp_dir(), $filename);

        $writer = new Xlsx($spreadsheet);

        $writer->save($filepath);

        return [
            'filepath' => $filepath,
            'filename' => $filename
        ];
    }

    private function prepareStatisticsSpreadsheet()
    {
        return [
            'firstLevelHeadStyle' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ],
                'font' => [
                    'bold' => true,
                    'color' => [
                        'argb' => 'FFFFFF',
                    ]
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => [
                        'argb' => '4859d9',
                    ]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ]
                ]
            ],
            'secondLevelHeadStyle' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ],
                'font' => [
                    'bold' => true,
                    'color' => [
                        'argb' => 'FFFFFF',
                    ]
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => [
                        'argb' => '4859d9',
                    ]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ]
                ]
            ],
            'thirdLevelHeadStyle' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'font' => [
                    'bold' => true,
                    'size' => 10,
                    'color' => [
                        'argb' => 'ff0066',
                    ]
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => [
                        'argb' => 'CCEDFF',
                    ]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ]
                ]
            ],
            'verticalTablebodyStyle' => [
                'font' => [
                    'size' => 10
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ]
                ]
            ],
            'horizontalTablebodyStyle' => [
                'font' => [
                    'size' => 10
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ]
                ]
            ]
        ];
    }

    public function generateStatisticsSpreadsheet(array $data, string $filename)
    {
        $spreadsheet = new Spreadsheet();
        
        $sheet = $spreadsheet->getActiveSheet();

        $spreadsheetStyle = $this->prepareStatisticsSpreadsheet();

        $elementsCounter = 0;
        $maxLeftSideTableWidth = 0;
        $leftSideElementsCounter = 0;
        $maxRow = FIRST_ROW;
        $numberOfElementsOnEachSide = (int) (count($data) / 2);

        $row = FIRST_ROW + 3;

        foreach ($data as $title => $array) {

            if($title == strtoupper('Remarques generales importantes'))
                continue;

            if($leftSideElementsCounter >= $numberOfElementsOnEachSide) {

                if($elementsCounter == $numberOfElementsOnEachSide) {

                    $row = FIRST_ROW + 3;

                }

                $currentColumnAsciiCode = FIRST_COLUMN_ASCII_CODE + $maxLeftSideTableWidth + 2;

            } else {

                if( $maxLeftSideTableWidth < $array['conditions']['tableWidth'] ) {

                    $maxLeftSideTableWidth = $array['conditions']['tableWidth'];

                }

                $currentColumnAsciiCode = FIRST_COLUMN_ASCII_CODE + 1;
                $leftSideElementsCounter++;

            }

            $lastColumnAsciiCode = $currentColumnAsciiCode + $array['conditions']['tableWidth'] - 1;

            $cellsRangeCoordinate = (
                sprintf('%c%d:%c%d', $currentColumnAsciiCode, $row, $lastColumnAsciiCode, $row)
            );
            
            $sheet
                ->getStyle($cellsRangeCoordinate)
                ->applyFromArray($spreadsheetStyle['secondLevelHeadStyle']);   

            $sheet->mergeCells($cellsRangeCoordinate);

            $cellCoordinate = $cellCoordinate = sprintf('%c%d', $currentColumnAsciiCode, $row++);

            $sheet->setCellValue(
                $cellCoordinate,
                $title
            );

            if($array['conditions']['displayType'] == 'horizontal') {

                foreach ($array['values'] as $key => $value) {

                    $cellsRangeCoordinate = (
                        sprintf('%c%d:%c%d', $currentColumnAsciiCode, $row, $lastColumnAsciiCode, $row)
                    );
                    
                    $sheet
                        ->getStyle($cellsRangeCoordinate)
                        ->applyFromArray($spreadsheetStyle['horizontalTablebodyStyle']);
                
                    $cellCoordinate = sprintf('%c%d', $currentColumnAsciiCode, $row);

                    $sheet->setCellValue(
                        $cellCoordinate,
                        $key
                    );

                    $cellCoordinate = sprintf('%c%d', $currentColumnAsciiCode + 1, $row++);

                    $sheet->setCellValue(
                        $cellCoordinate,
                        $value
                    );
                    
                }

            } else {

                foreach ($array['values'] as $question => $answers) {

                    $cellsRangeCoordinate = (
                        sprintf('%c%d:%c%d', $currentColumnAsciiCode, $row, $lastColumnAsciiCode, $row)
                    );
                    
                    $sheet
                        ->getStyle($cellsRangeCoordinate)
                        ->applyFromArray($spreadsheetStyle['thirdLevelHeadStyle']);
        
                    $sheet->mergeCells($cellsRangeCoordinate);
        
                    $cellCoordinate = $cellCoordinate = sprintf('%c%d', $currentColumnAsciiCode, $row++);
        
                    $sheet->setCellValue(
                        $cellCoordinate,
                        $question
                    );

                    $asciiCode = $currentColumnAsciiCode;

                    foreach ($answers as $value) {

                        if( $lastColumnAsciiCode - $currentColumnAsciiCode + 1 > count($answers) ) {

                            $cellsRangeCoordinate = (
                                sprintf('%c%d:%c%d', $currentColumnAsciiCode + count($answers) - 1, $row, $lastColumnAsciiCode, $row)
                            ); 

                            $sheet->mergeCells($cellsRangeCoordinate);

                            $cellsRangeCoordinate = (
                                sprintf('%c%d:%c%d', $currentColumnAsciiCode + count($answers) - 1, $row + 1, $lastColumnAsciiCode, $row + 1)
                            ); 

                            $sheet->mergeCells($cellsRangeCoordinate);
                            
                        }

                        $cellsRangeCoordinate = (
                            sprintf('%c%d:%c%d', $currentColumnAsciiCode, $row, $lastColumnAsciiCode, $row)
                        );
                        
                        $sheet
                            ->getStyle($cellsRangeCoordinate)
                            ->applyFromArray($spreadsheetStyle['verticalTablebodyStyle']);
                        
                        $cellCoordinate = $cellCoordinate = sprintf('%c%d', $asciiCode, $row);
        
                        $sheet->setCellValue(
                            $cellCoordinate,
                            $value['answer']
                        );

                        $cellsRangeCoordinate = (
                            sprintf('%c%d:%c%d', $currentColumnAsciiCode, $row + 1, $lastColumnAsciiCode, $row + 1)
                        );
                        
                        $sheet
                            ->getStyle($cellsRangeCoordinate)
                            ->applyFromArray($spreadsheetStyle['verticalTablebodyStyle']);

                        $cellCoordinate = $cellCoordinate = sprintf('%c%d', $asciiCode++, $row + 1);
            
                        $sheet->setCellValue(
                            $cellCoordinate,
                            $value['countAnswer']
                        );
                        
                    }

                    $row = $row + 2;

                }

            }

            $elementsCounter++;
            $row = $row + 2;

            if($maxRow < $row) {
                $maxRow = $row;
            }
            
        }

        if(isset($data[strtoupper('Remarques generales importantes')])) {

            $currentRow = $maxRow;
            $currentColumnAsciiCode = FIRST_COLUMN_ASCII_CODE + 1;

            $cellsRangeCoordinate = (
                sprintf('%c%d:%c%d', $currentColumnAsciiCode, $currentRow, $lastColumnAsciiCode, $currentRow)
            );
            
            $sheet
                ->getStyle($cellsRangeCoordinate)
                ->applyFromArray($spreadsheetStyle['secondLevelHeadStyle']);   

            $sheet->mergeCells($cellsRangeCoordinate);

            $cellCoordinate = $cellCoordinate = sprintf('%c%d', $currentColumnAsciiCode, $currentRow++);

            $sheet->setCellValue(
                $cellCoordinate,
                strtoupper('Remarques generales importantes')
            );

            foreach ($data[strtoupper('Remarques generales importantes')]['values'] as $value) {

                $cellsRangeCoordinate = (
                    sprintf('%c%d:%c%d', $currentColumnAsciiCode, $currentRow, $lastColumnAsciiCode, $currentRow)
                );
                
                $sheet
                    ->getStyle($cellsRangeCoordinate)
                    ->applyFromArray($spreadsheetStyle['horizontalTablebodyStyle']);

                $sheet->mergeCells($cellsRangeCoordinate);
            
                $cellCoordinate = sprintf('%c%d', $currentColumnAsciiCode, $currentRow);

                $sheet->setCellValue(
                    $cellCoordinate,
                    $value
                );

                $currentRow++;
                
            }

        }

        $sheet->getDefaultColumnDimension()->setWidth(15);

        $sheet->setTitle($filename . '_stats');

        $filename = sprintf('%s_%s.xlsx', $sheet->getTitle(), date('Y_m_d'));

        $filepath = tempnam(sys_get_temp_dir(), $filename);

        $writer = new Xlsx($spreadsheet);

        $writer->save($filepath);

        return [
            'filepath' => $filepath,
            'filename' => $filename
        ];
    }
}