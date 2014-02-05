<?php
namespace MakingWaves\eZDictionary;

class DictionaryLogic
{
    /**
     * @var string
     */
    private $operator_name;

    /**
     * @var string
     */
    private $operator_value;

    /**
     * @var array
     */
    private $named_parameters;

    /**
     * @var array
     */
    private $classes = array();

    /**
     * Default constructor
     * @param string $operator_value
     * @param array $named_parameters
     * @throws DictionaryLogicIncorrectOperatorValueException
     * @throws DictionaryLogicIncorrectOperatorParamException
     */
    public function __construct( $operator_value, $named_parameters )
    {
        if ( !is_string( $operator_value ) )
        {
            throw new DictionaryLogicIncorrectOperatorValueException( 'Incorrect operator value' );
        }

        if ( !is_array( $named_parameters ) || sizeof( $named_parameters ) === 0 )
        {
            throw new DictionaryLogicIncorrectOperatorParamException( 'Dictionary operator parameters are missing' );
        }

        $this->operator_value = $operator_value;
        $this->named_parameters = $named_parameters;
    }

    /**
     * Method returns all DictionaryClasses data - containing class names as array keys and attribute names as values
     * @return array
     */
    private function getClasses()
    {
        if( sizeof( $this->classes ) === 0 ) {
            $classes = \eZINI::instance( 'ezdictionary.ini' )->variableArray( 'TemplateOperator', 'DictionaryClasses' );
            $this->classes = $classes === false ? array() : $classes;
        }
        return $this->classes;
    }

    /**
     * Method fetches the nodes by names defined in TemplateOperator[Classes] which are children of TemplateOperator[ParentNodes]
     * @return array
     */
    private function getWordNodes()
    {
        $nodes = \eZFunctionHandler::execute( 'content', 'tree', array(
            'class_filter_type' => 'include',
            'class_filter_array' => array_keys( $this->getClasses() ),
            'parent_node_id' => \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'ParentNodes' )
        ) );

        return is_array( $nodes ) ? $nodes : array();
    }

    public function generateMarkup()
    {
        $dictionary = $this->generateDictionary( $this->getWordNodes() );
/*        foreach( $dictionary as $word => $description )
        {
            $dict_tpl = \eZTemplate::factory();
            $dict_tpl->setVariable( 'dict_desc', $description );

            $case_sensitive = $named_parameters['case_sensitive'] === true ? '' : 'i';
            $pattern = '/<(a|h1)[^\>]*?>[^\<]*?<\/\1>(\b' . $word . '\b)/' . $case_sensitive;

            if ( preg_match( $pattern, $operator_value ) )
            {
                $operator_value = preg_replace( $pattern, $dict_tpl->fetch( 'design:ezdictionary/tooltip.tpl' ), $operator_value );
            }
        }*/
    }
} 