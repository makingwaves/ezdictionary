<?php
namespace MakingWaves\ezDictionary;

/**
 * Class contains the logic for dictionary template operator
 */
class DictionaryLogicOld
{
    const OPERATOR_NAME = 'dictionary';
    const EXTENSION_NAME = 'eZDictionary';

    private $operators;
    private $classes = array();



    public function modify( $tpl, $operator_name, $operator_parameters, $root_namespace, $current_namespace, &$operator_value, $named_parameters )
    {
        if ( $operator_name !== self::OPERATOR_NAME )
        {
            $this->printError( 'Template operator "' . $operator_name . '" is not supported.' );
            return false;
        }

        $dictionary = $this->generateDictionary( $this->getWordNodes() );
        foreach( $dictionary as $word => $description )
        {
            $dict_tpl = \eZTemplate::factory();
            $dict_tpl->setVariable( 'dict_desc', $description );

            $case_sensitive = $named_parameters['case_sensitive'] === true ? '' : 'i';
            $pattern = '/<(a|h1)[^\>]*?>[^\<]*?<\/\1>(\b' . $word . '\b)/' . $case_sensitive;

            if ( preg_match( $pattern, $operator_value ) )
            {
                $operator_value = preg_replace( $pattern, $dict_tpl->fetch( 'design:ezdictionary/tooltip.tpl' ), $operator_value );
            }
        }
    }

}
