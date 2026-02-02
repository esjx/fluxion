<?php
namespace Fluxion;

use Fluxion\Auth\Auth;
use Fluxion\Auth\Models\Permission;

class ModelManipulate2 extends ModelManipulate
{

    public static function synchronize(string $model): void
    {

        /** @var Config $config */
        $config = $GLOBALS['CONFIG'];

        /** @var Model2 $class_model */
        $class_model = new $model;

        echo "<b style='color: orange;'>/* $model */</b>\n\n";

        //$dbId = $class_model->getDatabase();

        $connector = $config->getConnectorById(0);

        $obj = $connector->getPDO();

        // Criar as Permissões
        /*if ($model != 'Fluxion\Auth\Models\Permission' && $model != 'Fluxion\Auth\Models\PermissionGroup') {

            $perm = Permission::filter('name', $model)->firstOrNew($config, $auth);
            $perm->name = $model;
            $perm->save();

        }*/

        /*if ($class_model->_view) {

            if ($class_model->_view_script != '') {

                $obj->beginTransaction();

                if (is_array($class_model->_view_script)) {

                    foreach ($class_model->_view_script as $sql) {

                        $obj->exec($sql);

                    }

                } else {

                    $obj->exec($class_model->_view_script);

                }
                $obj->commit();

            }

            return false;
        }*/

        $class_model->changeState(Model::STATE_SYNC);

        // Criar a tabela principal
        $connector->synchronize($class_model);

        echo "\n\n";

    }

}
