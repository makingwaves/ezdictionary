<?php
namespace MakingWaves\eZDictionary;

/**
 * Class DictionaryLogic
 *
 * Separated logic for template operator.
 * Class is unit tested, so please update tests on each change.
 *
 * @package MakingWaves\eZDictionary
 */
class DictionaryLogic
{
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
     * Method generates an array of dictionary items basing on given array of nodes
     * @param array $nodes
     * @return array
     * @throws DictionaryLogicIncorrectNodesArrayException
     * @throws DictionaryLogicNotNodeException
     */
    private function generateDictionary( $nodes )
    {
        if ( !is_array( $nodes ) )
        {
            throw new DictionaryLogicIncorrectNodesArrayException( 'Input needs to be an array' );
        }

        if ( sizeof( $nodes ) === 0 )
        {
            \eZDebug::writeError( 'There are no nodes which matches the dictionary settings. Please check the extension configuration (ezdictionary.ini).' );
        }

        $dictionary = array();
        foreach ( $nodes as $node )
        {
            if ( !( $node instanceof \eZContentObjectTreeNode ) )
            {
                throw new DictionaryLogicNotNodeException( 'Incorrect node object' );
            }

            $attrib_values = $this->getAttributeValues( $node );
            if ( !empty( $attrib_values ) )
            {
                $dictionary[$attrib_values['keyword']] = $attrib_values['description'];
            }
        }

        return $dictionary;
    }

    /**
     * Method generates new html markup and returns it
     * @return string
     */
    public function generateMarkup()
    {
        $dictionary = $this->generateDictionary( $this->getWordNodes() );
        $new_value = $this->operator_value;

        foreach( $dictionary as $word => $description )
        {
            $dict_tpl = \eZTemplate::factory();
            $dict_tpl->setVariable( 'dict_desc', $description );

            $case_sensitive = $this->getParameter( 'case_sensitive' ) === true ? '' : 'i';
            $pattern =  '/(\b' . $word . '\b)/' . $case_sensitive;

            if ( preg_match( $pattern, $new_value ) )
            {
                $new_value = preg_replace( $pattern, $dict_tpl->fetch( 'design:ezdictionary/tooltip.tpl' ), $new_value );
            }
        }

        return $new_value;
    }

    /**
     * Method returns the node data for defined attributes.
     * @param \eZContentObjectTreeNode $node
     * @return array
     */
    private function getAttributeValues( \eZContentObjectTreeNode $node )
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
                \eZDebug::writeError( 'Class "' . $node->attribute( 'class_identifier' ) . '" doesn\'t contain attribute "'
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
            \eZDebug::writeError( 'Class "' . $node->attribute( 'class_identifier' ) . '" doesn\'t contain attribute "'
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
     * @throws DictionaryLogicIncorrectAttributeException
     * @throws DictionaryLogicIncorrectClassNameException
     */
    private function getClassAttributes( $class_name )
    {
        if ( !is_string( $class_name ) || strlen( $class_name ) === 0 )
        {
            throw new DictionaryLogicIncorrectClassNameException( 'Incorrect class name' );
        }

        $classes = $this->getClasses();
        $attributes = array();

        if ( !isset( $classes[$class_name][1] ) )
        {
            throw new DictionaryLogicIncorrectAttributeException( 'Class "' . $class_name . '" is not configured properly. Check ezdictionary.ini file.' );
        }

        $attributes = array(
            'keyword' => $classes[$class_name][0],
            'description' => $classes[$class_name][1]
        );

        return $attributes;
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
     * Method returns a value of given named parameter
     * @param string $name
     * @return mixed
     * @throws DictionaryLogicIncorrectParameterTypeException
     * @throws DictionaryLogicParameterDoesNotExistException
     */
    private function getParameter( $name )
    {
        if ( !is_string( $name ) )
        {
            throw new DictionaryLogicIncorrectParameterTypeException( 'Parameter name needs to be a string' );
        }

        if ( !isset( $this->named_parameters[$name] ) )
        {
            throw new DictionaryLogicParameterDoesNotExistException( 'Parameter with given name does not exists' );
        }

        return $this->named_parameters[$name];
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
} 