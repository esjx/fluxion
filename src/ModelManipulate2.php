<?php
namespace Fluxion;

class ModelManipulate2 extends ModelManipulate
{

    /** @throws CustomException */
    public static function synchronize(string $class_name): void
    {

        /** @var Model2 $model */
        $model = new $class_name;

        $model->changeState(Model2::STATE_SYNC);

        echo "<b style='color: orange;'>/* $class_name */</b>\n\n";

        $connector = Config2::getConnector();

        # Criar a tabela principal

        $connector->synchronize($model);

        echo "\n";

        # Criar as tabelas MN

        $many_to_many = $model->getManyToMany();

        foreach ($many_to_many as $key => $mn) {

            echo "<b style='color: gray;'>/* Tabela MN para o campo '$key' */</b>\n\n";

            $mn_model = new MnModel2($model, $key);

            $mn_model->changeState(Model2::STATE_SYNC);

            $mn_model->setComment(get_class($model) . " MN[$key]");

            # Criar a tabela de relacionamento

            $connector->synchronize($mn_model);

            echo "\n";

        }

    }

}
