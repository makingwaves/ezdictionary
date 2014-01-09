<?php
namespace MakingWaves\ezDictionary;

/**
 * Class contains the logic for dictionary template operator
 */
class Dictionary
{
    const OPERATOR_NAME = 'dictionary';
    private $operators;

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->operators = array( self::OPERATOR_NAME );
    }

    private function getWordNodes()
    {
        
    }

    public function modify( $tpl, $operator_name, $operatorParameters, $rootNamespace, $currentNamespace, &$operator_value, $namedParameters )
    {
        if ( $operator_name !== self::OPERATOR_NAME )
        {
            return false;
        }

        var_dump( $operator_value );
        die;
    }

    /**
     * Returns a list of operator's parameters
     * @return type
     */
    public function namedParameterList()
    {
        return array(
            self::OPERATOR_NAME => array(
                'display_string' => array(
                    'type' => 'string',
                    'required' => true,
                    'default' => ''
                 )
             )
        );
    }

    /**
     * This operator is named
     * @return boolean
     */
    public function namedParameterPerOperator()
    {
        return true;
    }

    /**
     * Returns a list of available oeprators
     * @return type
     */
    public function operatorList()
    {
        return $this->operators;
    }
}
