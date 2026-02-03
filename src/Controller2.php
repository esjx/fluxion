<?php
namespace Fluxion;

use ReflectionClass;
use ReflectionException;

class Controller2
{

    /**
     * @throws ReflectionException
     * @throws CustomException
     */
    public static function setup(): void
    {

        $start_time = microtime(true);

        echo '<pre>';

        $class_name = get_called_class();

        echo "<b style='color: black;'>/* $class_name */</b>\n\n";

        # Buscando todos os arquivos de Models

        $list = [];

        $ref_controller = new ReflectionClass($class_name);

        $dir = dirname($ref_controller->getFileName());

        foreach (Util::loadAllFiles("$dir/Models") as $file) {

            $file = str_replace($dir, '', $file);
            $file = preg_replace('/\.php$/i', '', $file);
            $file = str_replace(DIRECTORY_SEPARATOR, '\\', $file);

            $class = $ref_controller->getNamespaceName() . $file;

            $ref_model = new ReflectionClass($class);

            if ($ref_model->isSubclassOf(Model2::class) && !$ref_model->isAbstract()) {
                if (!(new $class())->getTable()->view) {
                    $list[$class] = -1;
                }
            }

        }

        # Verificando eventuais dependências

        foreach ($list as $class => $index) {

            /** @var Model2 $model */
            $model = new $class();

            foreach ($model->getForeignKeys() as $foreign_key) {
                if (isset($list[$foreign_key->class_name])) {
                    $list[$foreign_key->class_name] = $list[$class] - 1;
                }
            }

            foreach ($model->getManyToMany() as $many_to_many) {
                if (isset($list[$many_to_many->class_name])) {
                    $list[$many_to_many->class_name] = $list[$class] - 1;
                }
            }

        }

        # Ordenando e executando as sincronizações

        asort($list);
        foreach ($list as $class => $index) {
            ModelManipulate2::synchronize($class);
        }

        # Executando scripts SQL

        // TODO

        # Resumo do processo executado

        $end_time = microtime(true);

        $time = Util::formatNumber($end_time - $start_time);

        $memory = Util::formatSize(memory_get_usage());

        echo "-- Finalizado em <b>$time segundos</b> utilizando <b>$memory</b>";

        echo '<pre>';

    }

}
