<?php
namespace Fluxion;

use Psr\Http\Message\RequestInterface;
use stdClass;
use Fluxion\Database\{Crud, Detail, Field};
use Fluxion\Query\QuerySql;

trait ModelCrud
{

    # Detalhes dos campos

    /** @var array<string, Detail> */
    protected array $_details = [];

    /** @return array<string, Detail> */
    public function getDetails(): array
    {
        return $this->_details;
    }

    public function getDetail(string $key): Detail
    {
        return $this->_details[$key] ?? new Detail(label: $key);
    }

    protected ?Crud $_crud = null;

    public function getCrud(): Crud
    {

        if (is_null($this->_crud)) {

            $name = substr(strrchr(get_class($this), "\\"), 1);

            $this->_crud = new Crud($name);

        }

        return $this->_crud;

    }

    public function title(): string
    {
        return (string) $this;
    }

    public function subtitle(): ?string
    {

        if ($id = $this->id()) {
            return "#$id";
        }

        return null;

    }

    public function extras(): array
    {
        return [];
    }

    public function updateInfo(): ?string
    {
        return null;
    }

    /** @return Connector\TableOrder[] */
    public function getOrders(): array
    {

        $orders = [];
        $id = 0;

        foreach ($this->_fields as $key => $field) {

            if ($field instanceof Field\AutoIncrementField) {

                $orders[] = new Connector\TableOrder($id++, 'Mais novos', "-$key");
                $orders[] = new Connector\TableOrder($id++, 'Mais antigos', "$key");

            }

            if ($field instanceof Field\AutoDateTimeField) {

                $orders[] = new Connector\TableOrder($id++, 'Atualizados recentemente', "-$key");

            }

        }

        return $orders;

    }

    public function order(Query $query, int $id): Query
    {

        foreach ($this->getOrders() as $order) {
            if ($order->id == $id) {
                $query = $query->orderBy($order->order);
            }
        }

        return $query;

    }

    /**
     * @return array<string, Connector\TableTab>
     * @throws Exception
     */
    public function getTabs(Query $query): array
    {

        $list = [];

        $crud = $this->getCrud();

        if (is_null($crud->field_tab)) {
            return $list;
        }

        $field = $this->getField($crud->field_tab);
        $detail = $this->getDetail($crud->field_tab);

        if ($field instanceof Field\ChoicesField) {
            #TODO
        }

        elseif ($field instanceof Field\BooleanField) {

            foreach ((clone $query)->groupBy($crud->field_tab)->count($crud->field_tab)->select() as $tab) {

                $id = $tab->{$crud->field_tab};
                $label = ($id) ? 'Sim' : 'Não';

                $list[] = new Connector\TableTab(id: $id, label: $label, itens: $tab->total);

            }

        }

        elseif ($field instanceof Field\ForeignKeyField) {
            #TODO
        }

        elseif ($field instanceof Field\ManyChoicesField) {
            #TODO
        }

        elseif ($field instanceof Field\ManyToManyField) {
            #TODO
        }

        else {
            #TODO
        }

        return $list;

    }

    /**
     * @return array<string, Connector\TableFilter>
     * @throws Exception
     */
    public function getFilters(Query $query, stdClass $filters): array
    {

        $list = [];
        $i = 0;

        $crud = $this->getCrud();

        foreach ($this->getFields() as $key => $field) {

            $detail = $this->getDetail($key);

            if (is_null($detail) || $crud->field_tab == $key || !$detail->filterable) {
                continue;
            }

            $icon = $detail->filter_icon;

            if (is_null($icon)) {

                $icon = Icon::tryFrom('collection-item-' . ++$i)
                    ?? Icon::COLLECTION_ITEM;

            }

            $options = $filters->$key ?? [];

            $filter = new Connector\TableFilter(field: $key, label: $detail->label ?? $key, icon: $icon);

            $filter->itens[] = new Connector\TableFilterItem(
                id: null,
                label: '(Todos)',
                active: (count($options) == 0)
            );

            if ($field instanceof Field\ChoicesField) {
                #TODO
            }

            elseif ($field instanceof Field\BooleanField) {

                $filter->itens[] = new Connector\TableFilterItem(
                    id: false,
                    label: 'Não',
                    active: in_array(false, $options)
                );

                $filter->itens[] = new Connector\TableFilterItem(
                    id: true,
                    label: 'Sim',
                    active: in_array(true, $options)
                );

            }

            elseif ($field instanceof Field\ForeignKeyField) {
                #TODO
            }

            elseif ($field instanceof Field\ManyChoicesField) {
                #TODO
            }

            elseif ($field instanceof Field\ManyToManyField) {
                #TODO
            }

            else {
                #TODO
            }

            $list[] = $filter;

        }

        return $list;

    }

    /**
     * @throws Exception
     */
    public function tab(Query $query, ?string &$tab): Query
    {

        $crud = $this->getCrud();

        if (is_null($tab) || is_null($crud->field_tab)) {
            return $query;
        }

        $field = $this->getField($crud->field_tab);

        if ($field instanceof Field\ManyToManyField) {

            $mn_model = $field->getMnModel();

            $list = $mn_model->_filter('b', $tab)->groupBy('a');

            $test = (clone $query)->filter($crud->field_tab, $list)->first();

            if (!is_null($test)) {
                $query = $query->filter($crud->field_tab, $list);
            }

        }

        elseif ($field instanceof Field\ManyChoicesField) {
            #TODO
        }

        else {

            $test = (clone $query)->filter($crud->field_tab, $tab)->first();

            if (!is_null($test)) {
                $query = $query->filter($crud->field_tab, $tab);
            }

        }

        return $query;

    }

    /** @return Connector\TableTag[] */
    public function getTags(): array
    {
        return [];
    }

    /**
     * @return Connector\TableAction[]
     */
    public function getActions(Auth $auth): array
    {

        $actions = [];

        if ($this->getTable()?->view
            || $auth->hasPermission($this, Permission::DELETE)) {

            $actions[] = new Connector\TableAction(
                label: 'Apagar',
                action: Action::DELETE,
                color: Color::RED,
                confirm: 'Deseja apagar o registro?'
            );

        }

        return $actions;

    }

    public function search(Query $query, string $search): Query
    {

        $searches = [];

        foreach ($this->getFields() as $key => $field) {

            $detail = $this->getDetail($key);

            if (is_null($detail)) {
                continue;
            }

            if ($param = $field->getSearch($search)) {
                $searches[] = $param;
            }

        }

        if (count($searches) > 0) {
            return $query->filter(QuerySql::_or($searches));
        }

        return $query;

    }

    /**
     * @throws Exception
     */
    public function filterItens(Query $query, stdClass $filters): Query
    {

        foreach ($filters as $key => $value) {

            if (empty($value) || !array_key_exists($key, $this->getFields())) {
                continue;
            }

            $field = $this->getField($key);

            if ($field instanceof Field\ManyToManyField) {

                $mn_model = $field->getMnModel();

                $list = $mn_model->_filter('b', $value)->groupBy('a');

                $query = $query->filter($key, $list);

            }

            elseif ($field instanceof Field\ManyChoicesField) {
                #TODO
            }

            else {
                $query = $query->filter($key, $value);
            }

        }

        return $query;

    }

    public function changeState(State $state): void {}

    public function getFormHeader(mixed $arg): ?string
    {
        return null;
    }

    public function getFormFooter(mixed $arg): ?string
    {
        return null;
    }

}
