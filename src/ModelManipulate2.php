<?php
namespace Fluxion;

class ModelManipulate2 extends ModelManipulate
{

    /** @throws CustomException */
    public static function synchronize(string $class_name): void
    {

        /** @var Config $config */
        $config = $GLOBALS['CONFIG'];

        /** @var Model2 $model */
        $model = new $class_name;

        echo "<b style='color: orange;'>/* $class_name */</b>\n\n";

        $connector = $config->getConnectorById(0);

        // Criar as Permissões
        /*if ($model != 'Fluxion\Auth\Models\Permission' && $model != 'Fluxion\Auth\Models\PermissionGroup') {

            $perm = Permission::filter('name', $model)->firstOrNew($config, $auth);
            $perm->name = $model;
            $perm->save();

        }*/

        $model->changeState(Model2::STATE_SYNC);

        # Criar a tabela principal

        $connector->synchronize($model);

        echo "\n\n";

        $many_to_many = $model->getManyToMany();

        foreach ($many_to_many as $key => $mn) {

            $mn_model = new MnModel2($model, $key);

            # Criar a tabela de relacionamento

            $connector->synchronize($mn_model);

            echo "\n\n";

        }

    }

}
