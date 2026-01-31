<?php
namespace Fluxion;

class HtmlTable
{

    const PARAM_TYPE = 'type';
    const PARAM_VALUE = 'value';
    const PARAM_ALIGN = 'align';
    const PARAM_CLASS = 'class';
    const PARAM_STYLE = 'style';
    const PARAM_WIDTH = 'width';
    const PARAM_HEIGHT = 'height';
    const PARAM_COL_SPAN = 'colspan';
    const PARAM_ROW_SPAN = 'rowspan';
    const PARAM_BG_COLOR = 'bgcolor';

    const TABLE_ALIGN_LEFT = 'left';
    const TABLE_ALIGN_CENTER = 'center';
    const TABLE_ALIGN_RIGHT = 'right';

    const CELL_ALIGN_LEFT = 'left';
    const CELL_ALIGN_CENTER = 'center';
    const CELL_ALIGN_RIGHT = 'right';

    const CELL_TYPE_TH = 'th';
    const CELL_TYPE_TD = 'td';

    private $rows;
    private $cols;
    private $cells;
    private $align = null;
    private $class = null;
    private $style = null;
    private $border = null;
    private $cellPadding = 1;
    private $cellSpacing = 0;
    private $titleRows = 0;

    private $defaulRow = [
        'cols' => [],
        'class' => null,
        'style' => null,
    ];

    private $defaulCell = [
        'value' => '&nbsp;',
        'type' => 'td',
        'align' => null,
        'colspan' => 1,
        'rowspan' => 1,
        'class' => null,
        'style' => null,
        'width' => null,
        'height' => null,
        'bgcolor' => null,
    ];

    function __construct($rows = 1, $cols = 1)
    {
        
        $this->rows = $rows;
        $this->cols = $cols;

    }

    public function setTableTitleRows($titleRows)
    {
        $this->titleRows = $titleRows;
    }

    public function setTableAlign($align)
    {
        $this->align = $align;
    }

    public function setTableClass($class)
    {
        $this->class = $class;
    }

    public function setTableStyle($style)
    {
        $this->style = $style;
    }

    public function setTableBorder($border)
    {
        $this->border = $border;
    }

    public function setTableCellPadding($cellPadding)
    {
        $this->cellPadding = $cellPadding;
    }

    public function setTableCellSpacing($cellSpacing)
    {
        $this->cellSpacing = $cellSpacing;
    }

    public function setCellValue($row, $col, $value)
    {
        $this->setCellParam($row, $col, $this::PARAM_VALUE, $value);
    }

    public function getCellValue($row, $col)
    {
        $this->getCellParam($row, $col, $this::PARAM_VALUE);
    }

    public function setCellStyle($row, $col, $value)
    {
        $this->setCellParam($row, $col, $this::PARAM_STYLE, $value);
    }

    public function setCellClass($row, $col, $value)
    {
        $this->setCellParam($row, $col, $this::PARAM_CLASS, $value);
    }

    public function setCellAlign($row, $col, $value)
    {
        $this->setCellParam($row, $col, $this::PARAM_ALIGN, $value);
    }

    public function setCellType($row, $col, $value)
    {
        $this->setCellParam($row, $col, $this::PARAM_TYPE, $value);
    }

    public function setCellColSpan($row, $col, $value)
    {
        $this->setCellParam($row, $col, $this::PARAM_COL_SPAN, $value);
    }

    public function setCellBgColor($row, $col, $value)
    {
        $this->setCellParam($row, $col, $this::PARAM_BG_COLOR, $value);
    }

    public function setCellWidth($row, $col, $value)
    {
        $this->setCellParam($row, $col, $this::PARAM_WIDTH, $value);
    }

    public function setCellHeight($row, $col, $value)
    {
        $this->setCellParam($row, $col, $this::PARAM_HEIGHT, $value);
    }

    private function setCellParam($row, $col, $param, $value)
    {

        $this->rows = max($this->rows, $row + 1);
        $this->cols = max($this->cols, $col + 1);

        if (!isset($this->cells[$row]))
            $this->cells[$row] = $this->defaulRow;

        if (!isset($this->cells[$row]['cols'][$col]))
            $this->cells[$row]['cols'][$col] = $this->defaulCell;

        $this->cells[$row]['cols'][$col][$param] = $value;

    }

    public function setRowStyle($row, $value)
    {
        $this->setRowParam($row, $this::PARAM_STYLE, $value);
    }

    public function setRowClass($row, $value)
    {
        $this->setRowParam($row, $this::PARAM_CLASS, $value);
    }

    public function setRowBgColor($row, $value)
    {
        $this->setRowParam($row, $this::PARAM_BG_COLOR, $value);
    }

    private function setRowParam($row, $param, $value)
    {

        $this->rows = max($this->rows, $row + 1);

        if (!isset($this->cells[$row]))
            $this->cells[$row] = $this->defaulRow;

        $this->cells[$row][$param] = $value;

    }

    private function getCellParam($row, $col, $param)
    {
        return (isset($this->cells[$row]) && isset($this->cells[$row]['cols'][$col]) && isset($this->cells[$row]['cols'][$col][$param])) ? $this->cells[$row]['cols'][$col][$param] : $this->defaulCell[$param];
    }

    public function load(): string
    {

        $html = '<table';

        $html .= (!is_null($this->align)) ? " align=\"{$this->align}\"" : '';
        $html .= (!is_null($this->class)) ? " class=\"{$this->class}\"" : '';
        $html .= (!is_null($this->style)) ? " style='{$this->style}'" : '';
        $html .= (!is_null($this->border)) ? " border=\"{$this->border}\"" : '';
        $html .= (!is_null($this->cellPadding)) ? " cellpadding=\"{$this->cellPadding}\"" : '';
        $html .= (!is_null($this->cellSpacing)) ? " cellspacing=\"{$this->cellSpacing}\"" : '';

        $html .= '>' . PHP_EOL;

        $html .= '    <thead>' . PHP_EOL;

        for ($row = 0; $row < $this->titleRows; $row++)
            $html .= $this->loadRow($row);

        $html .= '    </thead>' . PHP_EOL;

        $html .= '    <tbody>' . PHP_EOL;

        for ($row = $this->titleRows; $row < $this->rows; $row++)
            $html .= $this->loadRow($row);

        $html .= '    </tbody>' . PHP_EOL;

        //TODO: tfoot

        $html .= '</table>' . PHP_EOL;

        return $html;

    }

    private function loadRow($row): string
    {

        $html = '        <tr';

        $html .= (!is_null($this->cells[$row]['class'])) ? " class=\"{$this->cells[$row]['class']}\"" : '';
        $html .= (!is_null($this->cells[$row]['style'])) ? " style=\"{$this->cells[$row]['style']}\"" : '';

        $html .= '>' . PHP_EOL;

        for ($col = 0; $col < $this->cols; $col++) {
            $html .= $this->loadCell($row, $col);
            $col += ( $this->getCellParam($row, $col, $this::PARAM_COL_SPAN) - 1 );
        }

        $html .= '        </tr>' . PHP_EOL;

        return $html;

    }

    private function loadCell($row, $col): string
    {

        $cell = (isset($this->cells[$row]) && isset($this->cells[$row]['cols'][$col])) ? $this->cells[$row]['cols'][$col] : $this->defaulCell;

        $html = '            <' . $cell['type'];

        $html .= (!is_null($cell['align'])) ? " align=\"{$cell['align']}\"" : '';
        $html .= (!is_null($cell['class'])) ? " class=\"{$cell['class']}\"" : '';
        $html .= (!is_null($cell['style'])) ? " style='{$cell['style']}'" : '';
        $html .= (!is_null($cell['width'])) ? " width=\"{$cell['width']}\"" : '';
        $html .= (!is_null($cell['height'])) ? " height=\"{$cell['height']}\"" : '';
        $html .= (!is_null($cell['bgcolor'])) ? " bgcolor=\"{$cell['bgcolor']}\"" : '';

        $html .= ($cell['colspan'] > 1) ? " colspan=\"{$cell['colspan']}\"" : '';
        $html .= ($cell['rowspan'] > 1) ? " rowspan=\"{$cell['rowspan']}\"" : '';

        $html .= '>';

        $html .= $cell['value'];

        $html .= '</' . $cell['type'] . '>' . PHP_EOL;

        return $html;

    }

}
