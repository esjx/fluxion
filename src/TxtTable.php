<?php
namespace Fluxion;

class TxtTable
{

    const COLOR = '#dddddd';

    const SEPARATOR = '#-#-#-#-#';

    private $rows;
    private $cols;

    private $cells = [];

    private $rowSize = [];
    private $colSize = [];
    private $colSpan = [];
    private $aligns = [];

    private $padding = 1;

    private $titleRows = 1;

    private $enconding = 'utf8';

    public function setEnconding(string $enconding): void
    {
        $this->enconding = $enconding;
    }

    function __construct(int $rows = 0, int $cols = 0)
    {
        
        $this->rows = $rows;
        $this->cols = $cols;

    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getCols(): int
    {
        return $this->cols;
    }
    
    public function addRow(array $dados)
    {
        
        $row = $this->rows + 1;
        
        for ($i = 0; $i < count($dados); $i++) {

            $this->setCellValue($row, $i + 1, $dados[$i]);

        }
        
    }

    public function setColAlign($col, string $align)
    {

        if (is_array($col)) {

            foreach ($col as $item) {
                $this->setColAlign($item, $align);
            }

            return;

        }

        $this->aligns[$col] = $align;

    }

    public function getTitleRows(): int
    {
        return $this->titleRows;
    }

    public function setTitleRows(int $titleRows)
    {
        $this->titleRows = $titleRows;
    }

    public function getCellValue(int $row, int $col): string
    {
        return (isset($this->cells[$row][$col])) ? $this->cells[$row][$col] : '';
    }

    public function setColWidth($col, int $width)
    {

        if (is_array($col)) {

            foreach ($col as $item) {
                $this->setColWidth($item, $width);
            }

            return;

        }

        if (!isset($this->colSize[$col])) {
            $this->colSize[$col] = 1;
        }

        $this->colSize[$col] = max($this->colSize[$col], $width);

    }

    public function setCellColSpan(int $row, int $col, int $cols)
    {

        if (!isset($this->colSpan[$row])) {
            $this->colSpan[$row] = [];
        }

        $this->colSpan[$row][$col] = max(1, $cols);

    }

    public function getCellColSpan(int $row, int $col): int
    {

        $colSpan = 1;

        if (isset($this->colSpan[$row][$col])) {
            $colSpan = $this->colSpan[$row][$col];
        }

        return $colSpan;

    }

    public function setCellValue(int $row, int $col, ?string $value)
    {

        $this->rows = max($this->rows, $row);
        $this->cols = max($this->cols, $col);

        $arr = explode(PHP_EOL, $value ?? '');

        foreach ($arr as $id=>$item) {

            $arr[$id] = $this->textBreak($item);

        }

        $value = implode(PHP_EOL, $arr);

        if (!isset($this->cells[$row])) {
            $this->cells[$row] = [];
        }

        $arr = explode(PHP_EOL, preg_replace('/<[^>]*>/', '', $value));

        if (!isset($this->rowSize[$row])) {
            $this->rowSize[$row] = 1;
        }

        $this->rowSize[$row] = max($this->rowSize[$row], count($arr));

        foreach ($arr as $item) {

            if ($item == self::SEPARATOR) {
                $item = '';
            }

            $this->setColWidth($col, mb_strlen($item, $this->enconding));

        }

        $this->cells[$row][$col] = $value;

    }

    public function __toString(): string
    {

        $txt = $this->separator();

        if ($this->titleRows > 0) {

            for ($row = 1; $row <= $this->titleRows; $row++) {

                $lines = (isset($this->rowSize[$row])) ? $this->rowSize[$row] : 1;

                for ($line = 0; $line < $lines; $line++) {
                    $txt .= $this->loadRow($row, $line);
                }

            }

            $txt .= $this->separator();

        }

        for ($row = $this->titleRows + 1; $row <= $this->rows; $row++) {

            $lines = (isset($this->rowSize[$row])) ? $this->rowSize[$row] : 1;

            for ($line = 0; $line < $lines; $line++) {
                $txt .= $this->loadRow($row, $line);
            }

        }

        $txt .= $this->separator();

        return $txt;

    }

    private function separator(): string
    {

        $txt = '+';

        for ($col = 1; $col <= $this->cols; $col++) {

            $size = (isset($this->colSize[$col])) ? $this->colSize[$col] : 1;

            $size += ($this->padding * 2);

            $txt .= str_pad('', $size, '-');

            $txt .= '+';

        }

        return $this->color($txt) . PHP_EOL;

    }

    private function loadRow(int $row, int $line): string
    {

        if ($this->getCellValue($row, 1) == self::SEPARATOR) {
            return $this->separator();
        }

        $txt = $this->color('|');

        for ($col = 1; $col <= $this->cols;) {

            $txt .= $this->loadCell($row, $col, $line);

            $col += $this->getCellColSpan($row, $col);

        }

        $txt .= PHP_EOL;

        return $txt;

    }

    private function loadCell(int $row, int $col, int $line): string
    {

        $value = $this->getCellValue($row, $col);

        $arr = explode(PHP_EOL, $value);

        $value = (isset($arr[$line])) ? $arr[$line] : '';

        $colSpan = $this->getCellColSpan($row, $col);

        $size = (isset($this->colSize[$col])) ? $this->colSize[$col] : 1;

        for ($i = 1; $i < $colSpan; $i++) {

            $size2 = (isset($this->colSize[$col + $i])) ? $this->colSize[$col + $i] : 1;

            $size += $size2 + 3;

        }

        $padding = str_pad('', $this->padding);
        $align = (isset($this->aligns[$col])) ? $this->aligns[$col] : 'left';

        if ($colSpan == $this->getCols()) {
            $align = 'center';
        }

        if ($row <= $this->titleRows || $align == 'center') {
            $pad_type = STR_PAD_BOTH;
        } elseif ($align == 'right') {
            $pad_type = STR_PAD_LEFT;
        } else {
            $pad_type = STR_PAD_RIGHT;
        }

        return ($row <= $this->titleRows)
            ? $padding . '<b>' . $this->pad($value, $size, $pad_type) . '</b>' . $padding . $this->color('|')
            : $padding . $this->pad($value, $size, $pad_type) . $padding . $this->color('|');

    }

    private function color(string $text): string
    {
        return '<span style="color:' . self::COLOR . ';">' . $text . '</span>';
    }

    private function pad(string $text, int $size, int $pad_type = STR_PAD_RIGHT): string
    {

        $size -= mb_strlen(preg_replace('/<[^>]*>/', '', $text), $this->enconding);

        $pad_left = '';
        $pad_right = '';

        switch ($pad_type) {

            case STR_PAD_RIGHT:
                $pad_right = str_pad('', $size);
                break;

            case STR_PAD_LEFT:
                $pad_left = str_pad('', $size);
                break;

            case STR_PAD_BOTH:
                $pad_left = str_pad('', floor($size / 2));
                $pad_right = str_pad('', ceil($size / 2));
                break;

        }

        return $pad_left . $text . $pad_right;

    }

    public function textBreak(string $in, int $size = 80): string
    {

        $out = '';
        $line_size = 0;

        $in = trim($in);

        if (mb_strlen(preg_replace('/<[^>]*>/', '', $in), $this->enconding) <= $size) {
            return $in;
        }

        $words = explode(' ', $in);

        foreach ($words as $word) {

            if ($word != '') {

                $word_size = mb_strlen(preg_replace('/<[^>]*>/', '', $word), $this->enconding);

                if (($line_size + 1 + $word_size) <= $size) {

                    $line_size += $word_size + 1;
                    $out .= ' ' . $word;

                } else {

                    $line_size = $word_size;
                    $out .= PHP_EOL . $word;

                }

            }

        }

        return trim($out);

    }

}
