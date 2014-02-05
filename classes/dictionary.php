<?php
namespace MakingWaves\eZDictionary;

class DictionaryOperator
{
    const OPERATOR_NAME = 'dictionary';

    /**
     * This method implements an invisible interface for template operators :)
     */
    public function modify( $tpl, $operator_name, $operator_parameters, $root_namespace, $current_namespace, &$operator_value, $named_parameters )
    {
        $dictionary = new DictionaryLogic( self::OPERATOR_NAME, $operator_value, $named_parameters );
        $operator_value = $dictionary->generateMarkup();
    }

    /**
     * Returns a list of operator's parameters
     * @return type
     */
    public function namedParameterList()
    {
        return array(
            self::OPERATOR_NAME => array(
                'case_sensitive' => array(
                    'type' => 'boolean',
                    'required' => false,
                    'default' => \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'CaseSensitive' ) === 'true'
                )
            )
        );
    }

    /**
     * This operator contains named parameters
     * @return bool
     */
    public function namedParameterPerOperator()
    {
        return true;
    }

    /**
     * Returns a list of available operators
     * @return type
     */
    public function operatorList()
    {
        return array( self::OPERATOR_NAME );
    }
} 