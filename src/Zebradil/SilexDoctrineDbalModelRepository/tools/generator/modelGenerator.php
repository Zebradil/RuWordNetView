<?php

namespace ModelGenerator;

use DIC\DI;

class ModelGenerator
{
    private $tableName = null;
    private $className = null;

    public function __construct($tableName, $className = null)
    {
        $this->tableName = strtoupper($tableName);
        $this->className = $className ?: implode(
            '',
            array_map(
                function ($item) {
                    return ucfirst($item);
                },
                explode('_', $tableName)
            )
        );
        DI::view()->setPaths(__DIR__, '__main__');
    }

    /**
     * @param $type
     * @param null $length
     * @return string
     */
    public static function orclTypeToPhp($type, $length = null)
    {
        switch ($type) {
            case 'NUMBER':
                $newType = $length == 1 ? 'bool' : 'int';
                break;
            default:
                $newType = 'string';
        }

        return $newType;
    }

    public function __toString()
    {
        return $this->generate();
    }

    private function generate()
    {
        $tplData = $this->getData();
        DI::twig()->addFunction(
            'orclTypeToPhp',
            new \Twig_SimpleFunction('orclTypeToPhp', [$this, 'orclTypeToPhp'])
        );
        return DI::view()->render('template.twig', $tplData, true);
    }

    private function getData()
    {
        return [
            'className' => $this->className,
            'tableName' => $this->tableName,
            'classComment' => $this->getTabComment(),
            'fields' => $this->getFileds()
        ];
    }

    private function getTabComment()
    {
        return DI::db()->run(
            'SELECT utc.comments FROM user_tab_comments utc WHERE table_name = :tab_name',
            ['tab_name' => $this->tableName]
        )->val();
    }

    private function getFileds()
    {
        return DI::db()->run(
            'SELECT uc.column_name, uc.data_type, uc.nullable, uc.data_precision, ucc.comments
             FROM user_tab_columns  uc
             LEFT JOIN user_col_comments ucc
                ON uc.table_name = ucc.table_name
                AND uc.column_name = ucc.column_name
             WHERE uc.table_name = :table_name',
            ['table_name' => $this->tableName]
        )->arr();
    }
}