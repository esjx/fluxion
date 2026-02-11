<?php
namespace Fluxion;

use ReflectionException;
use stdClass;
use Fluxion\Database\{Crud, Detail, Field};
use Fluxion\Query\QuerySql;
use Fluxion\Exception\{PermissionDeniedException};

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
        return $this->_details[$key] ?? new Detail(label: ucfirst($key));
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

        foreach ($this->getFields() as $field) {
            if ($field instanceof Field\AutoDateTimeField) {
                return Time::convert($field->getValue(), 'Y/m/d H:i:s');
            }
        }

        return null;

    }

    /** @return Connector\TableOrder[] */
    public function getOrders(): array
    {

        $orders = [];
        $id = 100;

        foreach ($this->_fields as $key => $field) {

            if ($field instanceof Field\AutoIncrementField) {

                $orders[] = new Connector\TableOrder(++$id, 'Mais novos', "-$key");
                $orders[] = new Connector\TableOrder(++$id, 'Mais antigos', "$key");

            }

            if ($field instanceof Field\AutoDateTimeField) {

                $orders[] = new Connector\TableOrder(++$id, 'Atualizados recentemente', "-$key");

            }

        }

        return $orders;

    }

    public function order(Query $query, ?int &$id = null): Query
    {

        if (is_null($id)) {
            foreach ($this->getOrders() as $order) {
                $id = $order->id;
                break;
            }
        }

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
     * @throws ReflectionException
     */
    public function getTabs(Query $query): array
    {

        $list = [];

        $crud = $this->getCrud();

        $key = $crud->field_tab;

        if (is_null($key)) {
            return $list;
        }

        $field = $this->getField($key);

        $item = (clone $query)->count()->first();

        $list[] = new Connector\TableTab(id: null, label: '(Todos)', itens: $item->total);

        if ($field instanceof Field\ChoicesField) {

            foreach ((clone $query)->groupBy($key)->count($key)->select() as $item) {

                $id = $item->$key;
                $label = $field->choices[$id] ?? (string) $id;

                $list[] = new Connector\TableTab(id: $id, label: $label, itens: $item->total);

            }

        }

        elseif ($field instanceof Field\BooleanField) {

            foreach ((clone $query)->groupBy($key)->count($key)->select() as $item) {

                $id = $item->$key;
                $label = ($id) ? 'Sim' : 'Não';

                $list[] = new Connector\TableTab(id: $id, label: $label, itens: $item->total);

            }

        }

        elseif ($field instanceof Field\ForeignKeyField) {

            $field_id = $field->getReferenceModel()->getFieldId();
            $field_id_name = $field_id->getName();

            $labels = [];

            foreach ($field->getReferenceModel()::filter($field_id_name, (clone $query)->groupBy($key))
                         ->select() as $item) {

                $labels[$item->$field_id_name] = (string) $item;

            }

            foreach ((clone $query)->groupBy($key)->count($key)->select() as $item) {

                $id = $item->$key;
                $label = $labels[$id] ?? (string) $id;

                $list[] = new Connector\TableTab(id: $id, label: $label, itens: $item->total);

            }

        }

        elseif ($field instanceof Field\ManyToManyField ) {

            $field_id = $field->getReferenceModel()->getFieldId();
            $field_id_name = $field_id->getName();

            $mn_model = $field->getManyToManyModel();
            $mn_field_name = $mn_model->getRight();

            $labels = [];

            $list_right = $mn_model->_query()->groupBy($mn_field_name);

            foreach ($field->getReferenceModel()::filter($field_id_name, $list_right)
                         ->select() as $item) {

                $labels[$item->$field_id_name] = (string) $item;

            }

            foreach ($list_right->count($mn_field_name)->select() as $item) {

                $id = $item->$mn_field_name;
                $label = $labels[$id] ?? (string) $id;

                $list[] = new Connector\TableTab(id: $id, label: $label, itens: $item->total);

            }

        }

        elseif ($field instanceof Field\ManyChoicesField) {

            $mn_model = $field->getManyChoicesModel();
            $mn_field_name = $mn_model->getRight();

            $list_right = $mn_model->_query()->groupBy($mn_field_name);

            foreach ($list_right->count($mn_field_name)->select() as $item) {

                $id = $item->$mn_field_name;
                $label = $field->choices[$id] ?? (string) $id;

                $list[] = new Connector\TableTab(id: $id, label: $label, itens: $item->total);

            }

        }

        else {

            foreach ((clone $query)->groupBy($key)->count($key)->select() as $item) {

                $id = $item->$key;
                $label = (string) $id;

                $list[] = new Connector\TableTab(id: $id, label: $label, itens: $item->total);

            }

        }

        return $list;

    }

    /**
     * @return array<string, Connector\TableFilter>
     * @throws Exception
     * @throws ReflectionException
     */
    public function getFilters(Query $query, stdClass $filters): array
    {

        $list = [];
        $i = 0;

        $crud = $this->getCrud();

        foreach ($this->getFields() as $key => $field) {

            $detail = $this->getDetail($key);

            if ($crud->field_tab == $key || !$detail->filterable) {
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

                foreach ((clone $query)->groupBy($key)->select() as $item) {

                    $id = $item->$key;
                    $label = $field->choices[$id] ?? (string) $id;

                    $filter->itens[] = new Connector\TableFilterItem(
                        id: $id,
                        label: $label,
                        active: in_array($id, $options),
                        color: $field->choices_colors[$id] ?? null
                    );

                }

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

                $field_id = $field->getReferenceModel()->getFieldId();
                $field_id_name = $field_id->getName();

                $field_color_name = '';

                foreach ($field->getReferenceModel()->getFields() as $f) {
                    if ($f instanceof Field\ColorField) {
                        $field_color_name = $f->getName();
                        break;
                    }
                }

                $labels = [];
                $colors = [];

                foreach ($field->getReferenceModel()::filter($field_id_name, (clone $query)->groupBy($key))
                             ->select() as $item) {

                    $labels[$item->$field_id_name] = (string) $item;
                    $colors[$item->$field_id_name] = Color::tryFrom($item->$field_color_name ?? '');

                }

                foreach ((clone $query)->groupBy($key)->select() as $item) {

                    $id = $item->$key;

                    $filter->itens[] = new Connector\TableFilterItem(
                        id: $id,
                        label: $labels[$id] ?? (string) $id,
                        active: in_array($id, $options),
                        color: $colors[$id] ?? null
                    );

                }

            }

            elseif ($field instanceof Field\ManyToManyField) {

                $field_id = $field->getReferenceModel()->getFieldId();
                $field_id_name = $field_id->getName();

                $mn_model = $field->getManyToManyModel();
                $mn_field_name = $mn_model->getRight();

                $field_color_name = '';

                foreach ($field->getReferenceModel()->getFields() as $f) {
                    if ($f instanceof Field\ColorField) {
                        $field_color_name = $f->getName();
                        break;
                    }
                }

                $labels = [];
                $colors = [];

                $list_right = $mn_model->_query()->groupBy($mn_field_name);

                foreach ($field->getReferenceModel()::filter($field_id_name, $list_right)
                             ->select() as $item) {

                    $labels[$item->$field_id_name] = (string) $item;
                    $colors[$item->$field_id_name] = Color::tryFrom($item->$field_color_name ?? '');

                }

                foreach ($list_right->select() as $item) {

                    $id = $item->$mn_field_name;

                    $filter->itens[] = new Connector\TableFilterItem(
                        id: $id,
                        label: $labels[$id] ?? (string) $id,
                        active: in_array($id, $options),
                        color: $colors[$id] ?? null
                    );

                }

            }

            elseif ($field instanceof Field\ManyChoicesField) {

                $mn_model = $field->getManyChoicesModel();
                $mn_field_name = $mn_model->getRight();

                $list_right = $mn_model->_query()->groupBy($mn_field_name);

                foreach ($list_right->select() as $item) {

                    $id = $item->$mn_field_name;
                    $label = $field->choices[$id] ?? (string) $id;

                    $filter->itens[] = new Connector\TableFilterItem(
                        id: $id,
                        label: $label,
                        active: in_array($id, $options),
                        color: $field->choices_colors[$id] ?? null
                    );

                }

            }

            else {

                foreach ((clone $query)->groupBy($key)->select() as $item) {

                    $id = $item->$key;

                    $filter->itens[] = new Connector\TableFilterItem(
                        id: $id,
                        label: "$id",
                        active: in_array($id, $options),
                        color: null
                    );

                }

            }

            $list[] = $filter;

        }

        return $list;

    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function tab(Query $query, null|string|int &$tab, null|string|int $default_tab): Query
    {

        $crud = $this->getCrud();

        if (is_null($tab) || is_null($crud->field_tab)) {
            return $query;
        }

        $field = $this->getField($crud->field_tab);

        if ($field instanceof Field\ManyToManyField) {

            $field_id = $this->getFieldId();

            $mn_model = $field->getManyToManyModel();
            $mn_field_right = $mn_model->getRight();
            $mn_field_left = $mn_model->getLeft();

            $list = $mn_model->_filter($mn_field_right, $tab)->groupBy($mn_field_left);

            $test = (clone $query)->filter($field_id->getName(), $list)->first();

            if (is_null($test)) {

                if (is_null($default_tab)) {
                    return $query;
                }

                else {
                    $tab = $default_tab;
                }

            }

            $query = $query->filter($field_id->getName(), $list);

        }

        elseif ($field instanceof Field\ManyChoicesField) {

            $field_id = $this->getFieldId();

            $mn_model = $field->getManyChoicesModel();
            $mn_field_right = $mn_model->getRight();
            $mn_field_left = $mn_model->getLeft();

            $list = $mn_model->_filter($mn_field_right, $tab)->groupBy($mn_field_left);

            $test = (clone $query)->filter($field_id->getName(), $list)->first();

            if (is_null($test)) {

                if (is_null($default_tab)) {
                    return $query;
                }

                else {
                    $tab = $default_tab;
                }

            }

            $query = $query->filter($field_id->getName(), $list);

        }

        else {

            $test = (clone $query)->filter($crud->field_tab, $tab)->first();

            if (is_null($test)) {

                if (is_null($default_tab)) {
                    return $query;
                }

                else {
                    $tab = $default_tab;
                }

            }

            $query = $query->filter($crud->field_tab, $tab);

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
     * @throws Exception
     */
    public function getActions(): array
    {

        $auth = Config::getAuth();

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

    /**
     * @throws Exception
     */
    public function executeAction(Action $action): void
    {

        $auth = Config::getAuth();

        if ($action == Action::DELETE) {

            if (!$auth->hasPermission($this, Permission::DELETE)) {
                throw new PermissionDeniedException('Usuário sem acesso à apagar!');
            }

            $this::findById($this->id())->delete();

        }

    }

    public function search(Query $query, string $search): Query
    {

        $searches = [];

        foreach ($this->getFields() as $field) {

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
     * @throws ReflectionException
     */
    public function filterItens(Query $query, stdClass $filters): Query
    {

        foreach ($filters as $key => $value) {

            if (empty($value) || !array_key_exists($key, $this->getFields())) {
                continue;
            }

            $field = $this->getField($key);

            if ($field instanceof Field\ManyToManyField) {

                $field_id = $this->getFieldId();

                $mn_model = $field->getManyToManyModel();
                $mn_field_right = $mn_model->getRight();
                $mn_field_left = $mn_model->getLeft();

                $list = $mn_model->_filter($mn_field_right, $value)->groupBy($mn_field_left);

                $query = $query->filter($field_id->getName(), $list);

            }

            elseif ($field instanceof Field\ManyChoicesField) {

                $field_id = $this->getFieldId();

                $mn_model = $field->getManyChoicesModel();
                $mn_field_right = $mn_model->getRight();
                $mn_field_left = $mn_model->getLeft();

                $list = $mn_model->_filter($mn_field_right, $value)->groupBy($mn_field_left);

                $query = $query->filter($field_id->getName(), $list);

            }

            else {
                $query = $query->filter($key, $value);
            }

        }

        return $query;

    }

    public function changeState(State $state): void {}

    /** @noinspection PhpUnusedParameterInspection */
    public function getFormHeader(?Action $action = null): ?string
    {
        return null;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function getFormFooter(?Action $action = null): ?string
    {
        return null;
    }

}
