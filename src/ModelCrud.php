<?php
namespace Fluxion;

use ReflectionException;
use stdClass;
use Fluxion\Database\{Crud, Detail, Field, FormInline, Inline};
use Fluxion\Query\QuerySql;
use Fluxion\Exception\{PermissionDeniedException};

trait ModelCrud
{

    public static string $empty_value = '__empty__';

    # Detalhes dos campos

    /** @var array<string, Detail> */
    protected array $_details = [];

    /** @return array<string, Detail> */
    public function getDetails(): array
    {
        return $this->_details;
    }

    /**
     * @throws Exception
     */
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

        $list[] = new Connector\TableTab(id: null, label: '(Todos)', items: $item->total);

        if ($field instanceof Field\ChoicesField) {

            foreach ((clone $query)->groupBy($key)->count($key)->select() as $item) {

                $id = $item->$key;
                $label = $field->choices[$id] ?? (string) $id;

                $list[] = new Connector\TableTab(id: $id, label: $label, items: $item->total);

            }

        }

        elseif ($field instanceof Field\BooleanField) {

            foreach ((clone $query)->groupBy($key)->count($key)->select() as $item) {

                $id = $item->$key;
                $label = ($id) ? 'Sim' : 'Não';

                $list[] = new Connector\TableTab(id: $id, label: $label, items: $item->total);

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

                $list[] = new Connector\TableTab(id: $id, label: $label, items: $item->total);

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

                $list[] = new Connector\TableTab(id: $id, label: $label, items: $item->total);

            }

        }

        elseif ($field instanceof Field\ManyChoicesField) {

            $mn_model = $field->getManyChoicesModel();
            $mn_field_name = $mn_model->getRight();

            $list_right = $mn_model->_query()->groupBy($mn_field_name);

            foreach ($list_right->count($mn_field_name)->select() as $item) {

                $id = $item->$mn_field_name;
                $label = $field->choices[$id] ?? (string) $id;

                $list[] = new Connector\TableTab(id: $id, label: $label, items: $item->total);

            }

        }

        else {

            foreach ((clone $query)->groupBy($key)->count($key)->select() as $item) {

                $id = $item->$key;
                $label = (string) $id;

                $list[] = new Connector\TableTab(id: $id, label: $label, items: $item->total);

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

            $filter->items[] = new Connector\TableFilterItem(
                id: null,
                label: '(Todos)',
                active: (count($options) == 0)
            );

            if ($field instanceof Field\ChoicesField) {

                foreach ((clone $query)->groupBy($key)->select() as $item) {

                    $id = $item->$key;
                    $label = $field->choices[$id] ?? (string) $id;

                    if (is_null($id)) {
                        $id = self::$empty_value;
                        $label = '(Vazios)';
                    }

                    $filter->items[] = new Connector\TableFilterItem(
                        id: $id,
                        label: $label,
                        active: in_array($id, $options),
                        color: $field->choices_colors[$id] ?? null
                    );

                }

            }

            elseif ($field instanceof Field\BooleanField) {

                $filter->items[] = new Connector\TableFilterItem(
                    id: false,
                    label: 'Não',
                    active: in_array(false, $options)
                );

                $filter->items[] = new Connector\TableFilterItem(
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

                $labels = [self::$empty_value => '(Vazios)'];
                $colors = [];

                foreach ($field->getReferenceModel()::filter($field_id_name, (clone $query)->groupBy($key))
                             ->select() as $item) {

                    $labels[$item->$field_id_name] = (string) $item;
                    $colors[$item->$field_id_name] = Color::tryFrom($item->$field_color_name ?? '');

                }

                foreach ((clone $query)->groupBy($key)->select() as $item) {

                    $id = $item->$key ?? self::$empty_value;

                    $filter->items[] = new Connector\TableFilterItem(
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

                    $filter->items[] = new Connector\TableFilterItem(
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

                    $filter->items[] = new Connector\TableFilterItem(
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

                    $filter->items[] = new Connector\TableFilterItem(
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

            foreach ($value as &$item) {
                if ($item == self::$empty_value) {
                    $item = null;
                }
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

    public function changeState(State $state, array $args = []): void {}

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

    public function getFormFields(bool $save = true, ?string $route = null): array
    {

        $fields = [];

        foreach ($this->getFields() as $f) {

            if ($f->protected) {
                continue;
            }

            $form_field = $f->getFormField([], $route);

            if (!$save) {
                $form_field->enabled = false;
            }

            $fields[] = $form_field;

        }

        return $fields;

    }

    /**
     * @return Inline[]
     */
    public function getInlines(): array
    {
        return [];
    }

    /**
     * @return FormInline[]
     * @throws Exception
     */
    public function getFormInlines(bool $save = true, ?string $route = null): array
    {

        $inlines = [];

        FormInline::$sequence = 0;

        foreach ($this->getInlines() as $inline) {

            $inlines[] = new FormInline($this, $inline, $save, $route);

        }

        return $inlines;

    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function saveFromForm(stdClass $is): void
    {

        $auth = Config::getAuth();

        $this->changeState(State::VIEW);

        $this->changeState(State::BEFORE_SAVE);

        # Salva dados

        foreach ($this->getFields() as $key => $f) {

            if ($f->readonly && !$f->identity && $f->primary_key && is_null($this->$key)) {
                $f->readonly = false;
            }

            if ($f->protected || $f->readonly) {
                continue;
            }

            $this->$key = $is->$key ?? null;

        }

        $this->changeState(State::SAVE);

        $this->save();

        # Salva os inlines

        FormInline::$sequence = 0;

        foreach ($this->getInlines() as $inline) {

            $id = $inline->id ?? 'inline_' . FormInline::$sequence++;

            if (!isset($is->__inlines->$id)) {
                continue;
            }

            $field_id = $this->getFieldId()->getName();

            $il_field_ref = $inline->getInlineField($this);

            foreach ($is->__inlines->$id as $data) {

                # Novos registros

                if (is_null($data->__id)) {

                    if ($data->__deleted) {
                        continue;
                    }

                    $il_model = clone $inline->getInlineModel();

                    $il_model->$il_field_ref = $this->$field_id;

                }

                # Valores existentes

                else {

                    if ($data->__deleted) {

                        if (!$inline->delete
                            && !$auth->hasPermission($inline->getInlineModel(), Permission::DELETE)) {
                            throw new PermissionDeniedException("Exclusão não permitida!");
                        }

                        $inline->getInlineModel()::findById($data->__id)->delete();

                        continue;

                    }

                    $il_model = $inline->getInlineModel()::loadById($data->__id);

                }

                # Atualiza os campos

                $il_model->changeState(State::INLINE_SAVE, $inline->args);

                foreach ($inline->fields as $field) {
                    $il_model->$field = $data->$field;
                }

                $il_model->save();

            }

        }

    }

}
