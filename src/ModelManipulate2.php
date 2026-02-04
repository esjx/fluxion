<?php
namespace Fluxion;

use Psr\Http\Message\StreamInterface;

class ModelManipulate2 extends ModelManipulate
{

    /** @throws CustomException */
    public static function synchronize(string $class_name): void
    {

        /** @var Model2 $model */
        $model = new $class_name;

        $model->changeState(Model2::STATE_SYNC);

        $connector = Config2::getConnector();

        $connector->comment("<b>$class_name</b>\n", Color::ORANGE);

        # Criar a tabela principal

        $connector->synchronize($model);

        # Criar as tabelas MN

        $many_to_many = $model->getManyToMany();

        foreach ($many_to_many as $key => $mn) {

            $connector->comment("<b>Tabela MN para o campo '$key'</b>\n");

            $mn_model = new MnModel2($model, $key);

            $mn_model->changeState(Model2::STATE_SYNC);

            $mn_model->setComment(get_class($model) . " MN[$key]");

            # Criar a tabela de relacionamento

            $connector->synchronize($mn_model);

        }

    }

}
