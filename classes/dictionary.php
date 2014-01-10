<?php
namespace MakingWaves\ezDictionary;

/**
 * Class contains the logic for dictionary template operator
 */
class Dictionary
{
    const OPERATOR_NAME = 'dictionary';
    const EXTENSION_NAME = 'eZDictionary';

    private $operators;
    private $classes = array();

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->operators = array( self::OPERATOR_NAME );
    }

    /**
     * Method generates an array of dictionary items basing on given array of nodes
     * @param array $nodes
     * @return array
     */
    private function generateDictionary( $nodes )
    {
        $dictionary = array();

        if ( empty( $nodes ) )
        {
            $this->printError( 'There are no nodes which matches the dictionary settings. Please check the extension configuration (ezdictionary.ini).' );
            return $dictionary;
        }

        foreach ( $nodes as $node )
        {
            $attrib_values = $this->getAttributeValues( $node );
            if ( !empty( $attrib_values ) )
            {
                $dictionary[$attrib_values['keyword']] = $attrib_values['description'];
            }
        }

        return $dictionary;
    }

    /**
     * Method returns the node data for defined attributes.
     * @param type $node
     * @return array
     */
    private function getAttributeValues( $node )
    {
        $data_map = $node->dataMap();
        $attribs = $this->getClassAttributes( $node->attribute( 'class_identifier' ) );
        $values = array();

        // set keyword value
        $values['keyword'] = $node->attribute( 'name' );
        if ( !empty( $attribs['keyword'] ) )
        {
            if ( !isset( $data_map[$attribs['keyword']] ) )
            {
                $this->printError( 'Class "' . $node->attribute( 'class_identifier' ) . '" doesn\'t contain attribute "'
                                   . $attribs['keyword'] . '" defined in ezdictionary.ini file. Keyword "' . $values['keyword'] . '" won\'t be used.' );

                // in case of missing keyword, it won't be used
                return array();
            }
            else
            {
                $values['keyword'] = $data_map[$attribs['keyword']]->attribute( 'content' );
            }
        }

        // set description value
        if ( !isset( $data_map[$attribs['description']] ) )
        {
            $this->printError( 'Class "' . $node->attribute( 'class_identifier' ) . '" doesn\'t contain attribute "'
                               . $attribs['description'] . '" defined in ezdictionary.ini file. Keyword "' . $values['keyword'] . '" won\'t be used.' );

            // in case of missing description keyword is not used at all
            return array();
        }
        else
        {
            $values['description'] = $data_map[$attribs['description']]->attribute( 'content' );
        }

        return $values;
    }

    /**
     * Method returns an array of class attributes which will be used for dictionary
     * @param string $class_name
     * @return array
     */
    private function getClassAttributes( $class_name )
    {
        $classes = $this->getClasses();
        $attributes = array();

        if ( isset( $classes[$class_name][1] ) )
        {
            $attributes = array(
                'keyword' => $classes[$class_name][0],
                'description' => $classes[$class_name][1]
            );
        }
        else
        {
            $this->printError( 'Class "' . $class_name . '" is not configured properly. Check ezdictionaty.ini file.' );
        }

        return $attributes;
    }

    /**
     * Method returns all DictionaryClasses data - containing class names as array keys and attribute names as values
     * @return array
     */
    private function getClasses()
    {
        if( empty( $this->classes ) ) {
            $this->classes = \eZINI::instance( 'ezdictionary.ini' )->variableArray( 'TemplateOperator', 'DictionaryClasses' );
        }
        return $this->classes;
    }

    /**
     * Method fetches the nodes by names defined in TemplateOperator[Classes] which are children of TemplateOperator[ParentNodes]
     * @return array
     */
    private function getWordNodes()
    {
        $parents = \eZINI::instance( 'ezdictionary.ini' )->variable( 'TemplateOperator', 'ParentNodes' );
        return \eZFunctionHandler::execute( 'content', 'tree', array(
            'class_filter_type' => 'include',
            'class_filter_array' => array_keys( $this->getClasses() ),
            'parent_node_id' => $parents
        ) );
    }

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
            $pattern = '/(\b' . $word . '\b)/' . $case_sensitive;

            if ( preg_match( $pattern, $operator_value ) )
            {
                $operator_value = preg_replace( $pattern, $tpl->fetch( 'design:ezdictionary/tooltip.tpl' ), $operator_value );
            }
        }
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

    /**
     * Function prints the error text in the eZDebug.
     * @param string $text
     */
    private function printError( $text, $type = 'error' )
    {
        $phrase = self::EXTENSION_NAME . ': ' . $text;
        switch ( $type )
        {
            case 'notice':
                \eZDebug::writeNotice( $phrase );
                break;

            default:
                \eZDebug::writeError( $phrase );
                break;
        }
    }
}
