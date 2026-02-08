<?php
namespace Fluxion;

use DateTime;
use Exception;

class Service
{

    const TYPEAHEAD_LIMIT = 30;

    /** @throws Exception */
    public function formFields(array &$fields): void
    {

        for ($i = 0; $i < count($fields); $i++) {

            if (!isset($fields[$i]['label'])) {
                $fields[$i]['label'] = ucfirst($fields[$i]['name']);
            }

            if (!isset($fields[$i]['size'])) {
                $fields[$i]['size'] = 12;
            }

            if (!isset($fields[$i]['value'])) {
                $fields[$i]['value'] = null;
            }

            if (!isset($fields[$i]['visible'])) {
                $fields[$i]['visible'] = true;
            }

            if (!isset($fields[$i]['enabled'])) {
                $fields[$i]['enabled'] = true;
            }

            if (!isset($fields[$i]['required'])) {
                $fields[$i]['required'] = true;
            }

            if (!isset($fields[$i]['readonly'])) {
                $fields[$i]['readonly'] = false;
            }

            if (!isset($fields[$i]['placeholder'])) {
                $fields[$i]['placeholder'] = '';
            }

            if ($fields[$i]['type'] == 'date' && !is_null($fields[$i]['value'])) {
                $fields[$i]['value'] = (new DateTime($fields[$i]['value']))->format('d/m/Y');
            }

            if ($fields[$i]['type'] == 'date') {
                $fields[$i]['daysDisabled'] = $fields[$i]['daysDisabled'] ?? [0, 6];
            }

            if ($fields[$i]['type'] == 'datetime' && !is_null($fields[$i]['value'])) {
                $fields[$i]['value'] = (new DateTime($fields[$i]['value']))->format('d/m/Y H:i:s');
            }

        }

    }

    /** @throws Exception */
    public function getFields(ModelOld &$model, $id, $save = true): array
    {

        $fields = [
            [
                'name' => '__id',
                'type' => 'string',
                'visible' => false,
                'value' => $id ?? null,
            ]
        ];

        foreach ($model->getFields() as $name => $value) {

            if (!$value['protected']) {

                $fields[] = $this->getField($name, $value, $model, $id, $save);

            }

        }

        $this->formFields($fields);

        return $fields;

    }

    public function getField($name, $value, $model, $id, $save, $extras = []): array
    {

        /** @var \Fluxion\Auth $auth */
        $auth = $GLOBALS['AUTH'];

        $model_name = get_class($model);

        if ($id == 'add') {
            $id = null;
        }

        $id_field = $model->getFieldId();

        $typeahead = false;
        $limit = self::TYPEAHEAD_LIMIT;

        if (in_array($value['type'], ['decimal', 'numeric'])) {
            $value['type'] = 'float';
        }

        if (!$save) {
            $value['disabled'] = true;
        }

        $field = [
            'visible' => $value['visible'] ?? true,
            'name' => $name,
            'type' => $value['form_type'] ?? $value['type'],
            'label' => $value['label'],
            'size' => $value['size'],
            'min' => $value['min'],
            'max' => $value['max'],
            'required' => $value['required'],
            'placeholder' => $value['placeholder'],
            'mask' => $value['mask'],
            'minlength' => $value['minlength'],
            'maxlength' => $value['maxlength'],
            'value' => ($value['type'] == 'upload') ? json_decode($value['value']) : $value['value'],
            'readonly' => $value['readonly'] ?? false,
            'help' => $value['help'] ?? null,
        ];

        if (isset($value['mask'])) {
            $field['maxlength'] = strlen($value['mask']);
        }

        if ($field['type'] == 'password') {
            $field['value'] = null;
        }

        if (isset($value['visible_conditions'])) {
            $field['visible_conditions'] = $value['visible_conditions'];
        }

        if (isset($value['required_conditions'])) {
            $field['required_conditions'] = $value['required_conditions'];
        }

        if (isset($value['enabled_conditions'])) {
            $field['enabled_conditions'] = $value['enabled_conditions'];
        }

        if (isset($value['label_conditions'])) {
            $field['label_conditions'] = $value['label_conditions'];
        }

        if (isset($value['help_conditions'])) {
            $field['help_conditions'] = $value['help_conditions'];
        }

        if (isset($value['inline'])) {
            $field['inline'] = $value['inline'];
        }

        if (isset($value['group_name'])) {
            $field['group_name'] = $value['group_name'];
        }

        if (isset($value['busca_cep'])) {
            $field['busca_cep'] = $value['busca_cep'];
        }

        if (isset($value['pattern'])) {
            $field['pattern'] = $value['pattern'];
        }

        if ($field['type'] == 'date') {

            if (isset($value['min'])) {
                $field['minDate'] = Util::jsDate($value['min'], 'Y/m/d');
            }

            if (isset($value['max'])) {
                $field['maxDate'] = Util::jsDate($value['max'], 'Y/m/d');
            }

        }

        elseif (isset($value['choices'])) {

            if ($field['type'] != 'colors' && $field['type'] != 'radio') {
                $field['type'] = 'choices';
            }

            $choices = [];

            foreach ($value['choices'] as $k => $v) {

                if ($field['type'] == 'radio' && isset($value['choices_colors'])) {
                    $cor = $value['choices_colors'][$k] ?? '';
                    $v = "<span class='text-$cor'>$v</span>";
                }

                if ($value['type'] == 'string') {
                    $choices[] = ['id' => strval($k), 'label' => $v];
                } else {
                    $choices[] = ['id' => $k, 'label' => $v];
                }

            }

            $field['choices'] = $choices;
            $field['multiple'] = false;


        }

        elseif (isset($value['foreign_key'])) {

            $field['type'] = $value['form_type'] ?? 'choices';

            /** @var ModelOld $m */
            $m = new $value['foreign_key']();
            $m_id = $m->getFieldId();

            $q = $m->query();

            $choices = [];

            foreach ($m->getOrder() as $k) {

                $q = $q->orderBy($k);

            }

            if ($field['readonly'] && !is_null($value['value'])
                && !($value['foreign_key_show_all'] ?? false)) {

                $q = $q->filter($m_id, $value['value']);

            }

            if (isset($value['foreign_key_filter'])) {

                foreach ($value['foreign_key_filter'] as $f => $v) {

                    $q = $q->filter($f, $v);

                }

            }

            if ($value['foreign_key'] == Auth\Models\CostCenter::class) {

                $permitted = $auth->getAllCostCenterAccess();

                if (!$auth->hasPermission($model_name, Auth\Models\Permission::UNDER)) {
                    $permitted = $auth->getCostCenter()->id;
                }

                if (!$auth->hasPermission($model_name, Auth\Models\Permission::SPECIAL)) {
                    $q = $q->filter($m_id, $permitted);
                }

            }

            $i = 0;
            $found = [];

            foreach ($q->limit($limit + 1)->xselect() as $k) {

                if ($i++ >= $limit) {
                    $typeahead = true;
                    break;
                }

                $found[] = $k->$m_id;

                $v = strval($k);

                if ($field['type'] == 'radio' && isset($k->cor)) {
                    $cor = $k->cor;
                    $v = "<span class='text-$cor'>$v</span>";
                }

                $choices[] = ['id' => $k->$m_id, 'label' => $v];

            }

            if ((!is_null($id) || !is_null($field['value']))
                && !in_array($field['value'], $found)) {

                foreach ($m->filter($m_id, $field['value'])->xselect() as $k) {

                    $found[] = $k->$m_id;

                    $choices[] = ['id' => $k->$m_id, 'label' => strval($k)];

                }

            }

            if (count($extras)) {

                foreach ($m->filter($m_id, $extras)->exclude($m_id, $found)->xselect() as $k) {

                    $choices[] = ['id' => $k->$m_id, 'label' => strval($k)];

                }

            }

            $field['choices'] = $choices;
            $field['multiple'] = false;

        }

        elseif (isset($value['many_to_many']) || isset($value['i_many_to_many'])) {

            $field['type'] = 'choices';

            $mn_type = (isset($value['many_to_many'])) ? 'many_to_many' : 'i_many_to_many';

            $m_name = $value[$mn_type];

            /** @var ModelOld $m */
            $m = new $m_name();
            $m_id = $m->getFieldId();

            $q = $m->query();

            $choices = [];

            foreach ($m->getOrder() as $k)
                $q = $q->orderBy($k[0], $k[1]);

            if (isset($value['many_to_many_filter']))
                foreach ($value['many_to_many_filter'] as $f => $v)
                    $q = $q->filter($f, $v);

            if (isset($value['i_many_to_many_filter']))
                foreach ($value['i_many_to_many_filter'] as $f => $v)
                    $q = $q->filter($f, $v);

            $i = 0;
            $found = [];

            foreach ($q->limit($limit + 1)->xselect() as $k) {

                if ($i++ >= $limit) {
                    $typeahead = true;
                    break;
                }

                $found[] = $k->$m_id;

                $choices[] = ['id' => $k->$m_id, 'label' => strval($k)];

            }

            if (!is_null($id)) {

                $mn = new MnModel($model_name, $name, isset($value['i_many_to_many']));
                $field['value'] = $mn->load($model->$id_field);

            }

            if (is_null($field['value'])) {
                $field['value'] = [];
            }

            if (count($field['value']) && $field['value'] != $found) {

                foreach ($m->filter($m_id, $field['value'])->exclude($m_id, $found)->xselect() as $k) {

                    $found[] = $k->$m_id;

                    $choices[] = ['id' => $k->$m_id, 'label' => strval($k)];

                }

            }

            if (count($extras)) {

                foreach ($m->filter($m_id, $extras)->exclude($m_id, $found)->xselect() as $k) {

                    $choices[] = ['id' => $k->$m_id, 'label' => strval($k)];

                }

            }

            $field['choices'] = $choices;
            $field['multiple'] = true;

        }

        elseif (isset($value['many_choices'])) {

            $field['type'] = 'choices';

            $choices = [];

            foreach ($value['many_choices'] as $k => $v) {

                if ($value['type'] == 'string') {
                    $choices[] = ['id' => strval($k), 'label' => $v];
                } else {
                    $choices[] = ['id' => $k, 'label' => $v];
                }

            }

            if (!is_null($id)) {

                $mn = new MnChoicesModelOld($model_name, $name);
                $field['value'] = $mn->load($model->$id_field);

            }

            if (is_null($field['value'])) {
                $field['value'] = [];
            }

            $field['choices'] = $choices;
            $field['multiple'] = true;


        }

        if ($value['required']
            && isset($field['choices'])
            && is_null($field['value'])
            && count($field['choices']) == 1) {

            $field['value'] = $field['choices'][0]['id'];

        }

        $field['enabled'] = !(
            ($name == $model->getFieldId() && $model->getFieldIdAi()) ||
            (isset($value['disabled']) && $value['disabled'])
        );

        if (!$field['enabled']) {
            $field['readonly'] = true;
        }

        if ($typeahead) {

            $model_name = Application::classToStr(get_class($model));

            $field['type'] = 'typeahead';
            $field['typeahead'] = $value['typeahead']
                ?? str_replace('\\', '/', $model_name) . 'typeahead/' . $name;

        }

        return $field;

    }

    public function getInlines($model, $save = true): array
    {

        /** @var \Fluxion\Auth $auth */
        $auth = $GLOBALS['AUTH'];

        $model_name = get_class($model);

        $inlines = [];

        foreach ($model->getInlines() as $key => $inline) {

            /** @var ModelOld $inline_model */
            $inline_model = new $inline['model']();

            $field_rel = null;

            $inline_model_fields = $inline_model->getFields();

            foreach ($inline_model_fields as $name => $value) {

                if (isset($value['foreign_key']) && $value['foreign_key'] == $model_name) {
                    $field_rel = $name;
                    break;
                }

            }

            if (is_null($field_rel)) {
                Application::error('Não foi encontrado um campo de relacionamento!');
            }

            $inline_model->$field_rel = $model->id();

            $inline_model->changeState($inline['state'] ?? ModelOld::STATE_INLINE);

            $inline_model_fields = $inline_model->getFields();

            $inline_permissions = $auth->getPermissions($inline_model);

            if (!$save) {
                $inline_permissions['insert'] = false;
                $inline_permissions['delete'] = false;
            }

            $inline_fields = [
                [
                    'name' => '__id',
                    'type' => 'string',
                    'visible' => false,
                ]
            ];

            $inline_itens = [];

            $inline_selects = [];

            if (!is_null($model->id())) {

                $query = $inline_model->query();

                foreach ($inline_model->getOrder() as $order) {
                    $query = $query->orderBy($order[0], $order[1]);
                }

                if (isset($inline['filters'])) {

                    foreach ($inline['filters'] as $f => $v) {
                        $query = $query->filter($f, $v);
                    }

                }

                foreach ($query->filter($field_rel, $model->id())->xselect() as $inline_item) {

                    $inline_item_data = [
                        '__id' => $inline_item->id(),
                    ];

                    foreach ($inline['fields'] as $f) {

                        try {

                            $v = $inline_item->$f;

                            if ($inline_model_fields[$f]['type'] == 'date' && !is_null($v)) {
                                $v = (new DateTime($v))->format('d/m/Y');
                            }

                            if ($inline_model_fields[$f]['type'] == 'datetime' && !is_null($v)) {
                                $v = (new DateTime($v))->format('d/m/Y H:i:s');
                            }

                            if (isset($inline_model_fields[$f]['foreign_key'])
                                || isset($inline_model_fields[$f]['many_to_many'])
                                || isset($inline_model_fields[$f]['i_many_to_many'])
                                || isset($inline_model_fields[$f]['many_choices'])) {

                                if (!isset($inline_selects[$f])) {
                                    $inline_selects[$f] = [];
                                }

                                if (!in_array($v, $inline_selects[$f])) {
                                    $inline_selects[$f][] = $v;
                                }

                            }

                        } catch (Exception $e) {
                            Application::error($e->getMessage());
                        }

                        $inline_item_data[$f] = $v;

                    }

                    $inline_itens[] = $inline_item_data;

                }

            }

            foreach ($inline_model_fields as $name => $value) {

                if (!in_array($name, $inline['fields'])) {
                    continue;
                }

                if (!$value['protected']) {
                    $inline_fields[] = $this->getField($name, $value, $inline_model, null, $save,
                        $inline_selects[$name] ?? []);
                }

            }

            $inlines[] = [
                'id' => $inline['id'] ?? 'inline_' . $key,
                'title' => $inline['title'] ?? $inline_model->getVerboseNamePlural(),
                'no_itens' => $inline['no_itens'] ?? 'Sem registros',
                'title_singular' => $inline['title_singular'] ?? $inline_model->getVerboseName(),
                'max_itens' => $inline['max_itens'] ?? 5,
                'insert' => $inline['insert'] ?? $inline_permissions['insert'],
                'delete' => $inline['delete'] ?? $inline_permissions['delete'],
                'fields' => $inline_fields,
                'itens' => $inline_itens,
            ];

        }

        return $inlines;

    }

    /** @throws Exception */
    public function saveToModel(ModelOld &$model, $data): ?ModelOld
    {

        /** @var \Fluxion\Auth $auth */
        $auth = $GLOBALS['AUTH'];

        $model->changeState(ModelOld::STATE_BEFORE_SAVE);

        $model_name = get_class($model);

        foreach ($data as $key => $value) {

            if (isset($model->getFields()[$key])) {

                if ($model->getFields()[$key]['type'] == 'date' && $value != '') {

                    $model->$key = DateTime::createFromFormat('d/m/Y', $value)->format('Y-m-d');

                }

                elseif ($model->getFields()[$key]['type'] == 'upload' && $value != '' && !is_null($value->data)) {

                    $base_dir = $_ENV['LOCAL_UPLOAD'] ?? '';

                    $dir = $base_dir . Application::classToStr(get_class($model));

                    Upload::createDir($dir);

                    if (preg_match('/\.[A-Z\d]+$/i', $value->name, $matches)) {

                        $ext = strtolower($matches[0]);

                        $file = $dir . Upload::createFileName($key, $auth) . $ext;

                        $fp = fopen($file,'wb');

                        fwrite($fp, base64_decode($value->data));

                        fclose($fp);

                        $imagem = in_array($ext, Upload::IMAGENS);

                        if (!$imagem && $ext != '.zip') {

                            $file = Upload::zipFile($file, $value->name);

                        }

                    } else {

                        $file = null;

                        Application::error('Nome do arquivo inválido!');

                    }

                    $size = filesize($file);

                    $file = str_replace($_ENV['LOCAL_UPLOAD'] ?? '', '', $file);

                    $file = str_replace(DIRECTORY_SEPARATOR, '/', $file);

                    $file_data = [
                        'cost_center' => $auth->getCostCenter()->id,
                        'user' => $auth->getUser()->login,
                        'size' => $size,
                        'name' => $value->name,
                        'file' => $file,
                        'data' => null,
                    ];

                    $model->$key = $file_data;

                } elseif ($model->getFields()[$key]['type'] == 'password' && !empty($value)) {

                    $model->$key = password_hash($value, PASSWORD_DEFAULT);

                } else {

                    $model->$key = $value;

                }

            }

        }

        if ($ok = $model->save()) {

            foreach ($model->getInlines() as $key => $inline) {

                $id = $inline['id'] ?? 'inline_' . $key;

                if (!isset($data->__inlines->$id)) {
                    continue;
                    //Application::error('!!!');
                }

                /** @var ModelOld $inline_model */
                $inline_model = new $inline['model']();

                $field_rel = null;

                foreach ($inline_model->getFields() as $name => $value) {

                    if (isset($value['foreign_key']) && $value['foreign_key'] == $model_name) {
                        $field_rel = $name;
                        break;
                    }

                }

                if (is_null($field_rel)) {
                    Application::error('Não foi encontrado um campo de relacionamento!');
                }

                $inline_model->$field_rel = $model->id();

                $inline_model->changeState($inline['state'] ?? ModelOld::STATE_INLINE_SAVE);

                foreach ($data->__inlines->$id as $inline_data) {

                    if ($inline_data->__delete ?? false) {

                        if ($inline_data->__id) {

                            $inline_model_delete = (clone $inline_model)->findById($inline_data->__id)->firstOrNew();
                            $inline_model_delete->setOriginals();

                            if ($inline_model_delete->onDelete()) {

                                (clone $inline_model)->findById($inline_data->__id)->delete();
                                $inline_model_delete->onDeleted();

                            }
                        }

                    } else {

                        $inline_model_save = clone $inline_model;

                        if (!is_null($inline_data->__id) ) {
                            $inline_model_save = $inline_model_save->findById($inline_data->__id)->firstOrNew();
                            $inline_model_save->setOriginals();
                        }

                        $inline_data->$field_rel = $model->id();

                        try {

                            $this->saveToModel($inline_model_save, $inline_data);

                        } catch (Exception $e) {
                            throw new Exception($e->getMessage());
                        }

                    }

                }

            }

        }

        return $ok;

    }

}
